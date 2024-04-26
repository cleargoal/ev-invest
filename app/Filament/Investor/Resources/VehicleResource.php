<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\RelationManagers;
use App\Models\Vehicle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('produced'),
                TextColumn::make('mileage'),
                TextColumn::make('sale_date')->date()->label('Дата продажу'),
                TextColumn::make('cost')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::End),
                TextColumn::make('price')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::End),
                TextColumn::make('profit')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::End)->weight(FontWeight::Bold),
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
            'index' => \App\Filament\Investor\Resources\VehicleResource\Pages\ListVehicles::route('/'),
            'create' => \App\Filament\Investor\Resources\VehicleResource\Pages\CreateVehicle::route('/create'),
            'edit' => \App\Filament\Investor\Resources\VehicleResource\Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
