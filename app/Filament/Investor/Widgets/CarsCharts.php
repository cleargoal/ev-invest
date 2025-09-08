<?php

namespace App\Filament\Investor\Widgets;

use Filament\Widgets\Widget;

class CarsCharts extends Widget
{
    protected static string $view = 'filament.widgets.cars-charts';
    protected int | string | array $columnSpan = 12;
    protected static ?string $widgetLabel = 'Графіки автівок';

    protected function getViewData(): array
    {
        return [];
    }
}