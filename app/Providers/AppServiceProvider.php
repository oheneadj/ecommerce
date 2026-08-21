<?php

/**
 * App-wide bindings, event listeners, and runtime defaults not owned by any other provider.
 */

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\RecordFailedBackup;
use App\Listeners\RecordSuccessfulBackup;
use App\Listeners\SendEmailVerificationOnRegistration;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Payments\PaymentManager;
use App\Policies\ActivityPolicy;
use App\Queries\DashboardMetricsQuery;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsManager;
use App\Support\PasswordPolicy;
use App\View\Composers\AdminBarComposer;
use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\DevCommands;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Symfony\Component\Mime\Address;

/**
 * Registers app-wide singletons/bindings and boots cross-cutting concerns
 * (event listeners, the admin bar's view composer, lazy-loading
 * prevention, local dev conveniences) that don't belong to a
 * domain-specific provider.
 */
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

        // A dashboard render resolves this multiple times (once per
        // widget's mount, again on every filter-triggered re-render) —
        // singleton so its per-instance StoreSetting memoization (see
        // DashboardMetricsQuery::store()) is actually shared across all
        // of them within one request, not just within a single call.
        // Safe as an app-lifetime singleton since this app doesn't run
        // under Octane (no persistent-worker request reuse) — the
        // container itself is destroyed at the end of every request.
        $this->app->singleton(DashboardMetricsQuery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevQueueWorker();
        $this->configureDevMailServer();
        $this->configureGoogleDriveDisk();

        Notification::extend('sms', fn ($app) => new SmsChannel($app->make(SmsGateway::class), $app->make(SmsManager::class)));

        // Keeps the admin bar partial itself pure markup — every query/
        // policy check it needs lives in the composer, not a @php block.
        View::composer('partials.admin-bar', AdminBarComposer::class);

        // Laravel's policy auto-discovery can't guess a policy for a
        // third-party model outside App\Models — registered explicitly.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Pulse's live performance dashboard runs in every environment
        // (CLAUDE.md §17), including production — restricted to the same
        // Super-Admin-only audience as Telescope and the System Health page.
        Gate::define('viewPulse', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin->value));

        // Covers every login path (phone OTP, Google, email+password, 2FA,
        // passkeys) — SessionGuard::login() always fires Login.
        Event::listen(Login::class, MergeGuestCartOnLogin::class);

        // Only email+password registration dispatches Registered — Laravel's
        // own built-in listener for this event silently skips sending
        // (see SendEmailVerificationOnRegistration's own docblock for why).
        Event::listen(Registered::class, SendEmailVerificationOnRegistration::class);

        // Backups (App\Jobs\RunBackupJob) — reacts to spatie/laravel-backup's
        // own events rather than anything in the job itself, so both the
        // scheduled and manual trigger paths get audit-logged/alerted the
        // same way.
        Event::listen(BackupWasSuccessful::class, RecordSuccessfulBackup::class);
        Event::listen(BackupHasFailed::class, RecordFailedBackup::class);

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
     * `composer run dev` (Laravel's built-in `artisan dev`) registers a
     * `queue:listen` process by default, but with no `--queue` flag — it
     * only ever services the `default` queue. This project deliberately
     * segments every job onto a named queue (`emails`, `sms`,
     * `notifications`, `processing`, `external-api`, `backups`, per
     * CLAUDE.md §15), so without this override every one of them sits in
     * the `jobs` table forever in local dev, never picked up — the exact
     * cause of a customer broadcast notification silently never arriving.
     * Re-registering the same `queue` name from userland (not a vendor
     * file) wins over the framework default, per
     * `DevCommands::resolvePriority()`.
     */
    protected function configureDevQueueWorker(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::artisan(
            'queue:listen --queue=notifications,emails,sms,processing,external-api,backups,default --tries=1 --timeout=0',
            'queue',
        );
    }

    /**
     * Mailpit catches real outgoing SMTP mail locally (UI at
     * http://127.0.0.1:8025) so a developer can actually see what an
     * email/order-confirmation/broadcast looks like instead of only
     * reading it out of `storage/logs/laravel.log` (the "log" mailer).
     * Only registered when the `mailpit` binary is actually present —
     * `composer run dev` must keep working on a machine that hasn't
     * installed it (it isn't a project dependency, just a common local
     * tool bundled with Herd/Herd Lite), and `--kill-others-on-fail`
     * would otherwise take down the whole dev process group over one
     * missing binary.
     */
    protected function configureDevMailServer(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (trim((string) shell_exec('command -v mailpit')) === '') {
            return;
        }

        DevCommands::register('mailpit', 'mail');
    }

    /**
     * Registers the 'google' Flysystem driver backing the 'gdrive' disk
     * (config/filesystems.php), used only for backups
     * (App\Jobs\RunBackupJob). Built from a Google Cloud service account,
     * not OAuth — masbug/flysystem-google-drive-ext's own README example
     * uses a client_id/secret/refresh_token flow, which needs a human to
     * complete a consent screen and can silently expire; a service
     * account works unattended indefinitely, which a scheduled backup
     * requires. Registered even when the credential env vars are empty
     * (Storage::disk('gdrive') simply fails loudly if actually used
     * without them) — RemoteStorageProvider::hasCredentialsConfigured()
     * is what gates whether a backup is ever attempted in the first
     * place.
     */
    protected function configureGoogleDriveDisk(): void
    {
        Storage::extend('google', function ($app, array $config): FilesystemAdapter {
            $client = new GoogleClient;
            $client->setApplicationName(config('app.name', 'Laravel'));
            $client->setAuthConfig($config['serviceAccountJson']);
            $client->addScope(GoogleDriveService::DRIVE);

            $service = new GoogleDriveService($client);
            $adapter = new GoogleDriveAdapter($service, $config['folder'] ?? '/');

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
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
            ? PasswordPolicy::strong()
            : null,
        );
    }
}
