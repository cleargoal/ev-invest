<?php

namespace App\Filament\Investor\Pages;

use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Page;

class Instruction extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static string $view = 'filament.pages.instruction';
    protected static ?string $navigationGroup = 'Документи';
    protected static ?string $title = 'Інструкція користувача';

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
