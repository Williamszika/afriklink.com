<?php
declare(strict_types=1);

/**
 * Inscription professionnelle. Les clés sont traduites via lang/ (pro.legal.*,
 * pro.lang.*). Le type d'activité (boutique, restaurant…) ne se choisit PAS à
 * l'inscription : le compte pro est créé d'abord, puis le tableau de bord pro
 * propose la création des vitrines.
 */
return [
    'legal_forms' => [
        'ei',          // Entrepreneur individuel / auto-entrepreneur
        'sarl',        // SARL / SUARL
        'sa_sas',      // SA / SAS
        'cooperative', // Coopérative / GIE
        'association', // Association
        'autre',
    ],
    'languages' => ['fr', 'en', 'pt', 'ar', 'wo'],

    'company_max'     => 150,
    'description_max' => 500,
];
