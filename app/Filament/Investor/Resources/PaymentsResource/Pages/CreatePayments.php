<?php

namespace App\Filament\Investor\Resources\PaymentsResource\Pages;

use App\Filament\Investor\Resources\PaymentsResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayments extends CreateRecord
{
    protected static string $resource = PaymentsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['amount'] = $data['amount'] * 100;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
