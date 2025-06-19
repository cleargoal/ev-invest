<?php

namespace App\Filament\Investor\Widgets;

use App\Services\WidgetPersonalChartsService;
use Filament\Widgets\ChartWidget;

class UserBalanceChart extends ChartWidget
{
    protected static ?string $heading = 'Баланс користувача';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = app(WidgetPersonalChartsService::class)->collectUserPayments();

        return [
            'datasets' => [
                [
                    'data' => $data['allTotals'],
                    'backgroundColor' => '#36A2EB22',
                    'borderColor' => '#9BD0F5',
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
