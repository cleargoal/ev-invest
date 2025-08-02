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
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Services\VehicleService;
use Illuminate\Support\Str;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?string $modelLabel = 'Автівка';
    protected static ?string $pluralModelLabel = 'Автівки у продажі';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getEloquentQuery(): Builder
    {
        // Show only vehicles for sale (not sold and not cancelled)
        return parent::getEloquentQuery()->where('profit', null)->orWhere('sale_date', null, );
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
                TextColumn::make('title')->label('Марка')->width('4rem')->sortable(),
                TextColumn::make('produced')->label(new HtmlString('Рік <br /> випуску'))->width('4rem')->sortable(),
                TextColumn::make('mileage')->label('Пробіг')->width('4rem')->sortable(),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br /> покупки'))->width('4rem')->sortable(),
                TextColumn::make('cost')->money('USD')->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума <br />покупки'))->sortable(),
                TextColumn::make('plan_sale')->money('USD')->width('4rem')->alignment(Alignment::End)
                    ->label(new HtmlString('Планова <br />Сума <br />продажу'))->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('sell')
                    ->modalIcon('heroicon-o-truck')
                    ->modalIconColor('warning')
                    ->modalHeading('Відмітити проданим Авто:')
                    ->modalDescription(fn(Vehicle $record) => new HtmlString('<div class="text-xl font-bold text-violet-800">' . $record->title . '</div>'))
                    ->label('Продаємо')
                    ->authorize('sell')
                    ->button()->color('success')
                    ->form([
                        TextInput::make('price')->label('Ціна продажу')->required(),
                        DatePicker::make('sale_date')->label('Дата продажу (не обов\'язково)'),
                    ])
                    ->action(function (array $data, Vehicle $record): void {
                        $record->price = $data['price']; // $data['price'] is in cents
                        $record->save();
                        $vehicleService = app(VehicleService::class);
                        $vehicleService->sellVehicle($record, $record->price);
                    })
                    ->modalWidth(MaxWidth::ExtraSmall)
                    ->modalCancelAction(fn(StaticAction $action) => $action->label('Поки що ні'))
                    ->modalSubmitAction(fn(StaticAction $action) => $action->label('Дійсно Продано')),

                DeleteAction::make(),
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
            'index' => \App\Filament\Investor\Resources\VehicleResource\Pages\ListVehicles::route('/'),
            'create' => \App\Filament\Investor\Resources\VehicleResource\Pages\CreateVehicle::route('/create'),
            'edit' => \App\Filament\Investor\Resources\VehicleResource\Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
