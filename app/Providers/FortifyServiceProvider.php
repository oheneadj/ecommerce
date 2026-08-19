<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        // Fortify's own route file (vendor/laravel/fortify/routes/routes.php)
        // only conditionally throttles login/two-factor/verification/
        // passkeys via a `config('fortify.limiters.*')`-driven middleware
        // option — registration and password-reset-request routes carry
        // no such option at all, unlike every other auth entry point.
        // Without this, a script could mass-create accounts via /register,
        // or flood a victim's inbox with reset links via /forgot-password,
        // both at unbounded rate.
        //
        // Attaching `throttle:*` directly to those already-registered
        // named routes (the approach used for every other limiter above)
        // doesn't work here: Fortify registers its routes inside its own
        // provider's boot(), but this app's route files — and therefore
        // the point at which every named route actually exists — aren't
        // guaranteed to be loaded relative to when app-level providers
        // boot, not even via `app()->booted()`. So instead this one
        // limiter is applied globally to the whole `web` middleware group
        // (see `bootstrap/app.php`), and branches on the matched route's
        // name here — `Limit::none()` for every route that isn't one of
        // these three, so it has zero effect on the rest of the app.
        RateLimiter::for('guest-auth-forms', function (Request $request) {
            $routeName = $request->route()?->getName();

            return match ($routeName) {
                'register.store' => Limit::perMinute(5)->by($request->ip()),
                'password.email', 'password.update' => Limit::perMinute(5)->by(
                    Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
                ),
                default => Limit::none(),
            };
        });
    }
}
