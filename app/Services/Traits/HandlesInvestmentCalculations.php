<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Enums\OperationType;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait HandlesInvestmentCalculations
{
    protected const PERCENTAGE_PRECISION = 1000000; // Percents have precision 99.9999
    protected const COMPANY_COMMISSION_RATE = 0.5; // 50% commission rate
    protected const MINIMUM_PAYMENT_AMOUNT = 0.01; // Minimum meaningful payment amount

    /**
     * Get company user with validation
     * 
     * @throws \InvalidArgumentException
     */
    protected function getCompanyUser(): User
    {
        $companyUser = User::role('company')->first();
        if (!$companyUser) {
            throw new \InvalidArgumentException('Company user not found. Please ensure a user with "company" role exists.');
        }
        return $companyUser;
    }

    /**
     * Get all investors with their last contributions
     */
    protected function getInvestorsWithContributions(): Collection
    {
        return User::with('lastContribution')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'investor');
            })
            ->get();
    }

    /**
     * Calculate company commission from amount
     */
    protected function calculateCompanyCommission(float $amount): float
    {
        return $amount * self::COMPANY_COMMISSION_RATE;
    }

    /**
     * Distribute income to investors based on their contribution percentages
     * 
     * @param float $totalAmount Total amount to distribute
     * @param OperationType $operationType Type of operation (INCOME or I_LEASING)
     * @param Carbon $paymentDate Date for the payments
     * @param PaymentService $paymentService Service to create payments
     * @return int Number of investors who received payments
     */
    protected function distributeIncomeToInvestors(
        float $totalAmount,
        OperationType $operationType,
        Carbon $paymentDate,
        PaymentService $paymentService
    ): int {
        if ($totalAmount <= 0) {
            return 0;
        }

        $profitForShare = $this->calculateCompanyCommission($totalAmount);
        $investors = $this->getInvestorsWithContributions();
        $processedInvestors = 0;

        foreach ($investors as $investor) {
            if (isset($investor->lastContribution) && $investor->lastContribution->percents > 0) {
                $incomeAmount = $profitForShare * $investor->lastContribution->percents / self::PERCENTAGE_PRECISION;
                
                // Only create payment if income amount is meaningful
                if ($incomeAmount >= self::MINIMUM_PAYMENT_AMOUNT) {
                    $payData = [
                        'user_id' => $investor->lastContribution->user_id,
                        'operation_id' => $operationType,
                        'amount' => $incomeAmount,
                        'confirmed' => true,
                        'created_at' => $paymentDate,
                    ];
                    $paymentService->createPayment($payData, true);
                    $processedInvestors++;
                }
            }
        }

        return $processedInvestors;
    }

    /**
     * Create company commission payment
     * 
     * @param float $amount Amount to calculate commission from
     * @param OperationType $operationType Type of operation
     * @param Carbon $paymentDate Date for the payment
     * @param PaymentService $paymentService Service to create payment
     * @return \App\Models\Payment
     * 
     * @throws \InvalidArgumentException
     */
    protected function createCompanyCommissionPayment(
        float $amount,
        OperationType $operationType,
        Carbon $paymentDate,
        PaymentService $paymentService
    ): \App\Models\Payment {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive to calculate commissions.');
        }

        $companyUser = $this->getCompanyUser();
        $commissions = $this->calculateCompanyCommission($amount);

        $payData = [
            'user_id' => $companyUser->id,
            'operation_id' => $operationType,
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $paymentDate,
        ];

        return $paymentService->createPayment($payData, true);
    }
}