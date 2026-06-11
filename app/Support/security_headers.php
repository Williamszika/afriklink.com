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

    // Permissions minimales, accordées à NOTRE origine uniquement (self) :
    // - geolocation : pré-remplissage de la ville à l'inscription (avec consentement)
    // - camera/microphone : prise de photo et vidéo dans le formulaire d'annonce
    //   (getUserMedia — le navigateur affiche sa demande de permission)
    header('Permissions-Policy: geolocation=(self), microphone=(self), camera=(self)');

    // CSP — à AFFINER selon tes scripts. Éviter 'unsafe-inline' à terme (utiliser des nonces).
    $csp = implode('; ', [
        "default-src 'self'",
        // blob: : aperçus locaux avant envoi (photos/vidéo d'annonce)
        "img-src 'self' data: blob: https:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' https://js.stripe.com",
        "frame-src https://js.stripe.com https://hooks.stripe.com",
        // api.bigdatacloud.net : géocodage inverse (ville depuis la position GPS)
        // api.cloudinary.com : envoi direct navigateur → Cloudinary (photos/vidéos d'annonces)
        "connect-src 'self' https://api.stripe.com https://api.bigdatacloud.net https://api.cloudinary.com",
        // res.cloudinary.com : lecture des vidéos d'annonces ; blob: : aperçu avant envoi
        "media-src 'self' blob: https://res.cloudinary.com",
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
