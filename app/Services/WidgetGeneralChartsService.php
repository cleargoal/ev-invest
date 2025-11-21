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

    /**
     * Get number of cars sold per month
     * @return array
     */
    public function getCarsSoldPerMonth(): array
    {
        $soldVehicles = Vehicle::whereNotNull('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();

        if ($soldVehicles->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        // Find the first sale date
        $firstSaleDate = Carbon::parse($soldVehicles->first()->sale_date);
        $currentDate = now();

        $salesByMonth = $soldVehicles->groupBy(function ($vehicle) {
            return Carbon::parse($vehicle->sale_date)->format('Y-m');
        });

        // Create months range from first sale to current month
        $months = collect();
        $tempDate = $firstSaleDate->copy()->startOfMonth();

        while ($tempDate <= $currentDate->copy()->startOfMonth()) {
            $month = $tempDate->format('Y-m');
            if (!$months->contains($month)) {
                $months->push($month);
            }
            $tempDate->addMonth();
        }

        $labels = $months->map(function ($month) {
            return Carbon::createFromFormat('Y-m', $month)->format('M Y');
        });

        $data = $months->map(function ($month) use ($salesByMonth) {
            return $salesByMonth->has($month) ? $salesByMonth[$month]->count() : 0;
        });

        return [
            'labels' => $labels->toArray(),
            'data' => $data->toArray(),
        ];
    }

    /**
     * Get number of cars sold per week
     * @return array
     */
    public function getCarsSoldPerWeek(): array
    {
        $soldVehicles = Vehicle::whereNotNull('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();

        if ($soldVehicles->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        // Find the first sale date
        $firstSaleDate = Carbon::parse($soldVehicles->first()->sale_date);
        $currentDate = now();

        $salesByWeek = $soldVehicles->groupBy(function ($vehicle) {
            $saleDate = Carbon::parse($vehicle->sale_date);
            // Get the start of the week (Monday) for grouping
            return $saleDate->startOfWeek()->format('Y-m-d');
        });

        // Create weeks range from first sale week to current week
        $weeks = collect();
        $tempDate = $firstSaleDate->copy()->startOfWeek();

        while ($tempDate <= $currentDate->copy()->startOfWeek()) {
            $week = $tempDate->format('Y-m-d');
            if (!$weeks->contains($week)) {
                $weeks->push($week);
            }
            $tempDate->addWeek();
        }

        $labels = $weeks->map(function ($week) {
            $startOfWeek = Carbon::createFromFormat('Y-m-d', $week);
            $endOfWeek = $startOfWeek->copy()->endOfWeek();
            
            // Format as "May 13-19" or "Dec 30-Jan 5" for cross-month weeks
            if ($startOfWeek->month === $endOfWeek->month) {
                return $startOfWeek->format('M j') . '-' . $endOfWeek->format('j');
            } else {
                return $startOfWeek->format('M j') . '-' . $endOfWeek->format('M j');
            }
        });

        $data = $weeks->map(function ($week) use ($salesByWeek) {
            return $salesByWeek->has($week) ? $salesByWeek[$week]->count() : 0;
        });

        return [
            'labels' => $labels->toArray(),
            'data' => $data->toArray(),
        ];
    }
}
