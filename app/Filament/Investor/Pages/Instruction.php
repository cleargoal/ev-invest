<?php

namespace App\Filament\Investor\Pages;

use App\Filament\Investor\Widgets\PayConfirmWidget;
use App\Filament\Investor\Widgets\SoldVehicles;
use App\Filament\Investor\Widgets\StatsOverview;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Page;

class Instruction extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static string $view = 'filament.pages.instruction';

    public function getTitle(): string | Htmlable
    {
        return 'Інструкція користувача';
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }

    public function toHtml()
    {
        // TODO: Implement toHtml() method.
    }
}
