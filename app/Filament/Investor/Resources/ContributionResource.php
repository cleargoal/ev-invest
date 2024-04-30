<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\ContributionResource\Pages;
use App\Filament\Investor\Resources\ContributionResource\RelationManagers;
use App\Models\Contribution;
use App\Models\Operation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\ViewColumn;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $modelLabel = 'Внесок';
    protected static ?string $pluralModelLabel = 'Внески';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->user()->id);
    }

    public static function table(Table $table): Table
    {
        $operationsFilterOptions = Operation::with('payments')->get()->pluck('title', 'id');
        return $table
            ->columns([
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('payment.user.name')->width('5rem')->label('Інвестор')->sortable(),
                TextColumn::make('payment.operation.title')->width('5rem')->label('Сутність операції')->sortable(),
                TextColumn::make('amount')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::Center)
                    ->label('Моя Сума'),
                ViewColumn::make('percents')->view('tables.columns.percents')->label('Мій Відсоток %')->width('5rem')->alignment(Alignment::Center),
            ])
            ->filters([
                SelectFilter::make('payment.operation_id')->options($operationsFilterOptions)->label('Операція'),
            ])
            ->actions([
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
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
//            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
