<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\PaymentsResource\Pages\CreatePayments;
use App\Filament\Investor\Resources\PaymentsResource\Pages\EditPayments;
use App\Filament\Investor\Resources\PaymentsResource\Pages\ListPayments;
use App\Filament\Investor\Resources\PaymentsResource\RelationManagers;
use App\Models\Operation;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;

class PaymentsResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Фінансова операція';
    protected static ?string $pluralModelLabel = 'Фінансові операції';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Radio::make('operation_id')->label('Я хочу')->inline()->inlineLabel(false)
                    ->options([
                        '4' => 'Додати до внеску',
                        '5' => 'Замовити вилучення',
                    ]),
                TextInput::make('amount')->label('Сума (можна з десятковими знаками)')->extraInputAttributes(['width' => 200]),
            ])->columns(2);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->user()->id);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn(Model $record) => match (true) {
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
                IconColumn::make('confirmed_icon')->label('Зарахування')->width('5rem')->alignment(Alignment::Center)
                    ->state(fn(?Payment $record) => $record->confirmed)
                    ->sortable()
                    ->icon(fn(bool $state): string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        false => 'heroicon-o-clock',
                    })
                    ->color(fn(bool $state): string => match ($state) {
                        true => 'success',
                        false => 'warning',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('operation_id')->label('Operation')
                    ->options(fn (): array => Operation::query()->pluck('title', 'id')->all()),
            ])
            ->actions([
                EditAction::make('edit')
                    ->visible(fn(Payment $record) => !$record->confirmed ),
                DeleteAction::make()
                    ->visible(fn(Payment $record) => !$record->confirmed )
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayments::route('/create'),
            'edit' => EditPayments::route('/{record}/edit'),
//            'show' => ViewPayment::route('/{record}/show'),
        ];
    }
}
