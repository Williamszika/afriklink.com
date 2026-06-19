<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Bébé & Enfant » (catégorie boutique
 * « bebe »). Même philosophie que config/alimentation.php (le TYPE pilote les
 * caractéristiques, la conservation par défaut, l'axe de déclinaison), mais avec
 * des GARDE-FOUS RÉGLEMENTAIRES propres aux aliments pour nourrissons :
 *   - age_fix    : âge minimum IMPOSÉ par le type (sinon le vendeur le choisit).
 *   - formula    : préparation infantile (lait) → note d'étiquetage réglementaire.
 *   - formula1   : préparation pour NOURRISSONS (0–6 mois) → PROMOTION INTERDITE et
 *                  aucune allégation (Règlement UE 2016/127 / Code OMS).
 *   - complement : complément / probiotique → note « avis d'un professionnel de santé ».
 *
 * Les champs « select » génériques (texture / conditionnement) vivent dans 'fields'
 * et sont rendus par le moteur. CONSERVATION, DLC/DDM + date, ALLERGÈNES (14 UE) et
 * RÉGIME/label sont des contrôles dédiés, affichés selon la liste 'fields' du type
 * (membres : 'conservation', 'allerg', 'regime'). Specs dans products.attributes
 * (JSON) — aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group?, fields(list), conserv(défaut), axis, age_fix?, formula?, formula1?, complement? ]
 */
return [
    'shop_categories' => ['bebe'],
    'conservations'   => ['Ambiante', 'Frais (réfrigéré)', 'Surgelé'],
    'dlc_types'       => ['DLC (à consommer jusqu’au)', 'DDM (à consommer de préférence avant)'],
    // 14 allergènes à déclaration obligatoire (UE / INCO 1169/2011).
    'allergenes'      => ['Gluten', 'Crustacés', 'Œufs', 'Poisson', 'Arachides', 'Soja', 'Lait', 'Fruits à coque', 'Céleri', 'Moutarde', 'Sésame', 'Sulfites', 'Lupin', 'Mollusques'],
    'regimes'         => ['Bio', 'Sans gluten', 'Sans lactose', 'Hypoallergénique (HA)', 'Sans sucre ajouté', 'Sans sel ajouté', 'Halal', 'Casher'],
    'ages'            => ['Dès 4 mois', 'Dès 6 mois', 'Dès 8 mois', 'Dès 10 mois', 'Dès 12 mois', 'Dès 18 mois', 'Dès 3 ans'],

    'rayons' => [
        'Alimentation' => [
            'groups' => [
                'laits'   => 'Laits infantiles',
                'repas'   => 'Repas & céréales',
                'gouters' => 'Goûters & encas',
                'autres'  => 'Autres',
            ],
            'atouts' => ['Bio', 'Sans sucre ajouté', 'Sans sel ajouté', 'Hypoallergénique', 'Fabriqué en UE', 'Étape de diversification', 'Gourde nomade', 'Recette simple'],
            // Champs « select » génériques rendus par le moteur (les autres membres de
            // 'fields' — conservation / allerg / regime — pilotent des contrôles dédiés).
            'fields' => [
                'texture' => ['label' => 'Texture', 'opts' => ['Liquide', 'Lisse / mixé', 'Petits morceaux', 'Morceaux', 'Solide']],
                'portion' => ['label' => 'Conditionnement', 'opts' => ['Pot', 'Gourde', 'Sachet', 'Boîte', 'Brique', 'Bouteille', 'Lot']],
            ],
            'types' => [
                // Laits infantiles — préparations réglementées.
                'Lait infantile 1er âge (0–6 mois)' => ['group' => 'laits', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => '0–6 mois', 'formula' => true, 'formula1' => true],
                'Lait infantile 2e âge (6–12 mois)' => ['group' => 'laits', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => 'Dès 6 mois', 'formula' => true],
                'Lait de croissance (1–3 ans)'      => ['group' => 'laits', 'fields' => ['portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => 'Dès 12 mois', 'formula' => true],
                // Repas & céréales.
                'Petit pot / repas bébé'            => ['group' => 'repas', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
                'Céréales infantiles / bouillie'    => ['group' => 'repas', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
                // Goûters & encas.
                'Compote / gourde'                  => ['group' => 'gouters', 'fields' => ['texture', 'portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Biscuit / goûter bébé'             => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Snack / encas enfant'              => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Boisson enfant (jus, eau)'         => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                // Autres.
                'Complément / probiotique bébé'     => ['group' => 'autres', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'complement' => true],
                'Autre aliment bébé/enfant'         => ['group' => 'autres', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons : par conditionnement (quel que soit l'axe).
    'size_systems' => [
        'Lot'     => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
        'Recette' => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
        'Parfum'  => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
    ],
];
