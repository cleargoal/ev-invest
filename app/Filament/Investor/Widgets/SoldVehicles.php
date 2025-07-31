<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Vehicle;
use App\Services\VehicleService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class SoldVehicles extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?string $widgetLabel = 'Продані автівки';

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Продані автівки';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vehicle::sold(), // Use sold scope to get only sold and not cancelled vehicles
            )
            ->columns([
                TextColumn::make('title')->label('Марка')->width('4rem')->sortable(),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br /> покупки'))->width('4rem')->sortable(),
                TextColumn::make('sale_date')->date()->label(new HtmlString('Дата<br /> продажу'))->width('4rem')->sortable(),
                TextColumn::make('sale_duration')->label(new HtmlString('Тривалість<br /> продажу,<br /> днів'))->width('4rem')->alignment(Alignment::Center)->sortable(),
                TextColumn::make('cost')->money('USD')->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> покупки'))->sortable(),
                TextColumn::make('plan_sale')->money('USD')->width('4rem')->alignment(Alignment::End)
                    ->label(new HtmlString('Планова <br />Сума<br /> продажу'))->sortable(),
                TextColumn::make('price')->money('USD')->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> продажу'))->sortable(),
                TextColumn::make('profit')->money('USD')->width('4rem')->alignment(Alignment::End)->weight(FontWeight::Bold)
                    ->label('Прибуток')->sortable(),
            ])
            ->defaultSort('sale_date', 'desc')
            ->actions([
                Action::make('unsell')
                    ->modalIcon('heroicon-o-arrow-uturn-left')
                    ->modalIconColor('warning')
                    ->modalHeading('Скасувати продаж автомобіля')
                    ->modalDescription(fn(Vehicle $record) => new HtmlString(
                        '<div class="space-y-2">' .
                        '<div class="text-lg font-semibold text-gray-900">' . $record->title . '</div>' .
                        '<div class="text-sm text-gray-600">Дата продажу: ' . $record->sale_date?->format('d.m.Y') . '</div>' .
                        '<div class="text-sm text-gray-600">Сума продажу: $' . number_format($record->price ?? 0, 2) . '</div>' .
                        '<div class="text-sm text-red-600 font-medium">Увага: Ця дія скасує всі пов\'язані платежі та перерахування!</div>' .
                        '</div>'
                    ))
                    ->label('Скасувати продаж')
                    ->button()
                    ->color('warning')
                    ->visible(fn() => auth()->user()?->hasRole('company')) // Only show to company role
                    ->form([
                        Textarea::make('reason')
                            ->label('Причина скасування')
                            ->placeholder('Вкажіть причину скасування продажу (наприклад: повернення покупцем, помилка в документах, тощо)')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (Vehicle $record, array $data) {
                        try {
                            $vehicleService = app(VehicleService::class);
                            $result = $vehicleService->unsellVehicle($record, $data['reason']);
                            
                            if ($result) {
                                Notification::make()
                                    ->title('Продаж скасовано')
                                    ->body("Продаж автомобіля \"{$record->title}\" успішно скасовано")
                                    ->success()
                                    ->send();
                                    
                                // Refresh the table to remove the unsold vehicle
                                $this->dispatch('$refresh');
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Помилка')
                                ->body('Не вдалося скасувати продаж: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Так, скасувати продаж')
                    ->modalCancelActionLabel('Скасувати')
            ])
            ;
    }
}
