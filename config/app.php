<?php
declare(strict_types=1);

/**
 * Application configuration. Values are read from the environment (.env) with
 * safe defaults. Access via config('app.<key>').
 */

// Session storage. Default 'file'. On serverless (Vercel) we switch to 'database'
// ONLY when a database is actually configured — otherwise the public pages (which
// need no DB) would fail trying to open a DB-backed session. Force with SESSION_DRIVER.
$dbConfigured = !empty($_ENV['DB_HOST']) && !empty($_ENV['DB_NAME']) && !empty($_ENV['DB_USER']);
$onServerless = !empty($_ENV['VERCEL']);
$sessionDriver = $_ENV['SESSION_DRIVER'] ?? (($onServerless && $dbConfigured) ? 'database' : 'file');

return [
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url'   => $_ENV['APP_URL'] ?? '',
    'key'   => $_ENV['APP_KEY'] ?? '',
    'session_driver' => $sessionDriver,

    'name'    => 'Afriklink',
    'support_email' => $_ENV['MAIL_FROM'] ?? 'no-reply@afriklink.com',

    // i18n / money. Language, country and currency are three independent axes.
    'default_locale'   => $_ENV['DEFAULT_LOCALE'] ?? 'fr',
    'default_currency' => $_ENV['DEFAULT_CURRENCY'] ?? 'EUR',
    'locales'          => ['fr', 'en'],
    'currencies'       => ['EUR', 'USD', 'XOF', 'NGN', 'GBP'],

    // Zero-decimal currencies (no minor unit) — never blindly /100 these.
    'zero_decimal_currencies' => ['XOF', 'XAF', 'JPY', 'KRW'],

    // Commission plateforme : SOURCE UNIQUE dans config/payment.php
    // ('platform_commission_pct' + helper platform_commission_cents()).

    // Token lifetimes (seconds).
    'email_verification_ttl' => 86400,   // 24h
    'password_reset_ttl'     => 3600,    // 1h

    // Password policy (see security.md §4).
    'password_min_length' => 12,

    // Relecteurs KYC (modérateurs/admins) par e-mail, en plus du rôle 'admin'.
    // Définir ADMIN_EMAILS="a@x.com,b@y.com" dans l'environnement (Vercel).
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) ($_ENV['ADMIN_EMAILS'] ?? ''))
    ))),
];
