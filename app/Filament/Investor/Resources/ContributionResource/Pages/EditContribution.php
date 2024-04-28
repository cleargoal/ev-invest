<?php

namespace App\Filament\Investor\Resources\ContributionResource\Pages;

use App\Filament\Investor\Resources\ContributionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContribution extends EditRecord
{
    protected static string $resource = ContributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
