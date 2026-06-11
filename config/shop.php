<?php
declare(strict_types=1);

/**
 * Boutique en ligne (1ʳᵉ vitrine pro). L'assistant de création vit en 3 étapes ;
 * les produits/commandes/paiements réels sont des chantiers ultérieurs.
 */
return [
    'name_max'    => 80,
    'tagline_max' => 120,
    'desc_max'    => 1500,
    'slug_min'    => 3,
    'slug_max'    => 40,
    // Mots réservés : ne peuvent pas servir de slug (collisions de routes/URL).
    'slug_reserved' => ['creer', 'gerer', 'api', 'admin', 'boutique', 'login', 'register', 'dashboard'],

    'delivery_zones'   => ['city', 'country', 'international'],
    'delivery_methods' => ['pickup', 'local', 'international'],
    'prep_options'     => ['same_day', '1_3', '3_7', 'over_7'],

    // Catégories : on réutilise celles des annonces.
    'categories' => null, // résolu via config('listings.categories')
];
