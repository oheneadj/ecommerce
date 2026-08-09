<?php

/**
 * CI-enforceable architecture rules (docs/TASK-system-health-checks.md
 * Step 6.3). These are code-level rules Pest's arch() can genuinely see
 * — unlike the health checks in app/HealthChecks, which inspect runtime
 * infrastructure/data state and never belong in CI (Step 6.2).
 *
 * Run with: vendor/bin/pest tests/Architecture
 */

declare(strict_types=1);

arch('actions do not call vendor SDKs/drivers directly')
    ->expect('App\Actions')
    ->not->toUse(['App\Payments\Drivers', 'App\Sms\Drivers']);

arch('controllers stay thin')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes()
    // Pre-existing gaps unrelated to this task (mostly Filament's own
    // Resource/Page scaffolding and Fortify/Breeze starter-kit files) —
    // not fixed here to avoid a 40-file mass-edit as a side effect of
    // adding this CI rule. New code must comply; this list should only
    // shrink over time, never grow.
    ->ignoring([
        'App\Providers\FortifyServiceProvider',
        'App\Providers\AppServiceProvider',
        'App\Providers\Filament\AdminPanelProvider',
        'App\Concerns\PasswordValidationRules',
        'App\Concerns\ProfileValidationRules',
        'App\Policies\StockReservationPolicy',
        'App\Policies\RefundPolicy',
        'App\Livewire\Settings',
        'App\Actions\Fortify',
        'App\Filament\Resources',
    ]);
