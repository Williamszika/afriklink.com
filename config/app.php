<?php
declare(strict_types=1);

/**
 * Application configuration. Values are read from the environment (.env) with
 * safe defaults. Access via config('app.<key>').
 */

return [
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url'   => $_ENV['APP_URL'] ?? '',
    'key'   => $_ENV['APP_KEY'] ?? '',

    'name'    => 'AfrikaLink',
    'support_email' => $_ENV['MAIL_FROM'] ?? 'no-reply@afrikalink.example',

    // i18n / money. Language, country and currency are three independent axes.
    'default_locale'   => $_ENV['DEFAULT_LOCALE'] ?? 'fr',
    'default_currency' => $_ENV['DEFAULT_CURRENCY'] ?? 'EUR',
    'locales'          => ['fr', 'en'],
    'currencies'       => ['EUR', 'USD', 'XOF', 'NGN', 'GBP'],

    // Zero-decimal currencies (no minor unit) — never blindly /100 these.
    'zero_decimal_currencies' => ['XOF', 'XAF', 'JPY', 'KRW'],

    // Marketplace commission, basis points (1000 = 10%).
    'platform_fee_bps' => (int) ($_ENV['PLATFORM_FEE_BPS'] ?? 1000),

    // Token lifetimes (seconds).
    'email_verification_ttl' => 86400,   // 24h
    'password_reset_ttl'     => 3600,    // 1h

    // Password policy (see security.md §4).
    'password_min_length' => 12,
];
