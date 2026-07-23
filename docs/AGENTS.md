# AGENTS.md — Architecture Map & Conventions

This file orients any developer or AI coding agent working in this codebase. It describes **where things live** and **what to expect**, so the codebase can be navigated by convention rather than by searching or guessing. This is a reusable product deployed separately per business client — keep that in mind: nothing here should ever contain business-specific (client) data or branding.

**This file assumes and layers on top of the project's general Laravel coding standard (`CLAUDE.md`)** — strict types, Pint/PHPStan, testing requirements, queue/logging conventions, etc. `AGENTS.md` covers what's specific to *this* project's domain and architecture; `CLAUDE.md` covers what applies to any Laravel project. Two standards adopted from `CLAUDE.md` are called out explicitly in this file because they affect nearly every table: **money as integer minor units** (Section 4f of the technical design, Section 8 below) and **ULID as the external identifier standard** (same sections).

---

## 1. Core Principle

**Business logic lives in Actions. Nowhere else.**

Controllers, Livewire components, Filament resources, and API endpoints are all thin — they validate input, call one Action, and return/render the result. They never contain business rules themselves. If you're looking for "what happens when X occurs," look in `app/Actions/`, not in a controller or component.

---

## 2. Folder Structure

```
app/
  Actions/                      ← ALL state-changing business logic. One class = one operation.
    Auth/
      RequestOtp.php
      VerifyOtp.php
      LoginWithGoogle.php
      SetPassword.php
      LinkAccountIdentifier.php
    Catalog/
      CreateProduct.php
      AttachProductImage.php
      ArchiveProduct.php
      DeleteProduct.php
      DeleteProductVariant.php
    Inventory/
      ReserveStockForOrder.php
      ReleaseExpiredReservations.php
      RecordStockMovement.php
      AdjustStockWithReservationCheck.php
    Cart/
      AddItemToCart.php
      RemoveItemFromCart.php
    Checkout/
      CreateOrderFromCart.php
      ApplyCouponToOrder.php
    Payment/
      InitiatePayment.php
      HandlePaymentWebhook.php
      VerifyPaystackTransaction.php
      VerifyPendingPayments.php
      HandleLatePaymentConfirmation.php
      ProcessRefund.php
    Order/
      UpdateOrderStatus.php
      GenerateOrderInvoice.php
      ClaimGuestOrder.php
    Review/
      SubmitReview.php
      EditReview.php
      DeleteReview.php
      ModerateReview.php

  Payments/                     ← Payment provider abstraction. Actions call this, never a vendor SDK directly.
    Contracts/
      PaymentGateway.php        ← interface: initiate(), verify(), refund()
    Drivers/
      MoolreGateway.php
      PaystackGateway.php
    PaymentManager.php          ← resolves the correct driver from config/payments.php

  Sms/                          ← SMS provider abstraction. Same pattern as Payments.
    Contracts/
      SmsGateway.php            ← interface: send()
    Drivers/
      MoolreSms.php
    SmsManager.php

  Queries/                      ← Read-only logic. No side effects. Not an Action.
    ProductListingQuery.php
    CartTotalsQuery.php
    OrderHistoryQuery.php

  Models/                       ← Eloquent models. Relationships and casts only — no business logic here.

  Http/
    Controllers/                ← Thin. Validate → call Action → return response.
    Requests/                   ← Form Request validation classes (typed input contracts).

  Livewire/
    Storefront/                 ← Shared Livewire components used across clients (rare — most client UI is custom).

  Filament/
    Resources/                  ← Admin panel resources (shared across all deployments).

resources/
  views/
    clients/
      {client-slug}/            ← Fully custom Blade views per business client. Design only — no logic.

routes/
  web.php                       ← Storefront + admin routes
  api.php                       ← API routes (Sanctum-protected), thin wrappers calling the same Actions

tests/
  Feature/                      ← Named after business rules (see Section 6)
```

---

## 3. Naming Convention for Actions

`{Verb}{Noun}` — always describes exactly one operation, in plain business language:

| Business event | Action class |
|---|---|
| Reserving stock at checkout | `ReserveStockForOrder` |
| A payment webhook arrives | `HandlePaymentWebhook` |
| Issuing a refund | `ProcessRefund` |
| Releasing expired reservations | `ReleaseExpiredReservations` |
| Applying a coupon | `ApplyCouponToOrder` |

Each Action:
- Lives in `app/Actions/{Domain}/`
- Has a single `__invoke()` (or `handle()` via `lorisleiva/laravel-actions`) with **explicitly typed parameters** — no raw arrays or untyped `$data`
- Has a one-line PHPDoc stating the **business rule it enforces**, not a restatement of the code, e.g.:
  ```php
  /**
   * Reserves stock for an order at checkout start. Uses row-level locking
   * to prevent overselling when concurrent checkouts target the same variant.
   */
  ```
- Can be dispatched as a queued job, called from a controller, or called from a Livewire component — same class, no duplicated logic anywhere

---

## 4. Concept → Class Lookup

Use this table to jump straight to the relevant code instead of searching:

| Concept | Class | Location |
|---|---|---|
| Requesting a login OTP | `RequestOtp` (rate-limited, hashes code) | `app/Actions/Auth/` |
| Verifying a login OTP | `VerifyOtp` (auto-registers if new phone) | `app/Actions/Auth/` |
| Google login | `LoginWithGoogle` (links on verified email match, else creates new account) | `app/Actions/Auth/` |
| Customer opting into email+password | `SetPassword` (requires existing authenticated session) | `app/Actions/Auth/` |
| Adding a second login method to an existing account | `LinkAccountIdentifier` (requires existing authenticated session — never automatic) | `app/Actions/Auth/` |
| Product/variant creation | `CreateProduct` | `app/Actions/Catalog/` |
| Discontinuing a product (may return) | `ArchiveProduct` (sets `status: archived`, no slug/SKU change) | `app/Actions/Catalog/` |
| Removing a product permanently | `DeleteProduct` / `DeleteProductVariant` (soft-delete + slug/SKU mutation to `{original}-deleted-{id}`) | `app/Actions/Catalog/` |
| Attaching an image to a product or variant | `AttachProductImage` | `app/Actions/Catalog/` |
| Stock movement logging | `RecordStockMovement` | `app/Actions/Inventory/` |
| Stock reservation | `ReserveStockForOrder` | `app/Actions/Inventory/` |
| Reservation expiry cleanup | `ReleaseExpiredReservations` | `app/Actions/Inventory/` (scheduled job) |
| Manual stock correction conflicting with active reservations | `AdjustStockWithReservationCheck` (real-world count wins; flags affected reservations `at_risk` for Admin) | `app/Actions/Inventory/` |
| Cart operations | `AddItemToCart`, `RemoveItemFromCart` | `app/Actions/Cart/` |
| Order creation | `CreateOrderFromCart` | `app/Actions/Checkout/` |
| Coupon application | `ApplyCouponToOrder` | `app/Actions/Checkout/` |
| Payment initiation | `InitiatePayment` (calls `PaymentGateway::initiate()`) | `app/Actions/Payment/`, `app/Payments/` |
| Webhook processing | `HandlePaymentWebhook` | `app/Actions/Payment/` |
| Paystack server-side verification | `PaystackGateway::verify()` (via `PaymentGateway` contract) | `app/Payments/Drivers/` |
| Refunds | `ProcessRefund` (calls `PaymentGateway::refund()`) | `app/Actions/Payment/`, `app/Payments/` |
| Polling fallback for slow/missing webhooks | `VerifyPendingPayments` (scheduled job) | `app/Actions/Payment/` |
| Payment confirmed after reservation expired | `HandleLatePaymentConfirmation` | `app/Actions/Payment/` |
| SMS sending | any Action needing SMS calls `SmsGateway::send()` | `app/Sms/` |
| Adding/swapping a payment or SMS provider | new driver class implementing `PaymentGateway`/`SmsGateway` + config entry — no Action changes | `app/Payments/Drivers/`, `app/Sms/Drivers/`, `config/payments.php`, `config/sms.php` |
| Order status changes | `UpdateOrderStatus` | `app/Actions/Order/` |
| Guest linking a past order to their account | `ClaimGuestOrder` — requires authentication first, never automatic | `app/Actions/Order/` |
| Invoice/receipt PDF | `GenerateOrderInvoice` | `app/Actions/Order/` |
| Review submission | `SubmitReview` | `app/Actions/Review/` |

*(This table should be kept up to date as new Actions are added — treat it as the canonical index.)*

---

## 5. Multi-Client Deployment Rules

- `app/Actions/`, `app/Models/`, `app/Filament/` are **never client-specific**. If a client's request requires changing logic here, question whether it's actually a business rule change (rare, needs care) or should be a presentation-layer choice instead.
- `resources/views/clients/{slug}/` is the **only** place client-specific customization belongs — and only for the pages listed as **override-eligible** below. Do not create a client override for a **shared/locked** page; extend `store_settings`/theme toggles instead.
- Branding (logo, colors, business name) comes from the `store_settings` table — never hardcode a business's name/colors in a Blade file or Action.
- If a new business need can't be expressed as a `store_settings` toggle or a client-specific view, **stop and design it as a proper configurable feature** before writing client-specific logic — don't fork an Action.

### Page classification

**Shared/locked — same Blade views for every client, reskinned via `store_settings` only, never forked per client:**

| Page |
|---|
| Login / Register / Password reset |
| Cart |
| Checkout (address → shipping → coupon → payment) |
| Order confirmation / payment failed-retry |
| Account dashboard, order history, order detail/tracking, addresses, wishlist |
| Static pages (About/Contact/Terms/Privacy/Refund) — content varies via `static_pages`, template does not |
| 404/error page |

These are transactional/utility pages where consistency matters more than brand differentiation, and several (checkout especially) are correctness-critical — never let a client override introduce a second, untested implementation of stock reservation, idempotency, or payment logic.

**Override-eligible — genuine per-client custom Blade views live in `resources/views/clients/{slug}/`:**

| Page | Typical customization |
|---|---|
| Homepage | Hero banner, featured layout, brand personality |
| Product listing / category page | Grid vs. list (also partly covered by `store_settings` layout toggles) |
| Product detail page | Layout/emphasis variation |

If a client requests a redesign of a shared/locked page, treat it as a signal to add a new `store_settings` toggle (e.g. another layout option), not as grounds to create a one-off override.

---

## 6. Testing Convention

Feature tests are named after the business rule they guarantee, not the class under test — they serve as accurate, unhallucinatable documentation of system behavior:

```
test_stock_is_reserved_not_deducted_at_checkout()
test_reservation_expires_and_releases_stock_after_window()
test_duplicate_webhook_event_does_not_double_process_payment()
test_payment_creation_is_idempotent_per_order()
test_review_requires_verified_purchase()
test_coupon_rejects_when_usage_limit_exceeded()
```

When looking for "does the system guarantee X," search test names first.

**See `test-plan-ecommerce.md` for the full enumerated checklist of critical scenarios and edge cases** that must have a passing test before the corresponding feature is considered done — this is the canonical pre-launch QA checklist, kept up to date as new edge cases are decided.

---

## 7. API Documentation

- Generated via `dedoc/scramble` directly from routes, Form Requests, and return types — **never hand-written**, so it cannot drift from actual behavior.
- Regenerate as part of CI on every merge to main.
- If an endpoint's behavior isn't clear from its generated spec, that's a signal to improve the Form Request/return type — not to add a manual doc comment that can go stale.

---

## 8. What NOT to Do

- Do not put business logic in a Controller, Livewire component, or Filament resource — call an Action.
- Do not create a new Service class for something that changes state — use an Action.
- Do not hardcode any business-specific value (name, color, contact info) outside `store_settings`.
- Do not duplicate an Action's logic inside a client's custom view "just this once" — extend the Action's options instead.
- Do not hand-maintain API documentation — regenerate it.
- Do not call Moolre, Paystack, or any SMS vendor SDK directly from an Action. Always go through `PaymentGateway` / `SmsGateway` interfaces (`app/Payments/`, `app/Sms/`). Adding or swapping a provider must never require touching an Action.
- Do not hardcode the stock reservation window. Always read `store_settings.stock_reservation_minutes`.
- Do not process a webhook without first calling `verifyWebhookSignature()`. An unverified webhook is logged, never acted upon.
- Do not hard-delete a `Product` or `ProductVariant` that could have order history. Always use `DeleteProduct`/`DeleteProductVariant` (soft delete + slug/SKU mutation using the row's own ID). Never write a raw `->forceDelete()` or a manual DB delete on these tables.
- Do not read from live `Product`/`ProductVariant` data when rendering an existing order, receipt, or invoice. Always read from `OrderItem.item_snapshot` — it is the permanent, immutable source of truth for what was actually purchased.
- Do not auto-attach a guest order to a registered account based on email match. Only `ClaimGuestOrder`, triggered after the customer authenticates, may link them.
- Do not let an Admin edit a review's content. `ModerateReview` may only change `status`; only the author's own `EditReview` call may change rating/title/body, which always resets status to `pending`.
- Do not store an OTP code in plaintext. Only `code_hash` is persisted; the plaintext code exists only in memory long enough to send via `SmsGateway`.
- Do not auto-link two accounts based on an unverified or coincidental identifier match. Only link when a **verified** identifier (Google's verified email, an OTP-confirmed phone) matches an existing account's verified identifier, or when the customer explicitly links a second method while already authenticated (`LinkAccountIdentifier`).
- Do not store money as `decimal` or `float`. Every money field is an `integer` in minor units (pesewas). Never do money arithmetic on a floated/divided value — only convert to a display string at the very last step, via a formatted accessor.
- Do not expose a model's raw `id` (bigint) in any route, URL, or API response. Use the model's `ulid` for route-model-binding and external references — except Product/Category, which use `slug` instead, and Order, whose human-readable `order_number` is distinct from and doesn't replace its `ulid`.
