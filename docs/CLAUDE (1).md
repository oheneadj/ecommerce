# CLAUDE.md — Universal Laravel AI Coding Agreement

> Generic, project-agnostic ruleset for any Laravel project. Project-specific 
> details (stack variations, domain features, integrations) are sourced separately 
> per Section 19 — never baked into this file.

## 0. Precedence Rules
1. This file
2. Project-specific overrides
3. Framework/package defaults
4. Tool suggestions

Non-negotiables always win.

## 1. Session Workflow

At the start of every session:
- Read this file (CLAUDE.md)
- Detect stack:
  - `composer.json` → PHP/Laravel packages & version
  - `package.json` → JS packages & build tooling
  - `routes/` → route structure (web, api, console)
  - `app/Models/` → domain models
  - `.env.example` → configured drivers (queue, cache, session, mail, filesystem)
  - `config/` → framework-level configuration overrides
- Determine architecture and integrations based on the above
- Check for `CHANGELOG.md` in project root:
  - If it exists, confirm it's up to date with recent work
  - If it does not exist, create one before proceeding (standard "Keep a Changelog" format recommended)
- Ask the user directly, before starting work:
  - Is this KISS (simplest viable approach)?
  - Is this DRY (any duplicated logic to reuse/extract)?
  - Is this YAGNI (are we building only what's needed now)?
  - Are tests needed for this change?
  - Does CHANGELOG.md need an entry for this session's work?

After every successfully implemented and verified unit of work (tests/Pint/PHPStan green):
- Add the corresponding `CHANGELOG.md` entry first
- Then create a git commit covering that implementation + its CHANGELOG entry (Conventional Commits format, per Section 23) — don't batch multiple unrelated pieces of work into one commit, and don't leave completed, verified work uncommitted

## 2. Commands

Every Laravel project must define these standard Composer scripts in `composer.json`
(so the workflow is identical across all projects):

```bash
composer run dev          # start local dev environment (serve + queue + vite, etc.)
composer run test         # run full test suite
composer run lint         # auto-fix lint/formatting issues
composer run lint:check   # check lint/formatting without fixing
composer run setup        # fresh project setup (install, key:generate, migrate, seed)
```

Direct tool commands (always available, used for targeted runs):

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=2G
php artisan test --compact --filter=testName
```

Static analysis (PHPStan/Larastan) and Pint formatting are **required** on every project —
install and configure them at project setup if not already present.

Frontend build tooling (Vite via npm) is **assumed standard**:

```bash
npm run dev
npm run build
```

MCP servers — recommended default tooling for all projects:
- **Laravel Boost MCP**
- **Chrome DevTools MCP**

## 3. Global Non-Negotiables

- KISS — simplest solution that satisfies the requirement
- DRY — no duplicated logic; extract and reuse
- YAGNI — build only what's needed now
- Tests required for every behavioral change
- Full test suite must be green before marking work done
- CHANGELOG.md must be updated for every session with real work
- No secrets committed to code — use `.env` + config, never hardcoded
- No `env()` calls outside `config/*.php` — always access via `config()`
- Never delete, skip, or weaken a test (loosened assertions, commented-out cases, `->skip()`) without explicit approval
- No raw integer IDs exposed externally — required on every project (see Section 5 for implementation)

## 4. Code Conventions

- `declare(strict_types=1)` required in all `app/` code (models, services, actions,
  controllers, jobs, events, listeners, policies, enums, requests, resources).
  Not enforced in migrations, config, or route files.
- PHP 8+ constructor property promotion
- Explicit return types on all methods
- Curly braces always (no single-line control structures)
- Use `artisan make:*` generators instead of hand-creating framework files
- Run Pint after every change (`vendor/bin/pint --dirty --format agent`)

Naming:
- Models → PascalCase (e.g. `Booking`)
- Services → PascalCase + `Service` suffix (e.g. `BookingService`)
- Actions → VerbNoun + `Action` suffix (e.g. `CreateBookingAction`)
- Enums → TitleCase (e.g. `BookingStatus`)
- Variables → camelCase
- Blade views/components → kebab-case (e.g. `status-badge.blade.php`)

## 5. Identifier Strategy

- Internal primary key: `bigint` auto-increment on every table (performance, indexing, joins)
- External/public identifier: **ULID**, via Laravel's `HasUlids` trait
  - Used in route model binding, API responses, and anywhere an ID is exposed outside the app
  - Never expose the internal bigint PK in routes, APIs, or the frontend
- Slug: added only on models with SEO-relevant public-facing routes (e.g. blog posts, products, public profiles) — not required on every table

## 6. Database Rules

- Prefer Eloquent over raw queries
- Avoid the `DB` facade unless Eloquent genuinely can't express the query
- Use relationships (not manual joins) wherever possible
- Eager load relationships to prevent N+1 queries
- Enum-like columns must be `string` type, storing the PHP backed enum's string value directly
- Never use native MySQL `ENUM` column type (inflexible, hard to migrate/alter)
- All migrations must be reversible (`down()` fully undoes `up()`)
- Add indexes on foreign keys and frequently-queried columns
- Never edit a migration that has already been committed/run in any shared environment — create a new migration instead
- Use model scopes for repeated query filters instead of duplicating `where()` chains
- Any column with a `unique` constraint on a soft-deletable model (slug, SKU, code, etc.) must be **mutated on delete** to free the original value for reuse — e.g. `{original}-deleted-{id}`, using the row's own permanent database ID as the suffix. The ID guarantees uniqueness forever, even across repeated create → delete → recreate → delete cycles for the same name/code, since no two rows ever share an ID. Never leave a unique column untouched on a soft-deleted row — it permanently blocks reuse of that value by a new record.

## 7. Enums

Use PHP backed enums (string-backed, per Section 6) for any column representing a
fixed set of states or categories.

General pattern: `{Domain}{Concept}` — e.g. `UserRole`, `UserStatus`, `OrderStatus`.

Guidelines:
- One enum per distinct domain concept — don't overload a single enum for multiple meanings
- Place enums in `app/Enums/`
- Add helper methods on the enum itself (e.g. `label()`, `color()`) rather than
  scattering conditional logic across the app

## 8. Comments & Documentation

Comments:
- Write in a human, simple, conversational tone — avoid stiff corporate/robotic phrasing
- Explain *why*, not *what* (the code already shows what; comments should cover intent,
  tradeoffs, or non-obvious reasoning)
- Inline comments only where logic is genuinely complex or non-obvious — don't narrate
  every line
- Use PHPDoc where it adds real value (complex params, return types that aren't obvious,
  edge cases) — not required boilerplate on every trivial method

Required docblocks (app-wide, all methods — not just services/actions):
- File-level docblock at the top of every class file
- Class-level docblock describing the class's responsibility
- Method-level docblock on every method (public, protected, and private) explaining
  purpose and any non-obvious behavior — kept short and human, not padded boilerplate

## 9. Architecture

Controllers / Livewire components:
- UI/HTTP layer only — receive input, delegate to Actions/Services, return response
- No business logic

Actions:
- A single business operation triggered by one entry point (e.g. `CreateBookingAction`)
- Does one thing; not reused/called by multiple unrelated entry points

Services:
- Reusable logic called by multiple Actions and/or Controllers
- Orchestrates multiple Actions when a workflow spans more than one operation
- Home for complex business logic that doesn't belong to a single entry point

Models:
- Persistence, relationships, scopes, casts, accessors/mutators
- No business logic beyond the model's own data concerns

Policies:
- Authorization logic only — "can this user do this?"

Events:
- Side effects that happen *because* something occurred (e.g. `BookingConfirmed`
  triggers a notification, doesn't create the booking itself)

Jobs:
- Async/queued work — anything that shouldn't block the request/response cycle

Third-party integrations:
- Any external provider (payment gateway, SMS/email gateway, KYC verification service, shipping/tracking API, etc.) must be integrated behind an **interface**, with one **driver class per provider**, resolved via config — never called directly from an Action/Service.
- Pattern: `{Domain}Gateway` interface (e.g. `PaymentGateway`, `SmsGateway`) → one driver per provider (e.g. `PaystackGateway`, `MoolreGateway`) → a Manager class (Laravel's Manager pattern, as used internally for Cache/Mail/Filesystem) resolves the active driver(s) from `config/{domain}.php`.
- Adding, removing, or swapping a provider must be a **config + one new driver class** change — it must never require modifying any Action, Service, or business logic that uses the gateway.
- Multiple drivers may be active simultaneously (e.g. one provider for mobile money, another for cards) — the calling code selects by channel/config, not by hardcoding a provider name.

## 10. Reusable Components

Rule: Before building new UI, check `resources/views/components/` (Blade) and
`app/Livewire/` (Livewire) for an existing reusable component first. Only create a
new component when nothing existing fits the need — never duplicate a component
that already covers the use case.

Starter set — every project should have these as a baseline:

Blade components:
- `alert`
- `button`
- `page-header`
- `status-badge`
- `card`
- `modal`

Livewire components:
- `search`
- `filters`
- `forms`
- `dashboards`

(Additional components should be added as the project genuinely needs them —
don't scaffold components speculatively.)

## 11. Frontend Rules

Default stack (used unless a project states otherwise):
- Blade
- Livewire 4
- Alpine.js
- Tailwind 4
  - If the project already uses FlyonUI, keep and continue using it
  - Otherwise, use plain Tailwind — don't introduce a UI kit unprompted
- Filament — optional, only for admin panel builds; not part of the default stack

Note: Flux UI is not part of the default or approved stack. Remove all traces of
Flux UI from the app and codebase (components, imports, config, assets) if found
in an existing project — replace with Blade + Tailwind (or FlyonUI, if already in use).

Rules:
- No business logic in components — UI/presentation only (see Section 9)
- Use `wire:key` on all looped Livewire elements
- Loading states required on any action with perceptible delay
- Check for reusable components first (see Section 10)
- No inline `<script>`/`<style>` — use dedicated JS/CSS files or Alpine directives

## 12. Security

- Validate everything — never trust request input
- Use Form Requests for all validation (not inline `$request->validate()` in controllers)
- Policies required for all authorization checks — no manual `if ($user->id === ...)` scattered in controllers
- Rate limit all public-facing endpoints (forms, APIs, auth routes)
- **Cost-triggering actions need their own rate limit, separate from security rate limiting.** Any endpoint that triggers a *billed* third-party call on each request (SMS OTP, paid API lookups, etc.) must be rate-limited specifically to control cost/abuse — e.g. max 1 SMS OTP request per phone per 60 seconds, max 5 per hour — in addition to, not instead of, standard brute-force/security rate limiting on the same endpoint.
- **Never auto-merge or auto-link two accounts based on an unverified or coincidental identifier match** (e.g. a guest checkout email happening to match a registered account, or a new login method's email matching an existing user). Only link when the matching identifier was independently **verified** by a trusted source (e.g. an OAuth provider's verified email, an OTP-confirmed phone) — otherwise, linking must require the user to already be authenticated and explicitly opt in.
- Customer-facing authentication is not required to be email + password. Phone number + OTP (SMS) is a valid, often lower-friction primary method for markets where email+password isn't the natural default, with email+password and/or social login (Google, etc.) as optional additional methods. Staff/admin panel authentication should remain email + password regardless of what customer-facing auth uses.
- File storage:
  - Private + signed URLs required for sensitive or user-specific files (uploads,
    KYC documents, invoices, private media, anything tied to a specific user/record)
  - Public assets genuinely meant to be publicly accessible (e.g. blog post images,
    site branding) may use public storage — no signing needed
- Escape all Blade output by default (`{{ }}`) — only use `{!! !!}` with explicit,
  reviewed justification (e.g. sanitized rich text)

## 13. Data Integrity

- Use DB transactions for any multi-step write (multiple related inserts/updates
  that must succeed or fail together)
- Webhooks must be idempotent — check for already-processed events (e.g. by
  storing and checking the provider's event ID) before acting

Concurrency on limited resources (required for any finite, contested quantity):
- Any limited-quantity resource — inventory stock, coupon/promo usage limits,
  booking/appointment slots, waitlist spots, or anything else where two
  simultaneous requests could both succeed and jointly overcommit past what's
  actually available — must validate availability and commit the change inside
  a **single DB transaction with row-level locking** (`lockForUpdate()`), never
  as two separate steps (check, then write) that a second request could
  interleave between.
- A concurrency test proving two simultaneous requests against the last
  available unit result in exactly one success and one rejection is required
  for any such resource before it ships.

Async confirmation reliability (required for any provider that confirms
success via webhook/callback — payments, KYC verification, shipment updates):
- Do not rely on a webhook arriving as the only confirmation path. Providers
  can deliver late or drop delivery entirely. Pair webhook handling with an
  **active polling fallback** — a scheduled job that checks status directly via
  the provider's verify/status endpoint for anything still pending beyond a
  short grace period.
- Define an explicit rule for confirmation arriving **after** a related time-bound
  hold has already expired and released (e.g. a payment succeeding after its
  stock reservation expired, a KYC approval arriving after a booking hold
  lapsed) — don't leave this as an undefined edge case. Typical resolution:
  attempt to honor it if the held resource is still available, otherwise
  reverse/refund/cancel automatically and notify the user — never silently
  drop either the confirmation or the conflict.

Historical/transactional snapshots (required for any record referencing
mutable data at a point in time — orders, invoices, sale records, receipts):
- A record documenting something that already happened must **snapshot**
  the referenced data (name, price/amount, attributes, image, etc.) at the
  moment it happened — it must never re-read live data from the referenced
  model afterward, for any reason (display, PDF regeneration, reporting).
- This must hold true even if the referenced record is later edited, archived,
  or deleted entirely — editing a product's price today must never change
  what a past order's receipt shows.
- Keep the snapshot broad enough to fully render the historical record without
  touching live data at all (not just the price — name, image, and any other
  displayed attribute), not just the minimum needed for calculations.

Ledger vs. transactional records (avoid over-building):
- A full double-entry accounting ledger is only justified when the project
  involves multi-party payouts (marketplace/vendor splits), heavy formal
  accounting reconciliation, or complex partial-settlement scenarios.
- Absent those, standard transactional records (payments/refunds/audit-log
  tables with clear amounts and statuses) are sufficient — they remain fully
  queryable and reconcilable without the added complexity of enforced
  double-entry balancing. Don't build a ledger by default; treat it as a
  deliberate addition once an actual multi-party or audit-grade need appears.

Money handling (strict — no exceptions):
- All money columns must be `integer` (or `unsignedBigInteger` for large amounts),
  storing minor units only (e.g. cents/kobo/pesewas). Example: $1.54 → stored as `154`.
- Never use `float`, `double`, or raw `decimal` columns for money.
- Raw integer minor units must never be passed directly to a Blade view or API
  response — always convert at the boundary:
  - Add a `formatted` accessor or a dedicated `Money`-style cast on the model
    (e.g. `getAmountFormattedAttribute()`) — never call `/ 100` inline in a Blade
    view or controller.
  - API Resources must expose both the raw minor-unit integer (for client-side
    math/precision) and a pre-formatted display string (for direct rendering) —
    never leave the client to do the `/ 100` conversion itself.
- All arithmetic on money (totals, discounts, tax, refunds) must be done on the
  integer minor-unit value — never convert to float mid-calculation and back.
- When integrating a payment provider (Stripe, Paystack, etc.), pass/receive
  amounts in minor units directly — no conversion needed at that boundary, since
  the provider already speaks minor units.

- Store all dates/timestamps in UTC — convert to local timezone only for display
- Prefer immutable date objects (`CarbonImmutable`) over mutable date handling
  to avoid accidental side effects

## 14. Events

See Section 9 for the architectural role of Events (side effects triggered by
something that already happened).

- Naming convention: `{Noun}{PastTenseVerb}` — e.g. `UserRegistered`,
  `StatusChanged`, `RecordApproved` (generic pattern, not tied to any specific domain)
- Fire events from Models, Actions, or Services — never from Controllers/Livewire directly
- Listeners should be single-purpose (one listener = one side effect); use multiple
  listeners on the same event rather than one listener doing several unrelated things
- Queue listeners (`ShouldQueue`) by default for any side effect that isn't required
  to complete before the response returns (see Section 15 — Queues & Jobs)

## 15. Queues & Jobs

Use queues for:
- Emails
- SMS
- Notifications
- External API calls
- Heavy/long-running processing

Every queued job must declare:
- `$tries` — max retry attempts
- `$timeout` — max execution time per attempt
- `$backoff` — delay strategy between retries (fixed or array for exponential backoff)
- `failed(Throwable $exception)` — explicit handling for permanent failure
  (e.g. notify admin, mark record as failed) — never let a failed job disappear silently

Queue segmentation (required):
- Route jobs to named queues by nature/priority rather than the single `default`
  queue — e.g. `emails`, `sms`, `notifications`, `external-api`, `processing`
- Time-sensitive jobs (e.g. transactional emails, OTPs) must not share a queue with
  slow/unreliable external API jobs, so one doesn't block the other
- Configure workers per queue in deployment (`php artisan queue:work --queue=emails,default`)
  so priority queues get dedicated capacity

## 16. Testing

Requirements:
- Happy path
- Auth
- Validation
- Edge cases

Use:
- Feature tests preferred
- Unit for isolated logic

Fake:
- Mail
- Queue
- Event
- Notification
- Storage
- HTTP

## 17. Performance

- Profile before optimizing — never optimize based on assumption; use Telescope/Debugbar
  data to confirm the actual bottleneck first
- Prevent lazy loading — enforce via `Model::preventLazyLoading()` in
  `AppServiceProvider::boot()`, throwing in local/staging (`!app()->isProduction()`),
  logged (not thrown) in production via `Model::handleLazyLoadingViolationUsing()`
- Query budget: no single request should execute more than ~50 queries. Use
  Telescope/Debugbar locally to monitor per-request query count; investigate and
  fix (via eager loading, caching, or query restructuring) before merging if
  a request exceeds budget
- Cache deliberately — cache expensive/repeated queries and computations, with
  explicit TTLs and cache invalidation on relevant writes; don't cache reflexively
- Push long-running or slow operations (external API calls, report generation,
  bulk processing) to queued Jobs — never block the request/response cycle
- Telescope enabled in local/staging for query, request, and job debugging
- Pulse enabled in production for live performance and health monitoring

## 18. Admin Panel Rules

### General (applies to any admin panel — Filament, Nova, hand-rolled, or otherwise)

- Every admin resource/action must be backed by a Policy — no admin action bypasses
  authorization, regardless of tooling
- Authorize every action explicitly (view, create, update, delete, and any custom
  action) — never assume "if they can reach the admin panel, they can do anything in it"
- Group related resources/sections logically in navigation — don't leave a flat,
  unorganized list as the panel grows
- Use dedicated read-only/detail views for complex records instead of cramming
  everything into the edit form
- Surface actionable counts (pending approvals, unread items, flagged records) in
  navigation/dashboard where it helps the admin prioritize — don't bury them

### Filament-specific (applies only when the project uses Filament)

- Every Filament `Resource` must have a corresponding `Policy` class registered
  and enforced (Filament auto-respects policies — don't bypass with `canX()`
  overrides unless intentional)
- Use `Section`/`Fieldset`/grouped `Tabs` within forms to organize related fields
  rather than one long flat form
- Use custom `Infolist` views for complex read-only detail pages instead of
  reusing the edit form as a pseudo-view
- Use Filament navigation badges (`getNavigationBadge()`) to surface actionable
  counts directly on the resource's nav item

## 19. Project-Specific Context

The universal rules in this file (Sections 0–18, 20–23) apply to every Laravel project.
Project-specific details (stack variations, domain features, integrations, identifier
overrides, etc.) do NOT live in this file — they must be sourced separately, per
Section 0's precedence rules (project-specific overrides > this file's defaults
only where explicitly stated).

At the start of every new project session, before starting real work:

1. Look for a project requirements/context file in the repo — check common locations
   and names first (e.g. `REQUIREMENTS.md`, `PROJECT.md`, `docs/context.md`,
   `.claude/context.md`, or similar).
2. If such a file exists: read it fully and treat it as the source of truth for
   this project's specific stack, domain, features, and integrations.
3. If no such file exists: stop and ask the user either to:
   - Point to/create a requirements file, or
   - Answer a short set of clarifying questions directly (stack/version specifics,
     core domain entities, third-party integrations, any deviations from this
     file's defaults — e.g. identifier strategy, UI kit) — and offer to write the
     answers into a new context file for future sessions.
4. Never assume or invent project-specific details (domain models, integrations,
   business rules) that aren't confirmed by the context file or the user directly.

## 20. Pre-Completion Checklist

Core principles:
- [ ] KISS — simplest viable solution?
- [ ] DRY — no duplicated logic?
- [ ] YAGNI — nothing built beyond what's needed now?
- [ ] Single responsibility respected (Controllers/Actions/Services/Models per Section 9)?

Code quality:
- [ ] `declare(strict_types=1)` present in all `app/` files touched?
- [ ] Explicit return types on all methods?
- [ ] File/class/method docblocks present, human-toned, PHPDoc used where it adds value?
- [ ] Comments explain *why*, not *what* — human tone, not robotic?
- [ ] No duplicate query filters — model scopes used for repeated filters?
- [ ] No business logic leaked into Controllers/Livewire components?
- [ ] Pint run (`vendor/bin/pint --dirty --format agent`)?
- [ ] PHPStan/Larastan passing?

Identifiers & data integrity:
- [ ] No raw integer IDs exposed externally (ULID used per Section 5)?
- [ ] Any unique constraint on a soft-deletable model mutated on delete to free the value for reuse (Section 6)?
- [ ] Money stored/handled as integer minor units — no float/decimal money, no raw
      minor-unit values leaked to Blade/API without formatting (Section 13)?
- [ ] Multi-step writes wrapped in DB transactions?
- [ ] Any limited-quantity resource (stock, coupon usage, slots) validated and committed under row-level locking, not check-then-write?
- [ ] Any webhook/callback-dependent flow paired with a polling fallback, and a defined rule for confirmation arriving after a related hold has expired?
- [ ] Any historical/transactional record fully snapshotted, never re-reading live data on display?
- [ ] Third-party providers (payment, SMS, etc.) integrated behind an interface with a swappable driver, never called directly (Section 9)?
- [ ] Dates stored in UTC, immutable date objects used?

Performance:
- [ ] No new N+1 queries introduced (lazy loading prevention respected)?
- [ ] Request query count within budget (~50), checked via Telescope/Debugbar?
- [ ] New queued jobs routed to the correct named queue (not dumped in `default`)?
- [ ] Jobs declare `tries`, `timeout`, `backoff`, `failed()`?

Frontend:
- [ ] No Flux UI present anywhere in touched code (Section 11)?
- [ ] Existing reusable components checked/reused before creating new ones (Section 10)?
- [ ] `wire:key` present on all looped Livewire elements?
- [ ] No inline `<script>`/`<style>`?

Security:
- [ ] All input validated via Form Requests?
- [ ] Policy in place and enforced for any new authorization-sensitive action?
- [ ] Public endpoints rate-limited?
- [ ] Any endpoint triggering a billed third-party call (SMS, paid lookups) separately rate-limited for cost control?
- [ ] No account auto-merged/linked on an unverified or coincidental identifier match?
- [ ] Sensitive files private + signed; only genuinely public assets left public?
- [ ] Blade output escaped by default (`{!! !!}` only with reviewed justification)?

Testing & delivery:
- [ ] Tests written for happy path, auth, validation, and edge cases?
- [ ] No test deleted, skipped, or weakened without explicit approval?
- [ ] Full test suite green?
- [ ] `CHANGELOG.md` updated (created if it didn't exist, per Section 1)?
- [ ] Git commit created for this implementation, made after the CHANGELOG entry (per Section 1/23)?

## 21. Error Handling & Logging

API/JSON error responses (standard Laravel convention — no custom format):
- Validation errors (422): `{ "message": "...", "errors": { "field": ["..."] } }`
- General errors: `{ "message": "..." }`
- Never leak stack traces, raw exception messages, or internal paths in production
  API responses — use `report()` internally, return a safe generic message externally

Logging levels (explicit, not loose):
- `Log::error()` — failures requiring human attention (payment failures, jobs failed
  after final retry, integration/API failures)
- `Log::warning()` — recoverable/degraded situations (retry succeeded after failure,
  fallback used, approaching a rate limit)
- `Log::info()` — audit-trail-worthy business events (record created, payment received,
  role/permission changed)
- `Log::debug()` — local-only diagnostic detail; not relied upon in production

Exceptions:
- Domain-specific error cases must use named custom exceptions extending a base
  `\Exception` (e.g. `InsufficientBalanceException`, `DuplicateBookingException`),
  not generic `throw new \Exception(...)`
- Custom exceptions live in `app/Exceptions/`
- Catch specific exception types where the caller needs to react differently —
  never catch-all `catch (\Exception $e)` to swallow errors silently

Sensitive data — never written to `laravel.log` or any file-based log channel:
- API keys, secrets, tokens, passwords, card/bank numbers, OTPs, KYC document
  contents, and any other sensitive/PII field must never appear in `laravel.log`
  or any file log — not even redacted inline, since redaction logic can fail/drift
- Anything needing to be recorded for audit/debugging purposes that touches
  sensitive data must be written to a dedicated database table instead
  (e.g. `api_call_logs`, `audit_logs`), where field-level access can be controlled
  and encryption/redaction applied at the column level if needed
- `laravel.log` (and any file channel) is reserved for non-sensitive operational
  logging only — errors, warnings, general application flow

Third-party API calls (required):
- Every outbound request to a third-party API (payment gateways, SMS providers,
  external services) and its response must be logged in full (request payload,
  response payload, status code) — recorded to a dedicated database table
  (e.g. `api_call_logs`), never to `laravel.log`
- If the payload contains sensitive fields, store them encrypted at the column
  level (Laravel's `encrypted` cast) rather than omitting them — full traceability
  is required for reconciliation, but exposure must still be controlled

Money / critical data auditing (required):
- Money-related and other audit-sensitive events (payments, balance changes, refunds,
  status transitions on financial records) must be persisted to a dedicated database
  table (e.g. `audit_logs`, `payment_logs`) — not log files
- The database is the source of truth for anything that may need to be queried,
  reported on, or reconciled later — file logs are operational only, never authoritative

## 22. API Response Standards

(Applies only to projects exposing an API — mobile backend, external integrations,
public API, etc.)

- Every API response must go through a Laravel API Resource (`JsonResource`/
  `ResourceCollection`) — never return raw Eloquent models or collections directly
- Resources are the only place internal fields are excluded/renamed — never rely
  on `$hidden` alone to prevent leakage (no raw integer PK exposed — see Section 5)
- Use Laravel's built-in paginator output (`links`/`meta`) for any paginated
  endpoint — don't invent a custom pagination shape
- URI-based versioning as the default: `/api/v1/...` — bump the version segment
  on breaking changes, keep old versions live until deprecated on a defined timeline
- Success responses return the resource/collection directly (Laravel's default
  wrapping); error responses follow Section 21's standard shape
- Money fields in API responses follow Section 13 — expose raw minor-unit integer
  and pre-formatted display string together, never raw minor units alone

## 23. Git & Commit Conventions

Commit messages — Conventional Commits format:
`type(scope): short description`

Types: `feat`, `fix`, `refactor`, `chore`, `test`, `docs`, `perf`, `style`

Examples:
- `feat(booking): add cancellation flow`
- `fix(auth): resolve session timeout on login`
- `refactor(payment): extract charge logic into action`

- Written in plain, human language — no vague messages like `fix stuff` or `updates`
- Scope should reference the relevant domain/module when applicable

Branch naming: `type/short-description`
- Examples: `feature/booking-cancellation`, `fix/session-timeout`
- Match the same `type` vocabulary as commits for consistency

Pull requests:
- Description must state *what* changed and *why* — not just a copy of commit messages
- Section 20's Pre-Completion Checklist must be satisfied before opening/merging
- Full test suite must be passing (per Section 3) — no PR merged with red tests
- Squash or keep history clean depending on project convention — but never merge
  with unresolved merge conflicts left in the diff
