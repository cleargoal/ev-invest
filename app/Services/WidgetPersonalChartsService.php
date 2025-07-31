<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class WidgetPersonalChartsService
{
    public function collectUserPayments(): array
    {
        $userId = Auth::id();
        $userPayments = Payment::where('user_id', $userId)->notCancelled()->orderBy('created_at')->get();
        $labels = [];
        $allTotals = [];
        $incomeTotals = [];

        $runningTotal = 0;
        $incomeTotal = 0;

        foreach ($userPayments as $payment) {
            $runningTotal += $payment->amount;

            // Include both INCOME and I_LEASING operations in income calculations
            if (in_array($payment->operation_id, [OperationType::INCOME->value, OperationType::I_LEASING->value])) {
                $incomeTotal += $payment->amount;
            }

            $labels[] = $payment->created_at->format('M j Y');
            $allTotals[] = $runningTotal;
            $incomeTotals[] = $incomeTotal;
        }

        return [
            'labels' => $labels,
            'allTotals' => $allTotals,
            'incomeTotals' => $incomeTotals,
        ];    }
}
