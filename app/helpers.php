<?php
declare(strict_types=1);

/**
 * Global helper functions for AfrikaLink.
 *
 * Loaded once at bootstrap. Security-critical escaping/validation helpers live in
 * app/Support/validation.php (e(), input_*), CSRF in csrf.php, DB in db.php.
 * This file adds config, i18n, views, flash, auth and small utilities.
 */

/* ------------------------------------------------------------------ */
/* Configuration                                                       */
/* ------------------------------------------------------------------ */

/**
 * Read a config value with dot notation: config('app.debug').
 * First segment = file in config/, remaining segments = nested keys.
 */
function config(string $key, mixed $default = null): mixed
{
    static $cache = [];

    $segments = explode('.', $key);
    $file = array_shift($segments);

    if (!array_key_exists($file, $cache)) {
        $path = CONFIG_PATH . '/' . $file . '.php';
        $cache[$file] = is_file($path) ? require $path : [];
    }

    $value = $cache[$file];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/** Read an environment variable (loaded from .env) with a default. */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        default => $value,
    };
}

/* ------------------------------------------------------------------ */
/* Logging                                                             */
/* ------------------------------------------------------------------ */

/** Append a structured line to a log file in storage/logs. Never log secrets. */
function log_message(string $level, string $message, array $context = [], string $channel = 'app'): void
{
    $line = sprintf(
        "[%s] %s: %s%s\n",
        (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
    );
    $file = STORAGE_PATH . '/logs/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log';
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/* ------------------------------------------------------------------ */
/* Internationalisation (i18n)                                         */
/* ------------------------------------------------------------------ */

/** Get the active interface locale (e.g. 'fr', 'en'). */
function current_locale(): string
{
    static $locale = null;
    if ($locale === null) {
        $locale = config('app.default_locale', 'fr');
    }
    return $GLOBALS['__afrikalink_locale'] ?? $locale;
}

/** Set the active locale (validated against config('app.locales')). */
function set_locale(string $locale): void
{
    $allowed = config('app.locales', ['fr', 'en']);
    if (!in_array($locale, $allowed, true)) {
        $locale = config('app.default_locale', 'fr');
    }
    $GLOBALS['__afrikalink_locale'] = $locale;
    $GLOBALS['__afrikalink_translations'] = null; // force reload
}

/** Translate a key from lang/<locale>.php, with :placeholder replacement. */
function t(string $key, array $replace = []): string
{
    if (empty($GLOBALS['__afrikalink_translations'])) {
        $path = LANG_PATH . '/' . current_locale() . '.php';
        $GLOBALS['__afrikalink_translations'] = is_file($path) ? require $path : [];
    }
    $translations = $GLOBALS['__afrikalink_translations'];
    $text = $translations[$key] ?? $key;

    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}

/** Active display currency (cookie > user preference > default). */
function current_currency(): string
{
    return $GLOBALS['__afrikalink_currency'] ?? config('app.default_currency', 'EUR');
}

function set_currency(string $currency): void
{
    $allowed = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
    $currency = strtoupper($currency);
    $GLOBALS['__afrikalink_currency'] = in_array($currency, $allowed, true)
        ? $currency
        : config('app.default_currency', 'EUR');
}

/* ------------------------------------------------------------------ */
/* Identifiers                                                         */
/* ------------------------------------------------------------------ */

/** Generate a RFC-4122 v4 UUID (uses ramsey/uuid if installed, else native). */
function uuid(): string
{
    if (class_exists(\Ramsey\Uuid\Uuid::class)) {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/* ------------------------------------------------------------------ */
/* HTTP / URLs                                                          */
/* ------------------------------------------------------------------ */

/** Absolute URL for a path, based on APP_URL (falls back to current host). */
function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    if ($base === '') {
        $scheme = request_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Versioned URL for a public asset. The ?v=<mtime> suffix busts browser caches
 * whenever the file changes, so deployed JS/CSS updates are picked up immediately.
 */
function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $url = url($relative);
    $file = PUBLIC_PATH . '/' . $relative;
    if (is_file($file)) {
        $url .= '?v=' . (string) filemtime($file);
    }
    return $url;
}

/** True if the current request is served over HTTPS (incl. behind Cloudflare). */
function request_is_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') === 'on') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
}

/** Read an incoming HTTP request header by name (e.g. 'X-Vercel-IP-Country'). */
function request_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : '';
}

/* ------------------------------------------------------------------ */
/* Geolocation (auto-detect country/city, server-side, no JS)          */
/* ------------------------------------------------------------------ */

/**
 * Best-effort ISO-3166 alpha-2 country code from edge geo headers:
 * Vercel (x-vercel-ip-country) then Cloudflare (cf-ipcountry). '' if unknown.
 */
function detect_country_code(): string
{
    foreach (['X-Vercel-IP-Country', 'CF-IPCountry'] as $header) {
        $value = strtoupper(request_header($header));
        if ($value !== '' && $value !== 'XX' && preg_match('/^[A-Z]{2}$/', $value)) {
            return $value;
        }
    }
    return '';
}

/** Best-effort city from Vercel's geo header (URL-encoded). '' if unknown. */
function detect_city(): string
{
    $value = request_header('X-Vercel-IP-City');
    return $value === '' ? '' : trim(rawurldecode($value));
}

/** French country name for an ISO code, or the code itself if unknown. */
function country_name(string $code): string
{
    $code = strtoupper($code);
    $list = config('countries', []);
    return $list[$code] ?? $code;
}

/**
 * Parse a French birthdate "jj/mm/aaaa" into a Y-m-d string, or null if invalid.
 * Must be a real calendar date, in the past, and after 1900.
 */
function parse_birthdate_fr(string $value): ?string
{
    if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim($value), $m)) {
        return null;
    }
    [, $day, $month, $year] = array_map('intval', $m);
    if (!checkdate($month, $day, $year) || $year < 1900) {
        return null;
    }
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    return $date <= (new DateTimeImmutable('today'))->format('Y-m-d') ? $date : null;
}

/** International dialing code (without '+') for an ISO country, or '' if unknown. */
function dial_code(string $iso): string
{
    $map = config('dialing_codes', []);
    return $map[strtoupper($iso)] ?? '';
}

/** Flag emoji for an ISO-3166 alpha-2 country code ('' if invalid). */
function flag_emoji(string $iso): string
{
    $iso = strtoupper($iso);
    if (!preg_match('/^[A-Z]{2}$/', $iso)) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($iso[0]) - 65, 'UTF-8')
        . mb_chr(0x1F1E6 + ord($iso[1]) - 65, 'UTF-8');
}

/** Initiales pour l'avatar par défaut (« AD » pour « Awa Diop »). */
function user_initials(array $user): string
{
    $fullName = trim((string) ($user['full_name'] ?? ''));
    $nickname = (string) ($user['nickname'] ?? '');
    if ($fullName !== '') {
        $parts = preg_split('/\s+/u', $fullName) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
    if ($nickname !== '') {
        return mb_strtoupper(mb_substr($nickname, 0, 2));
    }
    return 'A';
}

/** URL de l'avatar d'un utilisateur, versionnée pour le cache, ou null. */
function avatar_url(array $user, ?string $version): ?string
{
    if ($version === null || empty($user['public_id'])) {
        return null;
    }
    return url('/avatar/' . $user['public_id']) . '?v=' . (string) strtotime($version);
}

/** Normalise a phone number to E.164-ish '+digits' (or '' if it has no digits). */
function normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    return $digits === '' ? '' : '+' . $digits;
}

/* ------------------------------------------------------------------ */
/* Prix (annonces)                                                     */
/* ------------------------------------------------------------------ */

/** Devises sans subdivision décimale en usage courant. */
function currency_is_integer(string $currency): bool
{
    return in_array(strtoupper($currency), ['XOF', 'NGN'], true);
}

/**
 * Parse un prix saisi ("12,50" ou "12.50" ou "15 000") vers des centimes.
 * Retourne null si invalide. Les devises entières (XOF…) refusent les décimales.
 */
function parse_price_to_cents(string $raw, string $currency): ?int
{
    $raw = str_replace([' ', "\u{202F}", "\u{00A0}"], '', trim($raw));
    $raw = str_replace(',', '.', $raw);
    if ($raw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }
    if (currency_is_integer($currency) && str_contains($raw, '.')) {
        return null;
    }
    return (int) round(((float) $raw) * 100);
}

/** Formate des centimes en prix lisible : « 12,50 € », « 15 000 F CFA ». */
function format_price(int $cents, string $currency): string
{
    $currency = strtoupper($currency);
    $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'];
    $symbol = $symbols[$currency] ?? $currency;

    if (currency_is_integer($currency)) {
        $amount = number_format(intdiv($cents, 100), 0, ',', ' ');
    } else {
        $amount = number_format($cents / 100, 2, ',', ' ');
        $amount = str_ends_with($amount, ',00') ? substr($amount, 0, -3) : $amount;
    }
    return $amount . ' ' . $symbol;
}

/** Send a redirect and stop execution. */
function redirect(string $path, int $status = 302): never
{
    $location = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $location, true, $status);
    exit;
}

/** Redirect back to the referring page (or a fallback). */
function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    // Only honour same-host referrers to avoid open-redirects.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && $host !== '' && str_contains($ref, $host)) {
        redirect($ref);
    }
    redirect($fallback);
}

/** Send a JSON response and stop. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------ */
/* Views                                                               */
/* ------------------------------------------------------------------ */

/** Render a view template inside a layout and echo it. */
function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
{
    $content = render_partial($template, $data);
    if ($layout !== null) {
        echo render_partial($layout, array_merge($data, ['content' => $content]));
    } else {
        echo $content;
    }
}

/** Render a template to a string (no layout). */
function render_partial(string $template, array $data = []): string
{
    $file = APP_PATH . '/Views/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("View not found: {$template}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Flash messages, old input, validation errors                        */
/* ------------------------------------------------------------------ */

/** Queue a flash message ('success' | 'error' | 'info'). */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type][] = $message;
}

/** Pull and clear all flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/** Remember submitted input so a form can be re-populated after a redirect. */
function keep_old(array $input, array $except = ['password', 'password_confirm', 'csrf_token']): void
{
    foreach ($except as $key) {
        unset($input[$key]);
    }
    $_SESSION['_old'] = $input;
}

/** Previously submitted value for a field. */
function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return e(is_string($value) ? $value : $default);
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/** Store validation errors (keyed by field). */
function set_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

/** All current validation errors (pulled once, then cleared). */
function errors(): array
{
    static $pulled = null;
    if ($pulled === null) {
        $pulled = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
    }
    return $pulled;
}

function error(string $key): ?string
{
    return errors()[$key] ?? null;
}

function has_error(string $key): bool
{
    return isset(errors()[$key]);
}

/* ------------------------------------------------------------------ */
/* Authentication                                                      */
/* ------------------------------------------------------------------ */

/** The currently authenticated user (array) or null. Cached per request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) {
        return $user = null;
    }
    return $user = \App\Models\User::findById((int) $id);
}

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return $id === null ? null : (int) $id;
}

function auth_check(): bool
{
    return current_user_id() !== null;
}

/** Relecteur KYC : rôle 'admin'/'moderator' OU e-mail listé dans ADMIN_EMAILS. */
function is_staff(?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) {
        return false;
    }
    if (in_array($user['role'] ?? '', ['admin', 'moderator'], true)) {
        return true;
    }
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    return $email !== '' && in_array($email, config('app.admin_emails', []), true);
}

/** Establish an authenticated session for a user id (regenerates session id). */
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['__created'] = time();
}

/**
 * Log the current user out: drop all session data and rotate the session id.
 * (Clearing + regenerating — rather than a hard destroy — keeps the session alive
 * so a post-logout flash message can be shown, while invalidating the old id.)
 */
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['__created'] = time();
}

/** Stop with an HTTP error (404 is used to hide forbidden resources — see security.md §5). */
function abort(int $status, string $message = ''): never
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $view = 'errors/' . $status;
    // Error pages render WITHOUT the app layout so they never depend on the DB/session.
    if (is_file(APP_PATH . '/Views/' . $view . '.php')) {
        view($view, ['message' => $message], null);
    } else {
        echo $message !== '' ? e($message) : 'Error ' . $status;
    }
    exit;
}
