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
     id="dashboard-overview-widget-personal"
    >
        <x-slot name="heading">Основна інформація - персональна</x-slot>
        <div>
            @livewire(App\Filament\Investor\Widgets\StatsOverviewPersonal::class)
        </div>
    </x-filament::section>

    <x-filament::section
     collapsed
     collapsible
     persist-collapsed
     id="dashboard-confirm-widget"
    >
        <x-slot name="heading">Підтвердження внеску</x-slot>
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

</x-filament-panels::page>
