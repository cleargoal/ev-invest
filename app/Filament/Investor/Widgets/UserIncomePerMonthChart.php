<?php

namespace App\Filament\Investor\Widgets;

use App\Services\WidgetPersonalChartsService;
use Filament\Widgets\ChartWidget;

class UserIncomePerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Доход інвестора помісячно';
    protected static ?string $maxHeight = '300px';
    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
    ];

    protected function getData(): array
    {
        $data = app(WidgetPersonalChartsService::class)->getUserIncomePerMonth();

        return [
            'datasets' => [
                [
                    'data' => $data['data'],
                    'backgroundColor' => '#f59e0b22',
                    'borderColor' => '#f59e0b',
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
