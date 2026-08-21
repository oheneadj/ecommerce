# How To: Set Up Sentry (Error Tracking)

**Companion to:** `infrastructure-deployment.md`, `CLAUDE.md` §21
**Audience:** whoever is deploying/operating this app — one-time setup per deployment (each business installation gets its own Sentry project, never a shared one, so one store's errors never mix with another's).

---

## 0. How this fits together

Without Sentry, an unhandled exception in production is only ever visible in `storage/logs/laravel.log` on that one server — nobody is notified, and finding it means SSHing in and grepping a file. `sentry/sentry-laravel` is installed and wired into `bootstrap/app.php` (`Sentry\Laravel\Integration::handles($exceptions)`), so every unhandled exception is reported automatically once a DSN is configured. It ships **disabled** — `SENTRY_LARAVEL_DSN` is blank by default — so a fresh clone or a deployment that doesn't want error tracking incurs zero cost and sends nothing anywhere.

A [System Health](../app/Filament/Pages/SystemHealth.php) check (`App\HealthChecks\SentryConfigured`) flags an unconfigured DSN as a **warning** — worth fixing, but never a critical failure, since the app works correctly without it.

---

## 1. Create a Sentry project

1. Go to [sentry.io](https://sentry.io) (or your self-hosted instance) and sign in
2. **Projects → Create Project**
3. Platform: **Laravel**
4. Name it something identifiable per deployment (e.g. `yourstore-production`)

---

## 2. Get the DSN

After creating the project, Sentry shows a **DSN** (Data Source Name) — a URL like `https://examplePublicKey@o0.ingest.sentry.io/0`. You can also find it later under **Project Settings → Client Keys (DSN)**.

**This is not a secret you keep out of git for security reasons** — it's meant to be embedded in client code — but it still belongs in `.env`, not committed, since it's deployment-specific (a different value per business installation, same reasoning as every other per-deployment credential in this app).

---

## 3. Set the environment variables

```env
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
SENTRY_TRACES_SAMPLE_RATE=0.2
```

`SENTRY_TRACES_SAMPLE_RATE` is the fraction of requests captured for performance tracing (`0.2` = 20%). Kept well below `1.0` by default so a busy production store doesn't burn through Sentry's event quota on tracing alone — raise it temporarily while investigating a performance issue, lower it back down after.

Then apply the change:

```bash
php artisan config:clear
```

(or **Settings → System Cache → Config** from the admin panel, same as after any other `.env` change)

---

## 4. Verify it worked

1. **Settings → System Health** — confirm "Sentry Configured" now shows OK instead of a warning.
2. Trigger a real error to confirm delivery, then check it shows up in the Sentry project's **Issues** tab:
   ```bash
   php artisan tinker
   >>> throw new \Exception('Sentry test event');
   ```
3. Delete or ignore that test issue in Sentry once confirmed — it's not a real error.

---

## 5. What's captured, and what isn't

- **Every unhandled exception**, app-wide — no code change needed per Action/Controller, since it's wired into the global exception handler.
- **Breadcrumbs** (recent SQL queries, cache hits, notifications sent, etc.) attached to each error, to help reconstruct what led to it — configured in `config/sentry.php`, defaults are sane out of the box.
- **Not** request IP, headers, or the authenticated user by default (`send_default_pii` is `false` in `config/sentry.php`) — matches this app's own logging rule (CLAUDE.md §21: no PII in non-database logs). Turn it on only if you specifically need it and have a reason Sentry's own data-retention settings satisfy your privacy requirements.
- **Not** anything logged via `Log::info()`/`Log::debug()` by default — only `Log::error()` and above reach Sentry, and only if you opt into it (see below).

### Optional: also send `Log::error()` calls, not just uncaught exceptions

This app already writes `Log::error()` for things like payment failures and jobs failed after final retry (CLAUDE.md §21). To have those reach Sentry too (not just genuinely uncaught exceptions), add `sentry` to the log stack:

```env
LOG_STACK=single,sentry
```

The `sentry` channel (`config/logging.php`) is already defined and no-ops safely when `SENTRY_LARAVEL_DSN` is unset, so this is safe to leave in `LOG_STACK` even on a deployment that hasn't configured Sentry yet.

---

## 6. Optional: release tracking

Setting `SENTRY_RELEASE` (e.g. to the deployed git commit hash) lets Sentry group errors by release and show you exactly which deploy introduced a regression:

```env
SENTRY_RELEASE=${SOURCE_COMMIT}
```

Set this from your deploy script/CI rather than hardcoding a value — see `infrastructure-deployment.md` for this project's deploy process.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| System Health still shows the Sentry warning after setting the DSN | The app hasn't picked up the changed `.env` — run `php artisan config:clear` |
| No events show up in Sentry after a real error | Double-check the DSN was pasted correctly (a truncated/malformed DSN fails silently rather than throwing) — re-copy it from Sentry's Client Keys page |
| Too many events, quota exhausted quickly | Lower `SENTRY_TRACES_SAMPLE_RATE`, or add noisy/expected exceptions to `ignore_exceptions` in `config/sentry.php` |
| Sensitive data showing up in a captured event | Check `send_default_pii` is `false` in `config/sentry.php`, and that the exception/log message itself doesn't interpolate a sensitive value directly (Sentry can only redact what it's not given in the first place) |
