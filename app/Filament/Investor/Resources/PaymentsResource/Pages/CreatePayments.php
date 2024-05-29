<?php

namespace App\Filament\Investor\Resources\PaymentsResource\Pages;

use App\Filament\Investor\Resources\PaymentsResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayments extends CreateRecord
{
    protected static string $resource = PaymentsResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['amount'] = str_replace(',', '.', $data['amount']) * 100;
        if($data['operation_id'] === 5 || $data['operation_id'] === '5') {
            $data['amount'] = $data['amount'] * -1;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
