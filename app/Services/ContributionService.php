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

        // Calculate new contribution amount
        // Since WITHDRAW payments are stored as negative values, we can simply add
        $baseAmount = $lastContrib ? $lastContrib->amount : 0;
        $newContribution->amount = $baseAmount + $payment->amount;

        $newContribution->save();

        return $newContribution;
    }

    /**
     * Calculate contributions of all investors
     * @param Payment $payment
     * @param Carbon $createdAt
     * @return int
     */
    public function contributions(Payment $payment, Carbon $createdAt): int
    {
        // Check if payment owner has any contributions yet
        $paymentOwnerHasContributions = Contribution::where('user_id', $payment->user_id)->exists();

        // If this is their first payment, create their initial contribution
        if (!$paymentOwnerHasContributions) {
            $firstContribution = new Contribution();
            $firstContribution->payment_id = $payment->id;
            $firstContribution->user_id = $payment->user_id;
            $firstContribution->amount = $payment->amount;
            $firstContribution->percents = 0; // Will be calculated below
            $firstContribution->created_at = $createdAt;
            $firstContribution->updated_at = $createdAt;
            $firstContribution->save();
        }

        // Load all users who have contributions
        $usersWithLastContributions = User::whereHas('contributions')
            ->with('lastContribution')
            ->get();

        // Calculate total amount including the current payment's effect
        $totalAmount = $usersWithLastContributions->sum(function ($user) use ($payment, $paymentOwnerHasContributions) {
            $lastAmount = optional($user->lastContribution)->amount ?? 0;
            // If this user is the payment owner AND they had contributions before, add the payment amount
            if ($user->id === $payment->user_id && $paymentOwnerHasContributions) {
                return $lastAmount + $payment->amount;
            }
            return $lastAmount;
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

                // Calculate the user's current amount (including payment if this is the payment owner)
                $currentAmount = $lastContribution->amount;
                if ($user->id === $payment->user_id && $paymentOwnerHasContributions) {
                    $currentAmount += $payment->amount;
                }

                $userContributionPercent = ($currentAmount / $totalAmount) * FinancialConstants::PERCENTAGE_PRECISION;
                $contributionsData[] = [
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'percents' => $userContributionPercent,
                    'amount' => (int) round($currentAmount * FinancialConstants::CENTS_PER_DOLLAR), // Convert dollar amount back to cents for bulk insert
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
        }

        return (int) round($totalAmount * FinancialConstants::CENTS_PER_DOLLAR); // Convert total to cents for consistency
    }

}
