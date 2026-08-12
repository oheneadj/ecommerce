# How To: Add a New SMS Provider

**Companion to:** `technical-design-ecommerce.md`, `AGENTS.md`, `CLAUDE.md`
**Audience:** developers adding an SMS gateway (e.g. Twilio, Hubtel) to this codebase.

---

## 0. How this fits together

SMS providers are resolved through `App\Sms\SmsManager`, a Laravel `Manager`-pattern class — the exact same shape as `App\Payments\PaymentManager` (see `docs/HOWTO-add-payment-provider.md` if you haven't read it, the two are deliberately symmetric). No Action, Notification, or job ever talks to a vendor SDK directly — everything goes through `App\Sms\Contracts\SmsGateway`, so adding a provider is **a new driver class + a config entry + an enum case**, never an Action or Notification change.

Every outbound notification's `sms` channel (`App\Notifications\Channels\SmsChannel`) resolves the gateway via the `SmsGateway::class` container binding in `AppServiceProvider`, which itself resolves via `SmsManager::driver()` — so `LowStockAlert`, `CriticalHealthAlert`, `ReservationsAtRiskAlert`, `StaffInvited`, and OTP delivery (`RequestOtp`) all automatically use whichever provider is active, with zero changes to any of them.

The Super Admin picks the active provider from Store Settings (`/admin` → Settings → Store Settings → "Payment & SMS providers"). Existing reference implementation: `app/Sms/Drivers/MoolreSms.php` and `app/Sms/Drivers/GiantSms.php` — read whichever is closer to your new provider's API shape (bearer-token style vs. Basic-auth-with-a-static-token style, respectively).

---

## 1. Add the driver class

Create `app/Sms/Drivers/{Provider}Sms.php` implementing `App\Sms\Contracts\SmsGateway` — a single method:

```php
public function send(string $to, string $message): SmsSendResult;
```

Rules for the implementation:
- **The HTTP call goes through Laravel's `Http` facade**, never a vendor SDK/cURL directly, so it can be faked in tests (`Http::fake()`).
- **A connection-level failure must never throw out of `send()`.** Wrap the request in `try { ... } catch (ConnectionException $e) { return new SmsSendResult(success: false, ...); }` — every caller (`SmsChannel`, `RequestOtp`, staff-broadcast sending) depends on `send()` always returning a result so their own `SmsApiLog`/notification-status write still happens, even for this failure mode. See `GiantSms::send()` for the exact pattern.
- **A body-level failure (HTTP 200 but the provider's own payload reports failure) must also produce `success: false`** — don't rely on HTTP status alone; check the provider's documented "did this actually send" field.
- Constructor takes only the credentials this driver needs, as typed, required constructor parameters (no defaults, no nullable credential fields) — a missing credential should be a constructor-time failure (see step 3).

Return type, `app/Sms/SmsSendResult.php`:
```php
SmsSendResult(bool $success, ?string $providerReference, ?string $errorMessage, array $rawResponse, ?int $statusCode)
```

### Worked example: GiantSMS

```php
readonly class GiantSms implements SmsGateway
{
    private const BASE_URL = 'https://api.giantsms.com/api/v1/send';

    public function __construct(
        private string $apiToken,
        private string $senderId,
    ) {}

    public function send(string $to, string $message): SmsSendResult
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.$this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post(self::BASE_URL, [
                'from' => $this->senderId,
                'to' => $to,
                'msg' => $message,
            ]);
        } catch (ConnectionException $e) {
            return new SmsSendResult(success: false, errorMessage: $e->getMessage(), rawResponse: ['error' => $e->getMessage()], statusCode: null);
        }

        if ($response->failed() || $response->json('status') !== true) {
            return new SmsSendResult(success: false, errorMessage: $response->json('message') ?? 'GiantSMS request failed.', rawResponse: $response->json() ?? [], statusCode: $response->status());
        }

        return new SmsSendResult(success: true, providerReference: $response->json('data.message_id'), rawResponse: $response->json() ?? [], statusCode: $response->status());
    }
}
```

Use this as a template — swap the headers/body/response-parsing for your provider's documented API.

---

## 2. Add credentials to `config/sms.php`

```php
'providers' => [
    // ...existing entries...
    'yourprovider' => [
        'api_key' => env('YOURPROVIDER_SMS_KEY'),
        // whatever else the driver's constructor needs
    ],
],
```

The array key (`'yourprovider'`) **is the provider name everywhere else** — it's what the Super Admin's Select resolves to, and what `SmsManager::driver()` uses to find your driver method.

---

## 3. Register the driver in `SmsManager`

Add a `create{Studly}Driver()` method (Laravel's `Manager` convention — driver name `yourprovider` maps to method `createYourproviderDriver`):

```php
protected function createYourproviderDriver(): SmsGateway
{
    $config = $this->config->get('sms.providers.yourprovider', []);

    return new YourProviderSms(
        apiKey: $config['api_key'] ?? throw new InvalidArgumentException('YourProvider API key is not configured.'),
    );
}
```

---

## 4. Add the enum case

`app/Enums/SmsProvider.php` — this is what makes the provider selectable from Store Settings:

```php
enum SmsProvider: string implements HasLabel
{
    case Moolre = 'moolre';
    case Giantsms = 'giantsms';
    case Yourprovider = 'yourprovider'; // must match the config key exactly

    public function label(): string
    {
        return match ($this) {
            self::Moolre => 'Moolre',
            self::Giantsms => 'GiantSMS',
            self::Yourprovider => 'Your Provider',
        };
    }
    // ...
}
```

`hasCredentialsConfigured()` on this enum already works for any new case with no changes — it reads `config("sms.providers.{$this->value}")` generically.

---

## 5. Add credentials to `.env` / `.env.example`

```
YOURPROVIDER_SMS_KEY=
```

Also update `docs/infrastructure-deployment.md`'s "Environment Configuration" env var list.

---

## 6. Write tests

Follow the existing pattern in `tests/Feature/Sms/GiantSmsTest.php` (new file, e.g. `tests/Feature/Sms/YourProviderSmsTest.php`):
- `Http::fake()` your endpoint, assert the request shape (headers, body) matches the provider's documented API, using `Http::assertSent(...)`.
- Assert a body-level failure response (`status: false` or equivalent) produces `success: false`.
- Assert a connection-level failure (`Http::fake(fn () => throw new ConnectionException(...))`) is normalized into a failed result — never an uncaught exception (mirrors `MoolreSmsConnectionFailureTest.php`).

Extensibility is already proven generically by `tests/Feature/Sms/SmsGatewayTest.php` (`test_new_sms_gateway_driver_resolves_without_action_changes`) — no changes needed there for a new provider.

---

## 7. Verify

```bash
php -l app/Sms/Drivers/YourProviderSms.php
./vendor/bin/pint app/Sms/Drivers/YourProviderSms.php app/Enums/SmsProvider.php app/Sms/SmsManager.php --test
./vendor/bin/phpstan analyse
php artisan test --parallel
```

Then manually: in `/admin` → Store Settings, confirm the new provider appears in the "Active SMS provider" dropdown, confirm picking it with no credentials set is rejected by the save-time validation, add real/sandbox credentials, save, and trigger a real SMS send (e.g. request a phone OTP) to confirm it actually goes out.

---

## Checklist

- [ ] `app/Sms/Drivers/{Provider}Sms.php` implements `SmsGateway`
- [ ] `config/sms.php` — new `providers.{provider}` entry
- [ ] `SmsManager::create{Provider}Driver()` added
- [ ] `App\Enums\SmsProvider` — new case, `label()` updated
- [ ] `.env` / `.env.example` — credentials added
- [ ] `docs/infrastructure-deployment.md` — env var list updated
- [ ] Driver-specific test written (success, body-level failure, connection failure)
- [ ] `php -l` + Pint + PHPStan + full test suite green
- [ ] `CHANGELOG.md` entry + commit (per `CLAUDE.md` §1/§23)
