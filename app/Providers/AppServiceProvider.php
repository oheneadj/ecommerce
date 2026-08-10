<?php

namespace App\Providers;

use App\Listeners\MergeGuestCartOnLogin;
use App\Models\StoreSetting;
use App\Notifications\Channels\SmsChannel;
use App\Payments\PaymentManager;
use App\Policies\ActivityPolicy;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mime\Address;

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

        // Branding: every outgoing email shows the store's business name as
        // its "From" display name, not config/mail.php's static default —
        // one place, applies to every Notification/Mailable automatically.
        // Only the display name changes, never the actual address — the
        // envelope address stays whatever's configured/verified with the
        // real mail provider (overriding it to an arbitrary per-store
        // contact_email would risk SPF/DKIM failures and land in spam).
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $businessName = StoreSetting::current()->business_name;
            $fromAddress = config('mail.from.address');

            if ($businessName && $fromAddress) {
                $event->message->from(new Address($fromAddress, $businessName));
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Always on — `preventsLazyLoading` is read once per model at
        // hydration time (Builder::hydrate()), so toggling it off in
        // production wouldn't just relax detection, it would disable it
        // outright and the violation callback below would never fire.
        // The environment split that actually matters (throw vs. log) is
        // whether a violation callback is registered at all: local/
        // staging leaves it unregistered, so a lazy load throws
        // immediately and an N+1 (or a Livewire rehydration gap losing a
        // nested relation, as happened to
        // ProductVariant::attributeTerms/images/product across separate
        // wire:click requests) fails loudly during development. Production
        // never throws on a real customer's request over a performance
        // issue — logged instead, so it's still visible without taking
        // the storefront down.
        Model::preventLazyLoading();

        if (app()->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading violation', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);
            });
        }

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
