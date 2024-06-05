<?php

namespace App\Filament\Resources\TotalResource\Pages;

use App\Filament\Resources\TotalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTotals extends ListRecords
{
    protected static string $resource = TotalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
