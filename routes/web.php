<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Redirect home to Cameron
Route::redirect('/', '/cameron')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

    // Onboarding: connect Google (no shop required)
    Route::livewire('setup', 'pages::shop.setup')->name('shop.setup');

    // Shop create/edit: requires Google connected, but not a shop yet
    Route::livewire('shop/edit', 'pages::shop.edit')->name('shop.edit');
    Route::livewire('shop/tools', 'pages::shop.tools')->name('shop.tools');

    Route::middleware(['shop.configured'])->group(function () {
        Route::livewire('cameron/{conversation?}', 'pages::cameron')->name('cameron');
        Route::livewire('goals', 'pages::goals')->name('goals');
        Route::livewire('goals/{goal}', 'pages::goal')->name('goal');
        Route::livewire('agents/{agent}', 'pages::agent')->name('agent');
    });
});

require __DIR__.'/settings.php';
