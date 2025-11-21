<?php

namespace App\Filament\Widgets;

use App\Services\WidgetGeneralChartsService;
use Filament\Widgets\ChartWidget;

class CarsSoldPerWeekChart extends ChartWidget
{
    protected static ?string $heading = 'Кількість проданих авто за тиждень';
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
        $data = app(WidgetGeneralChartsService::class)->getCarsSoldPerWeek();

        return [
            'datasets' => [
                [
                    'data' => $data['data'],
                    'backgroundColor' => '#10b98122',
                    'borderColor' => '#10b981',
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