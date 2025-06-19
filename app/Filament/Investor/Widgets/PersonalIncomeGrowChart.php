<?php

namespace App\Filament\Investor\Widgets;

use App\Services\WidgetPersonalChartsService;
use Filament\Widgets\ChartWidget;

class PersonalIncomeGrowChart extends ChartWidget
{
    protected static ?string $heading = 'Баланс та Доходи інвестора';
    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $widgetService = app(WidgetPersonalChartsService::class);
        $data = $widgetService->collectUserPayments();
        return [
            'datasets' => [
                [
                    'label' => "Баланс",
                    'data' => $data['allTotals'],
                    'backgroundColor' => '#36A2EB22',
                    'borderColor' => '#9BD0F5',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                ],
                [
                    'label' => "Доходи",
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
