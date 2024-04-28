<?php

namespace App\Filament\Investor\Resources\TotalResource\Pages;

use App\Filament\Investor\Resources\TotalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTotal extends EditRecord
{
    protected static string $resource = TotalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
