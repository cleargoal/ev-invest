<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Services\CalculationService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['amount'] = str_replace(',', '.', $data['amount']) * 100;
        if($data['operation_id'] === 5 || $data['operation_id'] === '5') {
            $data['amount'] = $data['amount'] * -1;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $created = static::getModel()::create($data);
        (new CalculationService())->processing($created); // call processing because Payment has been created by Livewire
        return $created;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
