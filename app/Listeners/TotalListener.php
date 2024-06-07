<?php

namespace App\Listeners;

use App\Events\TotalChangedEvent;
use App\Mail\TotalChangedMail;
use App\Models\Total;
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
        $total = Total::orderBy('id', 'desc')->first()->amount/100;
        $mailTo = 'cleargoal01@gmail.com';
        Mail::to($mailTo)->send(new TotalChangedMail($total));
    }
}
