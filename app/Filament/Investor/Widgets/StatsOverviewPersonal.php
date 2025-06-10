<?php

namespace App\Filament\Investor\Widgets;

use App\Enums\OperationType;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverviewPersonal extends BaseWidget
{

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $commonTotal = (int) Payment::whereHas('user.roles', function ($query) {
            $query->where('name', 'investor');
        })->where('confirmed', true)->sum('amount');

        $myActualContributionAmount = Payment::where('user_id', auth()->user()->id)->where('confirmed', true)->sum('amount'); // !!! cast can not work here
        $myActualContributionPercents = round($myActualContributionAmount * 1000000 /$commonTotal);

        $myFirstContribution = Payment::where('user_id', auth()->user()->id)->where('operation_id', OperationType::FIRST)->first();

        $myPaymentsTotal = Payment::where('user_id', auth()->user()->id)->where('confirmed', true)->whereIn('operation_id',
            [OperationType::FIRST,OperationType::CONTRIB,OperationType::WITHDRAW])
            ->sum('amount');

        $myTotalIncome = Payment::where('user_id', auth()->user()->id)->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])->sum('amount');
        $myTotalGrow = $myFirstContribution ? $myTotalIncome / $myFirstContribution->amount : 0;

        $myLastYearIncome = Payment::where('user_id', auth()->user()->id)
            ->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])
            ->whereYear('created_at', '>=', now()->subDays(365))
            ->sum('amount');
        $myLastYearGrow = $myFirstContribution ? $myLastYearIncome / $myFirstContribution->amount  : 0;

        $myCurrentYearIncome = Payment::where('user_id', auth()->user()->id)
            ->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])
            ->whereYear('created_at', '>=', now()->year)
            ->sum('amount');
            $myCurrentYearGrow = $myFirstContribution ? $myCurrentYearIncome / $myFirstContribution->amount  : 0;

        $operatorId = User::whereHas('roles', function ($query) {
            $query->where('name', 'operator');
        })->first()->id;

        $company = User::where('id', auth()->user()->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'company');
            })->first();

        return [
            Stat::make('Мій поточний баланс, $',
                Number::format($myActualContributionAmount ? $myActualContributionAmount / 100: 0, 2, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Мій початковий внесок, $',
                Number::format($myFirstContribution ? $myFirstContribution->amount: 0, 2, locale: 'sv'))
                ->description($myFirstContribution ? $myFirstContribution->created_at : '')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Сума всіх моїх внесків, $',
                Number::format($myPaymentsTotal ? $myPaymentsTotal / 100: 0, 2, locale: 'sv'))
                ->description('Враховуються тільки внесення та вилучення грошей, без інвест-доходу')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Моя доля у сумі пулу (%)',
                $myActualContributionPercents ?
                    Number::format(round($myActualContributionPercents / 10000, 2), 2, locale: 'sv') : 0)
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),

            // 2d row
            Stat::make('Мій дохід за весь час, $$',
                Number::format(round($myTotalIncome / 100, 2), 2, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за весь час, %%',
                Number::format(round($myTotalGrow, 2), 2, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

            // Last Year income widgets;
            Stat::make('Мій дохід за останній рік, $$',
                Number::format($myLastYearIncome / 100, 2, locale: 'sv'))
                ->description('За 365 днів враховуючи сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за останній рік, %%',
                Number::format($myLastYearGrow, 2, locale: 'sv'))
                ->description('За 365 днів враховуючи сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

            // Current Year income widgets;
            Stat::make('Мій дохід за поточний рік, $$',
                Number::format($myCurrentYearIncome / 100, 2, locale: 'sv'))
                ->description('З 1-го Січня до сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за поточний рік, %%',
                Number::format($myCurrentYearGrow, 2, locale: 'sv'))
                ->description('З 1-го Січня до сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

        ];
    }

}
