<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\VehicleResource\RelationManagers;
use App\Models\Vehicle;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Services\CalculationService;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?string $modelLabel = 'Автівка';
    protected static ?string $pluralModelLabel = 'Автівки у продажі';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('profit', null);
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    TextInput::make('title')->label('Марка та модель'),
                    TextInput::make('produced')->label('Рік випуску'),
                    TextInput::make('mileage')->label('Пробіг'),
                ])->columns(3)->columnSpanFull(),
                Group::make([
                    TextInput::make('cost')->label('Ціна покупки'),
                    TextInput::make('plan_sale')->label('Планова Сума продажу')
                ])->columns(3)->columnSpanFull(),
                Group::make([
                    DatePicker::make('created_at')->label('Дата покупки'),
                    DatePicker::make('sale_date')->label('Дата продажу')
                        ->hidden(request()->routeIs(('filament.investor.resources.vehicles.create'))),
                ])->columns(3)->columnSpanFull(),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Марка')->width('4rem'),
                TextColumn::make('produced')->label(new HtmlString('Рік <br /> випуску'))->width('4rem'),
                TextColumn::make('mileage')->label('Пробіг'),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br /> покупки'))->width('4rem'),
                TextColumn::make('sale_date')->date()->label(new HtmlString('Дата<br /> продажу'))->width('4rem'),
                TextColumn::make('sale_duration')->label(new HtmlString('Тривалість<br /> продажу,<br /> днів'))->width('4rem')->alignment(Alignment::Center),
                TextColumn::make('cost')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> покупки')),
                TextColumn::make('plan_sale')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)
                    ->label(new HtmlString('Планова <br />Сума<br /> продажу')),
                TextColumn::make('price')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> продажу')),
                TextColumn::make('profit')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->weight(FontWeight::Bold)
                    ->label('Прибуток'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('sell')
                    ->authorize('sell')
                    ->button()->color('success')
                    ->label('Продано')
                    ->form([
                        TextInput::make('price')->label('Ціна продажу')->required(),
                    ])
                    ->action(function (array $data, Vehicle $record): void {
                        $record->price = $data['price'] * 100; // $data['price'] is in cents
                        $record->save();
                        (new CalculationService())->sellVehicle($record, $record->price);
                    })
                    ->modalWidth(MaxWidth::ExtraSmall)
                    ->modalCancelAction(fn(StaticAction $action) => $action->label('Поки що ні'))
                    ->modalSubmitAction(fn(StaticAction $action) => $action->label('Дійсно Продано')),
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
            'index' => \App\Filament\Investor\Resources\VehicleResource\Pages\ListVehicles::route('/'),
            'create' => \App\Filament\Investor\Resources\VehicleResource\Pages\CreateVehicle::route('/create'),
            'edit' => \App\Filament\Investor\Resources\VehicleResource\Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
