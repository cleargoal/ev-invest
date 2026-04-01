<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\LeasingResource\Pages;
use App\Filament\Investor\Resources\LeasingResource\RelationManagers;
use App\Models\Leasing;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeasingResource extends Resource
{
    protected static ?string $model = Leasing::class;
    protected static ?string $modelLabel = 'Оренда';
    protected static ?string $pluralModelLabel = 'Доходи від оренди';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-top-right-on-square';

    public static function form(Form $form): Form
    {
        $now = now();
        $currentMonth = $now->month;
        $previousMonth = $now->copy()->subMonth()->month;

        // Ukrainian month names
        $months = [
            1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
            5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
            9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
        ];

        // Only allow previous and current month
        $availableMonths = [
            $previousMonth => $months[$previousMonth],
            $currentMonth => $months[$currentMonth],
        ];

        return $form
            ->schema([
                Select::make('month')
                    ->label('Місяць')
                    ->options($availableMonths)
                    ->default($currentMonth)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $now = now();
                        $currentMonth = $now->month;

                        // If selected month is greater than current month, it's from previous year
                        // (e.g., December selected in January)
                        if ($state > $currentMonth) {
                            $set('year', $now->year - 1);
                        } else {
                            $set('year', $now->year);
                        }
                    }),
                TextInput::make('year')
                    ->label('Рік')
                    ->default(function () {
                        $now = now();
                        $currentMonth = $now->month;
                        $previousMonth = $now->copy()->subMonth()->month;

                        // If previous month is December (12), use previous year
                        return $previousMonth > $currentMonth ? $now->year - 1 : $now->year;
                    })
                    ->numeric()
                    ->readOnly()
                    ->required(),
                TextInput::make('price')
                    ->label('Прибуток')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->helperText('Сума прибутку від оренди за місяць'),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Ukrainian month names for display
        $months = [
            1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
            5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
            9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
        ];

        return $table
            ->columns([
                TextColumn::make('month')
                    ->label('Місяць')
                    ->formatStateUsing(fn ($state) => $months[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('year')
                    ->label('Рік')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Прибуток')
                    ->money('USD')
                    ->width('6rem')
                    ->alignment(Alignment::End)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Створено')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Змінено')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListLeasings::route('/'),
            'create' => Pages\CreateLeasing::route('/create'),
            'edit' => Pages\EditLeasing::route('/{record}/edit'),
        ];
    }
}
