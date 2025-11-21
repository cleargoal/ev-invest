<?php

namespace App\Filament\Investor\Resources\LeasingResource\Pages;

use App\Filament\Investor\Resources\LeasingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeasing extends EditRecord
{
    protected static string $resource = LeasingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
