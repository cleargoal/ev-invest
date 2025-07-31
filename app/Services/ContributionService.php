<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;

class ContributionService
{
    private const PERCENTAGE_PRECISION = 1000000; // Percents have precision 99.9999

    /**
     * Create contribution
     * @param Payment $payment
     * @return Contribution
     */
    public function createContribution(Payment $payment): Contribution
    {
        $lastContrib = Contribution::where('user_id', $payment->user_id)->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;
        $newContribution->amount = $lastContrib ? $lastContrib->amount + $payment->amount : $payment->amount;
        $newContribution->save();
        User::where('id', $payment->user_id)->update(['actual_contribution' => $newContribution->amount]);
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
                $userContributionPercent = ($lastContribution->amount / $totalAmount) * self::PERCENTAGE_PRECISION;
                $contributionsData[] = [
                    'user_id' => $lastContribution->user_id,
                    'payment_id' => $paymentId,
                    'percents' => $userContributionPercent,
                    'amount' => (int) round($lastContribution->amount * 100), // Convert dollar amount back to cents for bulk insert
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single bulk insert operation instead of N individual saves
        if (!empty($contributionsData)) {
            Contribution::insert($contributionsData);
        }
        
        return (int) round($totalAmount * 100); // Convert total to cents for consistency
    }

}
