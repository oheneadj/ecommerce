<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\ThemeCssController;
use Illuminate\Support\Facades\Route;

Route::get('/theme.css', ThemeCssController::class)->name('theme.css');

Route::view('/', 'welcome')->name('home');

Route::view('/login/phone', 'pages.login-phone')->middleware('guest')->name('login.phone');

Route::get('/login/google', [GoogleAuthController::class, 'redirect'])->name('login.google');
Route::get('/login/google/callback', [GoogleAuthController::class, 'callback'])->name('login.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('account', [AccountController::class, 'show'])->name('account.show');
});

require __DIR__.'/settings.php';
