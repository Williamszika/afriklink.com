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
    'slug_reserved' => ['creer', 'gerer', 'modifier', 'publier', 'produits', 'qr', 'stats', 'api', 'admin', 'boutique', 'login', 'register', 'dashboard'],

    'product_name_max'  => 150,
    'product_desc_max'  => 3000,
    'product_max_photos' => 5,
    'product_max_video_seconds' => 120, // 2 minutes
    'banner_max' => 10, // bannière = diaporama animé, jusqu'à 10 images

    'delivery_zones'   => ['city', 'country', 'international'],
    'delivery_methods' => ['hand_to_hand', 'pickup', 'local', 'international'],
    'prep_options'     => ['same_day', '1_3', '3_7', 'over_7'],

    // Quand le client paie (le vendeur coche celles qu'il propose).
    'payment_terms'    => ['on_delivery', 'deposit', 'before_delivery'],
    // Part payée d'avance pour la condition « acompte » (%). Le reste est réglé à la livraison.
    'deposit_pct'      => (int) env('SHOP_DEPOSIT_PCT', 50),
    // Comment le client paie (le vendeur coche ce qu'il peut recevoir).
    // L'encaissement réel (Stripe/PayPal…) est un chantier ultérieur (Phase 3).
    'payment_methods'  => ['cash', 'mobile_money', 'paypal', 'card', 'apple_pay', 'google_pay'],

    // Catégories : on réutilise celles des annonces.
    'categories' => null, // résolu via config('listings.categories')
];
