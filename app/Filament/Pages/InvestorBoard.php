<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class InvestorBoard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.investor-board';
}