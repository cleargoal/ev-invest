<?php

namespace App\Filament\Investor\Resources\LeasingResource\Pages;

use App\Filament\Investor\Resources\LeasingResource;
use App\Services\LeasingService;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;

class CreateLeasing extends CreateRecord
{
    protected static string $resource = LeasingResource::class;
    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['price'] = str_replace(',', '.', $data['price']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $leasingService = app(LeasingService::class);
        return $leasingService->getLeasing($data);
    }

}
