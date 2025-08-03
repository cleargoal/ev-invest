<?php

namespace App\Providers;

use App\Events\BoughtAutoEvent;
use App\Events\TotalChangedEvent;
use App\Listeners\TotalListener;
use App\Listeners\VehicleListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        BoughtAutoEvent::class => [
            [VehicleListener::class, 'handle'],
        ],
        TotalChangedEvent::class => [
            TotalListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
