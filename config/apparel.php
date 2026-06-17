<?php

/**
 * Taxonomie prêt-à-porter : genres (audiences), catégories de vêtements, et le
 * SYSTÈME DE TAILLES propre à chaque catégorie (un soutien-gorge ne se mesure pas
 * comme un pantalon ou une chaussure). Les tissus/pagnes se vendent AU MÈTRE.
 *
 * Extensible : ajoutez une catégorie en lui donnant un groupe, un système de
 * tailles (clé de 'size_systems') et son unité de vente ('piece' ou 'meter').
 * Les libellés sont traduits via i18n : apparel.aud.* / apparel.grp.* / apparel.cat.*
 */
return [
    // Catégories de boutique qui activent le mode « prêt-à-porter » (champs vêtements).
    'shop_categories' => ['mode'],

    // Publics visés.
    'audiences' => ['homme', 'femme', 'unisexe', 'enfant'],

    // Suggestions de tailles par système (la liste 'bra' est générée par helper).
    'size_systems' => [
        'alpha'     => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
        'shoe_eu'   => ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47'],
        'shoe_kids' => ['18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34'],
        'bra'       => [], // généré dynamiquement (tour de dos × bonnet)
        'waist_fr'  => ['36', '38', '40', '42', '44', '46', '48', '50', '52', '54'],
        'dress_fr'  => ['34', '36', '38', '40', '42', '44', '46', '48', '50'],
        'age'       => ['0-3 mois', '3-6 mois', '6-12 mois', '1 an', '2 ans', '3 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans'],
        'none'      => [],
        'meter'     => [],
    ],

    // Catégories : clé => [groupe, système de tailles, unité ('piece'|'meter'), genres].
    'categories' => [
        'tshirt'     => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'shirt'      => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe']],
        'pull'       => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'jacket'     => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe']],
        'pants'      => ['bas',            'waist_fr',  'piece', ['homme', 'femme', 'unisexe']],
        'shorts'     => ['bas',            'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'skirt'      => ['bas',            'dress_fr',  'piece', ['femme', 'enfant']],
        'dress'      => ['robes',          'dress_fr',  'piece', ['femme', 'enfant']],
        'bra'        => ['sous_vetements', 'bra',       'piece', ['femme']],
        'panties'    => ['sous_vetements', 'alpha',     'piece', ['femme']],
        'briefs'     => ['sous_vetements', 'alpha',     'piece', ['homme']],
        'socks'      => ['sous_vetements', 'none',      'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'shoes'      => ['chaussures',     'shoe_eu',   'piece', ['homme', 'femme', 'unisexe']],
        'shoes_kids' => ['chaussures',     'shoe_kids', 'piece', ['enfant']],
        'fabric'     => ['tissus',         'meter',     'meter', ['homme', 'femme', 'unisexe', 'enfant']],
        'uniform'    => ['uniformes',      'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'accessory'  => ['accessoires',    'none',      'piece', ['homme', 'femme', 'unisexe', 'enfant']],
    ],

    // ─────────────────────────────────────────────────────────────────────────────
    // Rayons ADAPTATIFS au type (comme l'électronique) : le rayon choisit la sous-config
    // ('rayons' => libellé => {groups, fields, types, atouts, genres, axis, quickfill}).
    // Le TYPE (clé de 'types') pilote les champs (clés de 'fields'). Communs mode :
    // genre (requis), couleur, état. Specs dans products.attributes (JSON) ; déclinaison
    // par pointure/taille (axe libre + remplissage rapide). 'garment' rattache le rayon à
    // une catégorie de tailles existante (affichage client + filtres).
    // ─────────────────────────────────────────────────────────────────────────────
    'conditions' => ['Neuf avec étiquette', 'Neuf', 'Très bon état', 'Bon état', 'Satisfaisant'],
    'genres'     => ['Femme', 'Homme', 'Mixte / unisexe', 'Enfant', 'Fille', 'Garçon', 'Bébé'],
    'couleurs'   => ['Noir', 'Blanc', 'Gris', 'Beige', 'Marron', 'Rouge', 'Bleu', 'Vert', 'Jaune', 'Rose', 'Violet', 'Orange', 'Multicolore', 'Doré', 'Argenté', 'Autre'],
    'axes'       => ['Pointure', 'Taille', 'Couleur'],

    'rayons' => [
        // =================== Chaussures ===================
        'Chaussures' => [
            'garment'   => 'shoes', // catégorie tailles/affichage (shoes_kids si public enfant)
            'axis'      => 'Pointure',
            'quickfill' => [
                ['label' => 'Femme 35–42', 'from' => 35, 'to' => 42],
                ['label' => 'Homme 40–46', 'from' => 40, 'to' => 46],
                ['label' => 'Enfant 28–35', 'from' => 28, 'to' => 35],
                ['label' => 'Bébé 17–27', 'from' => 17, 'to' => 27],
            ],
            'atouts' => ['Cuir véritable', 'Fait main', 'Confort / orthopédique', 'Imperméable', 'Antidérapant', 'Éco-responsable', 'Édition limitée', 'Neuf avec étiquette'],
            'groups' => ['sport' => 'Sport & ville', 'ete' => 'Été & habillé', 'hiver' => 'Hiver & spécial'],
            'fields' => [
                'montant'         => ['label' => 'Tige', 'opts' => ['Basse', 'Montante', 'Mi-montante']],
                'matiere_dessus'  => ['label' => 'Matière (dessus)', 'opts' => ['Cuir', 'Cuir synthétique', 'Daim', 'Toile', 'Textile / mesh', 'Caoutchouc', 'Plastique', 'Autre']],
                'matiere_semelle' => ['label' => 'Semelle', 'opts' => ['Caoutchouc', 'Gomme', 'EVA', 'Cuir', 'Crêpe', 'Autre']],
                'fermeture'       => ['label' => 'Fermeture', 'opts' => ['Lacets', 'Scratch (velcro)', 'Zip', 'À enfiler', 'Boucle', 'Élastique']],
                'talon'           => ['label' => 'Hauteur de talon', 'opts' => ['Plat', 'Petit (< 3 cm)', 'Moyen (3-7 cm)', 'Haut (> 7 cm)', 'Compensé', 'Plateforme']],
                'bout'            => ['label' => 'Forme du bout', 'opts' => ['Rond', 'Pointu', 'Carré', 'Ouvert']],
                'tige_hauteur'    => ['label' => 'Hauteur de tige', 'opts' => ['Cheville (bottine)', 'Mi-mollet', 'Genou', 'Cuissarde']],
                'doublure'        => ['label' => 'Doublure', 'opts' => ['Non doublé', 'Textile', 'Fourrée (chaud)']],
                'impermeable'     => ['label' => 'Imperméable', 'opts' => ['Oui', 'Résistant à l’eau', 'Non']],
                'saison'          => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Hiver', 'Mi-saison']],
                'usage'           => ['label' => 'Usage / style', 'opts' => ['Ville / casual', 'Sport', 'Soirée / habillé', 'Plage', 'Travail', 'Maison']],
                'sport_type'      => ['label' => 'Sport', 'opts' => ['Running', 'Football', 'Basketball', 'Tennis', 'Fitness', 'Randonnée', 'Multisport']],
                'terrain'         => ['label' => 'Terrain', 'opts' => ['Route', 'Terrain / trail', 'Salle', 'Gazon / stabilisé']],
                'norme_secu'      => ['label' => 'Norme de sécurité', 'opts' => ['SB', 'S1', 'S1P', 'S2', 'S3', 'Autre']],
                'embout'          => ['label' => 'Embout de protection', 'opts' => ['Acier', 'Composite', 'Aluminium', 'Sans']],
                'antiderapant'    => ['label' => 'Semelle antidérapante', 'opts' => ['Oui', 'Non']],
                'premiers_pas'    => ['label' => 'Étape (bébé/enfant)', 'opts' => ['Pré-marche', 'Premiers pas', 'Marche confirmée']],
            ],
            'types' => [
                'Baskets / sneakers'          => ['group' => 'sport', 'fields' => ['montant', 'matiere_dessus', 'matiere_semelle', 'fermeture', 'usage', 'saison']],
                'Chaussures de sport'         => ['group' => 'sport', 'fields' => ['sport_type', 'terrain', 'matiere_dessus', 'matiere_semelle', 'fermeture', 'saison']],
                'Mocassins / derbies / ville' => ['group' => 'sport', 'fields' => ['matiere_dessus', 'matiere_semelle', 'fermeture', 'bout', 'usage']],
                'Sandales / nu-pieds'         => ['group' => 'ete', 'fields' => ['matiere_dessus', 'fermeture', 'talon', 'usage', 'saison']],
                'Escarpins / talons'          => ['group' => 'ete', 'fields' => ['talon', 'bout', 'matiere_dessus', 'usage', 'saison']],
                'Ballerines'                  => ['group' => 'ete', 'fields' => ['matiere_dessus', 'bout', 'usage', 'saison']],
                'Tongs / claquettes'          => ['group' => 'ete', 'fields' => ['matiere_dessus', 'usage', 'saison']],
                'Bottes / bottines'           => ['group' => 'hiver', 'fields' => ['tige_hauteur', 'talon', 'fermeture', 'matiere_dessus', 'doublure', 'impermeable', 'saison']],
                'Chaussures de sécurité'      => ['group' => 'hiver', 'fields' => ['norme_secu', 'embout', 'antiderapant', 'impermeable', 'matiere_dessus']],
                'Chaussures enfant / bébé'    => ['group' => 'hiver', 'fields' => ['fermeture', 'premiers_pas', 'matiere_dessus', 'saison']],
                'Autre chaussure'             => ['group' => '', 'fields' => ['matiere_dessus', 'usage', 'saison']],
            ],
        ],
    ],
];
