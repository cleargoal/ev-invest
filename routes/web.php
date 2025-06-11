<?php

use App\Http\Controllers\ProfileController;
use App\Models\Payment;
use App\Models\Total;
use App\Notifications\NewPaymentNotify;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('main');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/mail', function () {
    $total = Total::orderBy('id', 'desc')->first()->amount;
    return new App\Mail\TotalChangedMail($total, 'total change', 250);
});

// route to check notification in browser
Route::get('/notify-new-payment', function () {
    $payment = Payment::latest()->first();

    return (new NewPaymentNotify($payment))
        ->toMail($payment->user);
});

require __DIR__.'/auth.php';
