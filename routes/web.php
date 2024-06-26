<?php

use App\Http\Controllers\ProfileController;
use App\Models\Total;
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
    $total = Total::orderBy('id', 'desc')->first()->amount/100;
    return new App\Mail\TotalChangedMail($total);
});

require __DIR__.'/auth.php';
