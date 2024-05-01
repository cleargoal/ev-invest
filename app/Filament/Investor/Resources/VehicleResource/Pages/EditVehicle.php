<?php

namespace App\Filament\Investor\Resources\VehicleResource\Pages;

use App\Filament\Investor\Resources\VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cost'] = $data['cost'] / 100;
        $data['plan_sale'] = $data['plan_sale'] / 100;

        return $data;
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['cost'] = $data['cost'] * 100;
        $data['plan_sale'] = $data['plan_sale'] * 100;

        return $data;
    }
}
