<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Query/request/job debugging dashboard — house style (CLAUDE.md §17) is
 * local/staging only, enforced via `config('telescope.enabled')` rather
 * than here (this provider's own gate() only controls who can view the
 * dashboard in whichever environments it's actually enabled in).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Most auth flows here are Livewire components, not classic form
        // posts — a Livewire AJAX request's body carries the component's
        // full public property state (App\Livewire\Auth\PhoneLogin's
        // $code, Settings\Security's $password, etc.) nested inside a
        // JSON-encoded `components[].snapshot` string. Telescope's own
        // redaction (Arr::get/Arr::set) can only reach literal top-level
        // request keys, never into that encoded string, so naming
        // `password`/`code` here would silently redact nothing for any
        // Livewire request — the entire `components` payload is hidden
        // instead. Laravel's stock Telescope stub hides
        // password/password_confirmation by default for classic form
        // posts; this app's override had dropped even that, so both are
        // named too for the (few) non-Livewire auth routes.
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password', 'components']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin->value);
        });
    }
}
