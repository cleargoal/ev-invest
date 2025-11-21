<?php

namespace App\Filament\Investor\Resources\PaymentsResource\Pages;

use App\Enums\OperationType;
use App\Filament\Investor\Resources\PaymentsResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\PaymentService;

class CreatePayments extends CreateRecord
{
    protected static string $resource = PaymentsResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['amount'] = abs(str_replace(',', '.', $data['amount']));
        if($data['operation_id'] === OperationType::WITHDRAW) {
            $data['amount'] = $data['amount'] * -1;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $paymentService = app(PaymentService::class);
        $paymentService->notify();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
