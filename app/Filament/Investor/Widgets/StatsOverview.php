<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Contribution;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $total = Total::orderBy('id', 'desc')->first()->amount/100;
        $myContribution = Contribution::where('user_id', auth()->user()->id)->orderBy('id', 'desc')->first();
        $operId = User::whereHas('roles', function ($query) {
            $query->where('name', 'operator');
        })->first()->id;

        $usersWithLastContributions = User::whereNot('id', $operId)->with('lastContribution')->get();
        $totalAmount = $usersWithLastContributions->sum(function ($user) {
                return $user->lastContribution ? $user->lastContribution->amount : 0;
            }) / 100;

        $totalPercents = $usersWithLastContributions->sum(function ($user) {
                return $user->lastContribution ? $user->lastContribution->percents : 0;
            }) / 10000;        $vehicles = Vehicle::where('sale_date', null)->get()->sum('cost')/100;

        return [
            Stat::make('Актуальна сума пулу, $$', $total),
            Stat::make('Сума мого внеску', $myContribution ? $myContribution->amount/100 : 0),
            Stat::make('Моя доля у сумі пулу (%)', $myContribution ? $myContribution->percents/10000 : 0),
            Stat::make('Загальна вартість автівок - закупівля', $vehicles),
            Stat::make('Загальна сума інвестицій', $totalAmount),
            Stat::make('Спільна Доля інвестицій у пулі (%)', $totalPercents),
        ];
    }
}
