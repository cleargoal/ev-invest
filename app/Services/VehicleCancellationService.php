<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Events\TotalChangedEvent;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleUnsoldNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class VehicleCancellationService
{

    /**
     * Cancel a vehicle sale (preserves sale data for audit purposes)
     * This marks the sale as cancelled and reverses all related financial transactions
     * Unlike unsellVehicle, this keeps sale_date, price, profit for audit trail
     *
     * @param Vehicle $vehicle
     * @param string|null $reason
     * @param User|null $cancelledBy
     * @return bool
     * @throws Throwable
     */
    public function cancelVehicleSale(Vehicle $vehicle, ?string $reason = null, ?User $cancelledBy = null): bool
    {
        if (is_null($vehicle->sale_date)) {
            throw new \InvalidArgumentException('Cannot cancel sale: Vehicle is not sold');
        }

        if ($vehicle->isCancelled()) {
            throw new \InvalidArgumentException('Cannot cancel sale: Vehicle sale is already cancelled');
        }

        return DB::transaction(function () use ($vehicle, $reason, $cancelledBy) {
            $now = Carbon::now();

            // 1. Find all related payments BEFORE any modifications
            $relatedPayments = $this->findVehicleRelatedPayments($vehicle);

            // 2. Clean up existing contributions FIRST to prevent data integrity issues
            foreach ($relatedPayments as $payment) {
                $this->cleanupPaymentContributions($payment);
            }

            // 3. Cancel the payments
            foreach ($relatedPayments as $payment) {
                $this->cancelPayment($payment, $now, $cancelledBy);

                // Note: For cancelVehicleSale, we only cancel payments without creating compensating contributions
                // because the sale data remains for audit purposes
            }

            // 3. Mark as cancelled but KEEP sale data for audit purposes
            $vehicle->update([
                'cancelled_at' => $now,
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledBy?->id,
            ]);

            return true;
        });
    }

    /**
     * Unsell a vehicle (cancel sale and clear all sale data)
     * This is used when you want the vehicle to return to "for sale" state
     *
     * @param Vehicle $vehicle
     * @param string|null $reason
     * @param User|null $cancelledBy
     * @return bool
     * @throws Throwable
     */
    public function unsellVehicle(Vehicle $vehicle, ?string $reason = null, ?User $cancelledBy = null): bool
    {
        if (is_null($vehicle->sale_date)) {
            throw new \InvalidArgumentException('Cannot unsell: Vehicle is not sold');
        }

        if ($vehicle->isCancelled()) {
            throw new \InvalidArgumentException('Cannot unsell: Vehicle sale is already cancelled');
        }

        return DB::transaction(function () use ($vehicle, $reason, $cancelledBy) {
            $now = Carbon::now();

            // 1. Find all related payments BEFORE any modifications
            $relatedPayments = $this->findVehicleRelatedPayments($vehicle);

            // 2. Create compensating contributions FIRST (before cleaning up or cancelling)
            // This ensures we work with the current contribution state
            foreach ($relatedPayments as $payment) {
                $this->createCompensatingContribution($payment, $now);
            }

            // 3. Clean up existing contributions AFTER compensation is created
            foreach ($relatedPayments as $payment) {
                $this->cleanupPaymentContributions($payment);
            }

            // 4. Cancel the payments
            foreach ($relatedPayments as $payment) {
                $this->cancelPayment($payment, $now, $cancelledBy);
            }

            // 3. Clear all sale data and reset vehicle to "for sale" state (like never sold)
            $originalProfit = $vehicle->profit; // Store profit before clearing
            $vehicle->update([
                'sale_date' => null,
                'price' => null,
                'profit' => null,
                'sale_duration' => null,
                'cancelled_at' => null,  // Reset to null so vehicle looks like never sold
                'cancellation_reason' => null,  // Clear cancellation data
                'cancelled_by' => null,  // Clear cancellation data
            ]);

            // 4. Get final total amount for notification
            $finalTotal = \App\Models\Total::orderBy('id', 'desc')->first();
            $finalTotalAmount = $finalTotal ? $finalTotal->amount : 0;

            // 5. Dispatch event notifications about vehicle unsell
            TotalChangedEvent::dispatch(
                $finalTotalAmount,
                'Скасовано продаж авто. Відкат прибутку:',
                -($originalProfit ?? 0) // Negative profit to show reversal
            );

            // 6. Send vehicle unsell notification to investors
            $investors = User::role('investor')->get();
            Notification::send($investors, new VehicleUnsoldNotification($vehicle, $reason));

            return true;
        });
    }

    /**
     * Restore a cancelled vehicle sale
     * This removes the cancellation and restores all related financial transactions
     *
     * @param Vehicle $vehicle
     * @return bool
     * @throws Throwable
     */
    public function restoreVehicleSale(Vehicle $vehicle): bool
    {
        if (!$vehicle->isCancelled()) {
            throw new \InvalidArgumentException('Cannot restore sale: Vehicle sale is not cancelled');
        }

        return DB::transaction(function () use ($vehicle) {
            // 1. Remove cancellation from vehicle
            $vehicle->update([
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'cancelled_by' => null,
            ]);

            // 2. Restore all related payments
            $cancelledPayments = Payment::where('vehicle_id', $vehicle->id)
                ->cancelled()
                ->get();

            foreach ($cancelledPayments as $payment) {
                $this->restorePayment($payment);

                // 3. Restore payments - this will be handled by payment restoration
            }

            return true;
        });
    }

    /**
     * Find all payments related to a vehicle sale
     * This includes company commissions and investor income payments
     *
     * @param Vehicle $vehicle
     * @return Collection
     */
    protected function findVehicleRelatedPayments(Vehicle $vehicle): Collection
    {
        // First try to find payments directly linked to this vehicle
        $directPayments = Payment::where('vehicle_id', $vehicle->id)
            ->whereIn('operation_id', [OperationType::REVENUE, OperationType::INCOME])
            ->notCancelled()
            ->get();

        // If we found payments with direct vehicle_id, use those
        if ($directPayments->isNotEmpty()) {
            return $directPayments;
        }

        // Do NOT return random payments - this causes data corruption
        // The original vehicle sale payments MUST have vehicle_id set for unselling to work
        return Payment::whereRaw('1 = 0')->get(); // Return empty Eloquent Collection
    }

    /**
     * Clean up contributions associated with a payment before cancelling
     * This prevents data integrity issues
     *
     * @param Payment $payment
     */
    protected function cleanupPaymentContributions(Payment $payment): void
    {
        // Remove any contributions that reference this payment
        Contribution::where('payment_id', $payment->id)->delete();

    }

    /**
     * Cancel a specific payment
     *
     * @param Payment $payment
     * @param Carbon $cancelledAt
     * @param User|null $cancelledBy
     */
    protected function cancelPayment(Payment $payment, Carbon $cancelledAt, ?User $cancelledBy): void
    {
        $payment->update([
            'is_cancelled' => true,
            'cancelled_at' => $cancelledAt,
            'cancelled_by' => $cancelledBy?->id,
        ]);
    }

    /**
     * Restore a cancelled payment
     *
     * @param Payment $payment
     */
    protected function restorePayment(Payment $payment): void
    {
        $payment->update([
            'is_cancelled' => false,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);
    }


    /**
     * Create compensating contribution to reverse income impact on investor balances
     *
     * @param Payment $originalPayment
     * @param Carbon $cancelledAt
     * @throws Throwable
     */
    protected function createCompensatingContribution(Payment $originalPayment, Carbon $cancelledAt): void
    {
        // Only create compensating contributions for INCOME operations
        // Other operations don't need compensation when unselling vehicles
        if ($originalPayment->operation_id !== \App\Enums\OperationType::INCOME->value) {
            return; // No compensation needed for non-income operations
        }

        // Create a compensating WITHDRAW payment with positive amount to reverse the INCOME
        // Using WITHDRAW operation type with positive amount because:
        // 1. INCOME adds to investor balance (baseAmount + paymentAmount)
        // 2. WITHDRAW subtracts from investor balance (baseAmount - paymentAmount)
        // 3. To reverse an INCOME, we need a WITHDRAW with the same positive amount
        $compensatingPaymentData = [
            'user_id' => $originalPayment->user_id,
            'operation_id' => \App\Enums\OperationType::WITHDRAW->value, // Use WITHDRAW to reverse INCOME
            'amount' => $originalPayment->amount, // POSITIVE amount - WITHDRAW subtracts this from balance
            'confirmed' => true,
            'created_at' => $cancelledAt,
            'vehicle_id' => $originalPayment->vehicle_id,
            'is_cancelled' => false,
        ];

        // Use PaymentService to create the payment (ensures contributions are handled correctly)
        // Use addIncome = true so that contribution percentages are NOT recalculated
        // (unselling reverses amounts only, like selling distributes amounts without recalculation)
        $paymentService = app(\App\Services\PaymentService::class);
        $compensatingPayment = $paymentService->createPayment($compensatingPaymentData, true);
    }

    /**
     * Get cancellation statistics
     *
     * @return array
     */
    public function getCancellationStats(): array
    {
        $totalVehicles = Vehicle::whereNotNull('sale_date')->count();
        $cancelledVehicles = Vehicle::cancelled()->count();
        $activeVehicles = Vehicle::sold()->count();

        $totalPayments = Payment::where('confirmed', true)->count();
        $cancelledPayments = Payment::cancelled()->count();
        $activePayments = Payment::active()->count();

        return [
            'vehicles' => [
                'total_sold' => $totalVehicles,
                'cancelled' => $cancelledVehicles,
                'active' => $activeVehicles,
                'cancellation_rate' => $totalVehicles > 0 ? round(($cancelledVehicles / $totalVehicles) * 100, 2) : 0,
            ],
            'payments' => [
                'total_confirmed' => $totalPayments,
                'cancelled' => $cancelledPayments,
                'active' => $activePayments,
                'cancellation_rate' => $totalPayments > 0 ? round(($cancelledPayments / $totalPayments) * 100, 2) : 0,
            ],
        ];
    }
}
