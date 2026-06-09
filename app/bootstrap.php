<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Loaded by public/index.php (the only web entry point) and by CLI scripts.
 * Responsibilities: paths, autoloading, .env, config, error handling, security
 * headers, and a hardened session — in that order, before any output.
 */

/* ---- 1. Paths ---------------------------------------------------- */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LANG_PATH', BASE_PATH . '/lang');
define('DATABASE_PATH', BASE_PATH . '/database');
define('PUBLIC_PATH', BASE_PATH . '/public');

/* ---- 2. Autoloading --------------------------------------------- */

// PSR-4: App\ -> app/  (works without `composer install`).
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Composer dependencies (Stripe, PHPMailer, ramsey/uuid...) if installed.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

/* ---- 3. Environment (.env) -------------------------------------- */

/**
 * Minimal, dependency-free .env loader so the app boots without Composer.
 * Supports KEY=VALUE, quotes, and trailing " # comments" on unquoted values.
 */
(static function (): void {
    $path = BASE_PATH . '/.env';
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end = strpos($value, $quote, 1);
            $value = $end !== false ? substr($value, 1, $end - 1) : substr($value, 1);
        } elseif (($hash = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $hash));
        }

        if ($key !== '' && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
})();

// Backfill $_ENV from the process environment (platforms like Vercel inject config
// as real env vars, not a .env file). .env-file values already set above win.
foreach (getenv() as $envKey => $envValue) {
    if (!array_key_exists($envKey, $_ENV)) {
        $_ENV[$envKey] = $envValue;
    }
}

/* ---- 4. Support helpers + global helpers ------------------------ */

require APP_PATH . '/Support/db.php';
require APP_PATH . '/Support/csrf.php';
require APP_PATH . '/Support/validation.php';
require APP_PATH . '/Support/rate_limit.php';
require APP_PATH . '/Support/security_headers.php';
require APP_PATH . '/helpers.php';

/* ---- 5. Error handling & logging -------------------------------- */

$debug = (bool) config('app.debug', false);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
// On serverless (Vercel) the project filesystem is read-only — log to the temp dir.
ini_set('error_log', empty($_ENV['VERCEL'])
    ? STORAGE_PATH . '/logs/php.log'
    : sys_get_temp_dir() . '/afrikalink-php.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false; // respect the @ operator
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e) use ($debug): void {
    log_message('error', $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'type' => $e::class,
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    } elseif (function_exists('view') && is_file(APP_PATH . '/Views/errors/500.php')) {
        view('errors/500', [], null);
    } else {
        echo 'Internal Server Error';
    }
});

/* ---- 6. HTTP security headers + session (web only) -------------- */

if (PHP_SAPI !== 'cli') {
    send_security_headers();

    // Serverless (Vercel) has an ephemeral filesystem, so file-based sessions don't
    // persist between invocations — store them in the database instead. Driven by
    // SESSION_DRIVER (auto = 'database' on Vercel, 'file' elsewhere). See config/app.php.
    if (config('app.session_driver', 'file') === 'database') {
        session_set_save_handler(new \App\Support\DbSessionHandler(), true);
    }

    // Env-aware session start. The skill's start_secure_session() forces a Secure
    // cookie (correct for production behind HTTPS). Locally over plain HTTP we relax
    // `secure` so dev sessions persist; everything else stays hardened.
    $secureCookie = config('app.env', 'production') !== 'local' || request_is_https();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Periodically rotate the session id to limit fixation.
    if (empty($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif (time() - (int) $_SESSION['__created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }

    // Resolve interface locale + display currency for this request.
    set_locale((string) (
        $_COOKIE['locale'] ?? config('app.default_locale', 'fr')
    ));
    set_currency((string) (
        $_COOKIE['currency'] ?? config('app.default_currency', 'EUR')
    ));
}
