<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Constants\FinancialConstants;
use App\Enums\OperationType;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait HandlesInvestmentCalculations
{

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
        return $amount * FinancialConstants::COMPANY_COMMISSION_RATE;
    }

    /**
     * Distribute income to investors based on their contribution percentages (optimized with bulk insert)
     * 
     * @param float $totalAmount Total amount to distribute
     * @param OperationType $operationType Type of operation (INCOME or I_LEASING)
     * @param Carbon $paymentDate Date for the payments
     * @param PaymentService $paymentService Service to create payments
     * @param int|null $vehicleId Optional vehicle ID to link payments to
     * @return int Number of investors who received payments
     */
    protected function distributeIncomeToInvestors(
        float $totalAmount,
        OperationType $operationType,
        Carbon $paymentDate,
        PaymentService $paymentService,
        ?int $vehicleId = null
    ): int {
        if ($totalAmount <= 0) {
            return 0;
        }

        $profitForShare = $this->calculateCompanyCommission($totalAmount);
        $investors = $this->getInvestorsWithContributions();
        
        if ($investors->isEmpty()) {
            return 0;
        }

        // Create individual payments using PaymentService to ensure contributions are handled
        $processedInvestors = 0;

        foreach ($investors as $investor) {
            if (isset($investor->lastContribution) && $investor->lastContribution->percents > 0) {
                $incomeAmount = $profitForShare * $investor->lastContribution->percents / FinancialConstants::PERCENTAGE_PRECISION;
                
                // Only create payment if income amount is meaningful
                if ($incomeAmount >= FinancialConstants::MINIMUM_PAYMENT_AMOUNT) {
                    $paymentData = [
                        'user_id' => $investor->lastContribution->user_id,
                        'operation_id' => $operationType->value,
                        'amount' => $incomeAmount, // PaymentService handles MoneyCast conversion
                        'confirmed' => true,
                        'created_at' => $paymentDate,
                    ];
                    
                    // Add vehicle_id if provided
                    if ($vehicleId) {
                        $paymentData['vehicle_id'] = $vehicleId;
                    }
                    
                    // Use PaymentService to ensure contributions are created
                    $paymentService->createPayment($paymentData, true);
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
     * @param int|null $vehicleId Optional vehicle ID to link payment to
     * @return \App\Models\Payment
     * 
     * @throws \InvalidArgumentException
     */
    protected function createCompanyCommissionPayment(
        float $amount,
        OperationType $operationType,
        Carbon $paymentDate,
        PaymentService $paymentService,
        ?int $vehicleId = null
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

        // Add vehicle_id if provided
        if ($vehicleId) {
            $payData['vehicle_id'] = $vehicleId;
        }

        return $paymentService->createPayment($payData, true);
    }
}