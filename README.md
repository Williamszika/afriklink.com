# AfrikaLink

International, multi-vertical marketplace connecting Africa and the world. Four kinds of
sellers share one platform:

- 🛍️ **Shops** — physical products (stock, local + international shipping)
- 🍽️ **Restaurants** — online menus, pickup / delivery
- 💈 **Salons** — services with time-slot booking
- 🛠️ **Trades & services** — professional profiles with quote or booking

Everyone can **buy and sell, locally and internationally**, across languages and currencies.

> Built with the `afrikalink-builder` skill (`.claude/skills/afrikalink-builder/`), which is the
> security-first construction plan: stack, golden rules, schema, phases and ready-to-use helpers.
> It auto-activates in Claude Code sessions on this repo.

## Stack

- **PHP 8.4**, lightweight home-grown MVC (no heavy framework)
- **MySQL 8.4 LTS** (or MariaDB 11.4+), `utf8mb4`, InnoDB
- **Hostinger** hosting, **Cloudflare** in front (DNS proxied, WAF, edge rate limiting)
- **Stripe Connect** for marketplace payments (Phase 3+)
- Composer for libraries (Stripe SDK, PHPMailer, ramsey/uuid, phpdotenv)

## Project layout

```
public/            # the ONLY web-exposed folder (document root)
  index.php        #   front controller
  .htaccess        #   HTTPS + routing + security headers
  assets/          #   css / js / img
app/
  Controllers/     # Home, Auth, Dashboard
  Models/          # User, EmailVerification, PasswordReset, LoginAttempt
  Services/        # MailService, AuditLog
  Middleware/      # Locale, RateLimit, Csrf, Auth, Guest
  Support/         # reused skill helpers: db, csrf, validation, rate_limit, security_headers
  Views/           # layouts, auth, errors, home, dashboard
  Router.php  Request.php  bootstrap.php  helpers.php
config/            # app.php (reads .env), routes.php
database/
  migrations/      # dated .sql files
  migrate.php      # forward-only migration runner
lang/              # fr.php, en.php (no hard-coded UI text)
storage/           # logs, uploads, cache — above the webroot
```

Full details: `.claude/skills/afrikalink-builder/references/architecture.md`.

## Getting started (local)

Requires PHP 8.4 with `pdo_mysql`, `intl`, `mbstring`, `openssl`, `fileinfo`.

```bash
cp .env.example .env
# set APP_ENV=local, APP_DEBUG=true, an APP_KEY, and DB_* credentials:
php -r "echo 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# (optional) install Composer dependencies — the app also boots without them
composer install

# create the database, then apply migrations (uses the DDL account if configured)
php database/migrate.php

# serve the public/ folder (router arg enables clean URLs on the built-in server)
php -S 127.0.0.1:8000 -t public public/index.php
```

Open http://127.0.0.1:8000. Without SMTP configured, verification and password-reset
e-mails are written to `storage/logs/mail.log` (so you can test the full flow offline).

> The bootstrap ships a dependency-free `.env` loader, a PSR-4 autoloader and a UUID
> fallback, so the foundation runs even before `composer install`.

## Security (built in, not bolted on)

Every feature follows the golden rules in
`.claude/skills/afrikalink-builder/references/security.md`:

- Prepared PDO statements only — never concatenated SQL
- Output escaping (`e()`), input validation (`input_*`)
- CSRF token on every mutating request
- `password_hash()` / `password_verify()`, session id regenerated on login & logout
- Hardened session cookies (HttpOnly, SameSite=Lax, Secure in prod)
- Rate limiting on login / register / reset
- Single-use, hashed, expiring tokens for email verification & password reset
- Neutral auth responses (no account-existence disclosure), IDOR-safe access checks
- Security headers + CSP (`security_headers.php` and `public/.htaccess`)
- Secrets in `.env` (never committed); amounts stored as integer cents

## Roadmap

- **Phase 0 — Foundations & security** ✅ *(this branch)* — structure, secure bootstrap,
  router + middleware, i18n (FR/EN), accounts + full auth (register, login, logout,
  email verification, password reset), audit log, error pages.
- **Phase 1** — Sellers & verticals (vendor profiles, light KYC, public pages)
- **Phase 2** — Catalogue & content (products, menus, services + availability)
- **Phase 3** — Transactions (cart, orders, bookings, Stripe Connect, webhooks)
- **Phase 4** — International (multi-currency, shipping zones, taxes/VAT)
- **Phase 5** — Trust & growth (reviews, messaging, moderation, dashboards)
- **Phase 6** — Hardening & compliance (audit, alerting, GDPR/DSA, backups)
