<?php

namespace App\Filament\Investor\Widgets;

use App\Services\WidgetPersonalChartsService;
use Filament\Widgets\ChartWidget;

class UserIncomeChart extends ChartWidget
{
    protected static ?string $heading = 'Доходи користувача';
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
        $data = app(WidgetPersonalChartsService::class)->collectUserPayments();

        return [
            'datasets' => [
                [
                    'data' => $data['incomeTotals'],
                    'backgroundColor' => '#36dea922',
                    'borderColor' => '#9BFE95',
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
