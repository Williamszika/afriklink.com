<?php
declare(strict_types=1);

/**
 * Vérification d'adresse (cohérence ville ↔ pays) par géocodage.
 * Fournisseur par défaut : OpenStreetMap / Nominatim — GRATUIT, sans clé.
 *
 * IMPORTANT (politique Nominatim) : usage modéré, un User-Agent identifiable
 * avec contact est OBLIGATOIRE, et l'attribution « © OpenStreetMap » doit être
 * visible. Le contrôle est « best-effort » et NE BLOQUE JAMAIS la commande :
 * en cas d'indisponibilité réseau, de quota ou de zone mal couverte, on laisse
 * passer (fail-open) — c'est un simple avertissement de cohérence.
 */
return [
    'enabled'    => filter_var(env('GEOCODE_ENABLED', '1'), FILTER_VALIDATE_BOOL),
    'provider'   => env('GEOCODE_PROVIDER', 'nominatim'),
    'endpoint'   => env('GEOCODE_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
    // User-Agent requis par Nominatim — METTRE un contact réel en production.
    'user_agent' => env('GEOCODE_USER_AGENT', 'AfrikaLink/1.0 (+https://afriklink.com; contact@afriklink.com)'),
    'timeout'    => (int) env('GEOCODE_TIMEOUT', 5),
];
