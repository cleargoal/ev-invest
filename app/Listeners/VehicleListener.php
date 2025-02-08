<?php

namespace App\Listeners;

use App\Events\BoughtAutoEvent;
use App\Mail\BoughtAutoMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class VehicleListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BoughtAutoEvent $event): void
    {
        if (config('app.env') !== 'local') {
            $mailTo = User::role('investor')->get();
        }
        else {
            $mailTo = 'cleargoal1@gmail.com';
        }
            Mail::to($mailTo)->send(new BoughtAutoMail($event->causeChange, $event->vehicle));
    }
}
