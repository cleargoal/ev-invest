<?php

namespace App\Filament\Resources\TotalResource\Pages;

use App\Filament\Resources\TotalResource;
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
