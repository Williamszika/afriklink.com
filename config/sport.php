<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Sport & loisirs » (catégorie boutique
 * « sport »). Même philosophie que config/auto.php : le TYPE pilote les
 * caractéristiques, l'axe de déclinaison et des repères contextuels. Premier rayon :
 * Chaussures (déclinaison Pointure). Repères : terrain/usage, amorti, fermeture ;
 * crampons adaptés au terrain (foot/rugby — note FG/SG/AG/IN-TF) ; chaussures
 * aquatiques (séchage / antidérapant). Specs dans products.attributes (JSON) —
 * aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group?, fields(list), axis, color?, cleats?/water? (notes), defaults? ]
 */
return [
    'shop_categories' => ['sport'],
    'conditions'      => ['Neuf', 'Comme neuf', 'Très bon état', 'Bon état', 'Occasion'],

    'rayons' => [
        'Chaussures' => [
            'groups' => [
                'course'  => 'Course & nature',
                'terrain' => 'Terrain & ballon',
                'salle'   => 'Salle & raquette',
                'autres'  => 'Autres',
            ],
            'atouts' => ['Léger', 'Respirant', 'Bon maintien', 'Semelle durable', 'Imperméable', 'Édition récente', 'Occasion testée', 'Confort longue distance'],
            'fields' => [
                'genre'     => ['label' => 'Public', 'opts' => ['Homme', 'Femme', 'Mixte', 'Junior', 'Enfant']],
                'terrain'   => ['label' => 'Terrain / usage', 'opts' => ['Route', 'Trail / chemin', 'Salle (indoor)', 'Synthétique (AG)', 'Terrain ferme (FG)', 'Terrain souple (SG)', 'Stabilisé / turf (TF)', 'Tout terrain']],
                'matiere'   => ['label' => 'Matière', 'opts' => ['Mesh / textile', 'Cuir', 'Synthétique', 'Daim', 'Maille', 'Cuir + textile']],
                'amorti'    => ['label' => 'Amorti', 'opts' => ['Minimaliste', 'Léger', 'Équilibré', 'Maximal']],
                'fermeture' => ['label' => 'Fermeture', 'opts' => ['Lacets', 'Scratch', 'Élastique', 'BOA', 'Sans lacet']],
            ],
            'types' => [
                // Course & nature
                'Running / course à pied'      => ['group' => 'course', 'fields' => ['genre', 'terrain', 'matiere', 'amorti', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                'Trail / randonnée'            => ['group' => 'course', 'fields' => ['genre', 'terrain', 'matiere', 'amorti', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                'Marche / lifestyle sport'     => ['group' => 'course', 'fields' => ['genre', 'matiere', 'amorti', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                // Terrain & ballon — crampons
                'Football (crampons)'          => ['group' => 'terrain', 'fields' => ['genre', 'terrain', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true, 'cleats' => true, 'defaults' => ['terrain' => 'Terrain ferme (FG)']],
                'Rugby (crampons)'             => ['group' => 'terrain', 'fields' => ['genre', 'terrain', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true, 'cleats' => true, 'defaults' => ['terrain' => 'Terrain ferme (FG)']],
                'Futsal / salle'               => ['group' => 'terrain', 'fields' => ['genre', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                // Salle & raquette
                'Basket / basketball'          => ['group' => 'salle', 'fields' => ['genre', 'matiere', 'amorti', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                'Tennis / sports de raquette'  => ['group' => 'salle', 'fields' => ['genre', 'terrain', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                'Fitness / training'           => ['group' => 'salle', 'fields' => ['genre', 'matiere', 'amorti', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                // Autres
                'Skate / streetwear'           => ['group' => 'autres', 'fields' => ['genre', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
                'Chaussures aquatiques'        => ['group' => 'autres', 'fields' => ['genre', 'matiere'], 'axis' => 'Pointure', 'color' => true, 'water' => true],
                'Autre chaussure de sport'     => ['group' => 'autres', 'fields' => ['genre', 'terrain', 'matiere', 'fermeture'], 'axis' => 'Pointure', 'color' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons : pointures adulte + enfant (le vendeur choisit).
    'size_systems' => [
        'Pointure' => [
            ['label' => 'Pointures', 'list' => ['39', '40', '41', '42', '43', '44', '45']],
            ['label' => 'Pointures enfant', 'list' => ['28', '30', '32', '34', '35', '36', '37', '38']],
        ],
    ],
];
