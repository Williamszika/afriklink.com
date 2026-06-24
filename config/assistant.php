<?php
declare(strict_types=1);

/**
 * Assistant d'aide intégré (« Assistant AfrikaLink »).
 *
 * Répond aux questions, guide la création de compte / de boutique, les premiers
 * pas, et aide à résoudre les problèmes — dans la langue du visiteur, en
 * s'appuyant sur la base de connaissances config/help.php et en illustrant par
 * des captures d'écran.
 *
 * Comme la traduction, l'assistant « intelligent » (IA) s'active dès qu'une clé
 * est présente (ASSISTANT_API_KEY, sinon ANTHROPIC_API_KEY / TRANSLATE_API_KEY).
 * SANS clé, l'assistant reste utile : il répond à partir de la base de
 * connaissances (recherche par mots-clés) avec liens et captures — jamais cassé.
 */
return [
    // Coupe complètement l'assistant (widget masqué) si mis à 0.
    'enabled'     => env('ASSISTANT_ENABLED', '1') !== '0',

    // Nom de l'assistant (persona), affiché dans le widget et utilisé par l'IA.
    'name'        => env('ASSISTANT_NAME', 'Agnès'),

    // Clé API IA — repli en cascade sur les clés déjà éventuellement présentes.
    'api_key'     => (string) (env('ASSISTANT_API_KEY', '')
                        ?: env('ANTHROPIC_API_KEY', '')
                        ?: env('TRANSLATE_API_KEY', '')),

    'provider'    => env('ASSISTANT_PROVIDER', 'anthropic'),
    // Petit modèle, rapide et économique, suffisant pour de l'aide produit.
    'model'       => env('ASSISTANT_MODEL', 'claude-haiku-4-5-20251001'),
    'max_tokens'  => (int) env('ASSISTANT_MAX_TOKENS', 800),

    // Nombre de tours (messages) conservés en session pour le contexte.
    'history_max' => (int) env('ASSISTANT_HISTORY_MAX', 12),
    // Longueur max d'un message utilisateur (garde-fou coût / abus).
    'input_max'   => (int) env('ASSISTANT_INPUT_MAX', 1500),
];
