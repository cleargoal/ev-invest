<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\TotalResource\Pages;
use App\Filament\Investor\Resources\TotalResource\RelationManagers;
use App\Models\Total;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class TotalResource extends Resource
{
    protected static ?string $model = Total::class;

    protected static ?string $modelLabel = 'Весь пул грошей';
    protected static ?string $pluralModelLabel = 'Весь пул грошей';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції')->sortable(),
                TextColumn::make('payment.user.name')->width('5rem')->label('Інвестор')->sortable(),
                TextColumn::make('payment.operation.title')->width('5rem')->label('Сутність операції')->sortable(),
                TextColumn::make('payment.amount')->money('USD')->width('5rem')->alignment(Alignment::Center)
                    ->label('Сума Операції'),
                TextColumn::make('amount')->money('USD')->width('5rem')->alignment(Alignment::End)
                    ->label('Ітогова Сума Пулу')->weight(FontWeight::Bold),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
            ])
            ->bulkActions([
//                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
//                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTotals::route('/'),
        ];
    }
}
