# How To: Add a New Payment Provider

**Companion to:** `technical-design-ecommerce.md`, `AGENTS.md`, `CLAUDE.md`
**Audience:** developers adding a payment gateway (e.g. Stripe, Flutterwave) to this codebase.

---

## 0. How this fits together

Payment providers are resolved through `App\Payments\PaymentManager`, a Laravel `Manager`-pattern class. No Action ever talks to a vendor SDK directly — every Action goes through the `App\Payments\Contracts\PaymentGateway` interface, so adding a provider is **a new driver class + a config entry + an enum case**, never an Action change.

Unlike a single "active" setting, **more than one provider can be enabled at once** — the Super Admin turns providers on/off and sets their display order from `/admin` → Settings → Payment Providers, and the customer picks one of the enabled providers at checkout. That choice only affects **new** payments — `HandlePaymentWebhook`, `VerifyPaymentWithGateway`, and `IssueProviderRefund` all resolve their gateway from the provider stored on the individual `Payment` row (`PaymentManager::driver($payment->provider)`), so enabling/disabling a provider never disrupts a payment already in progress.

Existing reference implementations: `app/Payments/Drivers/PaystackGateway.php` (hosted-checkout redirect style) and `app/Payments/Drivers/MoolreGateway.php` (request-to-pay style, no redirect). Read whichever is closer to how your new provider works before starting.

---

## 1. Add the driver class

Create `app/Payments/Drivers/{Provider}Gateway.php` implementing `App\Payments\Contracts\PaymentGateway`:

```php
public function initiate(Order $order): PaymentInitiationResult;
public function verify(string $providerReference): PaymentVerificationResult;
public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult;
public function verifyWebhookSignature(Request $request): bool;
public function webhookEventId(Request $request): string;
public function paymentReferenceFromWebhook(Request $request): ?string;
```

Rules for the implementation:
- **Never accept or log raw card data.** Card details are handled entirely by the provider (hosted checkout / redirect) — this platform is never in PCI scope.
- **Every HTTP call goes through Laravel's `Http` facade**, never a vendor SDK/cURL directly, so it can be faked in tests (`Http::fake()`).
- **`verifyWebhookSignature()` must use the provider's documented signature scheme** (HMAC, etc.) — see `PaystackGateway::verifyWebhookSignature()` for the pattern (`hash_hmac` + `hash_equals`, never a plain `===` comparison, to avoid timing attacks).
- **`webhookEventId()` must return a value stable and unique per webhook delivery** — used for the `webhook_events` table's idempotency uniqueness constraint, so a retried delivery is never double-processed.
- Constructor takes only the credentials this driver needs (API key, secret, webhook secret, etc.) as typed, required constructor parameters — no defaults, no nullable credential fields. A missing credential should be a constructor-time failure (see step 3).
- Return every result as the normalized value object (`PaymentInitiationResult`/`PaymentVerificationResult`/`RefundResult`), not the provider's raw response shape — `rawResponse` is where the raw payload goes, for logging.

Return types, all in `app/Payments/`:
```php
PaymentInitiationResult(bool $success, ?string $providerReference, ?string $redirectUrl, ?string $errorMessage, array $rawResponse)
PaymentVerificationResult(PaymentStatus $status, ?string $providerReference, array $rawResponse)
RefundResult(bool $success, ?string $providerRefundReference, ?string $errorMessage, array $rawResponse)
```

---

## 2. Add credentials to `config/payments.php`

```php
'providers' => [
    // ...existing entries...
    'yourprovider' => [
        'api_key' => env('YOURPROVIDER_API_KEY'),
        // whatever else the driver's constructor needs
    ],
],
```

The array key (`'yourprovider'`) **is the provider name everywhere else** — it's what gets stored in `payments.provider`, what the enum case's `->value` resolves to, and what `PaymentManager::driver()` uses to find your driver method.

---

## 3. Register the driver in `PaymentManager`

Add a `create{Studly}Driver()` method (Laravel's `Manager` convention — the driver name `yourprovider` maps to method `createYourproviderDriver`):

```php
protected function createYourproviderDriver(): PaymentGateway
{
    $config = $this->config->get('payments.providers.yourprovider', []);

    return new YourProviderGateway(
        apiKey: $config['api_key'] ?? throw new InvalidArgumentException('YourProvider API key is not configured.'),
    );
}
```

Throwing `InvalidArgumentException` on a missing credential (rather than passing `null` through) is what lets `InitiatePayment`'s existing try/catch turn a misconfiguration into a graceful `Failed` payment instead of a 500 — you get this for free, no changes needed there.

---

## 4. Add the enum case

`app/Enums/PaymentProvider.php` — this is what makes the provider selectable/enableable from the Payment Providers admin screen. `App\Models\PaymentProviderSetting::syncKnownProviders()` auto-seeds a disabled row for any case with no row yet, so the new provider appears in the list (disabled, greyed out until credentials are set) the next time that screen loads — no manual seeding needed:

```php
enum PaymentProvider: string implements HasLabel
{
    case Paystack = 'paystack';
    case Moolre = 'moolre';
    case Yourprovider = 'yourprovider'; // must match the config key exactly

    public function label(): string
    {
        return match ($this) {
            self::Paystack => 'Paystack',
            self::Moolre => 'Moolre',
            self::Yourprovider => 'Your Provider',
        };
    }
    // ...
}
```

`hasCredentialsConfigured()` on this enum already works for any new case with no changes — it reads `config("payments.providers.{$this->value}")` generically.

---

## 5. Wire up the webhook route

The webhook endpoint is already generic — `POST /webhooks/payments/{provider}` (`routes/webhooks.php`) resolves the driver by the `{provider}` URL segment via `PaymentManager::driver($provider)`. **No route change is needed.** Give your provider's dashboard this URL:

```
https://your-domain.com/webhooks/payments/yourprovider
```

`HandlePaymentWebhook` never trusts the webhook payload's own reported status — it always re-verifies server-side via your driver's `verify()` method (dispatched to the `VerifyPaymentWithGateway` job). Make sure your `paymentReferenceFromWebhook()` correctly extracts whatever reference `verify()` needs.

---

## 6. Add credentials to `.env` / `.env.example`

Append to both files (`.env.example` gets empty placeholders, `.env` gets real values if you have test credentials):

```
YOURPROVIDER_API_KEY=
```

Also update `docs/infrastructure-deployment.md`'s "Environment Configuration" env var list so future deployments know this variable exists.

---

## 7. Write tests

Follow the existing pattern in `tests/Feature/Payment/PaymentTest.php`:
- **Extensibility proof** (already covered generically — no new test needed per provider, but useful to sanity-check): register your driver via `PaymentManager::extend()`, enable it (`DB::table('payment_provider_settings')->insert(['provider' => 'yourprovider', 'enabled' => true, ...])` for a test-only driver name, or the `PaymentProviderSetting` factory for a real enum case), call `InitiatePayment::run($order, 'yourprovider')`, assert `$payment->provider === 'yourprovider'`.
- **Driver-specific test** (new file, e.g. `tests/Feature/Payment/YourProviderGatewayTest.php`), mirroring `MoolreSmsConnectionFailureTest.php`'s shape for the SMS side:
  - `Http::fake()` your endpoint(s), assert the request shape (headers, body) matches the provider's documented API.
  - Assert `verifyWebhookSignature()` accepts a correctly-signed request and rejects a tampered one.
  - Assert a connection-level failure (`Http::fake(fn () => throw new ConnectionException(...))`) is normalized into a failed result, never an uncaught exception.

---

## 8. Verify

```bash
php -l app/Payments/Drivers/YourProviderGateway.php
./vendor/bin/pint app/Payments/Drivers/YourProviderGateway.php app/Enums/PaymentProvider.php app/Payments/PaymentManager.php --test
./vendor/bin/phpstan analyse
php artisan test --parallel
```

Then manually: in `/admin` → Settings → Payment Providers, confirm the new provider appears in the list, confirm its "Enabled" toggle is disabled (greyed out, with a tooltip) while no credentials are set, add real/sandbox credentials, toggle it on, and place a real test checkout selecting it to confirm `initiate()` actually hits your driver (check `payment_api_logs`).

---

## Checklist

- [ ] `app/Payments/Drivers/{Provider}Gateway.php` implements `PaymentGateway`
- [ ] `config/payments.php` — new `providers.{provider}` entry
- [ ] `PaymentManager::create{Provider}Driver()` added
- [ ] `App\Enums\PaymentProvider` — new case, `label()` updated
- [ ] `.env` / `.env.example` — credentials added
- [ ] `docs/infrastructure-deployment.md` — env var list updated
- [ ] Driver-specific test written
- [ ] `php -l` + Pint + PHPStan + full test suite green
- [ ] `CHANGELOG.md` entry + commit (per `CLAUDE.md` §1/§23)
