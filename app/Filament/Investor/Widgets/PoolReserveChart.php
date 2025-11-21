<?php

declare(strict_types=1);

namespace App\Filament\Investor\Widgets;

use App\Services\WidgetGeneralChartsService;
use Filament\Widgets\ChartWidget;

class PoolReserveChart extends ChartWidget
{
    protected static ?string $heading = 'Весь пул/Резерв/Гроші в роботі';
    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $widgetChartService = app(WidgetGeneralChartsService::class);
        $data = $widgetChartService->getMergedChartData();

        return [
            'datasets' => [
                [
                    'label' => "Весь пул",
                    'data' => $data['poolAmounts'],
                    'backgroundColor' => '#36A2EB22',
                    'borderColor' => '#9BD0F5',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                ],
                [
                    'label' => "Резерв",
                    'data' => $data['differences'],
                    'backgroundColor' => '#aa449922',
                    'borderColor' => '#9999F5',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                ],
                [
                    'label' => "Гроші в роботі",
                    'data' => $data['vehiclesAmounts'],
                    'backgroundColor' => '#bbAc2222',
                    'borderColor' => '#bBab22',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
