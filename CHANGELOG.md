# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Root `.htaccess` forwarding requests into `public/` for hosts pointing the document root at the project root.

### Changed
- Switched the storefront font from Instrument Sans to Lexend (`vite.config.js` fonts entry, `--font-sans` in `resources/css/app.css`).
- Removed Flux UI (`livewire/flux`, `livewire/blaze`) per project standard (plain Blade + Tailwind only). Rebuilt every layout, auth view, and settings view (login, register, password reset/confirm, email verification, two-factor challenge, profile, security incl. 2FA setup + passkeys, appearance, account deletion) on a new set of reusable Blade + Alpine.js primitives: `x-button`, `x-input`, `x-checkbox`, `x-modal`/`x-modal-trigger`/`x-modal-close`, `x-dropdown`, `x-menu-item`, `x-otp-input`, `x-badge`, `x-callout`, `x-icon`, `x-toast-container`. Replaced `Flux::toast()` calls in `Profile`/`Security` Livewire components with a `dispatch('toast', ...)` browser event consumed by the new toast container.

### Removed
- Unused `layouts/app/header.blade.php` app-shell variant (dead code — only the sidebar variant was wired up).
- `resources/views/flux/` published component stub overrides.

### Added — Sprint 0 (project scaffolding)
- Installed and wired up: `lorisleiva/laravel-actions`, `filament/filament` (admin panel at `/admin`, staff-only via `User::canAccessPanel()` gated on Spatie roles), `spatie/laravel-permission`, `spatie/laravel-activitylog`, `barryvdh/laravel-dompdf`, `pxlrbt/filament-excel`, `laravel/socialite`, `dedoc/scramble` (API docs at `/docs/api`, local-env only by default).
- `app/Enums/UserRole` (`super_admin`, `admin`, `store_keeper`) backing Filament panel access.
- Scaffolded `app/Actions/{Auth,Catalog,Inventory,Cart,Checkout,Payment,Order,Review}`, `app/Queries`, `app/Payments/{Contracts,Drivers}`, `app/Sms/{Contracts,Drivers}` per `AGENTS.md`'s architecture map.
- Customer authentication (BRD Section 4.0): phone + OTP is now the primary login method, Google login is secondary, email+password remains available as an opt-in method set up from an authenticated session.
  - `users` table gained `phone`, `phone_verified_at`, `google_id` (name/email/password now nullable) — edited directly in the original day-zero migration since this is an unshared local project with no prior deployment.
  - New `otp_codes` table + `OtpCode` model (hashed codes only, never plaintext; `isUsable()` encodes the not-expired/not-consumed/under-5-attempts rule).
  - `App\Sms\Contracts\SmsGateway` interface + `SmsManager` (Laravel Manager pattern) + `MoolreSms` driver, resolved via `config/sms.php` — no Action ever calls a vendor SDK directly.
  - `RequestOtp` (rate-limited: 1/60s, 5/hour per phone) and `VerifyOtp` (auto-registers on first successful verification, locks after 5 failed attempts) Actions.
  - `App\Livewire\Auth\PhoneLogin` component + `/login/phone` page, linked from the existing email/password login page.
  - `LoginWithGoogle` Action (auto-links on verified email match, else creates a new account) + `GoogleAuthController` (`/login/google`, `/login/google/callback`).
  - `SetPassword` and `LinkAccountIdentifier` Actions — the latter only ever invoked for an already-authenticated session (never inferred automatically from a login attempt), used when the Google callback fires for a signed-in user.
  - 20 new feature tests covering OTP happy path/rate limiting/expiry/lockout/replay, Google login/linking/repeat-login, and SetPassword/LinkAccountIdentifier (incl. rejecting a Google account already linked elsewhere).

### Fixed
- Latent "multiple root elements" bug in all four page layouts (`layouts/app/sidebar`, `layouts/auth/{simple,card,split}`): `<x-toast-container />` was a sibling of the main content `<div>` at the `<body>` level instead of nested inside it. Only surfaces when `APP_DEBUG=true` and a Livewire full-page component is used, which is why it went undetected until the phone-login page hit it — added `PagesRenderTest` (debug-mode smoke test across all guest pages) to catch this class of bug going forward.

### Added — Sprint 1 (Catalog Management, Epic E2)
- `Category`, `Brand`, `Product`, `ProductVariant`, `AttributeValue`, `ProductImage` models and migrations. Categories support nesting via self-referential `parent_id`; products carry `status` (`draft`/`active`/`archived`) and soft deletes; variants carry price (integer pesewas), stock, and their own `status`.
- `App\Concerns\HasUlid` (ULID as a secondary route-binding key, bigint `id` stays the real primary key) and `HasFormattedMoney` (`GH₵`-formatted money accessor) traits, applied to `ProductVariant`.
- Catalog Actions: `CreateProduct` (rejects a product with zero variants — BRD E2.3, wrapped in a DB transaction), `ArchiveProduct`, `DeleteProduct` / `DeleteProductVariant` (soft-delete + slug/SKU mutated to `{original}-deleted-{id}` so the original value is immediately reusable), `AttachProductImage`.
- Filament resources for `Category`, `Brand`, and `Product` (the latter with a `VariantsRelationManager` for editing variants post-creation, and a required `Repeater` on the create form so a product can never be saved without at least one variant, per BRD E2.3).
- `CategoryPolicy`, `BrandPolicy`, `ProductPolicy` (Super Admin/Admin/Store Keeper can view/create/update; delete/restore limited to Admin+; permanent delete limited to Super Admin), auto-discovered by Laravel's model↔policy naming convention.
- 7 new feature tests covering the variant-required guard, archive vs. delete semantics, repeated create→delete→recreate slug/SKU reuse, and ULID-based route binding.

### Fixed — Sprint 1 follow-up (transactional integrity)
- `DeleteProduct` and `DeleteProductVariant` now wrap their slug/SKU mutation and soft-delete in a single `DB::transaction()` — previously two separate writes, so a failure between them could leave a soft-deleted-looking row with its original slug/SKU still permanently blocking reuse. `CreateProduct` was already transactional (unchanged). `ArchiveProduct` is a single-column update and intentionally left unwrapped.
- Audited all Catalog Actions for external side effects (SMS/payment/notification/job dispatch) that a transaction rollback couldn't undo — none found.
- Added 3 rollback tests (`test_product_deletion_rolls_back_slug_mutation_on_failure`, `test_product_variant_deletion_rolls_back_sku_mutation_on_failure`, `test_failed_product_creation_rolls_back_the_product_and_its_variants`) proving no partial writes survive a mid-transaction failure.
- Recorded transaction/row-level-locking requirements for not-yet-built Inventory/Checkout/Payment Actions in `AGENTS.md` Section 4a (`ReserveStockForOrder` and `ApplyCouponToOrder` require `lockForUpdate()`; everything else in that phase needs a transaction only) so they're implemented correctly on the first pass rather than retrofitted.
- Added `docs/infrastructure-deployment.md` (database platform/durability settings, backup policy, scheduler/queue operational requirements, new-client deployment and pre-launch checklists) and a new "4g. ACID Guarantees & Where Integrity Actually Lives" section in `docs/technical-design-ecommerce.md` (lock-discipline rule for stock/coupon writes, the invariants enforced only by the Action layer, and the audit-log exemption for `payment_api_logs`/`webhook_events`/`activity_log`).
