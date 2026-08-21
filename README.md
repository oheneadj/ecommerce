# Ecommerce Platform

A multi-store ecommerce platform template built on Laravel — a customer-facing storefront (catalog, cart, checkout, accounts) plus a full Filament admin panel for staff, designed to be reskinned per business deployment rather than forked. Each business gets its own separate installation (own database, own domain) running the same codebase — see [`docs/infrastructure-deployment.md`](docs/infrastructure-deployment.md) for the deployment model.

## Tech stack

- **Backend**: Laravel 13, PHP 8.3+
- **Frontend**: Livewire 4, Alpine.js, Tailwind CSS 4 (no Flux UI, no other JS framework)
- **Admin panel**: Filament v5
- **Business logic**: [`lorisleiva/laravel-actions`](https://laravelactions.com/) — single-purpose Action classes, one per business operation
- **Database**: MySQL 8.x in production (SQLite for local dev/tests) — see [`docs/infrastructure-deployment.md`](docs/infrastructure-deployment.md) §1 for required settings
- **Queue**: database driver, segmented into named queues (`emails`, `sms`, `notifications`, `processing`, `external-api`, `backups`)
- **Tooling**: Pint (formatting), PHPStan/Larastan (static analysis), Pest (testing)

## What's in the box

- **Storefront**: product catalog with variants (size/color/etc.), cart, checkout, order tracking, wishlists, product reviews, customer accounts.
- **Authentication**: phone number + OTP (SMS) as the primary customer login, with email+password and Google as optional additional methods. Staff/admin login is always email+password.
- **Payments**: Paystack and Moolre out of the box, both behind a swappable gateway interface — see [`docs/HOWTO-add-payment-provider.md`](docs/HOWTO-add-payment-provider.md) to add another.
- **SMS**: Moolre and GiantSMS, same swappable-driver pattern — see [`docs/HOWTO-add-sms-provider.md`](docs/HOWTO-add-sms-provider.md).
- **Admin panel** (`/admin`): catalog management, orders, customers, coupons, shipping methods, staff/role management, activity log, storefront announcements (banners/popups), and a System Health dashboard.
- **Backups**: scheduled + on-demand database and file backups to Google Drive, with a guarded restore action — see [`docs/HOWTO-setup-google-drive-backups.md`](docs/HOWTO-setup-google-drive-backups.md).
- **Error tracking**: [Sentry](https://sentry.io) captures every unhandled exception app-wide, ships disabled until a DSN is set — see [`docs/HOWTO-setup-sentry.md`](docs/HOWTO-setup-sentry.md).
- **Monitoring**: [Telescope](https://laravel.com/docs/telescope) (request/query/job debugging, Super-Admin-gated outside local) and [Pulse](https://laravel.com/docs/pulse) (live performance dashboard) — both off by default via `TELESCOPE_ENABLED`/`PULSE_ENABLED`.
- **Branding**: business name, logo, brand colors, contact details, and social links are all admin-editable (Store Settings), driving the storefront theme and PDF invoices with no code change.

## Requirements

- PHP 8.3+, with the extensions Laravel itself requires
- Composer
- Node.js + npm
- MySQL 8.x (production) or SQLite (local/tests — the default)
- A `mysql` CLI client available on the server if you intend to use the backup **restore** feature (it shells out to `mysql` to re-import a dump)

## Getting started

```bash
composer run setup
```

This installs PHP and JS dependencies, copies `.env.example` to `.env`, generates the app key, runs migrations, and builds frontend assets. A few things it doesn't do for you:

```bash
# Symlink storage/app/public so uploaded images are actually servable
php artisan storage:link

# Seed demo catalog/orders data (optional, local dev only — creates fake
# accounts with random passwords, never use in production)
php artisan migrate:fresh --seed

# Create a real first Super Admin account (interactive prompts)
php artisan app:create-super-admin
```

Then start the local dev environment (serves the app, runs the queue worker, and builds/watches frontend assets together):

```bash
composer run dev
```

The storefront is at `http://localhost:8000`, the admin panel at `http://localhost:8000/admin`.

### Environment variables

`.env.example` documents every variable this app reads, grouped and commented in place. The ones worth knowing about up front:

| Group | Variables | Notes |
|---|---|---|
| Payments | `PAYSTACK_SECRET_KEY`/`PAYSTACK_PUBLIC_KEY`, `MOOLRE_API_KEY`/`MOOLRE_WEBHOOK_SECRET`/`MOOLRE_SENDER_ID`, `PAYMENT_PROVIDER` | `PAYMENT_PROVIDER` is only the fallback before a Super Admin has ever saved Store Settings → Payment Providers; after that, the DB-backed choice takes over. |
| SMS | `MOOLRE_API_KEY`/`MOOLRE_SENDER_ID`, `GIANTSMS_TOKEN`/`GIANTSMS_SENDER_ID`, `SMS_PROVIDER` | Same fallback-then-DB pattern as payments. Used for OTP login, low-stock alerts, and staff invites. |
| Google login (optional) | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Optional additional customer login method, alongside phone+OTP. Not required for the app to run. |
| Backups | `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON`, `GOOGLE_DRIVE_FOLDER_ID`, `BACKUP_ARCHIVE_PASSWORD` | See [`docs/HOWTO-setup-google-drive-backups.md`](docs/HOWTO-setup-google-drive-backups.md) for the full one-time setup. Nothing breaks if these are left blank — backups just stay unconfigured until set. |
| Error tracking | `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE` | See [`docs/HOWTO-setup-sentry.md`](docs/HOWTO-setup-sentry.md). Blank by default — no events sent anywhere until a DSN is set; System Health flags this as a warning, never a critical failure. |
| Catalog limits | `PRODUCT_MAX_IMAGES`, `PRODUCT_MAX_VARIANTS`, `MEDIA_MAX_UPLOAD_SIZE_KB` | Admin-side upload/variant limits. |

Nothing in this app reads `env()` outside of `config/*.php` files — always go through `.env` → a `config()` key, never a raw `env()` call elsewhere in the codebase.

## Running the background pieces

Three things need to actually be running for the app to work correctly beyond a single request — none of these are optional in production:

1. **Queue worker** — `php artisan queue:work --queue=notifications,emails,sms,processing,external-api,backups,default`. Without it, order confirmations, SMS, and backups silently never run. (`composer run dev` starts one automatically for local development.)
2. **Scheduler** — a cron entry running `php artisan schedule:run` every minute. This drives stock-reservation expiry, payment verification polling, low-stock checks, health-check heartbeats, and scheduled backups (see `routes/console.php` for the full list). See [`docs/infrastructure-deployment.md`](docs/infrastructure-deployment.md) §3 for why this is correctness-critical, not just a performance nicety.
3. **Storage symlink** — `php artisan storage:link`, so uploaded images/logos are actually reachable over HTTP.

## Testing, linting, static analysis

```bash
composer run test         # config:clear, lint:check, types:check, then the full Pest suite
composer run lint         # auto-fix formatting (Pint)
composer run lint:check   # check formatting without fixing
composer run types:check  # PHPStan/Larastan

# Targeted runs:
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=2G
php artisan test --compact --filter=testName
```

## How the codebase is organized

- **Controllers/Livewire components** — HTTP/UI layer only, no business logic.
- **Actions** (`app/Actions/`) — one class per business operation (e.g. `CreateOrderFromCart`, `RecordStockMovement`), the actual home for business logic.
- **Services** — reusable logic shared by more than one Action/entry point.
- **Third-party integrations** (payment, SMS) sit behind an interface with one driver class per provider, resolved via a Laravel `Manager` — swapping or adding a provider never touches business logic. See the two HOWTO docs linked above.
- **Money** is always stored as integer minor units (pesewas/kobo/cents), never float — formatted only at the display boundary.
- **External identifiers** are ULIDs/slugs, never raw database IDs, in routes and anywhere exposed outside the app.

For the full architectural rationale and conventions, see [`docs/technical-design-ecommerce.md`](docs/technical-design-ecommerce.md) and the project's [`docs/CLAUDE (1).md`](<docs/CLAUDE (1).md>) coding agreement.

## Documentation index

| Doc | What it covers |
|---|---|
| [`docs/BRD-ecommerce-platform.md`](docs/BRD-ecommerce-platform.md) | Business requirements — what the platform needs to do and why. |
| [`docs/technical-design-ecommerce.md`](docs/technical-design-ecommerce.md) | Architecture, data model, and design decisions. |
| [`docs/agile-docs-ecommerce.md`](docs/agile-docs-ecommerce.md) | Epics/sprints this was built against. |
| [`docs/test-plan-ecommerce.md`](docs/test-plan-ecommerce.md) | Testing strategy and coverage expectations. |
| [`docs/infrastructure-deployment.md`](docs/infrastructure-deployment.md) | Database platform requirements, backups & recovery, scheduler/queue operational requirements, per-client deployment. |
| [`docs/HOWTO-add-payment-provider.md`](docs/HOWTO-add-payment-provider.md) | Step-by-step for adding a new payment gateway driver. |
| [`docs/HOWTO-add-sms-provider.md`](docs/HOWTO-add-sms-provider.md) | Step-by-step for adding a new SMS gateway driver. |
| [`docs/HOWTO-setup-google-drive-backups.md`](docs/HOWTO-setup-google-drive-backups.md) | One-time Google Cloud service-account setup for automated backups. |
| [`docs/HOWTO-setup-sentry.md`](docs/HOWTO-setup-sentry.md) | One-time Sentry project setup for error tracking. |
| [`docs/TASK-system-health-checks.md`](docs/TASK-system-health-checks.md) | Spec for the System Health dashboard in the admin panel. |
| [`docs/AGENTS.md`](docs/AGENTS.md) | Notes for AI coding agents working in this repo. |
| [`docs/CLAUDE (1).md`](<docs/CLAUDE (1).md>) | The coding agreement/conventions this codebase follows (KISS/DRY/YAGNI, testing, security, money handling, etc.). |
| [`CHANGELOG.md`](CHANGELOG.md) | Notable changes, in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. |
