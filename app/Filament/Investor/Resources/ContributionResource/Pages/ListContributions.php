<?php

namespace App\Filament\Investor\Resources\ContributionResource\Pages;

use App\Filament\Investor\Resources\ContributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContributions extends ListRecords
{
    protected static string $resource = ContributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
