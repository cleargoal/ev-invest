<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TotalResource\Pages;
use App\Filament\Resources\TotalResource\RelationManagers;
use App\Models\Total;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TotalResource extends Resource
{
    protected static ?string $model = Total::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('payment_id')
                    ->relationship('payment', 'id')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції'),
                Tables\Columns\TextColumn::make('payment.id')->sortable()->label('Payment ID'),
                Tables\Columns\TextColumn::make('payment.operation.title')->sortable(),
                Tables\Columns\TextColumn::make('payment.user.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'edit' => Pages\EditTotal::route('/{record}/edit'),
        ];
    }
}
