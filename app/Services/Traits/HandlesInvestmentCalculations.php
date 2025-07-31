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
     * Get all investors with their last contributions (optimized)
     */
    protected function getInvestorsWithContributions(): Collection
    {
        return User::whereHas('roles', function ($query) {
                $query->where('name', 'investor');
            })
            ->whereHas('contributions') // Only investors with contributions
            ->with('lastContribution')
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
     * Distribute income to investors based on their contribution percentages (optimized with bulk insert)
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
        
        if ($investors->isEmpty()) {
            return 0;
        }

        // Prepare bulk insert data instead of individual payment creations
        $paymentsData = [];
        $now = $paymentDate->format('Y-m-d H:i:s');
        $processedInvestors = 0;

        foreach ($investors as $investor) {
            if (isset($investor->lastContribution) && $investor->lastContribution->percents > 0) {
                $incomeAmount = $profitForShare * $investor->lastContribution->percents / self::PERCENTAGE_PRECISION;
                
                // Only create payment if income amount is meaningful
                if ($incomeAmount >= self::MINIMUM_PAYMENT_AMOUNT) {
                    $paymentsData[] = [
                        'user_id' => $investor->lastContribution->user_id,
                        'operation_id' => $operationType->value,
                        'amount' => (int) round($incomeAmount * 100), // Convert to cents for MoneyCast
                        'confirmed' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $processedInvestors++;
                }
            }
        }

        // Single bulk insert operation instead of N individual creates
        if (!empty($paymentsData)) {
            \App\Models\Payment::insert($paymentsData);
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