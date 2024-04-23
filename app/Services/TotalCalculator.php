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
     * @param $payment
     * @return bool
     */
    public function calculate($payment): bool
    {
        $lastRecord = Total::orderBy('id', 'desc')->first();
        $newAmount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;
        $newRecord = new Total();
        $newRecord->payment_id = $payment->id;
        $newRecord->amount = $newAmount;
        $newRecord->created_at = $payment->created_at;
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
                $userContributionPercent = ($lastContribution->amount / $totalAmount) * 1000000;
                $newContribution = new Contribution();
                $newContribution->user_id = $lastContribution->user_id;
                $newContribution->payment_id = $paymentId;
                $newContribution->percents = $userContributionPercent;
                $newContribution->amount = $lastContribution->amount;
                $newContribution->save();
            }
        }
        return $totalAmount;
    }

    public function processing($payment): true
    {
        $lastContrib = Contribution::where('user_id', $payment->user_id )->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;
        $newContribution->amount = $lastContrib ? $lastContrib->amount + $payment->amount : $payment->amount;
        $newContribution->save();

        $this->calculate($payment);
        $this->contributions($payment->id);
        return true;
    }

    /**
     * Just for seeding
     */
    public function seeding(): true
    {
        $payments = Payment::all();
        foreach ($payments as $payment) {
            $this->processing($payment);
        }
        return true;
    }
}
