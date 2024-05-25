<?php

namespace App\Filament\Investor\Pages;
use App\Filament\Investor\Widgets\PayConfirmWidget;
use App\Filament\Investor\Widgets\SoldVehicles;
use App\Filament\Investor\Widgets\StatsOverview;

class InvestorBoard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.investor-board';
    protected int | string | array $columnSpan = 4;


    public function getColumns(): int | string | array
    {
        return 4;
    }
    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            PayConfirmWidget::class,
            SoldVehicles::class,
        ];
    }
}
