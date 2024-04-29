<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Resources\PaymentsResource\Pages;
use App\Filament\Resources\PaymentsResource\RelationManagers;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;

class PaymentsResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Фінансова операція';
    protected static ?string $pluralModelLabel = 'Фінасові операції';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Radio::make('operation_id')->label('Я хочу')
                    ->options([
                        '4' => 'Додати до внеску',
                        '5' => 'Замовити вилучення',
                    ]),
                TextInput::make('amount')->label('Сума'),
                TextInput::make('user_id')->default(auth()->user()->id)->hidden(),
            ])->columns(4);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->user()->id)->where('confirmed', true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Model $record) => match (true) {
                $record->amount < 0 => 'bg-red-50',
                default => null,
            })
            ->heading('Мої платежі')
            ->columns([
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
            'index' => \App\Filament\Investor\Resources\PaymentsResource\Pages\ListPayments::route('/'),
            'create' => \App\Filament\Investor\Resources\PaymentsResource\Pages\CreatePayments::route('/create'),
            'edit' => \App\Filament\Investor\Resources\PaymentsResource\Pages\EditPayments::route('/{record}/edit'),
        ];
    }
}
