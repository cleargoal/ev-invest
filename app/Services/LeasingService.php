<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Models\Leasing;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LeasingIncomeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeasingService
{
    private const PERCENTAGE_PRECISION = 1000000; // Percents have precision 99.9999
    private const COMPANY_COMMISSION_RATE = 0.5; // 50% commission rate

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
        $companyUser = User::role('company')->first();
        if (!$companyUser) {
            throw new \InvalidArgumentException('Company user not found. Please ensure a user with "company" role exists.');
        }

        if (!$leasing->price || $leasing->price <= 0) {
            throw new \InvalidArgumentException('Leasing must have a positive price to calculate commissions.');
        }

        $commissions = $leasing->price * self::COMPANY_COMMISSION_RATE;
        $payData = [
            'user_id' => $companyUser->id,
            'operation_id' => OperationType::C_LEASING, // company commissions
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $leasing->created_at,
        ];

        return $this->paymentService->createPayment((array)$payData, true);
    }

    /**
     * Calculate invest income for every investor
     * @param Leasing $leasing
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Leasing $leasing): int
    {
        if (!$leasing->price || $leasing->price <= 0) {
            // No income to distribute, return 0 investors processed
            return 0;
        }

        $profitForShare = $leasing->price * self::COMPANY_COMMISSION_RATE; // Remaining income for investor distribution
        $investors = User::with('lastContribution')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'investor');
            })
            ->get();
            
        $processedInvestors = 0;
        foreach ($investors as $investor) {
            if (isset($investor->lastContribution) && $investor->lastContribution->percents > 0) {
                $incomeAmount = $profitForShare * $investor->lastContribution->percents / self::PERCENTAGE_PRECISION;
                
                // Only create payment if income amount is meaningful (> 0.01)
                if ($incomeAmount >= 0.01) {
                    $payData = [
                        'user_id' => $investor->lastContribution->user_id,
                        'operation_id' => OperationType::I_LEASING,
                        'amount' => $incomeAmount,
                        'confirmed' => true,
                        'created_at' => $leasing->created_at,
                    ];
                    $this->paymentService->createPayment((array)$payData, true);
                    $processedInvestors++;
                }
            }
        }
        return $processedInvestors;
    }

}
