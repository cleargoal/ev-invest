<?php

namespace App\Filament\Investor\Pages;

use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Page;

class Agreement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static string $view = 'filament.pages.agreement';
    protected static ?string $navigationGroup = 'Документи';
    protected static ?string $title = 'Інвестиційна Угода';

    public function getTitle(): string | Htmlable
    {
        return 'Інвестиційні умови';
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
