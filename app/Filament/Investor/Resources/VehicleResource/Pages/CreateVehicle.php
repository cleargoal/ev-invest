<?php

namespace App\Filament\Investor\Resources\VehicleResource\Pages;

use App\Filament\Investor\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\TotalCalculator;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['cost'] = $data['cost'] * 100;
        $data['plan_sale'] = $data['plan_sale'] * 100;
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return (new TotalCalculator())->buyVehicle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
