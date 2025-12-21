<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Enums\OperationType;
use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert negative withdrawal amounts to positive for display in form
        $data['amount'] = abs(str_replace(',', '.', $data['amount']));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['amount'] = abs(str_replace(',', '.', $data['amount']));
        if((int)$data['operation_id'] === OperationType::WITHDRAW->value) {
            $data['amount'] = $data['amount'] * -1;
        }

        return $data;
    }
}
