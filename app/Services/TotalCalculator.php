<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;

class TotalCalculator
{

    /**
     * Get any change of total pool from 'payments' and recalculate it
     * @param $income
     * @return bool
     */
    public function calculate($income): bool
    {
        $lastRecord = Total::latest()->first();
        $newAmount = $lastRecord->amount + $income;
        $newRecord = new Total();
        $newRecord->amount = $newAmount;
        return $newRecord->save();
    }

    /**
     * Calculate contributions of all investors
     *
     */
    public function contributions($paymentId)
    {
        $usersWithLastContributions = User::with('lastContribution')->get();
        $totalAmount = $usersWithLastContributions->sum(function ($user) {
            return optional($user->lastContribution)->amount ?? 0;
        });

        foreach ($usersWithLastContributions as $user) {
            $lastContribution = $user->lastContribution;

            if ($lastContribution && $totalAmount > 0) {
                $userContributionPercent = ($lastContribution->amount / $totalAmount) * 100;
                $newContribution = new Contribution();
                $newContribution->user_id = $lastContribution->user_id;
                $newContribution->payment_id = $paymentId;
                $newContribution->percents = $userContributionPercent;
                $newContribution->save();
            }
        }
        return $totalAmount;
    }

    public function seeding(): true
    {
        $payments = Payment::all();
        foreach ($payments as $payment) {
            $this->contributions($payment->id);
        }
        return true;
    }
}
