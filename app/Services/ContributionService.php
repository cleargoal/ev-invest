<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FinancialConstants;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ContributionService
{

    /**
     * Create contribution
     * @param Payment $payment
     * @return Contribution
     * @throws \InvalidArgumentException
     */
    public function createContribution(Payment $payment): Contribution
    {
        // Validate that the user exists
        $user = User::find($payment->user_id);
        if (!$user) {
            throw new \InvalidArgumentException("User with ID {$payment->user_id} does not exist");
        }

        $lastContrib = Contribution::where('user_id', $payment->user_id)->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;

        // Handle different operation types correctly
        $baseAmount = $lastContrib ? $lastContrib->amount : 0;

        // WITHDRAW operations should subtract from the contribution amount
        if ($payment->operation_id === \App\Enums\OperationType::WITHDRAW->value) {
            $newContribution->amount = $baseAmount - $payment->amount;
        } else {
            // All other operations add to the contribution amount
            $newContribution->amount = $baseAmount + $payment->amount;
        }

        $newContribution->save();

        // Update user's actual contribution
        // Both newContribution->amount and actual_contribution use MoneyCast, so we can assign directly
        $user->update(['actual_contribution' => $newContribution->amount]);

        return $newContribution;
    }

    /**
     * Calculate contributions of all investors
     * @param int $paymentId
     * @param Carbon $createdAt
     * @return int
     */
    public function contributions(int $paymentId, Carbon $createdAt): int
    {
        // Only load users who actually have contributions for better performance
        $usersWithLastContributions = User::whereHas('contributions')
            ->with('lastContribution')
            ->get();

        $totalAmount = $usersWithLastContributions->sum(function ($user) {
            return optional($user->lastContribution)->amount ?? 0;
        });


        if ($totalAmount <= 0) {
            return 0;
        }

        // Prepare bulk insert data instead of individual saves
        $contributionsData = [];
        $now = $createdAt->format('Y-m-d H:i:s');

        foreach ($usersWithLastContributions as $user) {
            $lastContribution = $user->lastContribution;

            if ($lastContribution) {
                // Verify data integrity
                if ($lastContribution->user_id !== $user->id) {
                    Log::warning("Contribution user_id mismatch detected", [
                        'user_id' => $user->id,
                        'contribution_user_id' => $lastContribution->user_id,
                        'contribution_id' => $lastContribution->id
                    ]);
                }

                $userContributionPercent = ($lastContribution->amount / $totalAmount) * FinancialConstants::PERCENTAGE_PRECISION;
                $contributionsData[] = [
                    'user_id' => $user->id, // Fixed: Use the user ID from the loop, not from lastContribution
                    'payment_id' => $paymentId,
                    'percents' => $userContributionPercent,
                    'amount' => (int) round($lastContribution->amount * FinancialConstants::CENTS_PER_DOLLAR), // Convert dollar amount back to cents for bulk insert
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } else {
                // Log users who were found by whereHas but have no lastContribution
                Log::warning("User found by whereHas('contributions') but lastContribution is null", [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'contribution_count' => $user->contributions()->count()
                ]);
            }
        }

        // Single bulk insert operation instead of N individual saves
        if (!empty($contributionsData)) {
            Contribution::insert($contributionsData);

            // Update actual_contribution for all affected users
            // contributionsData contains amounts in cents, but MoneyCast expects dollars and converts to cents
            // So we need to convert back to dollars for the MoneyCast to work correctly
            foreach ($contributionsData as $contributionData) {
                $userId = $contributionData['user_id'];
                $newAmountInDollars = $contributionData['amount'] / FinancialConstants::CENTS_PER_DOLLAR;

                User::where('id', $userId)->update(['actual_contribution' => $newAmountInDollars]);
            }
        }

        return (int) round($totalAmount * FinancialConstants::CENTS_PER_DOLLAR); // Convert total to cents for consistency
    }

}
