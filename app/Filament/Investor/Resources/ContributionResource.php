<?php

namespace App\Filament\Investor\Resources;

use App\Filament\Investor\Resources\ContributionResource\Pages;
use App\Filament\Investor\Resources\ContributionResource\RelationManagers;
use App\Models\Contribution;
use App\Models\Operation;
use App\Models\User;
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
use Filament\Tables\Columns\ViewColumn;
use Filament\Navigation\NavigationItem;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $modelLabel = 'Внесок';
    protected static ?string $pluralModelLabel = 'Внески';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

//    TODO: hide navigation of this resource for Company
//    NavigationItem::make()->hidden(fn (): bool => ! auth()->user()->can('viewAny', Payment::class));
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        if (!auth()->user()->hasRole('viewer')) {
            return parent::getEloquentQuery()->where('user_id', auth()->user()->id);
        }
        else {
            return parent::getEloquentQuery();
        }
    }

    public static function table(Table $table): Table
    {
        $operationsFilterOptions = Operation::with('payments')->get()->pluck('title', 'id');
        return $table
            ->columns([
                TextColumn::make('payment.created_at')->date()->width('5rem')->label('Дата операції'),
                TextColumn::make('payment.user.name')->width('5rem')->label('Інвестор')->sortable(),
                TextColumn::make('payment.operation.title')->width('5rem')->label('Сутність операції')->sortable(),
                TextColumn::make('payment.amount')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::Center)
                    ->label('Сума Операції'),
                TextColumn::make('amount')->money('USD', divideBy: 100)->width('5rem')->alignment(Alignment::Center)
                    ->label('Мій Ітого Внеску'),
                ViewColumn::make('percents')->view('tables.columns.percents')->label('Мій Відсоток %')->width('5rem')->alignment(Alignment::Center),
            ])
            ->filters([
                SelectFilter::make('payment.operation_id')->options($operationsFilterOptions)->label('Операція'),
                SelectFilter::make('payment.user_id')->options(User::all()->pluck('name'))->label('Інвестор'),
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
