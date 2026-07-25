<?php

use App\Actions\Inventory\CheckLowStockLevels;
use App\Actions\Inventory\ReleaseExpiredReservations;
use App\Actions\Payment\VerifyPendingPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => ReleaseExpiredReservations::run())
    ->everyMinute()
    ->name('release-expired-stock-reservations')
    ->withoutOverlapping();

Schedule::call(fn () => VerifyPendingPayments::run())
    ->everyTwoMinutes()
    ->name('verify-pending-payments')
    ->withoutOverlapping();

Schedule::call(fn () => CheckLowStockLevels::run())
    ->daily()
    ->name('check-low-stock-levels')
    ->withoutOverlapping();
