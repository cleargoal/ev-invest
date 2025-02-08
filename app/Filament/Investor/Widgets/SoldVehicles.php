<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Vehicle;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
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
                Vehicle::where('profit', '<>', null),
            )
            ->columns([
                TextColumn::make('title')->label('Марка')->width('4rem')->sortable(),
                TextColumn::make('created_at')->date()->label(new HtmlString('Дата<br /> покупки'))->width('4rem')->sortable(),
                TextColumn::make('sale_date')->date()->label(new HtmlString('Дата<br /> продажу'))->width('4rem')->sortable(),
                TextColumn::make('sale_duration')->label(new HtmlString('Тривалість<br /> продажу,<br /> днів'))->width('4rem')->alignment(Alignment::Center)->sortable(),
                TextColumn::make('cost')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> покупки'))->sortable(),
                TextColumn::make('plan_sale')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)
                    ->label(new HtmlString('Планова <br />Сума<br /> продажу'))->sortable(),
                TextColumn::make('price')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->label(new HtmlString('Сума<br /> продажу'))->sortable(),
                TextColumn::make('profit')->money('USD', divideBy: 100)->width('4rem')->alignment(Alignment::End)->weight(FontWeight::Bold)
                    ->label('Прибуток')->sortable(),
            ])
            ->defaultSort('sale_date', 'desc')            ;
    }
}
