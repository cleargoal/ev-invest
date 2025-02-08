<?php

namespace App\Filament\Investor\Resources\LeasingResource\Pages;

use App\Filament\Investor\Resources\LeasingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeasings extends ListRecords
{
    protected static string $resource = LeasingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
