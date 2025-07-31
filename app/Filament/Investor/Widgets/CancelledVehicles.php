<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Vehicle;
use App\Services\VehicleService;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CancelledVehicles extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?string $widgetLabel = 'Скасовані продажі';

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Скасовані продажі автомобілів';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vehicle::cancelled()->with(['cancelledBy']), // Get only cancelled vehicles
            )
            ->columns([
                TextColumn::make('title')->label('Марка')->width('3rem')->sortable(),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br />покупки'))->width('3rem')->sortable(),
                TextColumn::make('sale_date')->date()->label(new HtmlString('Дата<br />продажу'))->width('3rem')->sortable(),
                TextColumn::make('cancelled_at')->date()->label(new HtmlString('Дата<br />скасування'))->width('3rem')->sortable(),
                TextColumn::make('cost')->money('USD')->width('3rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br />покупки'))->sortable(),
                TextColumn::make('price')->money('USD')->width('3rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br />продажу'))->sortable(),
                TextColumn::make('profit')->money('USD')->width('3rem')->alignment(Alignment::End)->weight(FontWeight::Bold)
                    ->label('Прибуток')->sortable(),
                TextColumn::make('cancelledBy.name')->label('Скасував')->width('3rem')->sortable(),
                TextColumn::make('cancellation_reason')->label('Причина')->limit(30)->tooltip(function (TextColumn $column): ?string {
                    $state = $column->getState();
                    return strlen($state) > 30 ? $state : null;
                })->width('4rem'),
            ])
            ->defaultSort('cancelled_at', 'desc')
            ->actions([
                Action::make('restore')
                    ->modalIcon('heroicon-o-arrow-uturn-up')
                    ->modalIconColor('success')
                    ->modalHeading('Відновити продаж автомобіля')
                    ->modalDescription(fn(Vehicle $record) => new HtmlString(
                        '<div class="space-y-2">' .
                        '<div class="text-lg font-semibold text-gray-900">' . $record->title . '</div>' .
                        '<div class="text-sm text-gray-600">Дата скасування: ' . $record->cancelled_at?->format('d.m.Y H:i') . '</div>' .
                        '<div class="text-sm text-gray-600">Причина скасування: ' . ($record->cancellation_reason ?? 'Не вказано') . '</div>' .
                        '<div class="text-sm text-green-600 font-medium">Відновлення поверне всі пов\'язані платежі та перерахування</div>' .
                        '</div>'
                    ))
                    ->label('Відновити')
                    ->button()
                    ->color('success')
                    ->visible(fn() => auth()->user()?->hasRole('company')) // Only show to company role
                    ->action(function (Vehicle $record) {
                        try {
                            $vehicleService = app(VehicleService::class);
                            $result = $vehicleService->restoreVehicleSale($record);
                            
                            if ($result) {
                                Notification::make()
                                    ->title('Продаж відновлено')
                                    ->body("Продаж автомобіля \"{$record->title}\" успішно відновлено")
                                    ->success()
                                    ->send();
                                    
                                // Refresh the table to remove the restored vehicle
                                $this->dispatch('$refresh');
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Помилка')
                                ->body('Не вдалося відновити продаж: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Так, відновити продаж')
                    ->modalCancelActionLabel('Скасувати'),

                Action::make('view_details')
                    ->modalIcon('heroicon-o-eye')
                    ->modalHeading('Деталі скасування')
                    ->modalDescription(fn(Vehicle $record) => new HtmlString(
                        '<div class="space-y-3">' .
                        '<div><strong>Автомобіль:</strong> ' . $record->title . '</div>' .
                        '<div><strong>Дата покупки:</strong> ' . $record->created_at?->format('d.m.Y') . '</div>' .
                        '<div><strong>Дата продажу:</strong> ' . $record->sale_date?->format('d.m.Y') . '</div>' .
                        '<div><strong>Дата скасування:</strong> ' . $record->cancelled_at?->format('d.m.Y H:i:s') . '</div>' .
                        '<div><strong>Скасував:</strong> ' . ($record->cancelledBy?->name ?? 'Невідомо') . '</div>' .
                        '<div><strong>Сума покупки:</strong> $' . number_format($record->cost ?? 0, 2) . '</div>' .
                        '<div><strong>Сума продажу:</strong> $' . number_format($record->price ?? 0, 2) . '</div>' .
                        '<div><strong>Прибуток:</strong> $' . number_format($record->profit ?? 0, 2) . '</div>' .
                        '<div><strong>Причина скасування:</strong></div>' .
                        '<div class="p-3 bg-gray-50 rounded-md text-sm">' . ($record->cancellation_reason ?? 'Причина не вказана') . '</div>' .
                        '</div>'
                    ))
                    ->label('Деталі')
                    ->button()
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрити'),
            ])
            ->emptyStateHeading('Немає скасованих продажів')
            ->emptyStateDescription('Скасовані продажі автомобілів будуть відображатися тут.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}