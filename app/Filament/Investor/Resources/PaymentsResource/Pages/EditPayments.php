<?php

namespace App\Filament\Investor\Resources\PaymentsResource\Pages;

use App\Filament\Investor\Resources\PaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Enums\OperationType;

class EditPayments extends EditRecord
{
    protected static string $resource = PaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['amount'] = abs(str_replace(',', '.', $data['amount']));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['amount'] = abs(str_replace(',', '.', $data['amount']));
        if((int)$data['operation_id'] === OperationType::WITHDRAW->value) {
            $data['amount'] = $data['amount'] * -1;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


}
