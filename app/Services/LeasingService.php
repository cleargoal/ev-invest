<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Models\Leasing;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LeasingIncomeNotification;
use App\Services\Traits\HandlesInvestmentCalculations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeasingService
{
    use HandlesInvestmentCalculations;

    public function __construct(
        protected PaymentService $paymentService,
        protected TotalService $totalService,
        protected Leasing $leasing
    ) {}


    public function getLeasing(array $data): Leasing
    {
        return DB::transaction(function () use ($data) {
            $data['created_at'] = $data['created_at'] ?? Carbon::now();
            $leasing = $this->createLeasing($data);

            $payment = $this->companyCommissions($leasing);
            $totalAmount = $this->totalService->createTotal($payment);
            $this->investIncome($leasing);

            // Notifications are sent after successful transaction
            $this->notify();
            
            return $leasing;
        });
    }

    protected function createLeasing(array $data): Leasing
    {
        $leasing = new Leasing();
        $leasing->fill($data);
        $leasing->save();
        return $leasing;
    }

    protected function notify(): void
    {
        if (config('app.env') !== 'local') {
            $users = User::role('investor')->get();
        }
        else {
            $users = User::role('admin')->get();
        }
        Notification::send($users, new LeasingIncomeNotification());
    }
    /**
     * Company commissions add to Payment
     * @param Leasing $leasing
     * @return Payment
     */
    public function companyCommissions(Leasing $leasing): Payment
    {
        if (!$leasing->price || $leasing->price <= 0) {
            throw new \InvalidArgumentException('Leasing must have a positive price to calculate commissions.');
        }

        return $this->createCompanyCommissionPayment(
            $leasing->price,
            OperationType::C_LEASING,
            $leasing->created_at,
            $this->paymentService
        );
    }

    /**
     * Calculate invest income for every investor
     * @param Leasing $leasing
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Leasing $leasing): int
    {
        return $this->distributeIncomeToInvestors(
            $leasing->price ?? 0,
            OperationType::I_LEASING,
            $leasing->created_at,
            $this->paymentService
        );
    }

}
