# Agile Delivery Document
## E-Commerce Platform — Epics, User Stories & Sprint Plan

**Version:** 1.0
**Companion to:** BRD-ecommerce-platform.md, technical-design-ecommerce.md
**Storefront architecture: finalized.** Blade + Livewire, within the same Laravel application.

**Deployment model: finalized.** This is a reusable product deployed as a **separate installation per business** (own database/environment), not a multi-tenant SaaS system. The core backend and Filament admin are identical across deployments; the storefront is reskinned per business via a Store Settings/branding layer (Epic E13) rather than code changes.

---

## 1. Epic Overview

| # | Epic | Priority |
|---|---|---|
| E1 | Foundation & Auth | Must-have |
| E2 | Catalog Management | Must-have |
| E3 | Inventory & Stock | Must-have |
| E4 | Cart & Checkout | Must-have |
| E5 | Payments (Moolre & Paystack) | Must-have |
| E6 | Orders & Fulfillment | Must-have |
| E7 | Reviews | Should-have |
| E8 | Coupons & Discounts | Should-have |
| E9 | Wishlist | Could-have |
| E10 | Notifications | Must-have |
| E11 | Admin Panel & Reporting | Must-have |
| E12 | Storefront (Blade + Livewire) | Must-have |
| E13 | Store Settings & Branding (multi-deployment reuse) | Must-have |

---

## 2. Epics & User Stories

### E1 — Foundation & Auth

**E1.1** As a Super Admin, I want to log into a secure admin panel, so that I can manage the store.
- AC: Filament panel login separate from customer auth
- AC: Failed login attempts are rate-limited

**E1.2** As a Super Admin, I want to create Admin and Store Keeper accounts and assign roles, so that staff have appropriate access.
- AC: Roles implemented via Spatie Permission
- AC: Store Keeper cannot access Orders/Payments resources
- AC: Admin cannot access Role management

**E1.3** As a customer, I want to log in using just my phone number, so that I don't need to remember a password.
- AC: Requesting an OTP is rate-limited (1 per 60s, 5 per hour per phone)
- AC: OTP is a 6-digit code, valid 10 minutes, single-use, never stored in plaintext
- AC: 5 failed verification attempts locks the code, requiring a fresh request
- AC: A new phone number automatically creates an account on first successful verification — no separate registration form

**E1.3a** As a customer, I want to log in with Google, so that I have a fast alternative if I prefer it.
- AC: First-time Google login links to an existing account if the verified email matches one; otherwise creates a new account
- AC: Google's verified email is trusted directly — no separate email verification step needed for this path

**E1.3b** As a customer, I want to optionally set up an email + password login from my account settings, so that I have a backup method if I want one.
- AC: Only available to an already-authenticated customer (via OTP or Google) — never required or offered at first registration

**E1.3c** As a customer, I want to add a second login method (Google, or email+password) to my existing account, so that I have flexibility without creating duplicate accounts.
- AC: Only possible while already authenticated — the system never auto-links a new method based on an unverified identifier match alone

**E1.4** As a customer, I want to check out without creating an account, so that I can buy quickly.
- AC: Guest orders capture email + phone
- AC: Guest can optionally convert to a registered account post-purchase
- AC: A guest order is never auto-attached to an existing account, even on exact email match — `user_id` stays null for guest orders regardless
- AC: A logged-in customer can claim a past guest order made under their email via `ClaimGuestOrder`, only after successfully authenticating

**E1.5** As a developer, I want the project scaffolded with the Actions pattern (`lorisleiva/laravel-actions`) and folder structure defined in `AGENTS.md`, so that all business logic has one clear, consistent home from day one.
- AC: `app/Actions/{Domain}/` structure created for all domains
- AC: `app/Queries/` established for read-only logic
- AC: Controllers/Livewire/Filament confirmed to contain no business logic beyond validation + Action calls

**E1.6** As a developer (or AI agent), I want an `AGENTS.md` architecture map at the project root, so that the codebase can be navigated by convention without guessing.
- AC: Includes folder structure, naming conventions, and a maintained concept-to-class lookup table
- AC: Kept up to date as new Actions are added (added as a PR checklist item)

**E1.7** As a developer, I want API documentation generated automatically via `dedoc/scramble`, so that documentation never drifts from actual endpoint behavior.
- AC: Spec regenerates on every merge to main via CI
- AC: No hand-written API documentation maintained in parallel

**E1.8** As a developer, I want money and identifier conventions established project-wide from the start, so that no model or migration is ever built the wrong way and needs retrofitting later.
- AC: Every money-bearing column across every migration is `integer` (minor units/pesewas), never `decimal`/`float`
- AC: A shared `HasFormattedMoney`-style trait/accessor pattern exists so every model with a money field exposes a formatted display value consistently, rather than each model reinventing it
- AC: A shared `HasUlid` trait exists and is applied to every model listed in the technical design (Address, Review, CartItem, WishlistItem, Order, Payment, Refund, Shipment, Coupon, ProductVariant) — auto-generates the ULID on creation, sets it as the route key
- AC: Product/Category continue using `slug` and Order continues exposing `order_number` alongside its `ulid` — neither is removed or replaced
- AC: A test proves no controller/API Resource in the initial scaffold exposes a raw bigint `id`

---

### E2 — Catalog Management

**E2.1** As an Admin, I want to create/edit/delete Categories (with subcategories), so that products are organized.

**E2.2** As an Admin, I want to create/edit/delete Brands, so that products can be attributed to a brand.

**E2.3** As an Admin, I want to create a Product with multiple variants (size/color/etc.), each with its own SKU, price, and stock, so that customers can choose options.
- AC: A product cannot be saved without at least one variant

**E2.4** As an Admin, I want to upload multiple images per product/variant with a defined order and a primary image, so that the catalog looks complete.

**E2.5** As an Admin, I want to set SEO meta title/description and slug per product, so that product pages are discoverable.

**E2.5a** As an Admin, I want to archive a product (stop selling, may return later) without deleting it, so that its slug/SKU and data stay untouched for a possible relaunch.

**E2.5b** As an Admin, I want to permanently delete a product/variant, so that it's fully removed from the active catalog.
- AC: Deletion is a soft delete (`deleted_at`), never a hard delete
- AC: Slug/SKU is mutated to `{original}-deleted-{id}` on delete, immediately freeing the original value for reuse
- AC: Repeated create → delete → recreate → delete cycles for the same name/SKU never produce a uniqueness conflict
- AC: Deleting a product/variant does not alter any existing order's displayed data

**E2.5c** As the system, I want every `OrderItem` to store a complete, permanent snapshot (name, brand, SKU, variant attributes, image) at the moment of purchase, so that editing, archiving, or deleting a product never changes how any past order displays.
- AC: Order history, receipts, and invoices are rendered entirely from `item_snapshot`, never from live `Product`/`ProductVariant` data

**E2.6** As a customer, I want to search and filter products by category, brand, and price, so that I can find what I need.

---

### E3 — Inventory & Stock

**E3.1** As a Store Keeper, I want to record stock movements (restock, adjustment, damage, return), so that stock levels stay accurate.
- AC: Every movement is attributed to the acting user
- AC: Variant's cached stock total updates automatically

**E3.1a** As a Store Keeper, when my physical stock count would make available stock less than what's already held in active reservations, I want the system to let my count proceed and flag the affected reservations for Admin review, so that the correction isn't blocked but nothing is silently left unresolved.
- AC: Adjustment always proceeds — the physical count is authoritative
- AC: Reservations that can no longer be covered are marked `at_risk`
- AC: Admin receives a notification listing the affected reservations/orders

**E3.2** As a Super Admin, I want the checkout stock reservation window to be configurable from Store Settings, so that I can tune it without a code deploy.
- AC: `stock_reservation_minutes` editable via Filament Settings page, default 15
- AC: Change takes effect for new reservations immediately, no deploy required

**E3.3** As the system, I want to reserve stock automatically when an Order is created, so that other customers can't purchase the same reserved units.
- AC: Reservation uses a DB transaction with row locking to prevent race conditions
- AC: Reservation expiry reads from `store_settings.stock_reservation_minutes`, not a hardcoded value

**E3.4** As the system, I want to release expired reservations automatically via a scheduled job, so that abandoned checkouts don't lock stock indefinitely.
- AC: Job runs every 1–5 minutes
- AC: Released reservations return quantity to available stock

**E3.5** As the system, I want to convert a reservation into a permanent stock movement (sale) on payment success, so that stock is deducted only once payment is confirmed.

**E3.5** As a Store Keeper, I want to receive a low-stock notification, so that I can reorder in time.

---

### E4 — Cart & Checkout

**E4.1** As a customer, I want to add/remove product variants to a cart, so that I can collect items before purchasing.
- AC: Cart works for both logged-in (user-linked) and guest (session-based) states
- AC: Adding to cart does not affect stock

**E4.2** As a customer, I want to proceed to checkout and enter shipping details, so that I can complete my purchase.
- AC: Triggers Order creation and stock reservation
- AC: Duplicate checkout submissions (double-click/back button) do not create duplicate Orders
- AC: `OrderItem.unit_price` is set from the variant's current price at Order creation time, not the price when the item was added to cart

**E4.3** As a customer, I want to apply a coupon code at checkout, so that I get my discount.
- AC: Validates coupon scope (product/category/cart-wide), usage limits, expiry, min order amount

---

### E5 — Payments (Moolre & Paystack)

**E5.0** As a developer, I want payment providers integrated behind a `PaymentGateway` interface with a driver per provider, so that adding or swapping a provider later never requires touching checkout/order/refund logic.
- AC: `PaymentGateway` contract defines `initiate()`, `verify()`, `refund()`
- AC: `MoolreGateway` and `PaystackGateway` both implement the contract
- AC: Provider selection/config lives in `config/payments.php`; no provider name is hardcoded in any Action
- AC: A third dummy/test driver can be registered and resolved correctly without modifying any Action, proving the abstraction holds

**E5.1** As the system, I want to initiate a payment request to Moolre or Paystack when checkout is confirmed, so that the customer can pay.
- AC: Every outbound API call and response is logged to `payment_api_logs`
- AC: A `Payment` record is created with `status: pending`
- AC: Initiation goes through `PaymentGateway::initiate()`, never a vendor SDK call directly in the Action

**E5.2** As the system, I want to receive and verify webhook notifications from Moolre/Paystack, so that payment status updates reliably.
- AC: Every webhook's signature is verified (`PaymentGateway::verifyWebhookSignature()`) before any processing; unverified requests are rejected and logged with `verified: false`, never acted upon
- AC: Duplicate webhook deliveries are rejected via unique `event_id`
- AC: Handler checks `processed_at` before executing side effects
- AC: Paystack payments are additionally verified server-side via their verify endpoint, not trusted from client redirect alone

**E5.2a** As the system, I want to actively poll the payment provider for status on any payment still pending beyond a short grace period, so that a delayed or dropped webhook doesn't leave an order stuck.
- AC: `VerifyPendingPayments` scheduled job runs every ~2 minutes
- AC: Polling result and webhook result both funnel through the same idempotent status-update logic — never double-processed regardless of which arrives first

**E5.2b** As the system, when a payment is confirmed successful after its stock reservation has already expired, I want to re-fulfill the order if stock is still available, or automatically refund and cancel it if not, so that no order is left in a broken state.
- AC: `HandleLatePaymentConfirmation` checks current availability before deciding
- AC: Re-fulfillment creates a direct `StockMovement` (sale), bypassing the expired reservation
- AC: Auto-refund path creates a `Refund` record and cancels the order with a clear reason
- AC: Either outcome is logged to `OrderStatusHistory` and visible to Admin
- AC: Customer is notified in either case (fulfilled vs. refunded)

**E5.3** As the system, on payment success, I want to update Order status, convert stock reservation to sale, and trigger notifications, so that the order fully processes.

**E5.4** As an Admin, I want to issue a refund against a payment, so that customers can be reimbursed.
- AC: Refund creates a stock movement (return) restoring quantity
- AC: Partial refunds are supported

**E5.5** As an Admin, I want to see a full API/webhook trace for a given order's payment activity, so that I can debug payment issues.

---

### E6 — Orders & Fulfillment

**E6.1** As the system, I want to generate a unique customer-facing order number on Order creation, so that customers and support can reference it easily.

**E6.2** As an Admin, I want to update order status (processing, shipped, delivered, cancelled), so that fulfillment is tracked.
- AC: Every change logs to `OrderStatusHistory` with actor and timestamp

**E6.3** As a customer, I want to view my order status and history, so that I know where my order is.

**E6.4** As the system, I want to generate a PDF receipt automatically on payment success, so that customers have proof of purchase.

**E6.5** As an Admin, I want to assign a shipping method and tracking number to an order, so that shipment can be tracked.

---

### E7 — Reviews

**E7.1** As a customer, I want to leave a rating and review for a product I've purchased, so that I can share my experience.
- AC: Review is only allowed if linked to a verified `order_item`

**E7.1a** As a customer, I want to edit my own review's rating, title, or body, so that I can correct or update my feedback.
- AC: Editing always resets `status` to `pending`, requiring re-moderation before the update is publicly visible again

**E7.1b** As a customer, I want to delete my own review at any time, so that I control my own content.

**E7.2** As an Admin, I want to moderate reviews (approve/reject) before they're publicly visible.
- AC: Admin may delete a review (moderation action) but can never edit its rating/title/body — only the author's own words are ever displayed under their name

---

### E8 — Coupons & Discounts

**E8.1** As an Admin, I want to create coupons (percentage, fixed, free shipping) with scope (cart-wide, product, or category), usage limits, and expiry, so that I can run promotions.

**E8.2** As the system, I want to validate coupon eligibility at checkout (limits, expiry, min order), so that invalid coupons are rejected.
- AC: Usage limit validation counts actual `coupon_usages` rows, not a cached counter
- AC: Validation and usage recording happen inside the same DB transaction with a row lock on the `Coupon`, preventing two concurrent checkouts from both consuming the last available use
- AC: A concurrency test proves two simultaneous requests against a `usage_limit: 1` coupon result in exactly one success and one rejection

---

### E9 — Wishlist

**E9.1** As a customer, I want to save product variants to a wishlist, so that I can purchase them later.

---

### E10 — Notifications

**E10.0** As a developer, I want SMS providers integrated behind an `SmsGateway` interface with a driver per provider, so that adding or swapping an SMS provider later never requires touching notification logic.
- AC: `SmsGateway` contract defines `send()`
- AC: `MoolreSms` implements the contract
- AC: Provider selection/config lives in `config/sms.php`; no provider name hardcoded in any Action

**E10.1** As the system, I want to send email + SMS notifications for order placed, payment success/failure, and order shipped, so that customers stay informed.
- AC: SMS sending goes through `SmsGateway::send()`, never a vendor SDK directly

**E10.2** As a Store Keeper, I want to receive low-stock alerts, so that I can act on them.

**E10.3** As an Admin, I want to see notification delivery status, so that I can confirm customers were reached.

---

### E11 — Admin Panel & Reporting

**E11.1** As an Admin/Super Admin, I want a dashboard showing today's sales, pending orders, low-stock items, top products, monthly revenue, and new customers, so that I can monitor the business at a glance.

**E11.2** As an Admin, I want to export orders/products to CSV/Excel, so that I can use the data outside the system.

**E11.3** As an Admin, I want to perform bulk actions (stock adjustment, order status update, price update), so that I can manage records efficiently.

**E11.4** As a Super Admin, I want to view an activity log of staff actions, so that I can audit changes.

---

### E12 — Storefront (Blade + Livewire)

**Full page inventory for this epic:**

| Page | Story | Classification |
|---|---|---|
| Homepage | E12.1 | Override-eligible (per-client custom view) |
| Product listing / category / search results | E12.2 | Override-eligible |
| Product detail | E12.3 | Override-eligible |
| Cart | E12.4 | Shared/locked |
| Checkout (address → shipping → coupon → payment) | E12.5 | Shared/locked — correctness-critical, never fork |
| Order confirmation / success | E12.5a | Shared/locked |
| Payment failed / retry | E12.5b | Shared/locked |
| Login (phone OTP primary, Google secondary, email+password optional) | E1.3, E1.3a, E1.3b | Shared/locked |
| Account dashboard | E12.6 | Shared/locked |
| Order history | E12.6 | Shared/locked |
| Order detail / tracking | E12.6, E6.3 | Shared/locked |
| Addresses | E12.6 | Shared/locked |
| Wishlist | E12.6, E9.1 | Shared/locked |
| Static pages (About, Contact, Terms, Privacy, Refund Policy) | E12.8 | Shared/locked (content varies via `static_pages`, template doesn't) |
| 404 / error page | E12.9 | Shared/locked |

> Shared/locked pages are reskinned per client via `store_settings` only (colors, logo, layout toggles) — never forked into a client-specific view. Override-eligible pages are where genuine bespoke per-client design work happens, in `resources/views/clients/{slug}/`. See `AGENTS.md` Section 5 for the full rule and rationale.

**E12.1** As a customer, I want a homepage showcasing featured/new products and categories, so that I can start browsing.

**E12.2** As a customer, I want a product listing page with search and filters (category, brand, price), so that I can find products.
- AC: Livewire component with reactive filtering, no full page reload

**E12.3** As a customer, I want a product detail page showing images, variants, price, stock status, and reviews, so that I can decide to buy.
- AC: Variant selector updates price/image/stock reactively (Livewire)

**E12.4** As a customer, I want a reactive cart (add/update/remove items) without full page reloads, so that shopping feels smooth.

**E12.5** As a customer, I want a multi-step checkout flow (address, shipping method, coupon, payment) built with Livewire, so that I can complete my purchase.

**E12.5a** As a customer, I want a clear order confirmation page after successful payment, so that I know my purchase went through.

**E12.5b** As a customer, I want a clear payment-failed page with the option to retry or choose a different payment method, so that a failed payment isn't a dead end.

**E12.6** As a customer, I want an account area (dashboard, order history, order detail/tracking, addresses, wishlist) so that I can manage my account.

**E12.7** As a customer, I want the storefront to be responsive on mobile, so that I can shop from my phone.
- AC: Tested on common mobile viewport widths

**E12.8** As a customer, I want to view informational pages (About, Contact, Terms & Conditions, Privacy Policy, Refund Policy), so that I can learn about the business and its policies.
- AC: Content is admin-editable via a Filament `static_pages` resource, rendered through one shared Blade template — no per-client custom views needed for these pages
- AC: A new business deployment ships with placeholder content that the Admin fills in during setup

**E12.9** As a customer, I want a clear 404/error page, so that broken or invalid links don't look like the site is broken.

---

### E13 — Store Settings & Branding (Multi-Deployment Reuse)

**E13.1** As a Super Admin, I want to set business name, logo, brand colors, tagline, and contact details in a Store Settings area, so that the storefront reflects this specific business.

**E13.2** As a developer, I want all Blade layouts and Livewire components to read branding from Store Settings rather than hardcoded values, so that deploying for a new business requires no code changes.
- AC: Colors driven by CSS variables sourced from `store_settings`
- AC: Logo, business name, and contact info render dynamically across all storefront views and PDF receipts

**E13.3** As a developer, I want clear deployment documentation (env setup, DB migration, store settings seeding) for spinning up a new business installation, so that onboarding a new client is fast and repeatable.
- AC: A documented checklist/script exists for: provisioning DB, running migrations/seeders, setting Moolre/Paystack keys, setting store branding, creating the first Super Admin account

**E13.4** As a Super Admin, I want default/sample data seeders (categories, shipping methods, tax config) so that a fresh deployment isn't a completely blank slate.

---

## 3. Suggested Sprint Sequencing (2-week sprints, indicative)

| Sprint | Focus | Epics |
|---|---|---|
| Sprint 0 | Project setup, CI/CD, Laravel + Filament + Livewire install, phone OTP + Google + optional email/password auth, Actions architecture + AGENTS.md, money/ULID conventions established, API doc generation | E1 |
| Sprint 1 | Catalog data model + Filament resources + storefront homepage/listing/detail pages | E2, E12.1–E12.3 |
| Sprint 2 | Inventory: stock movements, reservations, scheduled release job | E3 |
| Sprint 3 | Cart & checkout: Livewire cart + multi-step checkout flow | E4, E12.4, E12.5 |
| Sprint 4 | Payment integration: Moolre + Paystack, webhooks, API logging | E5 |
| Sprint 5 | Orders, order numbers, status history, PDF receipts, shipping, order tracking page | E6, E12.6 |
| Sprint 6 | Notifications (email/SMS) | E10 |
| Sprint 7 | Coupons, Reviews, Wishlist (backend + Livewire UI) | E7, E8, E9 |
| Sprint 8 | Admin dashboard, exports, bulk actions, activity log | E11 |
| Sprint 9 | Store Settings/branding layer, static pages CMS, deployment docs/scripts, seeders | E13, E12.8 |
| Sprint 10 | Responsive/mobile polish, QA hardening, first-client deployment | E12.7 |

> With the storefront architecture finalized as Blade + Livewire in the same app, frontend and backend for each feature area are built together within the same sprint rather than as a separate later phase — reducing integration risk and giving a demoable feature end-to-end each sprint.

---

## 4. Definition of Done (applies to all stories)

- Code reviewed and merged to main branch
- Relevant migration(s) written and tested
- Any new money-bearing column is `integer` (minor units), never `decimal`/`float`
- Any new externally-referenceable model includes a `ulid` column (or uses `slug`/`order_number` per the established exceptions) and never exposes its raw `id`
- State-changing logic implemented as an Action in `app/Actions/{Domain}/`, not in a controller/Livewire/Filament class
- New Actions added to the concept-to-class lookup table in `AGENTS.md`
- Filament resource (if applicable) reflects role-based access correctly
- Automated feature test added, named after the business rule it guarantees (see `AGENTS.md` Section 6 and `test-plan-ecommerce.md`)
- Any scenario in `test-plan-ecommerce.md` relevant to this story has a passing test before merge
- Automated test coverage for critical logic (stock reservation, payment webhook handling, idempotency checks)
- API endpoints (if applicable) reflected correctly in the auto-generated Scramble docs — no manual API doc edits
- Manually verified against acceptance criteria
- No regression in existing functionality

---

*End of document.*
