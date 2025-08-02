<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FinancialConstants;
use App\Enums\OperationType;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleCancellationService
{
    public function __construct(
        protected TotalService $totalService
    ) {}

    /**
     * Cancel a vehicle sale ("unsell" the vehicle)
     * This marks the sale as cancelled and reverses all related financial transactions
     * 
     * @param Vehicle $vehicle
     * @param string|null $reason
     * @param User|null $cancelledBy
     * @return bool
     * @throws \Throwable
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
            
            // 1. Find and cancel all related payments BEFORE clearing sale data
            $relatedPayments = $this->findVehicleRelatedPayments($vehicle);
            
            foreach ($relatedPayments as $payment) {
                $this->cancelPayment($payment, $now, $cancelledBy);
                
                // 2. Create compensating total entries to reverse the financial impact
                $this->createCompensatingTotal($payment);
            }

            // 3. Clear all sale data and reset vehicle to "for sale" state
            $vehicle->update([
                'sale_date' => null,
                'price' => null,
                'profit' => null,
                'sale_duration' => null,
                'cancelled_at' => $now,
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledBy?->id,
            ]);

            return true;
        });
    }

    /**
     * Restore a cancelled vehicle sale
     * This removes the cancellation and restores all related financial transactions
     * 
     * @param Vehicle $vehicle
     * @return bool
     * @throws \Throwable
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
                
                // 3. Create compensating total entries to restore the financial impact
                $this->createCompensatingTotal($payment, true);
            }

            return true;
        });
    }

    /**
     * Find all payments related to a vehicle sale
     * This includes company commissions and investor income payments
     * 
     * @param Vehicle $vehicle
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function findVehicleRelatedPayments(Vehicle $vehicle): \Illuminate\Database\Eloquent\Collection
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

        // Fallback: Find payments created around the same time as the sale
        $saleDate = $vehicle->sale_date;
        if (!$saleDate) {
            return collect(); // Return empty collection if no sale date
        }

        $searchStart = $saleDate->copy()->subMinutes(5);
        $searchEnd = $saleDate->copy()->addMinutes(5);

        return Payment::where(function ($query) use ($searchStart, $searchEnd) {
                $query->whereBetween('created_at', [$searchStart, $searchEnd]);
            })
            ->whereIn('operation_id', [OperationType::REVENUE, OperationType::INCOME])
            ->notCancelled()
            ->get();
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
     * Create a compensating total entry to reverse/restore financial impact
     * 
     * @param Payment $payment
     * @param bool $restore Whether this is a restoration (positive) or cancellation (negative)
     */
    protected function createCompensatingTotal(Payment $payment, bool $restore = false): void
    {
        // Create a compensating payment with opposite amount
        $compensatingAmount = $restore ? $payment->amount : -$payment->amount;
        
        // Create and save a compensating payment record for the total calculation
        $compensatingPayment = Payment::create([
            'user_id' => $payment->user_id,
            'operation_id' => $payment->operation_id,
            'amount' => $compensatingAmount,
            'confirmed' => true,
            'created_at' => now(),
        ]);

        // Update the total
        $this->totalService->createTotal($compensatingPayment);
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