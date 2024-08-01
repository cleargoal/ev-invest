<?php

namespace App\Listeners;

use App\Events\TotalChangedEvent;
use App\Mail\TotalChangedMail;
use App\Models\Total;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TotalListener
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
    public function handle(TotalChangedEvent $event): void
    {
//        Log::info('event.total.amount', [$event->total->amount]);
//        $total = Total::orderBy('id', 'desc')->first()->amount/100;
        if (config('app.env') !== 'local') {
            $mailTo = User::role('investor')->get();
        }
        else {
            $mailTo = 'cleargoal1@gmail.com';
        }
            Mail::to($mailTo)->send(new TotalChangedMail($event->totalAmount, $event->causeChange, $event->causeAmount));
    }
}
