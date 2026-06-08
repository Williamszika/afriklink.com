<?php
declare(strict_types=1);

/**
 * Protection CSRF.
 * - csrf_token()  : retourne (et génère si besoin) le token de session.
 * - csrf_field()  : champ caché à inclure dans chaque formulaire.
 * - csrf_check()  : à appeler en tête de tout traitement POST/PUT/PATCH/DELETE.
 *
 * Prérequis : session démarrée (cookies HttpOnly/Secure/SameSite — voir bootstrap).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

/**
 * Vérifie le token (corps de formulaire OU en-tête X-CSRF-Token pour le JS).
 * Comparaison en temps constant. Rejette en 419 si invalide.
 */
function csrf_check(): void
{
    $sent = $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (empty($_SESSION['csrf_token']) || !is_string($sent)
        || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(419); // Authentication Timeout / token invalide
        exit('CSRF token invalide.');
    }
}
