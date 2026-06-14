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

/** Suffixe d'accord féminin (« e ») selon le genre de la personne connectée. */
function user_gender_suffix(): string
{
    try {
        $u = current_user();
    } catch (\Throwable) {
        return '';
    }
    return (($u['gender'] ?? '') === 'femme') ? 'e' : '';
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

    // Accord en genre : « :fe » devient « e » pour une utilisatrice, rien sinon
    // (ex. « Connecté:fe » → « Connectée » / « Connecté »). Le genre peut être
    // forcé via $replace['fe'] quand la session n'est pas encore en place (login).
    if (str_contains($text, ':fe')) {
        $fe = array_key_exists('fe', $replace) ? (string) $replace['fe'] : user_gender_suffix();
        $text = str_replace(':fe', $fe, $text);
        unset($replace['fe']);
    }

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

/**
 * Localisation détectée pour la session courante. Automatique et sans
 * permission : déduite des en-têtes edge (Vercel/Cloudflare) à la première
 * visite, puis mémorisée en session. La position GPS précise (avec
 * permission de l'utilisateur) l'enrichit via /api/geo/session (source 'gps').
 * @return array{city:?string,country_code:?string,country:?string,continent:?string,lat:?float,lng:?float,source:string}
 */
function detected_geo(): array
{
    if (isset($_SESSION['geo']) && is_array($_SESSION['geo'])) {
        return $_SESSION['geo'];
    }
    // Utilisateur connecté : sa localisation détectée enregistrée le suit
    // partout (toute visite, tout appareil) et prime sur la géo par IP.
    $u = current_user();
    if ($u !== null && !empty($u['geo_country_code'])) {
        $cc = strtoupper((string) $u['geo_country_code']);
        $geo = [
            'city'         => ($u['geo_city'] ?? '') !== '' ? (string) $u['geo_city'] : null,
            'country_code' => $cc,
            'country'      => country_name($cc),
            'continent'    => ($u['geo_continent'] ?? '') !== '' ? (string) $u['geo_continent'] : \App\Services\GeoService::continentOf($cc),
            'lat'          => isset($u['geo_lat']) && is_numeric($u['geo_lat']) ? (float) $u['geo_lat'] : null,
            'lng'          => isset($u['geo_lng']) && is_numeric($u['geo_lng']) ? (float) $u['geo_lng'] : null,
            'source'       => 'saved',
        ];
        $_SESSION['geo'] = $geo;
        return $geo;
    }
    $cc   = detect_country_code() ?: null;
    $city = detect_city() ?: null;
    $lat  = request_header('X-Vercel-IP-Latitude');
    $lng  = request_header('X-Vercel-IP-Longitude');
    $geo = [
        'city'         => $city,
        'country_code' => $cc,
        'country'      => $cc !== null ? country_name($cc) : null,
        'continent'    => \App\Services\GeoService::continentOf($cc),
        'lat'          => is_numeric($lat) ? (float) $lat : null,
        'lng'          => is_numeric($lng) ? (float) $lng : null,
        'source'       => ($cc !== null || $city !== null) ? 'ip' : 'unknown',
    ];
    if ($geo['source'] !== 'unknown') {
        $_SESSION['geo'] = $geo; // on ne mémorise pas un échec (réessai au prochain coup)
    }
    return $geo;
}

/** Mémorise une localisation précise (GPS) pour la session. */
function set_session_geo(array $geo): void
{
    $_SESSION['geo'] = $geo;
}

/** French country name for an ISO code, or the code itself if unknown. */
function country_name(string $code): string
{
    $code = strtoupper($code);
    $list = config('countries', []);
    return $list[$code] ?? $code;
}

/**
 * Étiquette d'horaires d'un restaurant à partir des champs structurés :
 * jours cochés regroupés (« Lun–Mer, Ven–Sam ») + plage horaire
 * (« 11:00–23:00 »). Repli sur l'ancien texte libre si rien de structuré.
 */
function resto_hours_label(?string $daysCsv, ?string $open, ?string $close, ?string $legacy = null): string
{
    $order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $days = array_values(array_intersect($order, array_filter(explode(',', (string) $daysCsv))));
    if ($days === []) {
        return trim((string) $legacy);
    }
    $idx = array_flip($order);
    $groups = [];
    $start = $prev = null;
    foreach ($days as $d) {
        if ($start === null) {
            $start = $prev = $d;
            continue;
        }
        if ($idx[$d] === $idx[$prev] + 1) {
            $prev = $d;
            continue;
        }
        $groups[] = [$start, $prev];
        $start = $prev = $d;
    }
    $groups[] = [$start, $prev];
    $parts = array_map(
        static fn (array $g): string => $g[0] === $g[1]
            ? t('resto.day.' . $g[0])
            : t('resto.day.' . $g[0]) . '–' . t('resto.day.' . $g[1]),
        $groups
    );
    $label = implode(', ', $parts);
    if ($open !== null && $open !== '' && $close !== null && $close !== '') {
        $label .= ' · ' . $open . '–' . $close;
    }
    return $label;
}

/**
 * Label of a delivery zone, personalised with the shop's geolocated city /
 * country when known ("🏠 Dakar", "🌍 Sénégal"), generic otherwise.
 */
function shop_zone_label(string $zone, ?string $city = null, ?string $countryCode = null): string
{
    if ($zone === 'city' && $city !== null && $city !== '') {
        return '🏠 ' . $city;
    }
    if ($zone === 'country' && $countryCode !== null && $countryCode !== '') {
        return '🌍 ' . country_name($countryCode);
    }
    return t('shop.zone.' . $zone);
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

/**
 * URL du logo officiel d'un réseau social (fichiers de public/assets/img/social).
 * Utilisé partout où une marque sociale est représentée (partage, boutons
 * WhatsApp, contacts…). '' si le réseau est inconnu.
 */
function social_logo(string $network): string
{
    $files = [
        'whatsapp'  => 'whatsapp.svg',
        'sms'       => 'sms.svg',
        'telegram'  => 'telegram.svg',
        'facebook'  => 'facebook.png',
        'instagram' => 'instagram.svg',
        'tiktok'    => 'tiktok.png',
    ];
    $file = $files[strtolower($network)] ?? null;
    return $file !== null ? asset('img/social/' . $file) : '';
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

/** URL slug : minuscules, accents retirés, séparés par des tirets. */
function slugify(string $text): string
{
    $map = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','œ'=>'oe','æ'=>'ae','ß'=>'ss',
    ];
    $text = strtr(mb_strtolower(trim($text)), $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
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

/**
 * Commission de la plateforme (en centimes) sur un sous-total donné.
 * SOURCE UNIQUE : config('payment.platform_commission_pct') (env PLATFORM_COMMISSION_PCT,
 * défaut 5 %). Bornée à [0 ; sous-total]. C'est ICI — et nulle part ailleurs — que se
 * calcule la part Afriklink, à brancher sur application_fee (Stripe) / split (CinetPay)
 * lors de l'encaissement réel.
 */
function platform_commission_cents(int $subtotalCents): int
{
    if ($subtotalCents <= 0) {
        return 0;
    }
    $pct = max(0.0, min(100.0, (float) config('payment.platform_commission_pct', 5.0)));
    return (int) min($subtotalCents, (int) round($subtotalCents * $pct / 100));
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

/**
 * Icône « outline » inline (SVG), famille cohérente définie dans config/icons.php.
 * Inline = compatible CSP stricte (pas de police d'icônes). Le trait suit la
 * couleur du texte (currentColor). Usage : icon('store'), icon('bell', ['size'=>18]).
 */
function icon(string $name, array $attr = []): string
{
    static $set = null;
    if ($set === null) {
        $set = is_file(CONFIG_PATH . '/icons.php') ? require CONFIG_PATH . '/icons.php' : [];
    }
    $inner = $set[$name] ?? null;
    if ($inner === null) {
        return '';
    }
    $size = (int) ($attr['size'] ?? 20);
    $cls  = 'icon icon-' . preg_replace('/[^a-z0-9_-]/', '', $name);
    if (!empty($attr['class'])) {
        $cls .= ' ' . $attr['class'];
    }
    return '<svg class="' . e($cls) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $inner . '</svg>';
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

/** Previously submitted array value (e.g. checkboxes). @return list<string> */
function old_array(string $key): array
{
    $value = $_SESSION['_old'][$key] ?? null;
    return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
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
    $found = \App\Models\User::findById((int) $id);
    if ($found === null) {
        // La session pointe vers un compte qui n'existe plus (supprimé) : on la
        // nettoie pour que toute la requête soit cohérente (= déconnecté). Sinon
        // l'utilisateur reste « à moitié connecté » : boutons invités affichés,
        // mais redirigé vers le tableau de bord (middleware guest).
        unset($_SESSION['user_id']);
        return $user = null;
    }
    return $user = $found;
}

function current_user_id(): ?int
{
    // Source de vérité = current_user() (vérifie l'existence en base) pour que
    // auth_check() et les vues ne se contredisent jamais.
    $user = current_user();
    return $user === null ? null : (int) ($user['id'] ?? 0);
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
