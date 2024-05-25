<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Vehicle;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\HtmlString;

class SoldVehicles extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vehicle::where('sale_date', '<>', null),
            )
            ->columns([
                TextColumn::make('title')->label('Марка')->width('4rem'),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br /> покупки'))->width('4rem'),
                TextColumn::make('sale_date')->date()->label(new HtmlString('Дата<br /> продажу'))->width('4rem'),
                TextColumn::make('sale_duration')->label(new HtmlString('Тривалість<br /> продажу,<br /> днів'))->width('4rem')->alignment(Alignment::Center),
                TextColumn::make('cost')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> покупки')),
                TextColumn::make('plan_sale')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)
                    ->label(new HtmlString('Планова <br />Сума<br /> продажу')),
                TextColumn::make('price')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> продажу')),
                TextColumn::make('profit')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->weight(FontWeight::Bold)
                    ->label('Прибуток'),
            ]);
    }
}
