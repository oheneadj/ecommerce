{{--
    Persistent banner shown across the admin panel whenever a CRITICAL
    health check is failing (docs/TASK-system-health-checks.md Step 5.3) —
    a dashboard nobody visits is a dashboard that doesn't work. Dismissal
    is per-session (sessionStorage), not permanent, so a new browser
    session sees it again until the underlying problem is actually fixed.
--}}
@php
    $criticalHealthFailing = \App\Actions\Health\DetermineCriticalHealthFailure::run();
@endphp

@if ($criticalHealthFailing)
    <div
        x-data="{ dismissed: sessionStorage.getItem('health-critical-banner-dismissed') === '1' }"
        x-show="! dismissed"
        class="flex items-center justify-between gap-4 bg-red-600 px-4 py-2 text-sm text-white"
    >
        <span>
            <strong>Critical health check failure.</strong>
            <a href="{{ \App\Filament\Pages\SystemHealth::getUrl() }}" class="underline">View system health</a>
        </span>
        <button
            type="button"
            x-on:click="dismissed = true; sessionStorage.setItem('health-critical-banner-dismissed', '1')"
            class="text-white/80 hover:text-white"
            aria-label="Dismiss"
        >
            &times;
        </button>
    </div>
@endif
