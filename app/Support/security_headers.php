<?php
declare(strict_types=1);

/**
 * En-têtes de sécurité HTTP + démarrage de session durci.
 * Appeler send_security_headers() puis start_secure_session() au tout début du bootstrap,
 * AVANT toute sortie.
 */

function send_security_headers(): void
{
    // HTTPS strict (le domaine doit être servi en HTTPS — Cloudflare "Full (strict)")
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

    // Empêche le navigateur de deviner les types MIME
    header('X-Content-Type-Options: nosniff');

    // Anti-clickjacking
    header('X-Frame-Options: DENY');

    // Fuite de referer limitée
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions minimales
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // CSP — à AFFINER selon tes scripts. Éviter 'unsafe-inline' à terme (utiliser des nonces).
    $csp = implode('; ', [
        "default-src 'self'",
        "img-src 'self' data: https:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' https://js.stripe.com",
        "frame-src https://js.stripe.com https://hooks.stripe.com",
        "connect-src 'self' https://api.stripe.com",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);

    // Ne pas divulguer la stack
    header_remove('X-Powered-By');
}

function start_secure_session(): void
{
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,      // HTTPS uniquement
        'httponly' => true,      // inaccessible au JS
        'samesite' => 'Lax',     // protège contre la plupart des CSRF cross-site
    ]);
    session_start();

    // Régénérer périodiquement l'ID pour limiter la fixation de session
    if (empty($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif (time() - $_SESSION['__created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}
