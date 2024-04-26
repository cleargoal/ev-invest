<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentsResource\Pages;
use App\Filament\Resources\PaymentsResource\RelationManagers;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class PaymentsResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->user()->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Model $record) => match ($record->amount < 0) {
                $record->amount < 0 => 'bg-red-50',
                default => null,
            })
            ->heading('Мої платежі')
            ->columns([
                TextColumn::make('user.name')->width('5rem'),
                TextColumn::make('created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('operation.title')->width('5rem')->label('Сутність операції')->sortable(),
                TextColumn::make('amount')
                    ->weight(FontWeight::Bold)
                    ->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::End)
                    ->label('Сума'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayments::route('/create'),
            'edit' => Pages\EditPayments::route('/{record}/edit'),
        ];
    }
}
