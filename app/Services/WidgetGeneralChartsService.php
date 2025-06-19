<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Total;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WidgetGeneralChartsService
{
    /**
     * Total pool data
     * @return array
     */
    public function getMergedChartData(): array
    {
        // Pool data
        $pool = Total::orderBy('created_at')->get();

        $poolData = $pool->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($group) {
            return $group->sortBy('created_at')->last()->amount;
        });

        // Vehicles inventory running total
        $vehiclesInventory = $this->getInventoryRunningTotal();

        $vehiclesMap = $vehiclesInventory->keyBy('date')->map(function ($item) {
            return $item['total'];
        });

        // Merge dates
        $allDates = $poolData->keys()->merge($vehiclesMap->keys())->unique()->sort()->values();

        $labels = $allDates->map(fn ($date) => Carbon::parse($date)->format('M j Y'));

        $poolAmounts = [];
        $vehiclesAmounts = [];

        $previousPoolAmount = 0;
        $previousVehicleTotal = 0;

        foreach ($allDates as $date) {
            if ($poolData->has($date)) {
                $previousPoolAmount = $poolData[$date];
            }
            $poolAmounts[] = $previousPoolAmount;

            if ($vehiclesMap->has($date)) {
                $previousVehicleTotal = $vehiclesMap[$date];
            }
            $vehiclesAmounts[] = $previousVehicleTotal;
        }

        // Calculate differences
        $differences = [];
        for ($i = 0; $i < count($poolAmounts); $i++) {
            $differences[] = $poolAmounts[$i] - $vehiclesAmounts[$i];
        }

        return [
            'labels' => $labels->toArray(),
            'poolAmounts' => $poolAmounts,
            'vehiclesAmounts' => $vehiclesAmounts,
            'differences' => $differences,
        ];
    }


    public function getInventoryRunningTotal(): Collection
    {
// Purchases: cost is cast by MoneyCast
        $purchases = Vehicle::select('created_at', 'cost')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->created_at->format('Y-m-d'),
                    'amount' => $item->cost, // already dollars
                ];
            });

// Sales: cost is cast by MoneyCast
        $sales = Vehicle::whereNotNull('sale_date')
            ->select('sale_date', 'cost')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->sale_date)->format('Y-m-d'),
                    'amount' => -$item->cost, // subtract sold vehicles
                ];
            });

        $events = $purchases->concat($sales)
            ->groupBy('date')
            ->map(fn($group, $date) => [
                'date' => $date,
                'amount' => $group->sum('amount'),
            ])
            ->sortBy('date')
            ->values();

        $runningTotal = [];
        $sum = 0;

        foreach ($events as $event) {
            $sum += $event['amount'];
            $runningTotal[] = [
                'date' => $event['date'],
                'total' => $sum,
            ];
        }

        return collect($runningTotal);
    }
}
