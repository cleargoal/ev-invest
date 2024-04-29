<?php

namespace App\Filament\Investor\Pages;
use App\Filament\Investor\Widgets\StatsOverview;

class InvestorBoard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.investor-board';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class
        ];
    }
}
