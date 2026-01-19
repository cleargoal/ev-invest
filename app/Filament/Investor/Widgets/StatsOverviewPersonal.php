<?php

namespace App\Filament\Investor\Widgets;

use App\Constants\FinancialConstants;
use App\Enums\OperationType;
use App\Models\Payment;
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
        $commonTotal = (int) Payment::whereHas('user', function ($query) {
            $query->where('role', 'investor');
        })->active()->sum('amount'); // Use active scope to exclude cancelled payments

        $myActualContributionAmount = Payment::where('user_id', auth()->user()->id)->active()->sum('amount'); // Use active scope to exclude cancelled payments
        $myActualContributionPercents = round($myActualContributionAmount * FinancialConstants::PERCENTAGE_PRECISION /$commonTotal);

        $myFirstContribution = Payment::where('user_id', auth()->user()->id)->where('operation_id', OperationType::FIRST)->first();

        $myPaymentsTotal = Payment::where('user_id', auth()->user()->id)->active()->whereIn('operation_id',
            [OperationType::FIRST,OperationType::CONTRIB,OperationType::WITHDRAW])
            ->sum('amount');

        $myTotalIncome = Payment::where('user_id', auth()->user()->id)->notCancelled()->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])->sum('amount');
        $myTotalGrow = $myFirstContribution ? $myTotalIncome / $myFirstContribution->amount : 0;

        $myLastYearIncome = Payment::where('user_id', auth()->user()->id)
            ->notCancelled()
            ->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])
            ->where('created_at', '>=', now()->subDays(FinancialConstants::DAYS_IN_YEAR))
            ->sum('amount');
        $myLastYearGrow = $myFirstContribution ? $myLastYearIncome / $myFirstContribution->amount  : 0;

        $myCurrentYearIncome = Payment::where('user_id', auth()->user()->id)
            ->notCancelled()
            ->whereIn('operation_id', [OperationType::INCOME,OperationType::I_LEASING,])
            ->whereYear('created_at', now()->year)
            ->sum('amount');
            $myCurrentYearGrow = $myFirstContribution ? $myCurrentYearIncome / $myFirstContribution->amount  : 0;

        $operatorId = User::where('role', 'operator')->first()->id;

        $company = User::where('id', auth()->user()->id)
            ->where('role', 'company')
            ->first();

        return [
            Stat::make('Мій поточний баланс, $',
                Number::format($myActualContributionAmount ? $myActualContributionAmount / FinancialConstants::CENTS_PER_DOLLAR: 0, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Мій початковий внесок, $',
                Number::format($myFirstContribution ? $myFirstContribution->amount: 0, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description($myFirstContribution ? $myFirstContribution->created_at : '')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Сума всіх моїх внесків, $',
                Number::format($myPaymentsTotal ? $myPaymentsTotal / FinancialConstants::CENTS_PER_DOLLAR: 0, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description('Враховуються тільки внесення та вилучення грошей, без інвест-доходу')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),
            Stat::make('Моя доля у сумі пулу (%)',
                $myActualContributionPercents ?
                    Number::format(round($myActualContributionPercents / FinancialConstants::PERCENTAGE_DISPLAY_DIVISOR, FinancialConstants::DECIMAL_PRECISION), FinancialConstants::DECIMAL_PRECISION, locale: 'sv') : 0)
                ->extraAttributes([
                    'class' => $company ? 'hidden' : '',
                ]),

            // 2d row
            Stat::make('Мій дохід за весь час, $$',
                Number::format(round($myTotalIncome / FinancialConstants::CENTS_PER_DOLLAR, FinancialConstants::DECIMAL_PRECISION), FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за весь час, %%',
                Number::format(round($myTotalGrow, FinancialConstants::DECIMAL_PRECISION), FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

            // Last Year income widgets;
            Stat::make('Мій дохід за останній рік, $$',
                Number::format($myLastYearIncome / FinancialConstants::CENTS_PER_DOLLAR, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description('За 365 днів враховуючи сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за останній рік, %%',
                Number::format($myLastYearGrow, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description('За 365 днів враховуючи сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

            // Current Year income widgets;
            Stat::make('Мій дохід за поточний рік, $$',
                Number::format($myCurrentYearIncome / FinancialConstants::CENTS_PER_DOLLAR, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description('З 1-го Січня до сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),
            Stat::make('Мій дохід за поточний рік, %%',
                Number::format($myCurrentYearGrow, FinancialConstants::DECIMAL_PRECISION, locale: 'sv'))
                ->description('З 1-го Січня до сьогодні')
                ->extraAttributes([
                    'class' => $company ? 'hidden' : 'cursor-pointer',
                    'onclick' => "window.location.href='investor/payments'",
                ]),

        ];
    }

}
