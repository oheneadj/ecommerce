<?php

namespace App\Providers;

use App\Listeners\MergeGuestCartOnLogin;
use App\Notifications\Channels\SmsChannel;
use App\Payments\PaymentManager;
use App\Policies\ActivityPolicy;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsManager::class);

        $this->app->bind(SmsGateway::class, fn ($app) => $app->make(SmsManager::class)->driver());

        $this->app->singleton(PaymentManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Notification::extend('sms', fn ($app) => new SmsChannel($app->make(SmsGateway::class)));

        // Laravel's policy auto-discovery can't guess a policy for a
        // third-party model outside App\Models — registered explicitly.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Covers every login path (phone OTP, Google, email+password, 2FA,
        // passkeys) — SessionGuard::login() always fires Login.
        Event::listen(Login::class, MergeGuestCartOnLogin::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
