<?php

namespace App\Filament\Investor\Resources\VehicleResource\Pages;

use App\Filament\Investor\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['cost'] = $data['cost'] * 100;
        return $data;
    }


}
