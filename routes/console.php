<?php

use App\Actions\Inventory\ReleaseExpiredReservations;
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
