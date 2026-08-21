# Storefront QA Checklist — Manual Browser Walkthrough

Click through the real app in your browser and check off each case as you verify it. Run through the admin sections logged in as Super Admin (or Store Keeper, where noted), then run the storefront sections in an incognito window as an anonymous/logged-in customer.

Cases marked **[regression check]** cover bugs already fixed this session — worth double-checking they stay fixed.

If a URL below 404s, the app has moved since this checklist was written — treat that as its own bug, not a checklist error.

---

## 1. Storefront — Browsing & Search

*Anonymous, logged out.*

- [ ] **Homepage loads with real data**
  Visit `/`.
  Expect: store name/logo, featured brands, categories, and new-arrival products all render — nothing shows placeholder/lorem content.

- [ ] **Product listing filters and sorts**
  Visit `/products`, apply a category filter, then a price sort, then reload the page with the resulting URL.
  Expect: results update live; reloading the filtered URL restores the same filtered/sorted view (state lives in the query string).

- [ ] **Search autosuggest**
  Start typing a known product name in the header search box.
  Expect: matching products appear in a dropdown before you press Enter; selecting one navigates straight to it.

- [ ] **Product detail page — variants & images**
  Open a product with multiple size/color variants, switch between variants.
  Expect: price, stock/availability, and gallery images update per the selected variant; out-of-stock variants are clearly disabled, not purchasable.

- [ ] **Product share preview shows the product photo** *[regression check]*
  Copy a product page's URL, paste it somewhere that unfurls links (e.g. WhatsApp/Slack), or view page source.
  Expect: `og:image` is the product's own photo, not the store logo.

---

## 2. Storefront — Cart & Checkout

- [ ] **Add to cart does not touch stock**
  Note a variant's stock in the admin catalog, add several units to the cart as a customer, re-check the admin stock figure.
  Expect: stock is unchanged — only a reservation at checkout affects it, never merely adding to cart.

- [ ] **Guest cart merges on login**
  As a guest, add items to the cart at `/cart`, then log in (phone OTP or Google).
  Expect: the guest cart's items are still present after login, merged into the account's cart.

- [ ] **Coupon applies at checkout**
  Go to `/checkout`, enter a valid, active coupon code.
  Expect: discount is reflected in the order total immediately, with a clear line item for it.

- [ ] **Expired/invalid coupon is rejected with a clear message**
  Enter an expired or nonexistent coupon code at checkout.
  Expect: a specific rejection message appears (not a generic error) and the total is unaffected.

- [ ] **Shipping method selection affects total**
  Select each available shipping method at checkout.
  Expect: order total updates to match the selected method's cost.

- [ ] **Payment completes and order confirms**
  Complete checkout with a test payment, land on the confirmation page.
  Expect: order appears in `/account/orders` with correct items/total; a confirmation email/SMS is received.

- [ ] **Discontinued variant in cart doesn't crash checkout** *[regression check]*
  Add a variant to cart, have an admin delete that variant, return to `/cart` or `/checkout`.
  Expect: the stale item is silently removed — no 500 error, no broken page.

---

## 3. Storefront — Accounts, Auth & Wishlist

- [ ] **Phone OTP login**
  Visit `/login/phone`, enter a phone number, request a code, enter it.
  Expect: logged in on correct code; a wrong code is rejected with a clear message; resend clears any prior error.

- [ ] **OTP request rate limiting**
  Request an OTP code for the same phone number 2–3 times quickly.
  Expect: rapid repeat requests are throttled with a clear "try again in..." message, not silently resent every time.

- [ ] **Google login**
  Visit `/login/google`, complete the Google consent flow.
  Expect: logged in and redirected back to the storefront; a returning Google user logs into the same existing account, not a duplicate.

- [ ] **Account pages accessible when logged in**
  Visit `/account`, `/account/addresses`, `/account/orders`, `/wishlist`.
  Expect: each loads with the account's own real data; none of these are reachable while logged out (redirect to login instead).

- [ ] **Notification bell shows only customer-relevant alerts** *[regression check]*
  Log in as a Super Admin account on the storefront side, open the bell icon / `/account/notifications`.
  Expect: only order-related notifications (placed/shipped/payment) appear — never a staff-only alert like a backup failure or health check warning.

- [ ] **Product review submission**
  Open an order containing a delivered item, leave a review from the order/product page.
  Expect: review is only submittable for something actually purchased and delivered; appears on the product page once approved by an admin.

---

## 4. Storefront — SEO & Discoverability

- [ ] **Sitemap lists real content**
  Visit `/sitemap.xml`.
  Expect: contains the homepage, product listing, every active product, and every published static page — no draft/archived products.

- [ ] **robots.txt points at the sitemap**
  Visit `/robots.txt`.
  Expect: references the sitemap by full URL and disallows `/cart`, `/checkout`, `/account`.

- [ ] **Private pages carry noindex**
  View page source on `/cart` or any `/account/*` page.
  Expect: a `<meta name="robots" content="noindex, nofollow">` tag is present.

- [ ] **Product page has canonical + structured data**
  View page source on any product page.
  Expect: a `<link rel="canonical">` with no query string, plus a `Product` JSON-LD block with correct price/availability.

---

## 5. Admin — Catalog

*Log into `/admin` as Super Admin (or Store Keeper, where noted).*

- [ ] **Create a product with variants**
  Admin → Products → Create, fill in name, category, at least one variant with price/stock.
  Expect: product saves and immediately appears (as Draft) in the storefront listing only once its status is set Active.

- [ ] **Duplicate a product**
  Admin → Products → open a product's row actions → Duplicate.
  Expect: a new Draft copy is created with variants/images copied, but with stock 0 and a proper stock-movement entry, not a silently nonzero cached figure.

- [ ] **Store Keeper catalog permissions**
  Log in as a Store Keeper, attempt to create and edit a product.
  Expect: create and edit both succeed; there is no delete action available to this role.

- [ ] **Category cannot become its own ancestor**
  Edit a category, try to set its parent to itself or one of its own descendants.
  Expect: rejected with a validation message — never silently creates a cycle.

- [ ] **Bulk stock/price adjustment on variants**
  Open a product's Variants tab, select several variants → bulk adjust stock or price.
  Expect: every selected variant updates, each with its own stock-movement record if stock changed.

---

## 6. Admin — Orders, Payments & Refunds

- [ ] **Order status change is logged**
  Admin → Orders → open an order → change its status.
  Expect: status updates immediately; the order's history tab shows the change with the acting admin and timestamp.

- [ ] **Invoice stays downloadable after shipment** *[regression check]*
  Open an order that has already shipped, download its invoice/receipt.
  Expect: downloads successfully — not blocked just because the order has moved past a certain status.

- [ ] **Partial refund**
  Open a paid order → issue a partial refund.
  Expect: refund amount cannot exceed what remains refundable; stock is restored via a return movement; order/payment status reflects the partial refund.

- [ ] **Payments table is read-only, export works**
  Admin → Payments.
  Expect: no delete/edit actions on individual payments; bulk export produces a downloadable file.

---

## 7. Admin — Customers & Staff

- [ ] **Disable / enable a customer account**
  Admin → Customers → open a customer → Disable, attempt to log in as that customer.
  Expect: login is blocked (phone OTP, Google, and password all rejected) while disabled; re-enabling restores login immediately.

- [ ] **Invite a new staff member**
  Admin → Staff → Create → assign Admin or Store Keeper role.
  Expect: invite email/SMS is sent; the new account cannot be assigned Super Admin from this form.

- [ ] **Super Admin accounts are invisible in Staff list**
  Admin → Staff, while logged in as Super Admin.
  Expect: no Super Admin account appears in this list or is reachable by direct edit URL (404s instead).

- [ ] **Role change is recorded in the activity log**
  Edit a staff member's role, check Admin → Activity Log.
  Expect: a "role changed" entry appears with old/new role and the acting admin.

---

## 8. Admin — Coupons & Shipping Methods

- [ ] **Deleting an in-use coupon is blocked, not a crash** *[regression check]*
  Create a coupon, use it on a test order, Admin → Coupons → attempt to delete that coupon.
  Expect: a clear "Cannot delete coupon — already used" notification appears; no 500 error.

- [ ] **Deleting an in-use shipping method is blocked, not a crash** *[regression check]*
  Use a shipping method on a test order, Admin → Shipping Methods → attempt to delete it.
  Expect: a clear "Cannot delete shipping method — still used by a shipment" notification appears; no 500 error.

- [ ] **Deactivate instead of delete still works**
  On an in-use coupon or shipping method, use the Deactivate action instead.
  Expect: it's immediately hidden from checkout while the historical record stays intact.

---

## 9. Admin — Store Settings & Local SEO

- [ ] **Branding changes reflect on the storefront**
  Admin → Store Settings → change business name/logo/colors → Save.
  Expect: storefront header/footer and PDF receipts pick up the new branding without a deploy.

- [ ] **Local SEO fields populate LocalBusiness structured data**
  Admin → Store Settings → Local SEO → fill in street + city (+ optionally lat/long) → Save, view storefront homepage source.
  Expect: a `LocalBusiness` JSON-LD block appears with the entered address.

- [ ] **Google Analytics only loads once configured**
  With no GA measurement ID set, view any storefront page source. Set a measurement ID in Store Settings, reload.
  Expect: no `gtag` script before configuring; the script appears with the correct ID after.

---

## 10. Admin — Backups & System Health

- [ ] **Manual backup run**
  Admin → Backups → Run backup now.
  Expect: a new backup run appears in the history and eventually shows Success, with a file landing in the configured remote storage.

- [ ] **Restore is heavily gated**
  Attempt to run Restore on a successful backup.
  Expect: requires a fresh password confirmation and a typed confirmation phrase before proceeding — never a single click.

- [ ] **System Health dashboard reflects reality**
  Admin → System Health.
  Expect: Sentry, SMS, and payment provider checks correctly show configured/unconfigured; an unconfigured Sentry DSN shows as a warning, not a critical failure.

- [ ] **Telescope and Pulse access (Super Admin only)**
  With `TELESCOPE_ENABLED`/`PULSE_ENABLED` on, visit `/telescope` and `/pulse` as Super Admin, then log out and retry.
  Expect: accessible while logged in as Super Admin; denied or redirected once logged out.
