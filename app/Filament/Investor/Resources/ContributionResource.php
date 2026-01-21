<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\ContributionResource\Pages;
use App\Models\Contribution;
use App\Models\Operation;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $modelLabel = 'Історія зміни Балансу';
    protected static ?string $pluralModelLabel = 'Історія зміни Балансу';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    public static function shouldRegisterNavigation(): bool
    {
        return !auth()->user()->hasRole('company');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()->hasRole('viewer')) {
            $query
                ->where('user_id', auth()->id())
                ->whereHas('payment', function (Builder $query) {
                    $query->where('user_id', auth()->id());
                });
        }

        return $query;
    }
    public static function table(Table $table): Table
    {
        $operationsFilterOptions = Operation::with('payments')->get()->pluck('title', 'id');
        return $table
            ->columns([
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('payment.operation.title')
                    ->width('5rem')
                    ->label('Сутність операції')
                    ->badge()
                    ->color(fn ($record) => match($record->payment->operation_id) {
                        1 => 'info',      // FIRST - blue
                        2 => 'emerald',   // Buy car - green
                        3 => 'purple',    // Sell CAR - purple
                        4 => 'fuchsia',   // Contrib - yellow
                        5 => 'warning',    // WITHDRAW - orange
                        6 => 'success',      // income - gray
                        7 => 'indigo',    // revenue - indigo
                        8 => 'gray',    // company leasing - indigo
                        9 => 'sky',    // investor leasing - indigo
                        10 => 'danger',    // recalculate - indigo
                        default => 'white',
                    })
                    ->sortable(),
                TextColumn::make('payment.amount')->money('USD')->width('5rem')->alignment(Alignment::Center)
                    ->label('Сума Операції'),
                TextColumn::make('amount')->money('USD')->width('5rem')->alignment(Alignment::Center)
                    ->label('Поточний Баланс'),
                ViewColumn::make('percents')->view('tables.columns.percents')->label('Мій Відсоток %')->width('5rem')->alignment(Alignment::Center),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
            ])
            ->actions([
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
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
//            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
