<?php

namespace App\Filament\Investor\Widgets;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverview extends BaseWidget
{

    protected function getStats(): array
    {
        $total = Total::orderBy('id', 'desc')->first()->amount / 100;
        $myActualContribution = Contribution::where('user_id', auth()->user()->id)->orderBy('id', 'desc')->first();
        $myFirstContribution = Contribution::where('user_id', auth()->user()->id)->orderBy('id', 'asc')->first();
        $myPaymentsTotal = Payment::where('user_id', auth()->user()->id)->whereIn('operation_id', [1,4,5])->sum('amount');
        $myTotalIncome = ($myActualContribution && $myPaymentsTotal) ? $myActualContribution->amount - $myPaymentsTotal : 0;
        $myTotalGrow = $myFirstContribution ? ($myTotalIncome * 100) / $myFirstContribution->amount * 100 : 0;
        $operatorId = User::whereHas('roles', function ($query) {
            $query->where('name', 'operator');
        })->first()->id;
        $company = User::where('id', auth()->user()->id)
            ->whereHas('roles', function ($query) {
            $query->where('name', 'company');
        })->first();

        $usersWithLastContributions = User::whereNot('id', $operatorId)->with('lastContribution')->get();

        $totalInvestAmount = $usersWithLastContributions->sum(function ($user) {
                return $user->lastContribution ? $user->lastContribution->amount : 0;
            }) / 100;

        $totalPercents = $usersWithLastContributions->sum(function ($user) {
                return $user->lastContribution ? $user->lastContribution->percents : 0;
            }) / 10000;

        $vehicles = Vehicle::where('sale_date', null)->get()->sum('cost') / 100;


        return [
            Stat::make('Актуальна сума пулу, $$', Number::format($total, locale: 'sv'))->color('success'),
            Stat::make('Сума вартості автівок у закупівлі, $$', Number::format($vehicles, locale: 'sv')),
            Stat::make('Резерв пулу - доступний для закупівлі, $$', Number::format($total - $vehicles, locale: 'sv')),
            Stat::make('Загальна сума інвестицій без Мажор-інвестора, $$', Number::format($totalInvestAmount, locale: 'sv')),


            Stat::make('Спільна доля міноритарних інвестицій у пулі (%)', $totalPercents)
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'onclick' => "window.location.href='investor/users'",
                ]),

            Stat::make('Актуальна сума мого внеску, $', Number::format($myActualContribution ? $myActualContribution->amount / 100 : 0, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Моя доля у сумі пулу (%)', $myActualContribution ? $myActualContribution->percents / 10000 : 0)
                ->extraAttributes([
                'class' => $company ? 'hidden' : '',
            ]),
            Stat::make('Мій початковий внесок, $',
                Number::format($myFirstContribution ? $myFirstContribution->amount / 100 : 0, locale: 'sv'))
                ->description($myFirstContribution ? $myFirstContribution->created_at : '')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Сума всіх моїх внесків, $',
                Number::format($myPaymentsTotal ? $myPaymentsTotal / 100 : 0, locale: 'sv'))
                ->description('Враховуються тільки внесення та вилучення грошей, без інвест-доходу')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),


            Stat::make('Мій дохід за весь час, $$', Number::format($myTotalIncome / 100, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за весь час, %%', Number::format($myTotalGrow / 100, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
        ];
    }

}
