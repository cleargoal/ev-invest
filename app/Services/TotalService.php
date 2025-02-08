<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Total;

class TotalService
{

    /**
     * Get any change of total pool from 'payments' and recalculate it
     * @param Payment $payment
     * @return int
     */
    public function createTotal(Payment $payment): int
    {
        $lastRecord = Total::orderBy('id', 'desc')->first();
        $total = new Total();
        $total->amount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;
        $total->payment_id = $payment->id;
        $total->created_at = $payment->created_at;
        $total->save();

        return $total->amount;
    }
}
