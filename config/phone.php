<?php

/**
 * Caractéristiques des téléphones (et électronique grand public). Le formulaire
 * produit bascule en mode « téléphone » quand la boutique est de catégorie
 * électronique : marque + modèle + état, et déclinaisons STOCKAGE × COULEUR.
 * Extensible (ajoutez marques/capacités).
 */
return [
    'brands' => [
        'Samsung', 'iPhone', 'Tecno', 'Infinix', 'Xiaomi', 'itel', 'Oppo', 'Huawei',
        'Nokia', 'Google Pixel', 'OnePlus', 'Realme', 'Vivo', 'Motorola', 'Honor',
    ],
    // Capacités de stockage (la « taille » des déclinaisons en mode téléphone).
    'storage' => ['16 Go', '32 Go', '64 Go', '128 Go', '256 Go', '512 Go', '1 To'],
    'ram'     => ['2 Go', '3 Go', '4 Go', '6 Go', '8 Go', '12 Go', '16 Go'],
    // États (clé => libellé i18n phone.cond.*).
    'conditions' => ['neuf', 'comme_neuf', 'occasion', 'reconditionne'],
    // Catégories de boutique qui activent le mode téléphone/électronique.
    'shop_categories' => ['electronique'],
];
