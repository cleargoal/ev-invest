<?php

namespace App\Filament\Investor\Pages;
use App\Filament\Investor\Widgets\PayConfirmWidget;
use App\Filament\Investor\Widgets\SoldVehicles;
use App\Filament\Investor\Widgets\StatsOverview;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class InvestorBoard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.investor-board';

    public function getTitle(): string | Htmlable
    {
        return 'Dashboard: ' . auth()->user()->name;
    }

    public function getColumns(): int | string | array
    {
        return 6;
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
