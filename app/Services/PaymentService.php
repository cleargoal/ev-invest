<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Events\TotalChangedEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{

    public function __construct(
        protected ContributionService $contributionService,
        protected TotalService $totalService,
        protected NotificationService $notificationService
    ) {}

    /**
     *  Create Payment
     * @param array $payData
     * @param bool $addIncome - gets true, when income calculated; in this case 'processing' method run from sellVehicle method
     * @return Payment
     */
    public function createPayment(array $payData, bool $addIncome = false): Payment
    {
        $newPay = new Payment();
        $newPay->fill($payData);
        $newPay->save();

        if ($payData['operation_id'] !== OperationType::REVENUE) {
            $this->manageContributions($newPay, $addIncome);
        }
        return $newPay;
    }

    /**
     * Process calculation of Total and contributions
     * @param Payment $payment
     * @param bool $addIncome
     * @return bool
     * @throws \Throwable
     */
    public function manageContributions(Payment $payment, bool $addIncome = false): bool
    {
        return DB::transaction(function () use ($payment, $addIncome) {
            if ($payment->confirmed) {
                $this->contributionService->createContribution($payment);
            }

            if (!$addIncome && $payment->confirmed) { // IF not add investors income when car sold. Just for operations of investors add or withdrawal
                $this->contributionService->contributions($payment->id, $payment->created_at);
            }

            return true;
        });
    }

    /**
     * Payment confirmation. Payment has been created by Livewire from Investor panel, but rest operations have to be run here
     * @param Payment $payment
     * @return void
     */
    public function paymentConfirmation(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->manageContributions($payment);
            $totalAmount = $this->totalService->createTotal($payment);
            
            // Events are dispatched after successful transaction
            TotalChangedEvent::dispatch($totalAmount, 'Внесок інвестора', $payment->amount);
        });
    }

    /**
     * Send new payment notification
     */
    public function notify(): void
    {
        $this->notificationService->notifyNewPayment();
    }


}
