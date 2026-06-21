<?php
declare(strict_types=1);

/**
 * Traduction automatique du CONTENU des vendeurs (noms et descriptions de
 * produits/boutiques) dans toutes les langues du site. S'active dès qu'une clé
 * de traduction est fournie (TRANSLATE_API_KEY, ou ANTHROPIC_API_KEY) — sinon le
 * contenu reste affiché dans la langue d'origine du vendeur (repli silencieux),
 * exactement comme le modèle des fournisseurs de paiement.
 *
 * Le pré-remplissage se fait par lots via le cron /cron/traduire-contenu ; le
 * rendu lit la traduction stockée pour la langue active (sinon l'original).
 */
return [
    // Fournisseur de traduction : 'anthropic' (Claude) par défaut.
    'provider' => env('TRANSLATE_PROVIDER', 'anthropic'),

    // Clé API — repli sur ANTHROPIC_API_KEY si TRANSLATE_API_KEY n'est pas défini.
    'api_key'  => (string) (env('TRANSLATE_API_KEY', '') ?: env('ANTHROPIC_API_KEY', '')),

    // Modèle de traduction (petit/rapide par défaut pour le coût).
    'model'    => env('TRANSLATE_MODEL', 'claude-haiku-4-5-20251001'),

    // Langues cibles = les langues d'interface du site.
    'locales'  => config('app.locales', ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'ar']),

    // Garde-fous du cron (par exécution) pour maîtriser temps et coût.
    'cron_max_items'  => (int) env('TRANSLATE_CRON_MAX_ITEMS', 20),
    'cron_max_calls'  => (int) env('TRANSLATE_CRON_MAX_CALLS', 80),
];
