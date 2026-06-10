<?php
declare(strict_types=1);

/**
 * Annonces entre particuliers (« Vendre un article »).
 * Les clés de catégories/états sont traduites via lang/ (listing.cat.*, listing.cond.*).
 */
return [
    'categories' => [
        'mode',          // Mode & vêtements
        'electronique',  // Électronique & téléphones
        'maison',        // Maison & meubles
        'beaute',        // Beauté & cosmétiques
        'alimentation',  // Alimentation
        'auto',          // Auto & pièces
        'artisanat',     // Artisanat & art africain
        'bebe',          // Bébés & enfants
        'sport',         // Sport & loisirs
        'autres',        // Autres
    ],
    'conditions' => ['neuf', 'tres_bon', 'bon', 'correct'],

    'max_photos'        => 5,
    'max_video_seconds' => 60,   // 1 minute max, vérifiée serveur
    'title_max'         => 120,
    'description_max'   => 5000,
];
