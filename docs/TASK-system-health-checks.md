# TASK: System Health Checks, Admin Dashboard & Deploy Gate

You are working in a Laravel + Filament e-commerce codebase. Follow `CLAUDE.md` and `AGENTS.md` at all times — this task does not override them.

**Goal:** turn the static checklists in `infrastructure-deployment.md` and the app-enforced invariants in `technical-design-ecommerce.md` §4g into executable checks with three consumption points: a Filament dashboard, a deploy gate, and CI architecture tests.

**Why this exists:** a markdown checklist is only as reliable as the person reading it, and it goes stale silently. Cron can be running on day one and dead by month three with nothing to signal it. This system deploys separately per client, so every install must be independently verifiable.

Work through the steps in order. Steps 1–5 are the core; Step 6 (CI) is separable if you want to ship incrementally.

---

## Foundational concept — four tiers, treated differently

Do not treat all checks the same. Conflating them makes the dashboard dishonest.

| Tier | Nature | How it's checked | Runs |
|---|---|---|---|
| **1. Config/schema** | Definitively knowable now | Query the system directly | On demand — cheap |
| **2. Heartbeat** | Absence *is* the failure | A job writes a timestamp; check staleness | On demand — reads cached timestamp |
| **3. Data integrity** | Invariants the DB can't enforce | Aggregate queries over large tables | **Scheduled nightly only** — expensive |
| **4. Attestation** | Not knowable by code at all | Stored human confirmation + staleness policy | On demand — reads a record |

Tier 3 must never run on page load. Tier 4 checks report *when someone last confirmed*, never a pass/fail the code invented.

---

## STEP 1 — Install and configure the package

```bash
composer require spatie/laravel-health
composer require shuvroroy/filament-spatie-laravel-health
php artisan vendor:publish --tag="health-migrations"
php artisan migrate
```

Register checks in a service provider (`AppServiceProvider` or a dedicated `HealthServiceProvider`). Use Spatie's built-in checks where they exist rather than reimplementing:

- `DebugModeCheck` — `APP_DEBUG` must be false
- `EnvironmentCheck` — must be `production`
- `DatabaseCheck` — connectivity
- `ScheduleCheck` — scheduler heartbeat (Tier 2)
- `QueueCheck` — queue worker heartbeat (Tier 2)
- `UsedDiskSpaceCheck`
- `OptimizedAppCheck` — config/routes cached in production

`ScheduleCheck` requires its ping registered in the scheduler, and `QueueCheck` requires its heartbeat job dispatched on a schedule — follow the package docs. **Without these, both silently report as failing forever.**

---

## STEP 2 — Custom checks, Tier 1 (config & schema)

Create these under `app/HealthChecks/`, each extending Spatie's `Check`. One class per check, single responsibility, named for the condition it asserts — same discipline as Actions.

Every check must return a **remediation hint** in its failure message: not "cron isn't running" but the exact line to add. The dashboard is for acting on, not just reading.

### 2.1 `DatabaseEngineIsInnoDb` — CRITICAL

```php
$nonInnoDb = DB::select("
    SELECT table_name FROM information_schema.tables
    WHERE table_schema = DATABASE() AND engine != 'InnoDB'
");
```

Fails if any row returns. **This is the single most important check in the system:** a MyISAM table accepts `DB::transaction()` without error and rolls back nothing — every atomicity guarantee in the codebase silently evaporates with no failure signal.

### 2.2 `TransactionDurabilityEnabled` — CRITICAL

`SHOW VARIABLES LIKE 'innodb_flush_log_at_trx_commit'` must be `1`. At `0` or `2`, a committed transaction can be lost on power failure. Skip gracefully (mark not-applicable) if the connection isn't MySQL.

### 2.3 `TransactionIsolationLevelIsSafe` — CRITICAL

Must be `REPEATABLE READ` or `READ COMMITTED`. Anything lower breaks the locking design in `ReserveStockForOrder` and `ApplyCouponToOrder`.

### 2.4 `ForeignKeysAreEnforced` — CRITICAL

Query `information_schema.key_column_usage` for the constraints declared in the technical design §3. Fails if any expected constraint is missing — a bare `unsignedBigInteger` looks identical to a foreign key in an ERD while enforcing nothing.

### 2.5 `PaymentProvidersConfigured` — CRITICAL

Each provider registered in `config/payments.php` has non-empty credentials, and at least one provider is enabled. **Assert presence only — never make a live API call from a health check.**

### 2.6 `SmsProviderConfigured` — CRITICAL

Same shape, against `config/sms.php`.

### 2.7 `StoreSettingsPopulated` — WARNING

Business name, logo, contact details, and `stock_reservation_minutes` are all set and non-default. A fresh install passing this means branding was actually configured.

### 2.8 `StaticPagesHaveContent` — WARNING

Every expected slug (`about`, `contact`, `terms`, `privacy-policy`, `refund-policy`) exists and does not still contain seeder placeholder text.

### 2.9 `SuperAdminExists` — CRITICAL

At least one user holds the Super Admin role.

### 2.10 `StorageIsWritableAndLinked` — CRITICAL

Public storage symlink exists; upload directories are writable.

---

## STEP 3 — Custom checks, Tier 2 (operational heartbeats)

Spatie's `ScheduleCheck` and `QueueCheck` cover "is the scheduler/worker alive." Add one domain-specific check they can't know about:

### 3.1 `ExpiredReservationsAreBeingReleased` — CRITICAL

```php
$stuck = StockReservation::where('status', 'active')
    ->where('expires_at', '<', now()->subMinutes(15))
    ->count();
```

Fails if any exist. This catches a subtler failure than "is cron running": the scheduler may be alive while this specific job errors, is unregistered, or silently throws. The symptom in production is stock reserved forever after abandoned checkouts — the site shows sold-out while physical stock sits on the shelf, with no error anywhere. This is the exact failure mode WooCommerce installs hit constantly.

### 3.2 `PendingPaymentsAreBeingVerified` — CRITICAL

Any `Payment` still `pending` well beyond the polling grace period (e.g. 30 minutes) indicates `VerifyPendingPayments` isn't running. Symptom: customer charged, order never progresses.

---

## STEP 4 — Custom checks, Tier 3 (data integrity) — SCHEDULED ONLY

These verify the invariants that `technical-design-ecommerce.md` §4g documents as **application-enforced, not database-enforced**. They are the only mechanism that can detect drift if a bug ever bypasses the Action layer.

**These must not run on page load.** Run nightly, store results, dashboard reads the stored result with its timestamp. Each result should surface *how many* records are affected, and identifiers for the first few, so the problem is actionable.

| Check | Asserts | Severity |
|---|---|---|
| `StockCacheMatchesMovements` | Every `product_variants.stock` equals the sum of its `stock_movements` | CRITICAL |
| `NoUsersWithoutIdentifier` | No user lacking all of `phone`, `email`, `google_id` | CRITICAL |
| `NoRefundExceedsItsPayment` | No `refund.amount` greater than its parent payment's amount | CRITICAL |
| `NoOrdersWithoutItems` | No `order` with zero `order_items` | CRITICAL |
| `NoProductsWithoutVariants` | No non-archived `product` with zero variants | WARNING |
| `NoReviewsWithoutVerifiedPurchase` | Every review links to a real completed `order_item` | WARNING |
| `StatusColumnsContainValidValues` | Every status column holds a value from its enum class (these are `string` columns per `CLAUDE.md` §6, so the DB does not constrain them) | CRITICAL |
| `NoSoftDeletedRecordHoldsOriginalUniqueValue` | No soft-deleted product/variant retains an unmutated `slug`/`sku` — proves the delete transaction worked | WARNING |

Register these to run nightly via the scheduler, separately from the on-demand checks.

---

## STEP 5 — Tier 4 (attestations) and the dashboard

### 5.1 Attestation model

Some requirements cannot be verified by code — only recorded as confirmed by a human, with staleness tracking.

```
health_attestations
  id, key, confirmed_by (FK users), confirmed_at, notes, created_at
```

Required attestations, each with a staleness policy:

| Key | Stale after | Severity when stale |
|---|---|---|
| `backup_restore_tested` | 90 days | CRITICAL |
| `real_payment_transaction_tested` | Per deployment (never expires once done) | CRITICAL if never |
| `sms_verified_all_networks` | Per deployment | CRITICAL if never |
| `webhook_signature_verified` | Per deployment | CRITICAL if never |

The dashboard must show **"last confirmed by X on DATE"** — never invent a pass. An untested backup is a hypothesis, not a backup, and the UI should say so plainly.

Provide a Filament action allowing a Super Admin to record an attestation (writes `confirmed_by` and `confirmed_at`).

### 5.2 Filament page

A Super-Admin-only page (`ActivityLog`-style, in the admin panel) showing:

- **Overall status badge** — severity-weighted percentage **and** three counts (critical failures / warnings / passing)
- **Hard rule: the badge is RED whenever any critical check fails, regardless of percentage.** A naive "38/40 = 95% healthy" is actively dangerous when the two failures are "backups not configured" and "cron not running." The percentage is for at-a-glance trend; the critical gate is for truth.
- Checks grouped by category (Infrastructure / Operations / Configuration / Data Integrity / Attestations)
- Each failing check shows its remediation hint
- Tier 3 results display their **last-run timestamp**, since they're cached and may be up to a day old
- A "re-run now" action for Tier 1 and 2 checks

### 5.3 Persistent admin banner

If any critical check fails, show a dismissible-per-session banner across the admin panel linking to the health page. A dashboard nobody visits is a dashboard that doesn't work.

---

## STEP 6 — Deploy gate and CI

### 6.1 `php artisan system:check`

```bash
php artisan system:check              # all checks, human-readable output
php artisan system:check --critical   # critical only; exits non-zero on any failure
```

**Wire `--critical` into the post-deploy step on the target server**, so a failing deploy aborts.

**Critical constraint — exclude Tier 2 heartbeat checks from the gate.** Immediately after a deploy, the queue worker has just restarted and the scheduler hasn't ticked yet; heartbeats will legitimately appear stale and would fail every single deploy. Teams respond to that by disabling the gate within a month. Heartbeats are for monitoring, not gating.

Add a `--skip-heartbeats` flag (or make `--critical` exclude them by default and document it clearly).

### 6.2 Why this does not belong in CI

CI runs against its own throwaway test database. It has no visibility into whether *production* uses InnoDB, whether *production's* cron is alive, or whether *production's* `APP_DEBUG` is false. A CI gate on these checks would be inspecting the wrong machine and passing meaninglessly.

### 6.3 What CI *can* enforce — architecture tests

These are code-level rules, which CI can genuinely see. Use Pest architecture testing:

```php
// No Action may call a payment/SMS vendor directly — must go via the gateway contract
arch('actions do not call vendor SDKs directly')
    ->expect('App\Actions')
    ->not->toUse(['App\Payments\Drivers', 'App\Sms\Drivers']);

// No business logic in controllers
arch('controllers stay thin')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

// Strict types everywhere
arch('strict types')->expect('App')->toUseStrictTypes();
```

Add a migration-linting test asserting no new migration introduces a `decimal` money column, a bare `unsignedBigInteger` where a foreign key is intended, or an externally-exposed table without a `ulid`.

---

## Constraints

- **Never make a live third-party API call from a health check.** Checks must be safe to run repeatedly and on demand; a check that costs money or hits rate limits will be disabled. Assert configuration presence, not connectivity.
- **Never run Tier 3 integrity scans on page load.** They are full-table aggregates over `stock_movements` and will not scale.
- Health checks are **read-only**. A check must never mutate data to "fix" what it finds.
- Every check returns a remediation hint on failure — the exact config line, command, or query needed.
- The health page is Super Admin only. It exposes infrastructure detail that Admin and Store Keeper roles should not see.
- Follow `AGENTS.md` naming: each check class is named for the condition it asserts, in the affirmative (`DatabaseEngineIsInnoDb`, not `CheckDatabase`).

---

## Definition of done

- [ ] `spatie/laravel-health` installed; built-in checks registered and passing
- [ ] `ScheduleCheck` ping and `QueueCheck` heartbeat registered in the scheduler — verified actually reporting, not permanently failing
- [ ] All Tier 1 checks implemented, each with a remediation hint
- [ ] Both Tier 2 domain checks implemented
- [ ] All Tier 3 integrity checks implemented and scheduled nightly, results stored with timestamps
- [ ] `health_attestations` table, model, and Filament recording action built
- [ ] Filament page built: weighted percentage + three counts, red on any critical failure, grouped by category, remediation hints visible, Tier 3 timestamps shown
- [ ] Persistent admin banner on critical failure
- [ ] `system:check` command with `--critical`, excluding heartbeats, exits non-zero appropriately
- [ ] Post-deploy gate wired into the deployment process
- [ ] Pest architecture tests added to CI
- [ ] `infrastructure-deployment.md` §5 checklist updated to reference the health page instead of duplicating items now checked automatically
- [ ] Pint, PHPStan, and the full test suite green

## Stop and ask if

- A Tier 3 integrity check is slow enough to be a problem even nightly — that may indicate it needs sampling or an indexed approach rather than a full scan.
- An expected foreign key constraint turns out to be missing in the current schema — that is a real finding, not a check bug, and needs a migration rather than a weakened check.
- The InnoDB or durability check fails on the current environment — stop and report before proceeding; this indicates the database is not currently ACID-compliant and takes priority over the rest of this task.
