<?php

namespace App\Filament\Widgets;

use App\Services\WidgetGeneralChartsService;
use Filament\Widgets\ChartWidget;

class CarsSoldPerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Кількість проданих авто за місяць';
    protected static ?string $maxHeight = '300px';
    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'ticks' => [
                    'stepSize' => 1,
                    'precision' => 0,
                ],
                'grid' => [
                    'display' => true,
                    'color' => 'rgba(0, 0, 0, 0.1)',
                    'lineWidth' => 1,
                ],
            ],
            'x' => [
                'grid' => [
                    'display' => true,
                    'color' => 'rgba(0, 0, 0, 0.05)',
                    'lineWidth' => 1,
                ],
            ],
        ],
    ];

    protected function getData(): array
    {
        $data = app(WidgetGeneralChartsService::class)->getCarsSoldPerMonth();

        return [
            'datasets' => [
                [
                    'data' => $data['data'],
                    'backgroundColor' => '#3b82f622',
                    'borderColor' => '#3b82f6',
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