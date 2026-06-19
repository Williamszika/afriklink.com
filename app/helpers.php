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

/**
 * Devise d'affichage par défaut pour un pays (ISO-3166). Sert à initialiser la
 * devise du VISITEUR depuis sa géolocalisation : un Ivoirien voit les prix en
 * XOF, un Français en EUR, etc. Ne renvoie qu'une devise réellement supportée,
 * ou null si le pays n'a pas de correspondance (l'appelant utilise le défaut).
 */
function currency_for_country(?string $cc): ?string
{
    $cc = strtoupper(trim((string) $cc));
    static $map = [
        'NG' => 'NGN', 'GB' => 'GBP', 'US' => 'USD',
        // UEMOA — franc CFA Ouest (XOF)
        'BJ' => 'XOF', 'BF' => 'XOF', 'CI' => 'XOF', 'GW' => 'XOF',
        'ML' => 'XOF', 'NE' => 'XOF', 'SN' => 'XOF', 'TG' => 'XOF',
        // Zone euro
        'FR' => 'EUR', 'DE' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'PT' => 'EUR',
        'BE' => 'EUR', 'NL' => 'EUR', 'IE' => 'EUR', 'AT' => 'EUR', 'FI' => 'EUR',
        'GR' => 'EUR', 'LU' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'EE' => 'EUR',
        'LV' => 'EUR', 'LT' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR',
    ];
    $cur = $map[$cc] ?? null;
    if ($cur === null) {
        return null;
    }
    return in_array($cur, config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']), true) ? $cur : null;
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
    static $ver = [];
    $relative = 'assets/' . ltrim($path, '/');
    $url = url($relative);
    $file = PUBLIC_PATH . '/' . $relative;
    if (is_file($file)) {
        // Cache-busting par HASH DE CONTENU (pas le mtime) : la version change
        // dès que le fichier change — et seulement alors. Robuste même quand
        // l'hébergeur fige les mtime au déploiement (ex. Vercel), ce qui rendait
        // l'ancien ?v=filemtime constant → CSS/JS servis périmés jusqu'à un
        // vidage de cache forcé. Mémoïsé par requête.
        if (!isset($ver[$file])) {
            $hash = md5_file($file);
            $ver[$file] = $hash !== false ? substr($hash, 0, 10) : (string) filemtime($file);
        }
        $url .= '?v=' . $ver[$file];
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

/**
 * Libellé de lieu « Ville · 🇸🇳 Pays » — la VILLE avant le pays. Les parties
 * vides sont ignorées. Renvoie du texte brut (à échapper par l'appelant).
 */
function place_label(?string $city, ?string $countryCode): string
{
    $parts = [];
    $city = trim((string) $city);
    if ($city !== '') {
        $parts[] = $city;
    }
    $cc = strtoupper(trim((string) $countryCode));
    if (preg_match('/^[A-Z]{2}$/', $cc) === 1) {
        $parts[] = trim(flag_emoji($cc) . ' ' . country_name($cc));
    }
    return implode(' · ', $parts);
}

/**
 * Opérateurs Mobile Money disponibles dans un pays (noms de marque), pour
 * afficher à la caisse les moyens de paiement adaptés à l'acheteur (via
 * CinetPay). Liste vide hors zones Mobile Money (Europe…) → seule la carte
 * s'applique. Indicatif (le choix final se fait sur la page CinetPay).
 * @return list<string>
 */
function country_mobile_money(?string $cc): array
{
    static $map = [
        'CI' => ['Orange Money', 'MTN MoMo', 'Moov Money', 'Wave'],
        'SN' => ['Orange Money', 'Wave', 'Free Money'],
        'BJ' => ['MTN MoMo', 'Moov Money'],
        'BF' => ['Orange Money', 'Moov Money'],
        'ML' => ['Orange Money', 'Moov Money'],
        'TG' => ['T-Money', 'Moov Money'],
        'NE' => ['Airtel Money', 'Moov Money'],
        'GW' => ['Orange Money'],
        'GN' => ['Orange Money', 'MTN MoMo'],
        'CM' => ['Orange Money', 'MTN MoMo'],
        'CD' => ['Orange Money', 'Airtel Money', 'M-Pesa'],
        'CG' => ['Airtel Money', 'MTN MoMo'],
    ];
    return $map[strtoupper(trim((string) $cc))] ?? [];
}

/* ------------------------------------------------------------------ */
/* Livraison / transporteurs                                           */
/* ------------------------------------------------------------------ */

/**
 * Transporteurs proposés au vendeur à l'expédition.
 * @return array<string,string> clé => libellé affichable
 */
function delivery_carriers(): array
{
    $out = [];
    foreach ((array) config('delivery.carriers', []) as $key => $c) {
        $out[(string) $key] = carrier_label((string) $key);
    }
    return $out;
}

/** Libellé affichable d'un transporteur (le « générique » est traduit). */
function carrier_label(?string $key): string
{
    $key = trim((string) $key);
    if ($key === '') {
        return '';
    }
    $c = config('delivery.carriers.' . $key);
    if (!is_array($c)) {
        return $key;
    }
    return (string) ($c['label'] ?? '') !== '' ? (string) $c['label'] : t('order.carrier.other');
}

/**
 * Lien de suivi cliquable construit depuis le gabarit du transporteur et le
 * numéro de suivi ({tracking} = numéro URL-encodé), ou null si le transporteur
 * n'a pas de gabarit (coursier local…) ou si le numéro est vide.
 */
function carrier_tracking_url(?string $carrier, ?string $number): ?string
{
    $number = trim((string) $number);
    $tpl = config('delivery.carriers.' . trim((string) $carrier) . '.url');
    if ($number === '' || !is_string($tpl) || $tpl === '') {
        return null;
    }
    return str_replace('{tracking}', rawurlencode($number), $tpl);
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
 * Promotion produit : le vendeur fixe un prix réduit (promo_price_cents) avec une
 * fin facultative (promo_until). La promo est active si le prix réduit est > 0,
 * strictement inférieur au prix normal, et non expirée.
 */
function product_promo_active(array $product): bool
{
    $promo = (int) ($product['promo_price_cents'] ?? 0);
    $price = (int) ($product['price_cents'] ?? 0);
    if ($promo <= 0 || $price <= 0 || $promo >= $price) {
        return false;
    }
    $until = $product['promo_until'] ?? null;
    return $until === null || $until === '' || strtotime((string) $until) >= time();
}

/** Pourcentage de réduction affiché (ex. 25). 0 si pas de promo active. */
function product_promo_pct(array $product): int
{
    if (!product_promo_active($product)) {
        return 0;
    }
    $price = (int) $product['price_cents'];
    $promo = (int) $product['promo_price_cents'];
    return (int) round(($price - $promo) / $price * 100);
}

/**
 * Prix effectif (centimes) d'une ligne en appliquant la promo produit au prix de
 * base (produit OU variante). La réduction s'applique proportionnellement, donc
 * une variante au prix différent est remisée du même pourcentage. Toujours ≤ base.
 */
function product_effective_unit_cents(array $product, int $basePriceCents): int
{
    if (!product_promo_active($product)) {
        return $basePriceCents;
    }
    $price = (int) $product['price_cents'];
    $promo = (int) $product['promo_price_cents'];
    $eff   = (int) round($basePriceCents * $promo / $price);
    return max(0, min($basePriceCents, $eff));
}

/* ---------- Prêt-à-porter : genres, catégories, systèmes de tailles ---------- */

/** @return list<string> Publics visés (homme, femme, unisexe, enfant). */
function apparel_audiences(): array
{
    return (array) config('apparel.audiences', []);
}

/** @return array<string,array> Catégories brutes : key => [groupe, système, unité, genres]. */
function apparel_categories(): array
{
    return (array) config('apparel.categories', []);
}

/** Détails d'une catégorie : ['group','size_system','unit','audiences'] ou null. */
function apparel_category(?string $key): ?array
{
    if ($key === null || $key === '') {
        return null;
    }
    $c = apparel_categories()[$key] ?? null;
    if (!is_array($c)) {
        return null;
    }
    return [
        'group'       => (string) ($c[0] ?? ''),
        'size_system' => (string) ($c[1] ?? 'alpha'),
        'unit'        => (string) ($c[2] ?? 'piece'),
        'audiences'   => (array) ($c[3] ?? []),
    ];
}

/** Unité de vente d'une catégorie : 'piece' (défaut) ou 'meter' (tissus). */
function apparel_category_unit(?string $key): string
{
    return apparel_category($key)['unit'] ?? 'piece';
}

/** @return list<string> Suggestions de tailles pour un système donné. */
function apparel_size_suggestions(string $system): array
{
    if ($system === 'bra') {
        $out = [];
        foreach (['80', '85', '90', '95', '100', '105', '110'] as $band) {
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $cup) {
                $out[] = $band . $cup;
            }
        }
        return $out;
    }
    return (array) (config('apparel.size_systems')[$system] ?? []);
}

/** @return array<string,list<string>> Carte système => suggestions (pour le JS du formulaire). */
function apparel_size_map(): array
{
    $out = [];
    foreach (array_keys((array) config('apparel.size_systems', [])) as $sys) {
        $out[$sys] = apparel_size_suggestions((string) $sys);
    }
    return $out;
}

/* ---------- Mode : rayons adaptatifs au type (Chaussures, …) — moteur type-driven ---------- */
/** @return list<string> Libellés des rayons mode adaptatifs (clés de apparel.rayons). */
function apparel_rayons(): array { return array_keys((array) config('apparel.rayons', [])); }
/** Le rayon mode est-il piloté par le moteur adaptatif au type ? */
function apparel_is_rayon(?string $rayon): bool { return isset(((array) config('apparel.rayons', []))[(string) $rayon]); }
/**
 * Sous-config d'un rayon mode (ou la valeur d'une clé : groups, fields, types, atouts, quickfill…).
 * @return array<string,mixed>
 */
function apparel_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) (((array) config('apparel.rayons', []))[(string) $rayon] ?? []);
    if ($key === null) { return $cfg; }
    return is_array($cfg[$key] ?? null) ? $cfg[$key] : [];
}
/** @return array<string,array{label:string,opts:list<string>}> */
function apparel_fields(?string $rayon): array { return apparel_rayon($rayon, 'fields'); }
/** @return array<string,array{group?:string,fields:list<string>}> */
function apparel_types(?string $rayon): array { return apparel_rayon($rayon, 'types'); }
/** @return array<string,string> */
function apparel_groups(?string $rayon): array { return apparel_rayon($rayon, 'groups'); }
/** @return list<string> */
function apparel_rayon_atouts(?string $rayon): array { return apparel_rayon($rayon, 'atouts'); }
/** Remplissage rapide brut du rayon (liste statique, ou map genre => boutons). */
function apparel_quickfill(?string $rayon): array { return apparel_rayon($rayon, 'quickfill'); }
/**
 * Boutons de remplissage rapide pour un genre donné : liste plate (statique, indépendante du
 * genre) renvoyée telle quelle ; map par genre => seuls les boutons du genre (vide si aucun).
 * @return list<array<string,mixed>>
 */
function apparel_quickfill_for(?string $rayon, ?string $genre): array
{
    $qf = apparel_quickfill($rayon);
    if ($qf === []) { return []; }
    if (array_is_list($qf)) { return $qf; } // statique
    return array_values((array) ($qf[(string) $genre] ?? [])); // dépendant du genre
}
/**
 * Boutons de remplissage RÉSOLUS selon le type : si le type impose une taille ('sizes'),
 * renvoie le jeu de tailles du rayon ; sinon, en mode couleur (type 'color' + palette du
 * rayon), renvoie des pastilles de coloris ; sinon, repli sur le remplissage par genre.
 * @return list<array<string,mixed>>
 */
function apparel_quickfill_resolved(?string $rayon, ?string $type, ?string $genre): array
{
    $meta = apparel_type_meta($rayon, $type);
    if ($meta !== null) {
        if (!empty($meta['sizes'])) {
            $sets = apparel_rayon($rayon, 'sizesets');
            return array_values((array) ($sets[(string) $meta['sizes']] ?? []));
        }
        $pal = apparel_rayon($rayon, 'palette');
        if ($pal !== [] && !empty($meta['color'])) {
            $out = [];
            foreach ($pal as $pc) { $pc = array_values((array) $pc); $out[] = ['label' => (string) ($pc[0] ?? ''), 'kind' => 'color', 'hex' => (string) ($pc[1] ?? '#222222')]; }
            return $out;
        }
    }
    return apparel_quickfill_for($rayon, $genre);
}
/** Publics proposés pour un type (override 'pub' du type, sinon les genres du rayon). @return list<string> */
function apparel_type_public(?string $rayon, ?string $type): array
{
    $meta = apparel_type_meta($rayon, $type);
    if ($meta !== null && !empty($meta['pub']) && is_array($meta['pub'])) { return array_values($meta['pub']); }
    return apparel_rayon_genres($rayon);
}
/** @return list<string> */
function apparel_conditions(): array { return (array) config('apparel.conditions', []); }
/** États proposés par un rayon (override 'conditions' du rayon, sinon les états globaux). @return list<string> */
function apparel_rayon_conditions(?string $rayon): array
{
    $c = apparel_rayon($rayon, 'conditions');
    return $c !== [] ? array_values($c) : apparel_conditions();
}

/* ---------- Mode : « nouveau rayon » générique adaptatif au slug ---------- */
/** Config « nouveau rayon » mode (rayon_suggest, generic_specs, atout_suggest, couleurs, *_sizes, R). */
function apparel_autre(?string $key = null): array
{
    $cfg = (array) config('apparel.autre', []);
    if ($key === null) { return $cfg; }
    return is_array($cfg[$key] ?? null) ? $cfg[$key] : [];
}
/** Config adaptative d'un rayon mode « autre » par libellé (slug) — ou null si inconnu. */
function apparel_autre_cfg(?string $rayon): ?array
{
    $r = (apparel_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}
/** Publics autorisés pour un rayon « autre » selon 'pub' (all|femme|none). @return list<string> */
function apparel_autre_genres(?string $rayon): array
{
    $pub = (string) (apparel_autre_cfg($rayon)['pub'] ?? 'all');
    if ($pub === 'femme') { return ['Femme']; }
    if ($pub === 'none') { return ['Non applicable']; }
    return apparel_genres();
}
/** Boutons de remplissage d'un rayon « autre » : au mètre (sizes='metre') ou par genre. @return list<array<string,mixed>> */
function apparel_autre_quickfill(?string $rayon, ?string $genre): array
{
    $cfg = apparel_autre_cfg($rayon);
    if ($cfg !== null && ($cfg['sizes'] ?? '') === 'metre') { return apparel_autre('metre_sizes'); }
    return array_values((array) ((apparel_autre('genre_sizes'))[(string) $genre] ?? []));
}
/** Genres globaux (tous publics). @return list<string> */
function apparel_genres(): array { return (array) config('apparel.genres', []); }
/** Genres proposés par un rayon (override 'genres' du rayon, sinon les genres globaux). @return list<string> */
function apparel_rayon_genres(?string $rayon): array
{
    $g = apparel_rayon($rayon, 'genres');
    return $g !== [] ? array_values($g) : apparel_genres();
}
/** Couleurs proposées par un rayon (override 'couleurs' du rayon, sinon les couleurs globales). @return list<string> */
function apparel_rayon_couleurs(?string $rayon): array
{
    $c = apparel_rayon($rayon, 'couleurs');
    return $c !== [] ? array_values($c) : apparel_couleurs();
}
/** Public imposé par un rayon verrouillé ('feminin'…) ou '' si non verrouillé. */
function apparel_rayon_public(?string $rayon): string
{
    $cfg = (array) (((array) config('apparel.rayons', []))[(string) $rayon] ?? []);
    return (string) ($cfg['public'] ?? '');
}
/** @return list<string> */
function apparel_couleurs(): array { return (array) config('apparel.couleurs', []); }
/** @return list<string> */
function apparel_axes(): array { return (array) config('apparel.axes', []); }
/** Métadonnées d'un type de produit mode (champs, groupe) — ou null si inconnu. */
function apparel_type_meta(?string $rayon, ?string $type): ?array
{
    $t = apparel_types($rayon);
    return isset($t[(string) $type]) && is_array($t[(string) $type]) ? $t[(string) $type] : null;
}
/**
 * Nettoie les caractéristiques d'un produit mode selon le type : ne garde que les champs
 * autorisés et les valeurs présentes dans les options (whitelist par type).
 * @param array<string,mixed> $attrs
 * @return array<string,string>
 */
function apparel_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = apparel_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $fields = apparel_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $fk) {
        $val = trim((string) ($attrs[$fk] ?? ''));
        if ($val === '' || !isset($fields[$fk])) { continue; }
        if (in_array($val, (array) ($fields[$fk]['opts'] ?? []), true)) { $out[$fk] = $val; }
    }
    return $out;
}

/** Valide un genre soumis ('' = non précisé). */
function apparel_audience_clean(?string $v): string
{
    $v = (string) $v;
    return in_array($v, apparel_audiences(), true) ? $v : '';
}

/** Valide une catégorie soumise ('' = non précisée). */
function apparel_category_clean(?string $v): string
{
    $v = (string) $v;
    return isset(apparel_categories()[$v]) ? $v : '';
}

/* ---------- Téléphones / électronique ---------- */

/** @return list<string> */
function phone_brands(): array
{
    return (array) config('phone.brands', []);
}

/** @return list<string> Capacités de stockage (la « taille » des déclinaisons téléphone). */
function phone_storage(): array
{
    return (array) config('phone.storage', []);
}

/** @return list<string> */
function phone_ram(): array
{
    return (array) config('phone.ram', []);
}

/** @return list<string> Clés d'état (neuf, comme_neuf, occasion, reconditionne). */
function phone_conditions(): array
{
    return (array) config('phone.conditions', []);
}

/** Valide un état soumis ('' = non précisé). */
function phone_condition_clean(?string $v): string
{
    $v = (string) $v;
    return in_array($v, phone_conditions(), true) ? $v : '';
}

/* ---------- Électronique : rayons adaptatifs au type (Accessoires, Audio…) ---------- */

/** Sous-config électronique par clé commune (conditions, garanties, axes). */
function elec(?string $key = null): array
{
    $cfg = (array) config('electronics', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return list<string> Libellés des rayons électroniques type-driven. */
function elec_rayons(): array { return array_keys((array) config('electronics.rayons', [])); }
/** Ce rayon a-t-il un formulaire électronique adaptatif ? */
function elec_is_rayon(?string $rayon): bool { return isset(((array) config('electronics.rayons', []))[(string) $rayon]); }
/** Sous-config d'un rayon électronique par clé. */
function elec_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('electronics.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> Champs techniques du rayon. */
function elec_fields(?string $rayon): array { return elec_rayon($rayon, 'fields'); }
/** @return array<string,array> Types du rayon. */
function elec_types(?string $rayon): array { return elec_rayon($rayon, 'types'); }
/** @return array<string,string> Groupes (optgroups) du rayon. */
function elec_groups(?string $rayon): array { return elec_rayon($rayon, 'groups'); }
/** @return list<string> Atouts du rayon. */
function elec_atouts(?string $rayon): array { return elec_rayon($rayon, 'atouts'); }
/** @return list<string> Capteurs (santé) disponibles pour le rayon (ex. montres). */
function elec_sensors(?string $rayon): array { return elec_rayon($rayon, 'sensors'); }

/** Config « autre / nouveau rayon » électronique (rayon_suggest, generic_specs, atout_suggest, warn_text, R). */
function elec_autre(?string $key = null): array
{
    $cfg = (array) config('electronics.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}
/** Config adaptative d'un rayon électronique « autre » par son libellé (slug) — ou null si inconnu. */
function elec_autre_cfg(?string $rayon): ?array
{
    $r = (elec_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}
/** @return list<string> */
function elec_conditions(): array { return (array) config('electronics.conditions', []); }
/** @return list<string> */
function elec_garanties(): array { return (array) config('electronics.garanties', []); }
/** @return list<string> */
function elec_axes(): array { return (array) config('electronics.axes', []); }

/** Métadonnées d'un type pour un rayon donné, ou null. */
function elec_type_meta(?string $rayon, ?string $type): ?array
{
    $t = elec_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un produit électronique : champs du type validés.
 * @return array<string,string>
 */
function elec_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = elec_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = elec_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Cuisine (Maison & meubles) : rayon adaptatif au type ---------- */

/** @return list<string> Catégories de boutique qui proposent le rayon Cuisine adaptatif. */
function cuisine_shop_categories(): array { return (array) config('cuisine.shop_categories', ['maison']); }

/** La boutique (catégorie) propose-t-elle le formulaire Cuisine adaptatif ? */
function cuisine_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, cuisine_shop_categories(), true); }

/** @return list<string> Libellés des rayons cuisine type-driven. */
function cuisine_rayons(): array { return array_keys((array) config('cuisine.rayons', [])); }

/** Ce rayon a-t-il un formulaire cuisine adaptatif ? */
function cuisine_is_rayon(?string $rayon): bool { return isset(((array) config('cuisine.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon cuisine par clé. */
function cuisine_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('cuisine.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function cuisine_fields(?string $rayon): array { return cuisine_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function cuisine_types(?string $rayon): array { return cuisine_rayon($rayon, 'types'); }

/** @return array<string,string> */
function cuisine_groups(?string $rayon): array { return cuisine_rayon($rayon, 'groups'); }

/** @return list<string> */
function cuisine_atouts(?string $rayon): array { return cuisine_rayon($rayon, 'atouts'); }

/** @return list<string> */
function cuisine_conditions(): array { return (array) config('cuisine.conditions', []); }

/** @return list<string> */
function cuisine_garanties(): array { return (array) config('cuisine.garanties', []); }

/** Métadonnées d'un type pour un rayon cuisine, ou null. */
function cuisine_type_meta(?string $rayon, ?string $type): ?array
{
    $t = cuisine_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un article de cuisine : champs du type validés.
 * @return array<string,string>
 */
function cuisine_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = cuisine_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = cuisine_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/** Config « nouveau rayon » Maison (rayon_suggest, generic_specs, atout_suggest, warn_text, R). */
function cuisine_autre(?string $key = null): array
{
    $cfg = (array) config('cuisine.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Maison « autre » par son libellé (slug) — ou null si inconnu. */
function cuisine_autre_cfg(?string $rayon): ?array
{
    $r = (cuisine_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/* ---------- Alimentation : rayons adaptatifs au type (Bio & naturel…) ---------- */

/** @return list<string> Catégories de boutique proposant les rayons alimentaires adaptatifs. */
function alim_shop_categories(): array { return (array) config('alimentation.shop_categories', ['alimentation']); }

/** La boutique (catégorie) propose-t-elle le formulaire alimentaire adaptatif ? */
function alim_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, alim_shop_categories(), true); }

/** @return list<string> Libellés des rayons alimentaires type-driven. */
function alim_rayons(): array { return array_keys((array) config('alimentation.rayons', [])); }

/** Ce rayon a-t-il un formulaire alimentaire adaptatif ? */
function alim_is_rayon(?string $rayon): bool { return isset(((array) config('alimentation.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon alimentaire par clé. */
function alim_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('alimentation.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function alim_fields(?string $rayon): array { return alim_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function alim_types(?string $rayon): array { return alim_rayon($rayon, 'types'); }

/** @return array<string,string> */
function alim_groups(?string $rayon): array { return alim_rayon($rayon, 'groups'); }

/** @return list<string> */
function alim_atouts(?string $rayon): array { return alim_rayon($rayon, 'atouts'); }

/** @return list<string> */
function alim_conservations(): array { return (array) config('alimentation.conservations', []); }

/** @return list<string> */
function alim_dlc_types(): array { return (array) config('alimentation.dlc_types', []); }

/** @return list<string> */
function alim_allergenes(): array { return (array) config('alimentation.allergenes', []); }

/** Métadonnées d'un type pour un rayon alimentaire, ou null. */
function alim_type_meta(?string $rayon, ?string $type): ?array
{
    $t = alim_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un produit alimentaire : champs du type validés.
 * @return array<string,string>
 */
function alim_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = alim_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = alim_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/** Config « nouveau rayon » Alimentation (rayon_suggest, generic_specs, atout_suggest, warn_text, R). */
function alim_autre(?string $key = null): array
{
    $cfg = (array) config('alimentation.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Alimentation « autre » par son libellé (slug) — ou null si inconnu. */
function alim_autre_cfg(?string $rayon): ?array
{
    $r = (alim_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/* ---------- Bébé & Enfant : rayons adaptatifs au type (Alimentation…) ---------- */

/** @return list<string> Catégories de boutique proposant les rayons bébé adaptatifs. */
function bebe_shop_categories(): array { return (array) config('bebe.shop_categories', ['bebe']); }

/** La boutique (catégorie) propose-t-elle le formulaire bébé/enfant adaptatif ? */
function bebe_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, bebe_shop_categories(), true); }

/** @return list<string> Libellés des rayons bébé type-driven. */
function bebe_rayons(): array { return array_keys((array) config('bebe.rayons', [])); }

/** Ce rayon a-t-il un formulaire bébé adaptatif ? */
function bebe_is_rayon(?string $rayon): bool { return isset(((array) config('bebe.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon bébé par clé. */
function bebe_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('bebe.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function bebe_fields(?string $rayon): array { return bebe_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function bebe_types(?string $rayon): array { return bebe_rayon($rayon, 'types'); }

/** @return array<string,string> */
function bebe_groups(?string $rayon): array { return bebe_rayon($rayon, 'groups'); }

/** @return list<string> */
function bebe_atouts(?string $rayon): array { return bebe_rayon($rayon, 'atouts'); }

/** @return list<string> */
function bebe_conservations(): array { return (array) config('bebe.conservations', []); }

/** @return list<string> */
function bebe_dlc_types(): array { return (array) config('bebe.dlc_types', []); }

/** @return list<string> */
function bebe_allergenes(): array { return (array) config('bebe.allergenes', []); }

/** @return list<string> */
function bebe_regimes(): array { return (array) config('bebe.regimes', []); }

/** @return list<string> */
function bebe_ages(): array { return (array) config('bebe.ages', []); }

/** Métadonnées d'un type pour un rayon bébé, ou null. */
function bebe_type_meta(?string $rayon, ?string $type): ?array
{
    $t = bebe_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques « select » d'un produit bébé : champs du type validés
 * (texture / conditionnement). Conservation, allergènes, régime et âge sont gérés à part.
 * @return array<string,string>
 */
function bebe_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = bebe_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = bebe_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        if (!isset($defs[$key])) { continue; } // seuls texture / portion ont une définition
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Bébé & Enfant · JOUETS : moteur séparé, sécurité enfant ---------- */

/** @return list<string> États acceptés pour un jouet. */
function bebe_conditions(): array { return (array) config('bebe.conditions', ['Neuf', 'Comme neuf', 'Occasion']); }

/** @return list<string> Tous les âges conseillés (jouets). */
function bebe_toy_ages(): array { return (array) config('bebe.toy_ages', []); }

/** @return list<string> Âges « moins de 36 mois » (interdiction de petites pièces). */
function bebe_toy_ages_under3(): array { return (array) config('bebe.toy_ages_under3', []); }

/** Un âge donné relève-t-il des moins de 36 mois ? */
function bebe_toy_is_under3(?string $age): bool { return $age !== null && $age !== '' && in_array($age, bebe_toy_ages_under3(), true); }

/** @return list<string> Libellés des rayons jouets type-driven. */
function bebe_toy_rayons(): array { return array_keys((array) config('bebe.toys', [])); }

/** Ce rayon a-t-il le formulaire jouet adaptatif ? */
function bebe_toy_is_rayon(?string $rayon): bool { return isset(((array) config('bebe.toys', []))[(string) $rayon]); }

/** Sous-config d'un rayon jouet par clé. */
function bebe_toy_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('bebe.toys.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function bebe_toy_fields(?string $rayon): array { return bebe_toy_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function bebe_toy_types(?string $rayon): array { return bebe_toy_rayon($rayon, 'types'); }

/** @return array<string,string> */
function bebe_toy_groups(?string $rayon): array { return bebe_toy_rayon($rayon, 'groups'); }

/** @return list<string> */
function bebe_toy_atouts(?string $rayon): array { return bebe_toy_rayon($rayon, 'atouts'); }

/** Métadonnées d'un type pour un rayon jouet, ou null. */
function bebe_toy_type_meta(?string $rayon, ?string $type): ?array
{
    $t = bebe_toy_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques « select » d'un jouet : champs du type validés.
 * @return array<string,string>
 */
function bebe_toy_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = bebe_toy_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = bebe_toy_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        if (!isset($defs[$key])) { continue; }
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Bébé & Enfant · PUÉRICULTURE : moteur séparé, sécurité ---------- */

/** @return list<string> États acceptés pour un article de puériculture (dont reconditionné). */
function bebe_puer_conditions(): array { return (array) config('bebe.puer_conditions', ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion']); }

/** @return list<string> Libellés des rayons puériculture type-driven. */
function bebe_puer_rayons(): array { return array_keys((array) config('bebe.puer', [])); }

/** Ce rayon a-t-il le formulaire puériculture adaptatif ? */
function bebe_puer_is_rayon(?string $rayon): bool { return isset(((array) config('bebe.puer', []))[(string) $rayon]); }

/** Sous-config d'un rayon puériculture par clé. */
function bebe_puer_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('bebe.puer.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function bebe_puer_fields(?string $rayon): array { return bebe_puer_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function bebe_puer_types(?string $rayon): array { return bebe_puer_rayon($rayon, 'types'); }

/** @return array<string,string> */
function bebe_puer_groups(?string $rayon): array { return bebe_puer_rayon($rayon, 'groups'); }

/** @return list<string> */
function bebe_puer_atouts(?string $rayon): array { return bebe_puer_rayon($rayon, 'atouts'); }

/** Métadonnées d'un type pour un rayon puériculture, ou null. */
function bebe_puer_type_meta(?string $rayon, ?string $type): ?array
{
    $t = bebe_puer_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques « select » d'un article de puériculture.
 * @return array<string,string>
 */
function bebe_puer_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = bebe_puer_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = bebe_puer_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        if (!isset($defs[$key])) { continue; }
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Bébé & Enfant · SOINS : moteur séparé, hygiène/santé ---------- */

/** État figé des produits de soin (hygiène : neuf scellé uniquement). */
function bebe_soin_condition(): string { return (string) config('bebe.soin_condition', 'Neuf (scellé)'); }

/** @return list<string> Labels / mentions proposés (multi). */
function bebe_soin_labels(): array { return (array) config('bebe.soin_labels', []); }

/** @return list<string> Libellés des rayons soins type-driven. */
function bebe_soin_rayons(): array { return array_keys((array) config('bebe.soin', [])); }

/** Ce rayon a-t-il le formulaire soins adaptatif ? */
function bebe_soin_is_rayon(?string $rayon): bool { return isset(((array) config('bebe.soin', []))[(string) $rayon]); }

/** Sous-config d'un rayon soins par clé. */
function bebe_soin_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('bebe.soin.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function bebe_soin_fields(?string $rayon): array { return bebe_soin_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function bebe_soin_types(?string $rayon): array { return bebe_soin_rayon($rayon, 'types'); }

/** @return array<string,string> */
function bebe_soin_groups(?string $rayon): array { return bebe_soin_rayon($rayon, 'groups'); }

/** @return list<string> */
function bebe_soin_atouts(?string $rayon): array { return bebe_soin_rayon($rayon, 'atouts'); }

/** Métadonnées d'un type pour un rayon soins, ou null. */
function bebe_soin_type_meta(?string $rayon, ?string $type): ?array
{
    $t = bebe_soin_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques « select » d'un produit de soin (labels gérés à part).
 * @return array<string,string>
 */
function bebe_soin_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = bebe_soin_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = bebe_soin_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        if (!isset($defs[$key])) { continue; } // 'labels' n'a pas de définition → géré en chips
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Bébé & Enfant · VÊTEMENTS BÉBÉ : moteur séparé, sécurité textile ---------- */

/** @return list<string> États acceptés (seconde main courante). */
function bebe_vet_conditions(): array { return (array) config('bebe.vet_conditions', ['Neuf avec étiquette', 'Comme neuf', 'Très bon état', 'Bon état']); }

/** @return list<string> Libellés des rayons vêtements bébé type-driven. */
function bebe_vet_rayons(): array { return array_keys((array) config('bebe.vet', [])); }

/** Ce rayon a-t-il le formulaire vêtements bébé adaptatif ? */
function bebe_vet_is_rayon(?string $rayon): bool { return isset(((array) config('bebe.vet', []))[(string) $rayon]); }

/** Sous-config d'un rayon vêtements bébé par clé. */
function bebe_vet_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('bebe.vet.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function bebe_vet_fields(?string $rayon): array { return bebe_vet_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function bebe_vet_types(?string $rayon): array { return bebe_vet_rayon($rayon, 'types'); }

/** @return array<string,string> */
function bebe_vet_groups(?string $rayon): array { return bebe_vet_rayon($rayon, 'groups'); }

/** @return list<string> */
function bebe_vet_atouts(?string $rayon): array { return bebe_vet_rayon($rayon, 'atouts'); }

/** Métadonnées d'un type pour un rayon vêtements bébé, ou null. */
function bebe_vet_type_meta(?string $rayon, ?string $type): ?array
{
    $t = bebe_vet_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques « select » d'un vêtement bébé : champs du type validés.
 * Applique au passage les valeurs par défaut du type ('defaults') si absentes.
 * @return array<string,string>
 */
function bebe_vet_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = bebe_vet_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = bebe_vet_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        if (!isset($defs[$key])) { continue; }
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val === '' && isset(((array) ($meta['defaults'] ?? []))[$key])) { $val = (string) $meta['defaults'][$key]; }
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/* ---------- Bébé & Enfant · NOUVEAU RAYON (générique adaptatif au slug) ---------- */

/** Le rayon est-il l'un des CINQ rayons bébé répertoriés (sinon = rayon personnalisé) ? */
function bebe_any_rayon(?string $rayon): bool
{
    return bebe_is_rayon($rayon) || bebe_toy_is_rayon($rayon) || bebe_puer_is_rayon($rayon)
        || bebe_soin_is_rayon($rayon) || bebe_vet_is_rayon($rayon);
}

/** Config « nouveau rayon » Bébé (rayon_suggest, generic_specs, atout_suggest, age_opts, conditions, R). */
function bebe_autre(?string $key = null): array
{
    $cfg = (array) config('bebe.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Bébé « autre » par son libellé (slug) — ou null si inconnu. */
function bebe_autre_cfg(?string $rayon): ?array
{
    $r = (bebe_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/* ---------- Sport & loisirs : rayons adaptatifs au type (Chaussures…) ---------- */

/** @return list<string> Catégories de boutique proposant les rayons sport adaptatifs. */
function sport_shop_categories(): array { return (array) config('sport.shop_categories', ['sport']); }

/** La boutique (catégorie) propose-t-elle le formulaire sport adaptatif ? */
function sport_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, sport_shop_categories(), true); }

/** @return list<string> Libellés des rayons sport type-driven. */
function sport_rayons(): array { return array_keys((array) config('sport.rayons', [])); }

/** Ce rayon a-t-il un formulaire sport adaptatif ? */
function sport_is_rayon(?string $rayon): bool { return isset(((array) config('sport.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon sport par clé. */
function sport_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('sport.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function sport_fields(?string $rayon): array { return sport_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function sport_types(?string $rayon): array { return sport_rayon($rayon, 'types'); }

/** @return array<string,string> */
function sport_groups(?string $rayon): array { return sport_rayon($rayon, 'groups'); }

/** @return list<string> */
function sport_atouts(?string $rayon): array { return sport_rayon($rayon, 'atouts'); }

/** @return list<string> */
function sport_conditions(): array { return (array) config('sport.conditions', ['Neuf', 'Occasion']); }

/** État figé des vêtements d'hygiène (maillot de bain, sous-vêtement). */
function sport_hygiene_condition(): string { return (string) config('sport.hygiene_condition', 'Neuf (scellé)'); }

/** @return list<string> Versions possibles d'un maillot d'équipe. */
function sport_team_versions(): array { return (array) config('sport.team_versions', []); }

/** Métadonnées d'un type pour un rayon sport, ou null. */
function sport_type_meta(?string $rayon, ?string $type): ?array
{
    $t = sport_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un produit sport : champs du type validés.
 * Applique au passage les valeurs par défaut du type ('defaults') si absentes.
 * @return array<string,string>
 */
function sport_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = sport_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = sport_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val === '' && isset(((array) ($meta['defaults'] ?? []))[$key])) { $val = (string) $meta['defaults'][$key]; }
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    // Défauts hors champs visibles (ex. brassière → public « Femme » non affiché).
    foreach ((array) ($meta['defaults'] ?? []) as $key => $dv) {
        if (isset($out[$key]) || !isset($defs[$key])) { continue; }
        $dv = (string) $dv;
        if ($dv !== '' && in_array($dv, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $dv; }
    }
    return $out;
}

/** Config « nouveau rayon » Sport (rayon_suggest, generic_specs, atout_suggest, R). */
function sport_autre(?string $key = null): array
{
    $cfg = (array) config('sport.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Sport « autre » par son libellé (slug) — ou null si inconnu. */
function sport_autre_cfg(?string $rayon): ?array
{
    $r = (sport_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/* ---------- Auto & pièces : rayons adaptatifs au type (Accessoires…) ---------- */

/** @return list<string> Catégories de boutique proposant les rayons auto adaptatifs. */
function auto_shop_categories(): array { return (array) config('auto.shop_categories', ['auto']); }

/** La boutique (catégorie) propose-t-elle le formulaire auto adaptatif ? */
function auto_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, auto_shop_categories(), true); }

/** @return list<string> Libellés des rayons auto type-driven. */
function auto_rayons(): array { return array_keys((array) config('auto.rayons', [])); }

/** Ce rayon a-t-il un formulaire auto adaptatif ? */
function auto_is_rayon(?string $rayon): bool { return isset(((array) config('auto.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon auto par clé. */
function auto_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('auto.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function auto_fields(?string $rayon): array { return auto_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function auto_types(?string $rayon): array { return auto_rayon($rayon, 'types'); }

/** @return array<string,string> */
function auto_groups(?string $rayon): array { return auto_rayon($rayon, 'groups'); }

/** @return list<string> */
function auto_atouts(?string $rayon): array { return auto_rayon($rayon, 'atouts'); }

/** @return list<string> */
function auto_conditions(): array { return (array) config('auto.conditions', []); }

/** Métadonnées d'un type pour un rayon auto, ou null. */
function auto_type_meta(?string $rayon, ?string $type): ?array
{
    $t = auto_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un accessoire auto : champs du type validés.
 * @return array<string,string>
 */
function auto_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = auto_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = auto_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/** Le rayon est-il en mode « dimension » (pneus : compatibilité = dimension composée) ? */
function auto_rayon_is_dimension(?string $rayon): bool { return !empty(auto_rayon($rayon)['dimension']); }

/**
 * Compose la dimension normalisée d'un pneu à partir des caractéristiques validées
 * (largeur/série/diamètre [+ charge + indice de vitesse]) — ex. « 205/55 R16 91V ».
 */
function auto_tyre_dimension(array $attrs): string
{
    $l = trim((string) ($attrs['largeur'] ?? ''));
    $s = trim((string) ($attrs['serie'] ?? ''));
    $d = trim((string) ($attrs['diametre'] ?? ''));
    $c = trim((string) ($attrs['charge'] ?? ''));
    $v = trim((string) ($attrs['vitesse'] ?? ''));
    if ($l !== '' && $s !== '' && $d !== '') {
        $dim = $l . '/' . $s . ' R' . $d;
        if ($c !== '') { $dim .= ' ' . $c; }
        if ($v !== '') { $dim .= explode(' ', $v)[0]; } // « V (240) » → « V »
        return $dim;
    }
    if ($d !== '' && $l === '') { return 'R' . $d; }
    return '';
}

/** Config « nouveau rayon » Auto (rayon_suggest, generic_specs, atout_suggest, warn_text, R). */
function auto_autre(?string $key = null): array
{
    $cfg = (array) config('auto.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Auto « autre » par son libellé (slug) — ou null si inconnu. */
function auto_autre_cfg(?string $rayon): ?array
{
    $r = (auto_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/* ---------- Artisanat & Art : rayons adaptatifs au type (Bijoux…) ---------- */

/** @return list<string> Catégories de boutique proposant les rayons artisanat adaptatifs. */
function arti_shop_categories(): array { return (array) config('artisanat.shop_categories', ['artisanat']); }

/** La boutique (catégorie) propose-t-elle le formulaire artisanat adaptatif ? */
function arti_capable(?string $boutiqueCategory): bool { return in_array((string) $boutiqueCategory, arti_shop_categories(), true); }

/** @return list<string> Libellés des rayons artisanat type-driven. */
function arti_rayons(): array { return array_keys((array) config('artisanat.rayons', [])); }

/** Ce rayon a-t-il un formulaire artisanat adaptatif ? */
function arti_is_rayon(?string $rayon): bool { return isset(((array) config('artisanat.rayons', []))[(string) $rayon]); }

/** Sous-config d'un rayon artisanat par clé. */
function arti_rayon(?string $rayon, ?string $key = null): array
{
    $cfg = (array) config('artisanat.rayons.' . (string) $rayon, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,array{label:string,opts:list<string>}> */
function arti_fields(?string $rayon): array { return arti_rayon($rayon, 'fields'); }

/** @return array<string,array> */
function arti_types(?string $rayon): array { return arti_rayon($rayon, 'types'); }

/** @return array<string,string> */
function arti_groups(?string $rayon): array { return arti_rayon($rayon, 'groups'); }

/** @return list<string> */
function arti_atouts(?string $rayon): array { return arti_rayon($rayon, 'atouts'); }

/** @return list<string> */
function arti_conditions(): array { return (array) config('artisanat.conditions', []); }

/** Métadonnées d'un type pour un rayon artisanat, ou null. */
function arti_type_meta(?string $rayon, ?string $type): ?array
{
    $t = arti_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'une pièce artisanale : champs du type validés.
 * @return array<string,string>
 */
function arti_attr_clean(?string $rayon, ?string $type, array $attrs): array
{
    $meta = arti_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $defs = arti_fields($rayon);
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    return $out;
}

/** Config « nouveau rayon » Artisanat (rayon_suggest, generic_specs, atout_suggest, R). */
function arti_autre(?string $key = null): array
{
    $cfg = (array) config('artisanat.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Config adaptative d'un rayon Artisanat « autre » par son libellé (slug) — ou null si inconnu. */
function arti_autre_cfg(?string $rayon): ?array
{
    $r = (arti_autre('R'))[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}



/** @return list<string> */
function beauty_pao(): array { return (array) config('beauty.pao', []); }
/** @return list<string> */
function beauty_volume_units(): array { return (array) config('beauty.volume_units', ['ml']); }
/** @return list<string> */
function beauty_atouts(): array { return (array) config('beauty.atouts', []); }
/** @return list<string> Nuances de carnation (très claire → très foncée). */
function beauty_nuances(): array { return (array) config('beauty.nuances', []); }

/** @return array<string,array{label:string,opts:list<string>}> Définitions des champs de caractéristiques. */
function beauty_fields(): array { return (array) config('beauty.fields', []); }
/** @return array<string,array> Configuration par type de produit. */
function beauty_types(): array { return (array) config('beauty.types', []); }
/** @return array<string,string> Groupes de types (teint/levres/yeux => libellé). */
function beauty_groups(): array { return (array) config('beauty.groups', []); }
/** @return array<string,list<array>> Palettes de déclinaison. */
function beauty_palettes(): array { return (array) config('beauty.palettes', []); }

/** @return list<string> Noms des types de produit maquillage. */
function beauty_product_types(): array { return array_keys(beauty_types()); }

/** Métadonnées d'un type de produit, ou null si inconnu. */
function beauty_type_meta(?string $type): ?array
{
    $t = beauty_types()[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/** Une palette de déclinaison normalisée : [ ['name'=>…,'hex'=>…,'nuance'=>…], … ]. @return list<array> */
function beauty_palette(?string $key): array
{
    $out = [];
    foreach ((array) (beauty_palettes()[(string) $key] ?? []) as $row) {
        $row = array_values((array) $row);
        $out[] = ['name' => (string) ($row[0] ?? ''), 'hex' => (string) ($row[1] ?? ''), 'nuance' => (string) ($row[2] ?? '')];
    }
    return $out;
}

/** Carte nom → hex sur TOUTES les palettes (pour dessiner le swatch d'une déclinaison). @return array<string,string> */
function beauty_hex_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (beauty_palettes() as $rows) {
            foreach ((array) $rows as $row) {
                $row = array_values((array) $row);
                if (($row[0] ?? '') !== '') { $map[(string) $row[0]] = (string) ($row[1] ?? ''); }
            }
        }
    }
    return $map;
}

/** Pastille couleur (hex) déduite du NOM d'une déclinaison, ou null si inconnue. */
function beauty_hex_for(?string $name): ?string
{
    return beauty_hex_map()[trim((string) $name)] ?? null;
}

/**
 * Pastille(s) couleur pour une valeur de déclinaison libre (fiche acheteur) :
 * mappe un nom de couleur courant (FR/EN) vers son hex. Gère les valeurs
 * « bicolores » (« Rouge/Noir », « Bleu & Blanc », « Vert et Or ») en
 * renvoyant jusqu'à deux teintes — d'où la prise en charge des produits à
 * doubles couleurs. Renvoie [] si rien n'est reconnu : une valeur de capacité
 * (« 256 Go », « 1 To ») ne reçoit donc aucune pastille, ce qui distingue
 * naturellement un axe « couleur » d'un axe « capacité ».
 *
 * @return list<string> 0, 1 ou 2 codes hex (#RRGGBB)
 */
function variant_color_hex(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }
    static $map = null;
    if ($map === null) {
        $map = [
            'noir' => '#1a1a1a', 'black' => '#1a1a1a',
            'blanc' => '#f5f5f0', 'white' => '#f5f5f0', 'ivoire' => '#fffdf0', 'ivory' => '#fffdf0',
            'crème' => '#fdf6e3', 'creme' => '#fdf6e3', 'cream' => '#fdf6e3', 'écru' => '#f3ead3', 'ecru' => '#f3ead3',
            'gris' => '#9ca3af', 'gray' => '#9ca3af', 'grey' => '#9ca3af', 'anthracite' => '#383838',
            'argent' => '#c0c0c0', 'silver' => '#c0c0c0',
            'rouge' => '#dc2626', 'red' => '#dc2626', 'bordeaux' => '#7f1d1d', 'burgundy' => '#7f1d1d',
            'corail' => '#ff6f61', 'coral' => '#ff6f61', 'saumon' => '#fa8072', 'salmon' => '#fa8072',
            'bleu' => '#2563eb', 'blue' => '#2563eb', 'marine' => '#1e3a5f', 'navy' => '#1e3a5f',
            'ciel' => '#7dd3fc', 'turquoise' => '#06b6d4', 'cyan' => '#06b6d4',
            'vert' => '#16a34a', 'green' => '#16a34a', 'kaki' => '#78866b', 'khaki' => '#78866b',
            'olive' => '#808000', 'menthe' => '#98d8c4', 'mint' => '#98d8c4',
            'jaune' => '#facc15', 'yellow' => '#facc15', 'moutarde' => '#d4a017', 'mustard' => '#d4a017',
            'or' => '#d4af37', 'gold' => '#d4af37', 'doré' => '#d4af37', 'dore' => '#d4af37',
            'orange' => '#ea580c',
            'rose' => '#ec4899', 'pink' => '#ec4899', 'fuchsia' => '#d946ef',
            'violet' => '#7c3aed', 'purple' => '#7c3aed', 'mauve' => '#b57edc', 'lavande' => '#b57edc', 'lavender' => '#b57edc',
            'prune' => '#701f53', 'plum' => '#701f53',
            'marron' => '#92400e', 'brun' => '#92400e', 'brown' => '#92400e', 'chocolat' => '#5c4033', 'chocolate' => '#5c4033',
            'camel' => '#c19a6b', 'taupe' => '#8b8589', 'beige' => '#e7d8b8', 'sable' => '#e0cda9', 'sand' => '#e0cda9',
            'cognac' => '#9a463d', 'bronze' => '#cd7f32', 'cuivre' => '#b87333', 'copper' => '#b87333',
        ];
    }
    $resolve = static function (string $s) use ($map): ?string {
        $k = mb_strtolower(trim($s));
        if ($k === '') {
            return null;
        }
        if (isset($map[$k])) {
            return $map[$k];
        }
        // « Bleu clair », « Vert foncé »… → on retombe sur la teinte de base (1er mot).
        $first = preg_split('/\s+/', $k)[0] ?? '';
        return ($first !== '' && isset($map[$first])) ? $map[$first] : null;
    };
    // Valeur bicolore : « A/B », « A & B », « A et B », « A · B ».
    $parts = preg_split('#\s*(?:/|&|·|\bet\b)\s*#ui', $value, 3) ?: [$value];
    $out = [];
    foreach ($parts as $p) {
        $h = $resolve((string) $p);
        if ($h !== null && !in_array($h, $out, true)) {
            $out[] = $h;
        }
        if (count($out) >= 2) {
            break;
        }
    }
    return $out;
}

/**
 * Fiche acheteur : aplatit TOUTES les informations saisies au formulaire en une
 * liste « libellé → valeur », pour qu'aucun renseignement ne reste caché. On
 * réunit les caractéristiques adaptatives (attributes JSON, y compris les specs
 * libres libellé→valeur) et les champs clés du produit. Les clés internes et les
 * valeurs vides sont écartées ; les listes sont jointes ; les booléens → « Oui ».
 *
 * @return list<array{label:string,value:string}>
 */
function product_spec_rows(array $product): array
{
    $rows = [];
    $seen = [];
    $add = static function (string $label, $value) use (&$rows, &$seen): void {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(static fn ($v): string => trim((string) $v), $value), static fn ($v): bool => $v !== ''));
        }
        $label = trim($label);
        $value = trim((string) $value);
        if ($label === '' || $value === '') {
            return;
        }
        $k = mb_strtolower($label);
        if (isset($seen[$k])) {
            return;
        }
        $seen[$k] = true;
        $rows[] = ['label' => $label, 'value' => $value];
    };

    // Libellés FR des clés d'attributs les plus courantes (repli : clé « humanisée »).
    static $labels = [
        'condition' => 'État', 'garantie' => 'Garantie', 'couleur' => 'Couleur', 'genre' => 'Genre',
        'public' => 'Public', 'matiere' => 'Matière', 'matière' => 'Matière', 'dimension' => 'Dimensions',
        'dimensions' => 'Dimensions', 'compatibilite' => 'Compatibilité', 'conservation' => 'Conservation',
        'allergenes' => 'Allergènes', 'regime' => 'Régime', 'age_min' => 'Âge minimum', 'age' => 'Âge',
        'taille' => 'Taille', 'taille_couche' => 'Taille (couche)', 'spf' => 'Indice SPF', 'tog' => 'Indice TOG',
        'groupe' => 'Groupe / âge', 'labels' => 'Labels', 'terrain' => 'Terrain', 'volume' => 'Volume',
        'contenance' => 'Contenance', 'saveur' => 'Saveur', 'peremption' => 'Date limite', 'origine' => 'Origine',
        'histoire' => 'Histoire', 'ref_oem' => 'Référence OEM', 'norme' => 'Norme', 'amorti' => 'Amorti',
        'resistance' => 'Résistance', 'puissance' => 'Puissance', 'autonomie' => 'Autonomie', 'capacite' => 'Capacité',
        'ce' => 'Conforme CE', 'en71' => 'Norme EN71', 'fait_main' => 'Fait main', 'piece_unique' => 'Pièce unique',
        'par_paire' => 'Vendu par paire', 'personnalisation' => 'Personnalisable', 'universel' => 'Montage universel',
        'alcoolise' => 'Alcoolisé', 'contact_alimentaire' => 'Contact alimentaire', 'small_parts' => 'Petites pièces',
        'avertissement_3ans' => 'Déconseillé < 3 ans', 'securite_enfant' => 'Sécurité enfant',
    ];
    $boolKeys = ['ce', 'en71', 'fait_main', 'piece_unique', 'par_paire', 'personnalisation', 'universel',
        'alcoolise', 'contact_alimentaire', 'small_parts', 'avertissement_3ans', 'securite_enfant'];
    // Clés purement techniques (déjà servies ailleurs comme libellés d'axe / non pertinentes).
    $skip = ['variant_axis', 'variant_axis2', 'sale_mode', 'unit', 'hex', 'nuance', 'notes', 'capteurs'];

    // 1) Champs « colonnes » clés du produit.
    $add('Rayon', (string) ($product['collection'] ?? ''));
    $add('Marque', (string) ($product['brand'] ?? ''));
    $add('Modèle', (string) ($product['model'] ?? ''));
    $add('Gamme', (string) ($product['line'] ?? ''));
    $add('Type', (string) ($product['product_type'] ?? ''));

    // 2) Caractéristiques adaptatives (attributes JSON).
    $attr = json_decode((string) ($product['attributes'] ?? ''), true);
    if (is_array($attr)) {
        foreach ($attr as $key => $val) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            // specs : caractéristiques libres (libellé → valeur) saisies par le vendeur.
            if ($key === 'specs' && is_array($val)) {
                foreach ($val as $sk => $sv) {
                    if (is_string($sk)) {
                        $add(ucfirst($sk), $sv);
                    } else {
                        $add('Caractéristique', $sv);
                    }
                }
                continue;
            }
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
            if (in_array($key, $boolKeys, true)) {
                if (!empty($val)) {
                    $add($label, 'Oui');
                }
                continue;
            }
            $add($label, $val);
        }
    }

    // 3) Quelques colonnes complémentaires si renseignées.
    if (((float) ($product['volume'] ?? 0)) > 0) {
        $add('Volume', rtrim(rtrim(number_format((float) $product['volume'], 2, '.', ''), '0'), '.') . ' ' . (string) ($product['volume_unit'] ?: 'ml'));
    }
    $add('PAO', $product['pao'] ?? '');
    $add('EAN', $product['ean'] ?? '');
    $add('SKU', $product['sku'] ?? '');
    if (!empty($product['expiry_date'])) {
        $add('Date de péremption', date('d/m/Y', (int) strtotime((string) $product['expiry_date'])));
    }
    return $rows;
}

/** Valide une valeur beauté contre une liste blanche ('' = non précisé). */
function beauty_clean(?string $v, array $allowed): string
{
    $v = trim((string) $v);
    return in_array($v, $allowed, true) ? $v : '';
}

/**
 * Nettoie les caractéristiques (attributes) soumises pour un type donné :
 * ne garde que les champs du type, chaque valeur validée contre ses options.
 * @return array<string,string>
 */
function beauty_attr_clean(?string $type, array $attrs): array
{
    $meta = beauty_type_meta($type);
    if ($meta === null) { return []; }
    $defs = beauty_fields();
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        $opts = (array) ($defs[$key]['opts'] ?? []);
        if ($val !== '' && in_array($val, $opts, true)) { $out[$key] = $val; }
    }
    return $out;
}

/** Nettoie/valide la liste d'atouts soumise → CSV des atouts connus. */
function beauty_atouts_clean(array $vals): string
{
    $allowed = beauty_atouts();
    $keep = [];
    foreach ($vals as $v) {
        $v = trim((string) $v);
        if (in_array($v, $allowed, true) && !in_array($v, $keep, true)) { $keep[] = $v; }
    }
    return implode(', ', $keep);
}

/* ----- Rayon « Ongles » : faux ongles ----- */

/** Sous-config du rayon Ongles (formes, longueurs, designs, couleurs, kit, atouts, toggles). */
function beauty_ongles(?string $key = null): array
{
    $cfg = (array) config('beauty.ongles', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** @return array<string,string> Couleur d'ongle (nom) => hex. */
function ongles_couleur_hex(): array
{
    $map = [];
    foreach (beauty_ongles('couleurs') as $row) {
        $row = array_values((array) $row);
        if (($row[0] ?? '') !== '') { $map[(string) $row[0]] = (string) ($row[1] ?? ''); }
    }
    return $map;
}

/** Sous-config du rayon Parfums (concentrations, genres, familles, tailles…). */
function beauty_parfum(?string $key = null): array
{
    $cfg = (array) config('beauty.parfum', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Sous-config du rayon Perruque (constructions, textures, couleurs, longueurs…). */
function beauty_perruque(?string $key = null): array
{
    $cfg = (array) config('beauty.perruque', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Sous-config d'un rayon de soins par genre ('corps' / 'visage') et clé. */
function beauty_soins(string $kind, ?string $key = null): array
{
    $cfg = (array) config('beauty.soins.' . $kind, []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** PAO commun aux soins. @return list<string> */
function beauty_soins_pao(): array { return (array) config('beauty.soins.pao', []); }

/** Genre de soin selon le rayon ('Soins visage' => 'visage', sinon 'corps'). */
function beauty_soins_kind(?string $rayon): string
{
    return trim((string) $rayon) === 'Soins visage' ? 'visage' : 'corps';
}

/** @return array<string,array> Types de produit du rayon de soins. */
function beauty_soins_types(?string $rayon): array
{
    return beauty_soins(beauty_soins_kind($rayon), 'types');
}

/** @return array<string,string> Groupes (optgroups) du rayon de soins. */
function beauty_soins_groups(?string $rayon): array
{
    return beauty_soins(beauty_soins_kind($rayon), 'groups');
}

/** Métadonnées d'un type de soin pour un rayon donné, ou null. */
function beauty_soins_type_meta(?string $rayon, ?string $type): ?array
{
    $t = beauty_soins_types($rayon)[(string) $type] ?? null;
    return is_array($t) ? $t : null;
}

/**
 * Nettoie les caractéristiques d'un soin : seulement les champs du type, validés ;
 * + actifs (liste blanche du rayon). @return array<string,mixed>
 */
function beauty_soins_attr_clean(?string $rayon, ?string $type, array $attrs, array $actifs): array
{
    $meta = beauty_soins_type_meta($rayon, $type);
    if ($meta === null) { return []; }
    $kind = beauty_soins_kind($rayon);
    $defs = beauty_soins($kind, 'fields');
    $out = [];
    foreach ((array) ($meta['fields'] ?? []) as $key) {
        $val = trim((string) ($attrs[$key] ?? ''));
        if ($val !== '' && in_array($val, (array) ($defs[$key]['opts'] ?? []), true)) { $out[$key] = $val; }
    }
    if (!empty($meta['actifs'])) {
        $keep = keep_in_list($actifs, beauty_soins($kind, 'actifs'));
        if ($keep !== []) { $out['actifs'] = $keep; }
    }
    return $out;
}

/** Sous-config « Autre / nouveau rayon » beauté (suggestions, axes, conformité, R). */
function beauty_autre(?string $key = null): array
{
    $cfg = (array) config('beauty.autre', []);
    if ($key === null) { return $cfg; }
    return (array) ($cfg[$key] ?? []);
}

/** Identifiant (slug) d'un libellé de rayon — même logique que le JS (sans accents, tirets). */
function beauty_slug(?string $label): string
{
    $s = mb_strtolower(trim((string) $label), 'UTF-8');
    $s = strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'ò' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ñ' => 'n', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'autre';
}

/** Config adaptative d'un rayon « autre » par son libellé (ou null si inconnu). */
function beauty_autre_cfg(?string $rayon): ?array
{
    $r = beauty_autre('R')[beauty_slug($rayon)] ?? null;
    return is_array($r) ? $r : null;
}

/** Type de conformité d'un rayon « autre » ('cosmetic' par défaut). */
function beauty_autre_warn(?string $rayon): string
{
    $cfg = beauty_autre_cfg($rayon);
    return (string) ($cfg['warn'] ?? 'cosmetic');
}

/** @return array<string,string> Couleur de perruque (nom) => hex. */
function perruque_couleur_hex(): array
{
    $map = [];
    foreach (beauty_perruque('couleurs') as $row) {
        $row = array_values((array) $row);
        if (($row[0] ?? '') !== '') { $map[(string) $row[0]] = (string) ($row[1] ?? ''); }
    }
    return $map;
}

/** Filtre une liste soumise contre une liste blanche (sans doublon). @return list<string> */
function keep_in_list(array $vals, array $allowed): array
{
    $keep = [];
    foreach ($vals as $v) {
        $v = trim((string) $v);
        if (in_array($v, $allowed, true) && !in_array($v, $keep, true)) { $keep[] = $v; }
    }
    return $keep;
}

/**
 * Vertical du formulaire produit selon la catégorie de la boutique :
 * 'phone' (électronique), 'apparel' (mode/vêtements), 'beauty' (beauté &
 * cosmétiques) ou 'generic' (le reste). Les produits respectent ainsi la
 * catégorie principale (verrouillée) de la boutique.
 */
function product_vertical(?string $boutiqueCategory): string
{
    $cat = (string) $boutiqueCategory;
    if (in_array($cat, (array) config('phone.shop_categories', []), true)) {
        return 'phone';
    }
    if (in_array($cat, (array) config('apparel.shop_categories', ['mode']), true)) {
        return 'apparel';
    }
    if (in_array($cat, (array) config('beauty.shop_categories', ['beaute']), true)) {
        return 'beauty';
    }
    return 'generic';
}

/** @return list<string> Rayons proposés pour la catégorie principale de la boutique. */
function shop_rayons_for(?string $category): array
{
    return array_keys((array) (config('rayons.list', [])[(string) $category] ?? []));
}

/**
 * Clé d'axe de déclinaison d'un rayon donné (taille → 'alpha', stockage → 'stockage',
 * contenance → 'volume', teinte → 'teinte', pointure, longueur, etc.). 'none' si le rayon
 * n'impose pas d'axe ou est inconnu. C'est cet axe qui adapte le formulaire produit.
 */
function rayon_axis(?string $category, ?string $rayon): string
{
    $map = (array) (config('rayons.list', [])[(string) $category] ?? []);
    $axis = (string) ($map[(string) $rayon] ?? 'none');
    return isset(config('rayons.axes', [])[$axis]) ? $axis : 'none';
}

/**
 * Métadonnées des axes de déclinaison : clé d'axe => ['label' => libellé, 'opts' => suggestions].
 * Sert à la fois côté serveur (libellé de la taille) et côté client (datalist dynamique).
 *
 * @return array<string,array{label:string,opts:list<string>}>
 */
function rayon_axes(): array
{
    return (array) config('rayons.axes', []);
}

/** Métadonnées de l'axe d'un rayon : ['label' => …, 'opts' => […]]. */
function rayon_axis_meta(?string $category, ?string $rayon): array
{
    $axis = rayon_axis($category, $rayon);
    $meta = rayon_axes()[$axis] ?? ['label' => 'Option', 'opts' => []];
    return [
        'key'   => $axis,
        'label' => (string) ($meta['label'] ?? 'Option'),
        'opts'  => array_values((array) ($meta['opts'] ?? [])),
    ];
}

/**
 * Nom d'une ligne de commande SANS sa déclinaison (taille/couleur/longueur), qui
 * est conservée à part dans variant_label. Le titre vaut « Nom — déclinaison » ;
 * on retire le suffixe exact pour un affichage structuré (nom + étiquette).
 */
function order_item_name(array $item): string
{
    $title = (string) ($item['title'] ?? '');
    $vl    = (string) ($item['variant_label'] ?? '');
    $suffix = ' — ' . $vl;
    if ($vl !== '' && str_ends_with($title, $suffix)) {
        return mb_substr($title, 0, mb_strlen($title) - mb_strlen($suffix));
    }
    return $title;
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

/**
 * Affiliation PAR PRODUIT — modèle : le vendeur fixe un taux R % (ce qu'il veut, dans
 * les bornes). Sur une vente via lien d'apporteur, R % est retranché du vendeur, dont
 * la plateforme garde 1,5 % (fixe) et l'apporteur touche R − 1,5 %.
 * Les taux sont stockés en points de base (350 = 3,50 %).
 */

/** Part FIXE (%) que la plateforme garde sur une vente affiliée. Défaut 1,5 %. */
function affiliate_platform_keep_pct(): float
{
    return max(0.0, min(100.0, (float) config('payment.affiliate_platform_keep_pct', 1.5)));
}

/** Part plateforme (points de base) sur une vente affiliée (ex. 150 = 1,5 %). */
function affiliate_keep_bps(): int
{
    return (int) round(affiliate_platform_keep_pct() * 100);
}

/** Taux d'affiliation MIN qu'un vendeur peut fixer (= la part plateforme), en bps. */
function affiliate_min_rate_bps(): int
{
    return affiliate_keep_bps();
}

/** Taux d'affiliation MAX qu'un vendeur peut fixer, en bps (défaut 50 %). */
function affiliate_max_rate_bps(): int
{
    return (int) round(max(0.0, min(100.0, (float) config('payment.affiliate_max_rate_pct', 50.0))) * 100);
}

/** Borne le taux fixé par le vendeur (bps) : 0 = désactivé, sinon dans [min ; max]. */
function affiliate_clamp_bps(int $bps): int
{
    if ($bps <= 0) {
        return 0;
    }
    return max(affiliate_min_rate_bps(), min(affiliate_max_rate_bps(), $bps));
}

/** Déduction VENDEUR (centimes) sur une ligne affiliée : le taux PLEIN R % fixé par le vendeur. */
function affiliate_line_deduction_cents(int $lineTotalCents, int $rateBps): int
{
    $bps = affiliate_clamp_bps($rateBps);
    if ($lineTotalCents <= 0 || $bps <= 0) {
        return 0;
    }
    return (int) round($lineTotalCents * $bps / 10000);
}

/** Commission de l'APPORTEUR (centimes) sur une ligne : R − part plateforme (ex. R − 1,5 %). */
function affiliate_line_commission_cents(int $lineTotalCents, int $rateBps): int
{
    $bps = affiliate_clamp_bps($rateBps);
    if ($lineTotalCents <= 0 || $bps <= 0) {
        return 0;
    }
    $affBps = max(0, $bps - affiliate_keep_bps());
    return (int) round($lineTotalCents * $affBps / 10000);
}

/** Moyens de retrait proposés pour un pays (Mobile Money local + virement). @return list<string> */
function payout_providers_for(?string $countryCode): array
{
    $map  = (array) config('payment.payout_providers', []);
    $list = $map[strtoupper((string) $countryCode)] ?? null;
    return is_array($list) && $list !== [] ? $list : ['Mobile Money', 'Virement bancaire'];
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

/**
 * Équivalent INDICATIF d'un prix dans la devise d'affichage de l'acheteur
 * (current_currency()), à montrer à côté du prix boutique (« ≈ »). Renvoie ''
 * si l'acheteur affiche déjà la devise de la boutique ou si aucun taux n'existe.
 * Le règlement reste dans la devise de la boutique : c'est un simple repère.
 */
function format_price_approx(int $cents, string $shopCurrency): string
{
    $buyer = current_currency();
    if (strtoupper($buyer) === strtoupper($shopCurrency)) {
        return '';
    }
    $converted = \App\Services\ExchangeRates::convert($cents, $shopCurrency, $buyer);
    return $converted === null ? '' : format_price($converted, $buyer);
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

/** Administrateur : rôle 'admin' OU e-mail listé dans ADMIN_EMAILS. Peut approuver
 *  ce que les modérateurs proposent (les modérateurs sont staff mais PAS admin). */
function is_admin(?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
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
