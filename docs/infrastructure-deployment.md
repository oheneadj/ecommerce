# Infrastructure & Deployment

**Companion to:** BRD, technical design, agile docs, AGENTS.md, test plan
**Covers:** the durability half of ACID, database configuration, backups, per-client deployment, and the operational requirements that have no home in the other documents.

**Deployment model reminder:** each business client receives a **fully separate installation** — own database, own environment, own domain — running the same codebase. Nothing below is shared between clients.

---

## 1. Database Platform (required)

| Setting | Requirement | Why |
|---|---|---|
| Engine | **MySQL 8.x, InnoDB on every table** (or PostgreSQL 14+) | MyISAM accepts transactions and silently rolls back nothing — every atomicity guarantee in the system fails with no error signal |
| Isolation level | REPEATABLE READ (MySQL default). Never below READ COMMITTED | The locking design is correct at either, but lowering further breaks it |
| `innodb_flush_log_at_trx_commit` | `1` (the default) | The **D** in ACID. At `0` or `2`, a committed transaction can be lost on power failure — acceptable for caches, never for payments |
| `sync_binlog` | `1` if replication is used | Ensures the binlog survives a crash alongside the transaction |
| Character set | `utf8mb4` / `utf8mb4_unicode_ci` | Full Unicode including emoji in product names, reviews, addresses |
| Timezone | Server and app both UTC | `CLAUDE.md` §13 requires UTC storage; conversion happens at display only |

**Verify after provisioning, before any production data exists:**

```sql
SELECT table_name, engine FROM information_schema.tables
WHERE table_schema = DATABASE() AND engine != 'InnoDB';
-- Must return zero rows.

SHOW VARIABLES LIKE 'innodb_flush_log_at_trx_commit'; -- must be 1
SHOW VARIABLES LIKE 'transaction_isolation';
```

Foreign keys must exist as real constraints. Audit with:

```bash
grep -rn "unsignedBigInteger" database/migrations/
```

Any result referencing another table should be `foreignId()->constrained()` instead.

---

## 2. Backups & Recovery

Durability protects against a crash mid-transaction. It does not protect against a dropped table, a bad migration, or a failed disk — backups do.

| Requirement | Target |
|---|---|
| Full database backup | Daily, automated |
| Retention | 30 days minimum |
| Off-server storage | Required — a backup on the same disk protects against nothing |
| Binlog / PITR | Recommended for any client processing meaningful transaction volume |
| Restore test | **Quarterly, mandatory** — an untested backup is a hypothesis, not a backup |
| Uploaded files (product images, logos) | Backed up alongside the database; a restored database referencing missing images is only half a recovery |

Per-client isolation means one client's restore never touches another's data — but it also means backup configuration must be part of the standard deployment checklist, not something remembered per client.

### Implementation (Settings → Store Settings → Backups / Settings → Backups)

Database + uploaded files (`storage/app/public`, `storage/app/private`) back up together as one run, via `spatie/laravel-backup` targeting a Google Drive destination (`App\Jobs\RunBackupJob`). Both triggers dispatch the same queued job:

- **Automatic** — off by default. A Super Admin turns it on and picks Daily/Weekly from Store Settings; `App\Actions\Backup\RunScheduledBackup` (scheduled daily in `routes/console.php`, self-guarding per the chosen frequency, same pattern `SendCriticalHealthAlert` uses for its own snooze) decides whether one is actually due.
- **Manual** — "Run backup now" on the Backups page (Settings → Backups, Super Admin only).
- **History & restore** — every run is logged to `backup_runs` (status, size, who triggered it, error class on failure). A "Restore" action exists on a successful run, gated behind re-entering the admin's password **and** typing a literal confirmation phrase — this overwrites the live database and every uploaded file, so it's deliberately heavy to trigger.
- **Alerting** — a failed run emails every Super Admin (`App\Notifications\BackupFailed`), and `App\HealthChecks\BackupIsRecent` fails on the System Health page if auto-backup is off or the latest successful run is older than the configured frequency allows — giving the pre-existing `backup_restore_tested` attestation (§5.1 of the health-checks task) a real automated signal to sit alongside.
- **Retention** — enforced by `backup:clean`, with the admin-configured `backup_retention_days` (30-day floor, matching the target above) applied at runtime before each cleanup run.

**One-time setup per deployment** (Google Cloud service account — works unattended, no OAuth consent screen to babysit):

1. Create/select a Google Cloud project, enable the **Google Drive API**.
2. Create a **service account**, generate a JSON key, download it.
3. Create (or pick) the destination Drive folder, share it with the service account's own email address (Editor access).
4. Set `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` (path to the key file) and `GOOGLE_DRIVE_FOLDER_ID` in `.env`. Set `BACKUP_ARCHIVE_PASSWORD` too — the dump contains customer PII (names, phone numbers, addresses).
5. Confirm `Settings → Store Settings → Backups` shows the provider as configured, then run one manually to verify end-to-end before relying on the schedule.

The quarterly restore test above is exactly what the "Restore" action gives you a real, in-app way to perform — against a disposable staging copy, never production.

---

## 3. Scheduler & Queue (correctness-critical, not just performance)

Two scheduled jobs are load-bearing. If the scheduler is not actually running, the system fails **silently**:

| Job | Frequency | Consequence if it never runs |
|---|---|---|
| `ReleaseExpiredReservations` | Every 1–5 min | Stock stays reserved forever after abandoned checkouts. Inventory appears to sell out while physical stock sits on the shelf |
| `VerifyPendingPayments` | Every ~2 min | Payments whose webhook was dropped stay `pending` indefinitely — customer charged, order never progresses |

**Required cron entry (per deployment):**

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Defining a schedule in `routes/console.php` or `Kernel.php` does nothing without this. This is the single most commonly missed step in a new deployment — WooCommerce installations exhibit exactly this failure mode (orders stuck holding stock forever) when cron isn't wired up.

**Queue worker:** must run under a process supervisor (Supervisor or systemd) with automatic restart. A dead queue worker means order confirmations and SMS notifications silently stop sending, **and payment verification/refunds stop resolving** (they're queued jobs too — `VerifyPaymentWithGateway`, `IssueProviderRefund`), while the site otherwise appears healthy.

Queues are segmented by nature — run a worker covering all four, or separate workers per queue if you want independent scaling/restart:

```
php artisan queue:work database --queue=external-api,emails,sms,notifications
```

`external-api` (payment gateway verify/refund calls), `emails`/`sms` (staff-composed ad-hoc customer messages), and `notifications` (order-lifecycle and system alert notifications — mail+SMS+database together per notification, since a single notification send can span multiple channels) are kept separate from Laravel's `default` queue so a slow/flaky provider call never delays a transactional notification, or vice versa. `emails`/`sms` are split from `notifications` specifically so a burst of staff bulk-messaging never backs up order-confirmation/payment-status delivery, or vice versa.

**Monitoring:** add an uptime/heartbeat check for both the scheduler and the queue worker. Both fail quietly; neither produces a user-visible error until a customer complains. The System Health dashboard's `ScheduleCheck`/`QueueCheck` cover "is the scheduler/worker alive at all," and `ExpiredReservationsAreBeingReleased`/`PendingPaymentsAreBeingVerified` cover the subtler failure of the scheduler being alive while one specific job errors or is unregistered — see `docs/TASK-system-health-checks.md`.

---

## 4. Environment Configuration

Per-client `.env` values (never committed):

```
APP_ENV=production
APP_DEBUG=false          # non-negotiable in production
APP_URL=https://client-domain.com

DB_*                     # this client's isolated database

PAYMENT_PROVIDER=paystack  # fallback until Store Settings is saved once
SMS_PROVIDER=moolre         # ditto

MOOLRE_API_KEY=
MOOLRE_WEBHOOK_SECRET=
MOOLRE_SENDER_ID=
PAYSTACK_SECRET_KEY=
PAYSTACK_PUBLIC_KEY=
GIANTSMS_TOKEN=
GIANTSMS_SENDER_ID=
GOOGLE_CLIENT_ID=        # for Google login
GOOGLE_CLIENT_SECRET=

QUEUE_CONNECTION=redis   # or database
SESSION_DRIVER=redis     # or database — not file, in multi-server setups
```

Per `CLAUDE.md`, `env()` is called only inside `config/` files — never in application code.

Provider credentials live in `.env`, never in `store_settings`. Settings control business behaviour (default provider, reservation window); `.env` controls technical wiring (keys, driver registration). This keeps secrets out of the database and out of the Filament-editable surface.

---

## 5. New Client Deployment Checklist

Corresponds to story E13.3.

> Most of this checklist is now checked automatically by the System Health
> dashboard (`/admin` → Settings → System Health, Super Admin only) and its
> CLI counterpart, `php artisan system:check --critical` (wired into the
> post-deploy step so a failing deploy aborts — see
> `docs/TASK-system-health-checks.md`). Items below are annotated with
> **[health]** where a check already verifies them on demand, **[nightly]**
> where a check verifies them via a nightly scheduled scan, and
> **[attestation]** where the platform can only ever record that a human
> confirmed it, never invent a pass — this checklist still names the exact
> manual action to take; the dashboard is where you go afterward to confirm
> it stuck.

**Provision**
- [ ] Server/hosting provisioned, PHP 8.2+, MySQL 8 with InnoDB confirmed **[health: `DatabaseEngineIsInnoDb`]**
- [ ] Domain pointed, SSL/TLS issued and auto-renewing
- [ ] Isolated database created for this client

**Deploy**
- [ ] Codebase deployed from the shared repo
- [ ] `.env` populated (see §4)
- [ ] `php artisan migrate --force`
- [ ] Seeders run: `php artisan db:seed --class=ProductionSeeder --force` — **not** `migrate:fresh --seed`'s full `DatabaseSeeder`, which also creates fake demo users/catalog/orders meant for local dev only
- [ ] Storage linked, upload directories writable **[health: `StorageIsWritableAndLinked`]**

**Configure**
- [ ] First Super Admin account created via `php artisan app:create-super-admin` (interactive; never via `UserSeeder`, which is fake demo data with a password nobody knows) **[health: `SuperAdminExists`]**
- [ ] Store Settings populated via the admin panel (`/admin` → Settings → Store Settings) — business name, logo, colours, tagline, contact details, tax rate, reservation window **[health: `StoreSettingsPopulated`]**
- [ ] Static pages filled in (About, Contact, Terms, Privacy, Refund Policy) via the admin panel (`/admin` → Settings → Static Pages) — content is authored here ahead of the storefront existing; publishing to a public URL is a storefront-phase task, not part of this checklist **[health: `StaticPagesHaveContent`]**
- [ ] Payment providers configured **[health: `PaymentProvidersConfigured`, presence only]** and tested with a real low-value transaction, refunded afterwards **[attestation: `real_payment_transaction_tested`]**
- [ ] SMS sending verified end-to-end on **each** network (MTN, Telecel, AirtelTigo) **[attestation: `sms_verified_all_networks`]**
- [ ] Webhook URLs registered with Moolre and Paystack; signature verification confirmed working **[attestation: `webhook_signature_verified`]**
  - **Paystack**: no dashboard step needed — `PaystackGateway::initiate()` passes `callback_url` explicitly on every transaction (`route('orders.confirmation', ...)`), so redirect-mode checkout works correctly with zero webhook/callback configuration in the Paystack dashboard. The actual payment webhook (`POST /webhooks/payments/paystack`) can still optionally be set in the dashboard as a defence-in-depth fallback, but isn't required for the callback redirect itself to work.
  - **Moolre**: the webhook `callback` URL is **account-level, not per-transaction** — Moolre's Mobile Money Collection API has no per-request callback parameter. Set it once when creating/configuring the Moolre merchant account (`callback: Webhook URL for processing real-time transaction callbacks`), pointing at `https://your-domain.com/webhooks/payments/moolre`. This step genuinely can't be automated from this codebase — confirm it's set on Moolre's side before go-live.

**Operational (the steps most often forgotten)**
- [ ] **Cron entry added and verified running** — see §3 **[health: `ScheduleCheck`, heartbeat]**
- [ ] **Queue worker running under supervisor and verified** **[health: `QueueCheck`, heartbeat]**
- [ ] Automated daily backups configured and one restore tested **[attestation: `backup_restore_tested`, re-confirm every 90 days]**
- [ ] `APP_DEBUG=false` confirmed **[health: `DebugModeCheck`]**
- [ ] Error monitoring / log destination configured
- [ ] Uptime monitoring on the site, the scheduler, and the queue worker

---

## 6. Pre-Launch Verification (per client)

Beyond the automated suite in `test-plan-ecommerce.md`, verify manually on the live deployment:

- [ ] A full end-to-end purchase using **real money** on each payment channel — mobile money and card
- [ ] The refund path, executed against that real transaction
- [ ] Reservation expiry actually releases stock (place an order, abandon it, confirm release after the configured window)
- [ ] Order confirmation SMS and email both arrive
- [ ] A deliberately mistimed/dropped webhook is recovered by `VerifyPendingPayments`
- [ ] Store Keeper login sees inventory but **not** orders or payments
- [ ] No route exposes a raw integer ID

---

## 7. Open Items

| Item | Status |
|---|---|
| Hosting provider / server sizing | Not yet decided |
| Redis vs. database for queue and sessions | Not yet decided — Redis preferred where available |
| Git strategy across clients (branch-per-client vs. core Composer package) | Branch-per-client to start; revisit past ~5–10 active deployments |
| Staging environment per client, or one shared staging | Not yet decided |

---

*End of document.*
