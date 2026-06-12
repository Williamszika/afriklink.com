<?php
declare(strict_types=1);

/**
 * Verticale Restaurant : carte (menu) en ligne. Réutilise la base commune
 * (géolocalisation, contacts, paiement, commandes) et ajoute le spécifique
 * restauration.
 */
return [
    'name_max'  => 80,
    'slug_min'  => 3,
    'slug_max'  => 40,
    'desc_max'  => 1000,
    'slug_reserved' => ['creer', 'gerer', 'plat', 'plats', 'categorie', 'menu', 'api', 'admin', 'resto', 'login', 'register', 'dashboard'],

    'item_name_max' => 80,
    'item_desc_max' => 400,
    'item_max_photos' => 1,

    // Types de cuisine (pour le filtre/Explorer plus tard).
    'cuisines' => ['africaine', 'senegalaise', 'ivoirienne', 'maghrebine', 'libanaise',
        'fast_food', 'patisserie', 'grillades', 'pizza', 'asiatique', 'europeenne', 'autre'],

    // Mode de service.
    'services' => ['dine_in', 'takeaway', 'delivery'],

    // Jours de la semaine (horaires d'ouverture à cocher).
    'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],

    // Étiquettes alimentaires d'un plat (badges).
    'diets' => ['vegetarien', 'vegan', 'halal', 'epice', 'sans_gluten', 'populaire'],

    // Catégories de carte proposées par défaut à la création.
    'default_categories' => ['entrees', 'plats', 'desserts', 'boissons'],

    // Catégories standard sélectionnables (déroulant) : clé => type.
    'standard_categories' => [
        'entrees'         => 'dish',
        'plats'           => 'dish',
        'accompagnements' => 'dish',
        'grillades'       => 'dish',
        'salades'         => 'dish',
        'pizzas'          => 'dish',
        'sandwichs'       => 'dish',
        'desserts'        => 'dish',
        'menus'           => 'dish',
        'petit_dej'       => 'dish',
        'boissons'        => 'drink',
        'jus'             => 'drink',
    ],

    // Type d'une catégorie : pilote le formulaire d'ajout.
    //  - 'dish'  : plat standard (nom, prix, description, étiquettes)
    //  - 'drink' : boisson (nom + contenances cochables avec prix)
    'category_kinds' => ['dish', 'drink'],

    // Contenances proposées pour les boissons (en litres).
    'drink_volumes' => ['0.33', '0.5', '1', '1.5', '2'],
];
