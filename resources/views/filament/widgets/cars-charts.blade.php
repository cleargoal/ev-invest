<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Графіки автівок
        </x-slot>
        
        <div class="grid grid-cols-1 gap-6">
            @livewire(\App\Filament\Investor\Widgets\CarsSoldPerMonthChart::class)
        </div>
    </x-filament::section>
</x-filament-widgets::widget>