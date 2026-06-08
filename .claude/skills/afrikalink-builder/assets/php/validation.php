<?php
declare(strict_types=1);

/**
 * Helpers de validation / normalisation des entrées.
 * Principe : valider en entrée, échapper en sortie (voir e() ci-dessous).
 */

/** Échappement HTML pour l'affichage (sortie). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Récupère une chaîne nettoyée (trim) ou null. */
function input_string(string $key, ?string $default = null): ?string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (!is_string($v)) {
        return $default;
    }
    $v = trim($v);
    return $v === '' ? $default : $v;
}

/** Email valide normalisé, ou null. */
function input_email(string $key): ?string
{
    $v = input_string($key);
    if ($v === null) {
        return null;
    }
    $v = strtolower($v);
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
}

/** Entier dans une plage optionnelle, ou null. */
function input_int(string $key, ?int $min = null, ?int $max = null): ?int
{
    $raw = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($raw === null || !is_numeric($raw)) {
        return null;
    }
    $n = (int) $raw;
    if ($min !== null && $n < $min) {
        return null;
    }
    if ($max !== null && $n > $max) {
        return null;
    }
    return $n;
}

/** Code devise ISO 4217 (3 lettres majuscules) whitelisté. */
function input_currency(string $key, array $allowed = ['EUR', 'USD', 'XOF', 'NGN', 'GBP']): ?string
{
    $v = input_string($key);
    if ($v === null) {
        return null;
    }
    $v = strtoupper($v);
    return in_array($v, $allowed, true) ? $v : null;
}

/**
 * Whiteliste une valeur contre une liste fixe (utile pour ENUM, tri, colonnes).
 * À utiliser pour tout ce qui ne peut PAS être un paramètre préparé (noms de colonnes, ORDER BY).
 */
function whitelist(mixed $value, array $allowed, mixed $fallback = null): mixed
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

/** Mot de passe : longueur minimale raisonnable. */
function is_valid_password(string $pwd): bool
{
    return strlen($pwd) >= 12 && strlen($pwd) <= 4096;
}
