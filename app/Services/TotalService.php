<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Total;
use Illuminate\Support\Facades\DB;

class TotalService
{

    /**
     * Get any change of total pool from 'payments' and recalculate it with transaction safety
     * @param Payment $payment
     * @return int
     * @throws \Throwable
     */
    public function createTotal(Payment $payment): int
    {
        return DB::transaction(function () use ($payment) {
            // Check if total already exists for this payment to prevent duplicates
            $existingTotal = Total::where('payment_id', $payment->id)->first();
            if ($existingTotal) {
                return (int) $existingTotal->amount;
            }

            // Get the latest total amount atomically
            $lastRecord = Total::orderBy('id', 'desc')->lockForUpdate()->first();
            $newAmount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;

            // Use updateOrCreate for better atomicity
            $total = Total::updateOrCreate(
                ['payment_id' => $payment->id],
                [
                    'amount' => $newAmount,
                    'created_at' => $payment->created_at,
                ]
            );

            return (int) $total->amount;
        });
    }
}
