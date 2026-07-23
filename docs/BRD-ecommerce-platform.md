# Business Requirements Document (BRD)
## E-Commerce Platform

**Version:** 1.0
**Status:** Draft
**Prepared for:** Internal build (Laravel + Filament)
**Payment Partners:** Moolre, Paystack

---

## 1. Document Purpose

This BRD defines the business requirements for a custom-built e-commerce platform. It captures the scope, functional requirements, roles, business rules, and constraints agreed upon during discovery, and serves as the foundation for downstream technical design, sprint planning, and agile delivery documentation (to be produced separately).

---

## 2. Business Overview

### 2.1 Business Context
A single-location retail business requires a custom e-commerce platform to sell products online, manage inventory accurately, process payments through local Ghanaian payment rails (Moolre for mobile money, Paystack for cards/bank transfers), and support a small internal staff structure with differentiated access levels.

### 2.2 Business Objectives
- Enable direct-to-consumer online sales with a reliable checkout experience
- Prevent overselling through accurate, real-time inventory control
- Support local payment methods relevant to the Ghanaian market
- Provide staff with role-appropriate tools to manage catalog, inventory, and orders
- Build trust with customers through verified reviews and transparent order tracking
- Maintain an auditable trail of stock and administrative actions

### 2.3 Deployment & Reuse Model
This system is designed as a **reusable product**, not a one-off single-business build or a multi-tenant SaaS platform. Each business receives its own fully separate installation (own database, own deployment, own domain) of the same core Laravel + Filament codebase. The backend, business logic, and admin panel remain structurally identical across all deployments; the customer-facing storefront (Blade + Livewire) is **themeable** per business — branding (logo, colors, business name, contact info) is configured through a Store Settings layer rather than requiring code changes per client. No shared database or tenant-scoping logic is required, since each business's data is isolated by being a wholly separate installation.

### 2.4 Out of Scope (v1)
- Multiple warehouses / locations
- Multi-currency support
- Product bundles, kits, or subscriptions
- Gift cards
- Pre-purchase product Q&A
- Advanced search (Scout/Meilisearch/Algolia) — basic database search only for v1
- (Storefront architecture has been finalized — see Section 7)

---

## 3. Stakeholders & User Roles

| Role | Description | Access |
|---|---|---|
| **Super Admin** | Full system owner | Full access, including managing other admins and roles |
| **Admin** | Day-to-day store operations | Products, orders, payments, coupons — no role/permission management |
| **Store Keeper** | Inventory-focused staff | Inventory, stock levels, product variants — no access to orders or payments |
| **Customer (registered)** | Shoppers with an account | Storefront only — no access to admin panel |
| **Customer (guest)** | Shoppers without an account | Can browse and complete checkout without registering |

**Access control:** Role and permission management implemented via Spatie Laravel Permission, surfaced in the Filament admin panel (e.g. via Filament Shield). Customers never have access to the Filament admin panel under any role.

---

## 4. Functional Requirements

### 4.0 Authentication & Login (Customer)
- FR-0.1: The system shall support **phone number + OTP (SMS)** as the primary customer login method, requiring no password. A 6-digit code is sent via SMS, valid for 10 minutes, single-use.
- FR-0.2: Requesting a login OTP shall be rate-limited (max 1 per phone per 60 seconds, max 5 per hour) to control SMS cost and prevent abuse.
- FR-0.3: OTP verification shall be locked after 5 failed attempts on a given code, requiring a fresh OTP request.
- FR-0.4: The system shall support **Google login** as a secondary method. A first-time Google login shall link to an existing account if the returned (verified) email matches one, or create a new account otherwise.
- FR-0.5: The system shall support **email + password** as an optional method, set up by an already-authenticated customer from their account settings — never required at registration.
- FR-0.6: Registration and login via phone + OTP shall be the same flow — a new phone number automatically creates an account on first successful verification; no separate registration form is required for this path.
- FR-0.7: The system shall never automatically merge or link two accounts based on an unverified or coincidental identifier match. Adding a second login method to an existing account shall only be possible while the customer is already authenticated.
- FR-0.8: OTP codes shall never be stored in plaintext.
- FR-0.9: Staff (Super Admin, Admin, Store Keeper) authentication via the Filament admin panel remains email + password only and is unaffected by customer authentication methods.

---

### 4.1 Product Catalog
- FR-1.1: The system shall allow staff to organize products under **Categories**, which support nested subcategories.
- FR-1.2: The system shall allow products to be associated with a **Brand**.
- FR-1.3: The system shall support **product variants** (e.g. size, color), each with independent price, SKU, and stock.
- FR-1.4: The system shall support **multiple images per product/variant**, with a defined display order and a designated primary image.
- FR-1.5: The system shall support SEO metadata (meta title, meta description, slug) per product.
- FR-1.6: The system shall support basic keyword search and filtering (by category, brand, price) using standard database queries for v1.

### 4.2 Inventory Management
- FR-2.1: The system shall track stock at the **variant** level.
- FR-2.2: All stock changes shall be recorded as discrete **Stock Movements** (sale, restock, adjustment, return, damage), each attributable to the staff member who made the change.
- FR-2.2a: If a manual stock adjustment (e.g. following a physical count) would reduce available stock below what is already committed to active reservations, the adjustment shall still be permitted (the physical count is authoritative), and the affected reservations shall be flagged for Admin review rather than silently left unresolved.
- FR-2.3: The variant's displayed stock quantity shall be a running total derived from its stock movement history.
- FR-2.4: The system shall support a single physical location; multi-warehouse stock is not required.
- FR-2.5: The system shall **reserve stock** for a limited time window when a customer begins checkout (Order creation), preventing other customers from purchasing the same reserved units. The window is **admin-configurable** (Store Settings), default 15 minutes.
- FR-2.6: If payment is not completed within the reservation window, the system shall automatically release the reserved stock back to availability via a scheduled background process.
- FR-2.7: On successful payment, the system shall convert the stock reservation into a permanent stock movement (sale).
- FR-2.8: Stock availability checks and reservation creation shall be handled atomically to prevent overselling when multiple customers attempt to purchase the same limited-stock item simultaneously.

### 4.3 Shopping Cart & Checkout
- FR-3.1: The system shall allow customers to add product variants to a cart without affecting stock availability.
- FR-3.2: The system shall support both authenticated and **guest checkout**, capturing guest contact details (email, phone) for order communication.
- FR-3.2a: A guest order shall **never be automatically attached** to an existing registered account, even if the guest's email matches one — regardless of email match, the order is stored against `guest_email` only. A guest may only link a past guest order to their account by successfully logging into the matching account and explicitly opting to claim it.
- FR-3.3: The cart shall support both logged-in (user-linked) and guest (session-based) states.

### 4.4 Orders
- FR-4.1: The system shall create an **Order** when checkout is initiated, capturing a snapshot of product name, variant, and price at the time of order (independent of later catalog changes).
- FR-4.2: The system shall maintain a full **status history** for each order (e.g. pending, paid, processing, shipped, delivered, cancelled), including timestamp and the actor (staff or system) responsible for each change.
- FR-4.3: The system shall provide customers with an order tracking view showing current status and history.
- FR-4.4: The system shall generate a **PDF receipt/invoice** automatically upon successful payment.

### 4.5 Payments
- FR-5.1: The system shall integrate with **Moolre** (mobile money) and **Paystack** (cards, bank transfer) as payment providers, built behind a common payment gateway interface so additional or alternate providers can be added later without modifying checkout, order, or refund logic.
- FR-5.2: The system shall record each payment attempt, including provider, provider reference, status, and payment channel.
- FR-5.3: The system shall confirm payment success via provider **webhooks**, not solely via client-side redirect, and shall log all incoming webhook events for auditability and idempotent processing. As a reliability fallback, the system shall also **actively poll** the provider's verification endpoint for any payment still pending beyond a short grace period, in case a webhook is delayed or never delivered.
- FR-5.3a: If a payment is confirmed successful after its associated stock reservation has already expired and been released, the system shall attempt to re-fulfill the order if stock is still available; if not, it shall automatically issue a refund and cancel the order, notifying the customer in either case.
- FR-5.3b: The system shall **verify the authenticity of every incoming webhook** (signature/HMAC check against the provider's secret) before processing it. Unverified or improperly signed webhook payloads shall be rejected and logged, never acted upon.
- FR-5.4: The system shall support **refunds**, linked to the original payment, with provider refund reference and reason captured.
- FR-5.5: A refund shall trigger a stock movement restoring the returned quantity to available inventory.

### 4.6 Coupons & Discounts
- FR-6.1: The system shall support percentage-based, fixed-amount, and free-shipping coupon types.
- FR-6.2: Coupons shall support cart-wide application, or restriction to specific products or categories.
- FR-6.3: Coupons shall support minimum order value, total usage limits, per-customer usage limits, and expiry dates.
- FR-6.4: Coupon usage limits shall be enforced against actual recorded usage (not a cached counter) with database-level locking, so that two simultaneous checkouts cannot both consume the last available use of a limited coupon.

### 4.7 Reviews
- FR-7.1: The system shall allow customers to submit a rating and written review for a product.
- FR-7.2: Reviews shall only be permitted from customers with a **verified purchase** of the reviewed product.
- FR-7.3: Reviews shall be subject to a moderation status (pending, approved, rejected) before public display.
- FR-7.4: A customer shall be able to edit their own review's rating, title, or body at any time; any such edit resets the review to pending status for re-moderation.
- FR-7.5: A customer shall be able to delete their own review at any time. Admins may delete a review as a moderation action but shall never edit its content.

### 4.8 Wishlist
- FR-8.1: The system shall allow registered customers to save product variants to a personal wishlist for later purchase.

### 4.9 Notifications
- FR-9.1: The system shall send transactional notifications via **email and SMS**, including: order placed, payment success/failure, order shipped, and low-stock alerts to relevant staff.
- FR-9.2: SMS delivery shall use **Moolre's native SMS API** initially, built behind a common SMS gateway interface so additional or alternate SMS providers can be added later without modifying notification logic.

### 4.10 Admin & Operations
- FR-10.1: The system shall provide an admin panel (Filament) with role-appropriate views and actions for Super Admin, Admin, and Store Keeper.
- FR-10.2: The system shall log all significant administrative actions (create/update/delete on key records) with the responsible staff member, for audit purposes.
- FR-10.3: The system shall support bulk actions on key records (e.g. bulk stock adjustment, bulk order status update, bulk price update).
- FR-10.4: The system shall support exporting orders and products to CSV/Excel.
- FR-10.5: The admin panel shall provide a dashboard summarizing: today's sales, pending order count, low-stock items, top products for the current month, total monthly revenue, new customers this period, and a recent orders list.

### 4.11 Shipping
- FR-11.1: The system shall support configurable shipping methods and associate a shipment record (with tracking information where available) to each order.

### 4.12 Tax
- FR-12.1: The system shall apply a flat-rate tax/VAT to orders as a configuration value; per-category or per-region tax variation is not required for v1.

### 4.13 Store Settings & Branding
- FR-13.1: The system shall provide a centralized Store Settings area (admin-managed) for business name, logo, brand colors, tagline, contact details, and currency.
- FR-13.2: The storefront (Blade + Livewire) shall render branding dynamically from Store Settings, so that a new business deployment can be rebranded without code changes.
- FR-13.3: The core backend, business logic, and admin panel structure shall remain consistent across all business deployments, to minimize maintenance overhead across multiple installations.
- FR-13.4: The system shall provide admin-editable static informational pages (About, Contact, Terms & Conditions, Privacy Policy, Refund Policy) rendered through a shared template, so that a new business deployment gets working content pages without a developer writing new views.

---

## 5. Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Reliability** | Stock reservation release must run via a dependable scheduled job; failure to run must not permanently lock stock |
| **Data integrity** | Stock availability checks and reservations must be handled with database-level locking to prevent race conditions on concurrent checkouts |
| **Auditability** | All stock changes and admin actions must be traceable to a responsible user and timestamp |
| **Security** | Card/payment data is never handled directly by the platform; all sensitive payment processing is delegated to Moolre/Paystack APIs. Every incoming payment webhook is signature-verified before processing; unverified requests are rejected and logged, never acted upon. OTP codes are never stored in plaintext and are rate-limited per phone number to prevent brute-force guessing and SMS-cost abuse. |
| **Usability** | Admin panel must present role-scoped views — staff should only see functionality relevant to their role |
| **Localization** | Currency displayed in GHS; SMS/email content appropriate for the Ghanaian market |
| **Maintainability** | Standard, well-supported Laravel packages preferred over custom-built infrastructure where equivalent functionality exists (e.g. Spatie packages, Filament plugins) |
| **Extensibility** | Payment and SMS providers must be swappable or addable without modifying business logic — integrated behind interfaces (`PaymentGateway`, `SmsGateway`) and resolved via configuration, so adding a new provider is a driver class + config entry, not a rewrite |
| **Configurability** | Operationally significant values (e.g. stock reservation expiry window) must be admin-configurable at runtime via Store Settings, not hardcoded, so behavior can be tuned per deployment without a code deploy |
| **Financial data integrity** | All money values are stored as integers in minor units (pesewas), never `decimal`/`float`, to eliminate floating-point rounding risk in arithmetic and to align directly with Moolre/Paystack's native minor-unit APIs |
| **Identifier security** | No internal sequential database ID is ever exposed in a route, URL, or API response. Every externally-referenceable record uses a ULID (or, where applicable, a `slug`/`order_number`) instead, preventing ID enumeration and information leakage about record volume |

---

## 6. Business Rules Summary

1. Stock is only permanently deducted after **payment success is confirmed via webhook**.
2. Stock is **reserved**, not deducted, from the moment a customer starts checkout, and released automatically if payment is not completed within the configured window.
3. Orders always reference a specific **product variant**, never a bare product.
4. Reviews require **proof of purchase** (linked order/order item).
5. Guest customers may complete a full purchase without an account, but must provide contact details for order communication.
6. Only Super Admin may manage staff roles and permissions.
7. Store Keeper access is limited to inventory and catalog stock; order and payment data are out of scope for this role.
8. **The cart never locks in a price.** A customer is always charged the product's price at the moment they complete checkout (Order creation), not the price at the time an item was added to their cart. Once an Order is created, its item prices are permanently fixed regardless of later catalog price changes.
9. Every incoming payment webhook must pass signature verification before being processed; unverified requests are rejected and logged.
10. Coupon usage limits are enforced against actual recorded usage under a database lock, not a cached counter, to prevent two simultaneous checkouts from both consuming the last available use.
11. A payment confirmed successful after its stock reservation has already expired triggers re-fulfillment if stock is still available, or an automatic refund and order cancellation if not.
12. Editing, archiving, or deleting a product never changes how any past order displays — every order item stores its own permanent, complete snapshot (name, price, image, attributes) captured at the moment of purchase.
13. "Archive" (stop selling, may return) and "Delete" (permanently remove) are distinct actions. Deleting a product soft-deletes it and frees its slug/SKU for reuse by mutating it with the record's own unique database ID — safe to repeat indefinitely with no risk of naming collisions.
14. A guest order is never auto-attached to a registered account based on email match alone. Linking a guest order to an account requires the customer to actually log in and explicitly opt to claim it.
15. A physical stock count always takes priority over the system's recorded stock ledger. If a correction would leave active reservations uncovered, the correction still proceeds, and the affected reservations are flagged for Admin review rather than blocking the correction or silently ignoring the shortfall.
16. Customer login never requires a password by default — phone + OTP is the primary path. Email + password exists only as an opt-in addition a customer sets up themselves.
17. Two accounts are never automatically merged or linked unless a verified identifier (an OTP-confirmed phone, Google's verified email) matches between them. Adding a new login method to an existing account requires the customer to already be authenticated.

---

## 7. Assumptions & Constraints

- Single physical business location; no near-term plans for multi-location expansion.
- Business currently operates only in Ghana; Moolre and Paystack cover required payment channels.
- Development will use Laravel with the Filament admin panel.
- Customer authentication is phone + OTP (primary), Google login (secondary), and optional email + password (opt-in) — see Section 4.0. Staff/admin authentication remains standard email + password via Filament, unaffected.
- Storefront will be built within the same Laravel application using Blade + Livewire (not a separate decoupled frontend). This gives a single codebase/deploy, session-based auth shared with the customer-facing side, and server-rendered pages for SEO. Chosen over a separate frontend (e.g. Next.js + API) since no current requirement (mobile app, highly custom SPA UX) justifies the added complexity of maintaining two codebases, an API contract, and token-based auth. Can be extended with a Sanctum API later without a rewrite if needs change.

---

## 8. Data Model Summary (Reference)

The following entities were identified as necessary to support the requirements above. Full technical schema (fields, types, migrations) to be defined in technical design documentation.

| Domain | Entities |
|---|---|
| Catalog | Category, Brand, Product, ProductVariant, AttributeValue, ProductImage |
| Inventory | StockMovement, StockReservation |
| Users & Access | User, Address, Role, Permission, OtpCode |
| Shopping | Cart, CartItem, WishlistItem |
| Orders | Order, OrderItem, OrderStatusHistory |
| Payments | Payment, Refund, WebhookEvent, PaymentApiLog |
| Marketing | Coupon, Review |
| Fulfillment | ShippingMethod, Shipment |
| Content | StaticPage (About/Contact/Terms/Privacy/Refund) |
| Infrastructure | Notification (Laravel built-in), ActivityLog (Spatie built-in) |

---

## 9. Open Items for Follow-Up

| Item | Status |
|---|---|
| Reservation expiry window (minutes) | To be finalized — recommend 15 minutes as a starting default |

---

## 10. Next Steps

1. ~~Finalize storefront architecture decision.~~ ✅ Done — Blade + Livewire, same Laravel app.
2. ~~Produce technical design documentation (ERD, route structure).~~ ✅ Done.
3. ~~Produce agile delivery documentation (epics, user stories, sprint backlog).~~ ✅ Done.
4. ~~Produce enumerated edge-case/test plan.~~ ✅ Done — see `test-plan-ecommerce.md`.
5. Begin scaffolding: migrations → models → Filament resources → payment integrations → Livewire storefront components.

---

*End of document.*
