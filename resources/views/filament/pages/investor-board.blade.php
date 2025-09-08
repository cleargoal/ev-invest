@php
    $hasUnconfirmedPayments = \App\Models\Payment::where('confirmed', false)->exists();
@endphp

<x-filament-panels::page>

    <x-filament::section
     collapsed
     collapsible
     persist-collapsed
     id="dashboard-overview-widget-general"
    >
        <x-slot name="heading">Основна інформація - загальна</x-slot>
        <div class="grid columns-4">
            @livewire(App\Filament\Investor\Widgets\StatsOverviewGeneral::class)
        </div>
    </x-filament::section>

    <x-filament::section
        collapsed
        collapsible
        persist-collapsed
        id="dashboard-general-charts"
    >
        <x-slot name="heading">Загальні графіки</x-slot>
        <div class="w-full">
            @livewire(App\Filament\Investor\Widgets\PoolReserveChart::class)
        </div>
    </x-filament::section>

    @if(!auth()->user()->hasRole('company'))
        <x-filament::section
         collapsed
         collapsible
         persist-collapsed
         id="dashboard-overview-widget-personal"
        >
            <x-slot name="heading">Основна інформація - персональна</x-slot>
            <div>
                @livewire(App\Filament\Investor\Widgets\StatsOverviewPersonal::class)
            </div>
        </x-filament::section>
    @endif

    <x-filament::section
        collapsed
        collapsible
        persist-collapsed
        id="dashboard-personal-charts"
    >
        <x-slot name="heading">Персональні графіки</x-slot>
        <div class="w-full">
            @livewire(App\Filament\Investor\Widgets\UserBalanceChart::class)
        </div>
        <div class="w-full mt-8">
            @livewire(App\Filament\Investor\Widgets\UserIncomeChart::class)
        </div>
    </x-filament::section>

    <x-filament::section
     collapsed
     collapsible
     persist-collapsed
     id="dashboard-confirm-widget"
    >
        <x-slot name="heading">
        <span class="{{ $hasUnconfirmedPayments ? 'text-red-600' : 'text-gray-900' }}">
            Підтвердження внеску
        </span>
        </x-slot>
        <div>
            @livewire(App\Filament\Investor\Widgets\PayConfirmWidget::class)
        </div>
    </x-filament::section>


    <x-filament::section
        collapsed
        collapsible
        persist-collapsed
        id="dashboard-vehicles-widget"
    >
        <x-slot name="heading">Продані автівки</x-slot>
        <div>
            @livewire(App\Filament\Investor\Widgets\SoldVehicles::class)
        </div>
    </x-filament::section>

    <x-filament::section
        collapsed
        collapsible
        persist-collapsed
        id="dashboard-cars-charts-widget"
    >
        <x-slot name="heading">Графіки автівок</x-slot>
        <div class="w-full">
            @livewire(App\Filament\Investor\Widgets\CarsSoldPerMonthChart::class)
        </div>
        <div class="w-full mt-8">
            @livewire(App\Filament\Investor\Widgets\CarsSoldPerWeekChart::class)
        </div>
    </x-filament::section>

    @if(auth()->user()->hasRole('company'))
        <x-filament::section
            collapsed
            collapsible
            persist-collapsed
            id="dashboard-cancelled-vehicles-widget"
        >
            <x-slot name="heading">Скасовані продажі</x-slot>
            <div>
                @livewire(App\Filament\Investor\Widgets\CancelledVehicles::class)
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
