<?php

use App\Http\Controllers\Admin\SystemCacheController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\StaticPageController;
use App\Http\Controllers\Storefront\ThemeCssController;
use Illuminate\Support\Facades\Route;

Route::get('/theme.css', ThemeCssController::class)->name('theme.css');

Route::view('/', 'welcome')->name('home');

Route::get('/pages/{staticPage}', [StaticPageController::class, 'show'])->name('pages.show');

Route::view('/login/phone', 'pages.login-phone')->middleware('guest')->name('login.phone');

Route::get('/login/google', [GoogleAuthController::class, 'redirect'])->name('login.google');
Route::get('/login/google/callback', [GoogleAuthController::class, 'callback'])->name('login.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('account', [AccountController::class, 'show'])->name('account.show');

    Route::view('account/addresses', 'account.addresses')->name('account.addresses');

    Route::view('account/orders', 'account.orders')->name('account.orders');
    Route::view('account/orders/{order}', 'account.orders-show')->name('account.orders.show');

    Route::view('cart', 'cart.show')->name('cart.show');

    Route::view('wishlist', 'wishlist.show')->name('wishlist.show');

    Route::view('checkout', 'checkout.show')->name('checkout.show');

    Route::view('orders/{order}/confirmation', 'orders.confirmation')->name('orders.confirmation');

    Route::post('system/cache/{action}', [SystemCacheController::class, 'run'])
        ->whereIn('action', ['config', 'route', 'view', 'event', 'all', 'optimize'])
        ->name('system.cache.run');
});

require __DIR__.'/settings.php';
