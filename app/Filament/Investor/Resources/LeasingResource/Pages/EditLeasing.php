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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['price'] = $data['price'] / 100;
        return $data;
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['price'] = $data['price'] * 100;
        return $data;
    }
}
