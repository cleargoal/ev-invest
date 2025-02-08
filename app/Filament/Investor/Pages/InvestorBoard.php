<?php

namespace App\Filament\Investor\Pages;

use App\Filament\Investor\Widgets\PayConfirmWidget;
use App\Filament\Investor\Widgets\SoldVehicles;
use App\Filament\Investor\Widgets\StatsOverviewGeneral;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class InvestorBoard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.investor-board';

    public function getTitle(): string | Htmlable
    {
        return 'Dashboard: ' . auth()->user()->name;
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 6;
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 6;
    }

    protected function getHeaderWidgets(): array
    {
        return [
//            StatsOverviewGeneral::class,
//            PayConfirmWidget::class,
//            SoldVehicles::class,
        ];
    }
}
