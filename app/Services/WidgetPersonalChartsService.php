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
        ];
    }

    public function getUserIncomePerMonth(): array
    {
        $userId = Auth::id();

        // Get all income payments for the user (INCOME and I_LEASING)
        $incomePayments = Payment::where('user_id', $userId)
            ->whereIn('operation_id', [OperationType::INCOME->value, OperationType::I_LEASING->value])
            ->notCancelled()
            ->orderBy('created_at')
            ->get();

        if ($incomePayments->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        // Find the first income date
        $firstIncomeDate = $incomePayments->first()->created_at;
        $currentDate = now();

        // Group payments by month
        $incomeByMonth = $incomePayments->groupBy(function ($payment) {
            return $payment->created_at->format('Y-m');
        });

        // Create months range from first income to current month
        $months = collect();
        $tempDate = $firstIncomeDate->copy()->startOfMonth();

        while ($tempDate <= $currentDate->copy()->startOfMonth()) {
            $month = $tempDate->format('Y-m');
            if (!$months->contains($month)) {
                $months->push($month);
            }
            $tempDate->addMonth();
        }

        $labels = $months->map(function ($month) {
            return \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y');
        });

        // Sum income amounts per month
        $data = $months->map(function ($month) use ($incomeByMonth) {
            if ($incomeByMonth->has($month)) {
                return $incomeByMonth[$month]->sum('amount');
            }
            return 0;
        });

        return [
            'labels' => $labels->toArray(),
            'data' => $data->toArray(),
        ];
    }
}
