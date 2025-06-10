<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\LeasingResource\Pages;
use App\Filament\Investor\Resources\LeasingResource\RelationManagers;
use App\Models\Leasing;
use App\Models\Vehicle;
use DateTime;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
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
        return $form
            ->schema([
                Select::make('vehicle_id')
                    ->label('Автівка здана в оренду')
                    ->options(Vehicle::all()->pluck('title', 'id') )
                    ->required()
                    ->searchable(),
                DatePicker::make('start_date')->label('Дата початку')
                    ->required()
                    ->reactive() // Trigger reactivity when this value changes
                    ->afterStateUpdated(function ($state, callable $set, $get) {
                        if ($get('end_date')) {
                            $duration = (new DateTime($state))->diff(new DateTime($get('end_date')))->days;
                            $set('duration', $duration);
                        }
                    }),
                DatePicker::make('end_date')->label('Дата закінчення')
                    ->required()
                    ->reactive() // Trigger reactivity when this value changes
                    ->afterStateUpdated(function ($state, callable $set, $get) {
                        if ($get('start_date')) {
                            $duration = (new DateTime($get('start_date')))->diff(new DateTime($state))->days;
                            $set('duration', $duration);
                        }
                    }),
                TextInput::make('duration')->label('Тривалість, днів')
                    ->numeric()
                    ->readOnly(),
                TextInput::make('price')->label('Дохід')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.title')->label('Авто яке здавали')
                    ->numeric()->sortable(),
                TextColumn::make('start_date')->label('Дата початку')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')->label('Дата закінчення')
                    ->date()
                    ->sortable(),
                TextColumn::make('duration')->label('Тривалість, днів')
                    ->numeric()->alignment(Alignment::Center)
                    ->sortable(),
                TextColumn::make('price')->label('Дохід')
                    ->money('USD')->width('5rem')->alignment(Alignment::End)
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->label('Створено')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->label('Змінено')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
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
