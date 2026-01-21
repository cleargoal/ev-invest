<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContributionResource\Pages;
use App\Filament\Resources\ContributionResource\RelationManagers;
use App\Models\Contribution;
use App\Models\Operation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('payment_id')
                    ->relationship('payment', 'id')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('percents')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment.created_at')
                    ->label('Payment Date')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Investor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment.user.name')
                    ->label('Initiator')
                    ->description(fn ($record) => $record->payment->operation->title)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment.operation.title')
                    ->label('Operation')
                    ->badge()
                    ->color(fn ($record) => match($record->payment->operation_id) {
                        1 => 'info',      // FIRST - blue
                        2 => 'success',   // CONTRIB - green
                        3 => 'purple',    // BUY_CAR - purple
                        4 => 'warning',   // INCOME - yellow
                        5 => 'orange',    // REVENUE - orange
                        6 => 'gray',      // WITHDRAW - gray
                        7 => 'indigo',    // RECALC - indigo
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment.amount')
                    ->label('Payment Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Investor Balance')
                    ->money('USD')
                    ->description('Cumulative total')
                    ->sortable(),
                ViewColumn::make('percents')
                    ->label('Pool Share %')
                    ->view('tables.columns.percents')
                    ->width('5rem')
                    ->alignment(Alignment::Center),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('operation')
                    ->label('Operation')
                    ->options(fn (): array => Operation::query()->pluck('title', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $operationId): Builder =>
                                $query->whereHas('payment', fn ($q) => $q->where('operation_id', $operationId))
                        );
                    })
                    ->searchable(),
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereHas('payment', fn ($q) => $q->whereDate('created_at', '>=', $date)),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereHas('payment', fn ($q) => $q->whereDate('created_at', '<=', $date)),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
