<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\TotalResource\Pages;
use App\Filament\Investor\Resources\TotalResource\RelationManagers;
use App\Models\Total;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class TotalResource extends Resource
{
    protected static ?string $model = Total::class;

    protected static ?string $modelLabel = 'Весь пул';
    protected static ?string $pluralModelLabel = 'Весь пул';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('payment.user.name')->width('5rem')->label('Інвестор')->sortable(),
                TextColumn::make('payment.operation.title')->width('5rem')->label('Сутність операції')->sortable(),
                TextColumn::make('amount')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::End)
                    ->label('Ітогова Сума Пулу')->weight(FontWeight::Bold),
            ])
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
            'create' => Pages\CreateTotal::route('/create'),
//            'edit' => Pages\EditTotal::route('/{record}/edit'),
        ];
    }
}
