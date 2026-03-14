<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Redirect home to Cameron
Route::redirect('/', '/cameron')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

    Route::livewire('cameron', 'pages::cameron')->name('cameron');
    Route::livewire('goals', 'pages::goals')->name('goals');
    Route::livewire('agents/{agent}', 'pages::agent')->name('agent');
});

require __DIR__.'/settings.php';
