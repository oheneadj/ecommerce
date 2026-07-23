# Test Plan — Critical Scenarios & Edge Cases
## E-Commerce Platform

**Version:** 1.0
**Companion to:** BRD, Technical Design, Agile Docs, AGENTS.md
**Purpose:** This is the enumerated, pre-launch QA checklist — every scenario listed here must have a passing automated test (named per the convention in `AGENTS.md` Section 6) before the corresponding feature is considered done. This is not a style guide; it's a concrete checklist derived from every edge case and business rule decided during planning.

---

## 1. Stock & Inventory

| # | Test scenario | Test name (example) |
|---|---|---|
| 1.1 | Adding items to cart never affects stock or creates a reservation | `test_adding_to_cart_does_not_affect_stock` |
| 1.2 | Stock is reserved (not deducted) when an Order is created at checkout | `test_stock_is_reserved_not_deducted_at_checkout` |
| 1.3 | Two simultaneous checkouts for the last unit of a variant result in exactly one success and one rejection | `test_concurrent_checkout_on_last_unit_prevents_overselling` |
| 1.4 | Reservation creation uses row-level locking (`lockForUpdate`) within a DB transaction | `test_reservation_creation_uses_row_locking` |
| 1.5 | A reservation past `expires_at` is released automatically by the scheduled job, restoring availability | `test_reservation_expires_and_releases_stock_after_window` |
| 1.6 | The reservation window is read from `store_settings.stock_reservation_minutes`, not hardcoded | `test_reservation_window_is_configurable_via_store_settings` |
| 1.7 | On payment success, a reservation converts to a permanent `StockMovement` (sale) | `test_reservation_converts_to_stock_movement_on_payment_success` |
| 1.8 | A refund creates a `StockMovement` (return) restoring the returned quantity | `test_refund_restores_stock_via_movement` |
| 1.9 | A manual stock adjustment that would make `stock - active reservations` negative is still allowed to proceed | `test_manual_adjustment_below_reserved_stock_is_permitted` |
| 1.10 | Reservations that can no longer be covered after such an adjustment are marked `at_risk` and trigger an Admin notification | `test_uncovered_reservations_flagged_at_risk_after_adjustment` |
| 1.11 | Every stock movement records the acting user (or null for system-triggered) | `test_stock_movement_records_actor` |

---

## 2. Payments & Idempotency

| # | Test scenario | Test name (example) |
|---|---|---|
| 2.1 | A duplicate webhook delivery (same `event_id`) is rejected and never processed twice | `test_duplicate_webhook_event_does_not_double_process_payment` |
| 2.2 | An unverified/incorrectly signed webhook is rejected and logged, never acted upon | `test_unverified_webhook_signature_is_rejected` |
| 2.3 | A webhook handler checks `processed_at` before executing side effects, even if the unique constraint is somehow bypassed | `test_webhook_handler_is_idempotent_via_processed_at_check` |
| 2.4 | Creating a payment for an order that already has a `pending` payment reuses/rejects rather than duplicating | `test_payment_creation_is_idempotent_per_order` |
| 2.5 | Double-submitting checkout (double-click / back button) does not create duplicate Orders | `test_duplicate_checkout_submission_does_not_create_duplicate_order` |
| 2.6 | Every outbound API call to a payment provider is logged to `payment_api_logs`, tied to the order | `test_outbound_payment_api_calls_are_logged_to_order` |
| 2.7 | Paystack payments are verified server-side via the verify endpoint, not trusted from client redirect alone | `test_paystack_payment_is_server_side_verified` |
| 2.8 | A payment still `pending` beyond the grace period is actively polled via `VerifyPendingPayments`, independent of webhook delivery | `test_pending_payment_is_polled_when_webhook_is_late` |
| 2.9 | Polling and webhook confirmation both funnel through the same status-update logic without double-processing | `test_polling_and_webhook_confirmation_are_mutually_idempotent` |
| 2.10 | A payment confirmed successful after its reservation expired re-fulfills the order if stock is still available | `test_late_payment_confirmation_refulfills_when_stock_available` |
| 2.11 | A payment confirmed successful after its reservation expired triggers an automatic refund if stock is no longer available | `test_late_payment_confirmation_auto_refunds_when_stock_unavailable` |
| 2.12 | A partial refund is supported and does not exceed the original payment amount | `test_partial_refund_cannot_exceed_original_payment_amount` |
| 2.13 | Adding/swapping a payment provider (a new `PaymentGateway` driver) requires zero changes to any Action | `test_new_payment_driver_requires_no_action_changes` |

---

## 3. Coupons

| # | Test scenario | Test name (example) |
|---|---|---|
| 3.1 | A coupon with `usage_limit: 1` used by two simultaneous checkouts results in exactly one success | `test_concurrent_coupon_use_respects_usage_limit` |
| 3.2 | Coupon usage validation counts actual `coupon_usages` rows, not a cached counter | `test_coupon_usage_limit_counts_actual_usage_rows` |
| 3.3 | A coupon scoped to a specific product/category is rejected for out-of-scope items | `test_coupon_rejects_out_of_scope_products` |
| 3.4 | An expired coupon is rejected at checkout | `test_expired_coupon_is_rejected` |
| 3.5 | `usage_limit_per_user` is enforced for guests via `guest_email` matching | `test_guest_coupon_usage_limit_enforced_by_email` |

---

## 4. Orders & Pricing

| # | Test scenario | Test name (example) |
|---|---|---|
| 4.1 | `OrderItem.unit_price` reflects the variant's price at Order creation, not at the time it was added to cart | `test_order_item_price_snapshot_taken_at_order_creation` |
| 4.2 | A price change after Order creation never affects that order's stored `unit_price` or `item_snapshot` | `test_past_order_unchanged_by_later_price_edit` |
| 4.3 | Editing a product's name, image, or attributes never changes how a past order displays | `test_past_order_display_reads_only_from_item_snapshot` |
| 4.4 | Order history/receipt rendering never queries live `Product`/`ProductVariant` data | `test_order_rendering_does_not_read_live_product_data` |
| 4.5 | A unique, customer-facing `order_number` is generated on Order creation | `test_order_number_generated_on_creation` |
| 4.6 | Every order status change is logged to `OrderStatusHistory` with actor and timestamp | `test_order_status_change_logs_history_entry` |
| 4.7 | A PDF receipt is generated automatically on payment success | `test_pdf_receipt_generated_on_payment_success` |

---

## 4a. Customer Authentication (Phone OTP / Google / Email+Password)

| # | Test scenario | Test name (example) |
|---|---|---|
| 4a.1 | Requesting an OTP is rate-limited to 1 per phone per 60 seconds | `test_otp_request_rate_limited_per_minute` |
| 4a.2 | Requesting an OTP is rate-limited to 5 per phone per hour | `test_otp_request_rate_limited_per_hour` |
| 4a.3 | An OTP code is never stored in plaintext, only hashed | `test_otp_code_stored_as_hash_not_plaintext` |
| 4a.4 | An OTP code expires after 10 minutes and cannot be verified after expiry | `test_otp_code_expires_after_ten_minutes` |
| 4a.5 | An OTP code is single-use — verifying it twice fails the second time | `test_otp_code_is_single_use` |
| 4a.6 | 5 failed verification attempts locks the code, requiring a fresh request | `test_otp_locks_after_five_failed_attempts` |
| 4a.7 | Verifying a valid OTP for a new phone number auto-creates an account | `test_new_phone_otp_verification_creates_account` |
| 4a.8 | Verifying a valid OTP for an existing phone logs into the existing account, not a duplicate | `test_existing_phone_otp_verification_logs_into_same_account` |
| 4a.9 | First-time Google login with an email matching an existing account links to that account | `test_google_login_links_to_existing_account_on_verified_email_match` |
| 4a.10 | First-time Google login with a new email creates a new account | `test_google_login_creates_new_account_when_no_email_match` |
| 4a.11 | A customer can set up email+password only while already authenticated via another method | `test_set_password_requires_existing_authenticated_session` |
| 4a.12 | Linking a second login method (Google or email+password) to an account requires the customer to already be authenticated — never automatic | `test_link_account_identifier_requires_authentication_not_automatic` |
| 4a.13 | Two different identifiers with no verified overlap are never silently merged into one account | `test_unrelated_identifiers_never_auto_merged` |
| 4a.14 | A Google-only customer with no phone on file receives notifications via email only, never SMS | `test_notification_channel_falls_back_to_email_when_no_phone` |
| 4a.15 | Staff/admin login via Filament remains email+password and is unaffected by customer auth changes | `test_admin_login_unaffected_by_customer_auth_methods` |

---

## 5. Guest Checkout & Accounts

| # | Test scenario | Test name (example) |
|---|---|---|
| 5.1 | A guest order using an email matching an existing account is never auto-attached to that account | `test_guest_order_not_auto_attached_on_email_match` |
| 5.2 | A guest order can only be linked to an account via `ClaimGuestOrder`, after the customer authenticates | `test_guest_order_claim_requires_authentication` |

---

## 6. Catalog Lifecycle

| # | Test scenario | Test name (example) |
|---|---|---|
| 6.1 | Deleting a product soft-deletes it (`deleted_at` set), never hard-deletes | `test_product_deletion_is_soft_delete` |
| 6.2 | Deleting a product mutates its slug to `{slug}-deleted-{id}`, freeing the original slug immediately | `test_product_deletion_mutates_slug_using_record_id` |
| 6.3 | A repeated create → delete → recreate → delete cycle for the same product name never produces a uniqueness conflict | `test_repeated_delete_recreate_cycle_has_no_slug_collision` |
| 6.4 | Archiving a product (`status: archived`) does not alter its slug/SKU | `test_archiving_product_does_not_change_slug` |
| 6.5 | Deleting a product/variant does not alter any existing order's displayed data | `test_product_deletion_does_not_affect_past_orders` |

---

## 7. Reviews

| # | Test scenario | Test name (example) |
|---|---|---|
| 7.1 | A review can only be submitted if linked to a verified `order_item` | `test_review_requires_verified_purchase` |
| 7.2 | Editing a review's rating/title/body resets its status to `pending` | `test_editing_review_resets_status_to_pending` |
| 7.3 | A customer can delete their own review at any time | `test_customer_can_delete_own_review` |
| 7.4 | An Admin can delete a review but cannot edit its content | `test_admin_cannot_edit_review_content` |

---

## 8. Roles & Access

| # | Test scenario | Test name (example) |
|---|---|---|
| 8.1 | A Store Keeper cannot access Order or Payment resources in the admin panel | `test_store_keeper_cannot_access_orders_or_payments` |
| 8.2 | An Admin cannot access Role/Permission management | `test_admin_cannot_manage_roles` |
| 8.3 | Only a Super Admin can create/modify staff roles | `test_only_super_admin_can_manage_roles` |
| 8.4 | Customers have no access to the Filament admin panel under any role | `test_customers_cannot_access_admin_panel` |

---

## 9. Provider Extensibility

| # | Test scenario | Test name (example) |
|---|---|---|
| 9.1 | A new dummy `PaymentGateway` driver can be registered and resolved correctly without modifying any Action | `test_new_payment_gateway_driver_resolves_without_action_changes` |
| 9.2 | A new dummy `SmsGateway` driver can be registered and resolved correctly without modifying any Action | `test_new_sms_gateway_driver_resolves_without_action_changes` |

---

## 10. Multi-Deployment / Branding

| # | Test scenario | Test name (example) |
|---|---|---|
| 10.1 | Storefront branding (logo, colors, business name) renders from `store_settings`, not hardcoded values | `test_storefront_branding_reads_from_store_settings` |
| 10.2 | Changing `store_settings` values updates the storefront without a code deploy | `test_branding_change_takes_effect_without_deploy` |

---

## 11. Non-Functional / Cross-Cutting

| # | Test scenario | Test name (example) |
|---|---|---|
| 11.1 | API documentation (Scramble) regenerates correctly from current routes/Form Requests on CI | `test_api_documentation_generates_without_manual_edits` |
| 11.2 | Every Action added to the codebase has a corresponding entry in the `AGENTS.md` lookup table (process check, not automated) | Manual PR checklist item |
| 11.3 | Every money-bearing field is an integer column, never `decimal`/`float` | `test_money_columns_are_integer_type` |
| 11.4 | Money arithmetic (subtotal + tax + shipping - discount) produces exact results with no floating-point drift across many line items | `test_order_total_calculation_has_no_floating_point_drift` |
| 11.5 | A formatted display accessor exists and is used wherever a money value is rendered — no raw integer division in Blade/API output | `test_money_display_uses_formatted_accessor_not_inline_division` |
| 11.6 | No route or API response exposes a model's raw bigint `id` for any ULID-bearing model | `test_no_route_exposes_raw_bigint_id` |
| 11.7 | Route-model-binding resolves correctly via `ulid` (or `slug`/`order_number` where applicable) for every externally-facing model | `test_route_model_binding_uses_ulid_not_id` |

---

## 12. Pre-Launch Checklist Summary

Before considering v1 launch-ready, every scenario above must have:
- [ ] A passing automated test
- [ ] Verification against the acceptance criteria in `agile-docs-ecommerce.md`
- [ ] No known regression introduced by later changes (re-run full suite before each release)

This document should be updated whenever a new edge case or business rule is decided — it is the single source of truth for "what must never break."

---

*End of document.*
