<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PayConfirmWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?string $widgetLabel = 'Не підтверджені Фінансові операції';

    public static function canView(): bool
    {
        return Payment::where('confirmed', false)->get()->count() > 0;
    }
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::where('confirmed', false),
            )
            ->heading('Не підтверджені платежі')
            ->columns([
                TextColumn::make('created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('operation.title')->width('5rem')->label('Сутність операції'),
                TextColumn::make('user.name')->width('5rem')->label('Інвестор'),
                TextColumn::make('amount')
                    ->weight(FontWeight::Bold)
                    ->money('USD')->width('5rem')->alignment(Alignment::End)
                    ->label('Сума'),
                ToggleColumn::make('confirmed')->label('Підтвердження')->width('5rem')->alignment(Alignment::Center)
                    ->visible(auth()->user()->hasRole('company'))
                    ->afterStateUpdated(function ($record, $state) {
                        $paymentService = app(PaymentService::class);
                        $paymentService->paymentConfirmation($record);
                    }),
            ]);
    }
}
