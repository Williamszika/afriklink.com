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

        'Fitness' => [
            'groups' => [
                'poids'   => 'Poids libres',
                'station' => 'Stations & bancs',
                'cardio'  => 'Cardio & électro',
                'access'  => 'Accessoires & sol',
                'autre'   => 'Autre',
            ],
            'atouts' => ['Compact / pliable', 'Antidérapant', 'Réglable', 'Silencieux', 'Notice incluse', 'Garantie incluse', 'Occasion testée', 'Qualité pro'],
            'fields' => [
                'usage'     => ['label' => 'Usage', 'opts' => ['Maison', 'Studio / salle', 'Outdoor', 'Pro / intensif']],
                'niveau'    => ['label' => 'Niveau', 'opts' => ['Débutant', 'Intermédiaire', 'Confirmé', 'Tous niveaux']],
                'poids'     => ['label' => 'Poids / charge', 'opts' => ['1–5 kg', '5–10 kg', '10–20 kg', '20–50 kg', '50 kg +', 'Réglable', 'Sans objet']],
                'matiere'   => ['label' => 'Matière', 'opts' => ['Fonte', 'Acier', 'Caoutchouc / néoprène', 'Vinyle', 'PVC', 'Mousse EVA', 'Textile', 'Plastique', 'Mixte']],
                'reglable'  => ['label' => 'Réglable', 'opts' => ['Oui', 'Non']],
                'pliable'   => ['label' => 'Pliage / rangement', 'opts' => ['Pliable', 'Compact', 'Non pliable']],
                'alim'      => ['label' => 'Alimentation', 'opts' => ['Secteur (220–240 V)', 'Auto-alimenté', 'Piles', 'Sans alimentation']],
                'garantie'  => ['label' => 'Garantie', 'opts' => ['Aucune', '6 mois', '1 an', '2 ans']],
                'dimension' => ['label' => 'Taille / dimensions', 'opts' => ['S', 'M', 'L', 'XL', 'Standard', 'Sur mesure']],
            ],
            // types : group, fields, axis, color, + drapeaux weight / heavy / elec.
            'types' => [
                'Haltères / poids'                       => ['group' => 'poids', 'fields' => ['poids', 'matiere', 'usage', 'niveau'], 'axis' => 'Poids', 'color' => false, 'weight' => true],
                'Kettlebell'                             => ['group' => 'poids', 'fields' => ['poids', 'matiere', 'usage', 'niveau'], 'axis' => 'Poids', 'color' => false, 'weight' => true],
                'Barre & disques'                        => ['group' => 'poids', 'fields' => ['poids', 'matiere', 'usage'], 'axis' => 'Poids', 'color' => false, 'weight' => true],
                'Ballon de gym / médecine-ball'          => ['group' => 'poids', 'fields' => ['poids', 'dimension', 'matiere', 'usage'], 'axis' => 'Diamètre', 'color' => true, 'weight' => true],
                'Banc de musculation'                    => ['group' => 'station', 'fields' => ['usage', 'reglable', 'pliable', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'heavy' => true],
                'Rack / cage de squat'                   => ['group' => 'station', 'fields' => ['usage', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'heavy' => true],
                'Station multifonction'                  => ['group' => 'station', 'fields' => ['usage', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'heavy' => true],
                'Appareil cardio'                        => ['group' => 'cardio', 'fields' => ['usage', 'pliable', 'alim', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true, 'heavy' => true],
                'Électrostimulation / récupération'      => ['group' => 'cardio', 'fields' => ['usage', 'alim', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Tapis de yoga / fitness'                => ['group' => 'access', 'fields' => ['matiere', 'dimension', 'usage', 'niveau'], 'axis' => 'Couleur', 'color' => true],
                'Élastiques / bandes'                    => ['group' => 'access', 'fields' => ['niveau', 'matiere', 'usage'], 'axis' => 'Résistance', 'color' => true],
                'Corde à sauter'                         => ['group' => 'access', 'fields' => ['matiere', 'niveau', 'reglable'], 'axis' => 'Modèle', 'color' => true],
                'Step / plateforme'                      => ['group' => 'access', 'fields' => ['reglable', 'usage'], 'axis' => 'Modèle', 'color' => true],
                'Accessoires (gants, ceinture, sangles)' => ['group' => 'access', 'fields' => ['matiere', 'dimension', 'usage'], 'axis' => 'Taille', 'color' => true],
                'Autre matériel fitness'                 => ['group' => 'autre', 'fields' => ['usage', 'matiere', 'niveau', 'garantie'], 'axis' => 'Modèle', 'color' => true],
            ],
        ],

        'Plein air' => [
            'groups' => [
                'camping'   => 'Camping & bivouac',
                'rando'     => 'Randonnée',
                'cuisson'   => 'Cuisson & lumière',
                'activites' => 'Activités',
                'autre'     => 'Autre',
            ],
            'atouts' => ['Imperméable', 'Ultra-léger', 'Compact / pliable', 'Montage rapide', 'Grand froid', 'Accessoires inclus', 'Occasion testée', 'Robuste'],
            'fields' => [
                'usage'       => ['label' => 'Usage', 'opts' => ['Randonnée', 'Camping', 'Bivouac', 'Plage', 'Jardin / pique-nique', 'Pêche', 'Montagne', 'Sports nautiques']],
                'capacite'    => ['label' => 'Capacité / places', 'opts' => ['1 personne', '2 personnes', '3–4 personnes', '4–6 personnes', '6+ personnes', 'Famille']],
                'saison'      => ['label' => 'Saison / température', 'opts' => ['Été', '3 saisons', '4 saisons', 'Hiver / grand froid']],
                'matiere'     => ['label' => 'Matière', 'opts' => ['Polyester', 'Nylon', 'Toile coton', 'Aluminium', 'Acier', 'Inox', 'Bois', 'Plastique', 'Mixte']],
                'impermeable' => ['label' => 'Imperméabilité', 'opts' => ['Non', 'Déperlant', 'Imperméable', 'Étanche (colonne d’eau)']],
                'pliable'     => ['label' => 'Pliage / portage', 'opts' => ['Pliable', 'Compact', 'Ultra-léger', 'Non pliable']],
                'volume'      => ['label' => 'Volume / contenance', 'opts' => ['< 20 L', '20–40 L', '40–60 L', '60–80 L', '> 80 L', 'Sans objet']],
                'alim'        => ['label' => 'Alimentation / énergie', 'opts' => ['Sans', 'Piles', 'Rechargeable USB', 'Gaz', 'Essence', 'Charbon', 'Solaire']],
                'garantie'    => ['label' => 'Garantie', 'opts' => ['Aucune', '6 mois', '1 an', '2 ans']],
            ],
            // types : group, fields, axis, color, + drapeaux conseils shelter/sleep/pack/fire/light/watersport/fishing/elec.
            'types' => [
                'Tente'                            => ['group' => 'camping', 'fields' => ['capacite', 'saison', 'impermeable', 'matiere'], 'axis' => 'Places', 'color' => true, 'shelter' => true],
                'Tente de plage / abri'            => ['group' => 'camping', 'fields' => ['capacite', 'impermeable', 'matiere'], 'axis' => 'Couleur', 'color' => true, 'shelter' => true],
                'Sac de couchage'                  => ['group' => 'camping', 'fields' => ['saison', 'matiere', 'pliable'], 'axis' => 'Modèle', 'color' => true, 'sleep' => true],
                'Matelas / tapis de sol'           => ['group' => 'camping', 'fields' => ['saison', 'matiere', 'pliable'], 'axis' => 'Modèle', 'color' => true, 'sleep' => true],
                'Mobilier camping (table, chaise)' => ['group' => 'camping', 'fields' => ['matiere', 'pliable', 'capacite'], 'axis' => 'Modèle', 'color' => true],
                'Hamac'                            => ['group' => 'camping', 'fields' => ['capacite', 'matiere', 'impermeable'], 'axis' => 'Couleur', 'color' => true],
                'Sac à dos / sac de rando'         => ['group' => 'rando', 'fields' => ['volume', 'usage', 'impermeable', 'matiere'], 'axis' => 'Volume', 'color' => true, 'pack' => true],
                'Bâtons de marche'                 => ['group' => 'rando', 'fields' => ['matiere', 'pliable', 'usage'], 'axis' => 'Couleur', 'color' => true],
                'Glacière / gourde isotherme'      => ['group' => 'rando', 'fields' => ['volume', 'matiere', 'usage'], 'axis' => 'Contenance', 'color' => true],
                'Jumelles / GPS rando'             => ['group' => 'rando', 'fields' => ['alim', 'usage', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Réchaud / popote'                 => ['group' => 'cuisson', 'fields' => ['alim', 'matiere', 'pliable'], 'axis' => 'Modèle', 'color' => false, 'fire' => true],
                'Barbecue portable / grill'        => ['group' => 'cuisson', 'fields' => ['alim', 'matiere', 'pliable'], 'axis' => 'Modèle', 'color' => false, 'fire' => true],
                'Lampe / frontale'                 => ['group' => 'cuisson', 'fields' => ['alim', 'pliable'], 'axis' => 'Modèle', 'color' => true, 'light' => true],
                'Matériel de pêche'                => ['group' => 'activites', 'fields' => ['usage', 'matiere', 'pliable'], 'axis' => 'Modèle', 'color' => true, 'fishing' => true],
                'Jeux de plein air'                => ['group' => 'activites', 'fields' => ['usage', 'matiere'], 'axis' => 'Modèle', 'color' => true],
                'Sports nautiques (paddle, kayak)' => ['group' => 'activites', 'fields' => ['capacite', 'matiere', 'pliable'], 'axis' => 'Couleur', 'color' => true, 'watersport' => true],
                'Autre plein air'                  => ['group' => 'autre', 'fields' => ['usage', 'matiere', 'pliable', 'impermeable'], 'axis' => 'Couleur', 'color' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons (axe → groupes de valeurs).
    'size_systems' => [
        'Pointure' => [
            ['label' => 'Pointures', 'list' => ['39', '40', '41', '42', '43', '44', '45']],
            ['label' => 'Pointures enfant', 'list' => ['28', '30', '32', '34', '35', '36', '37', '38']],
        ],
        'Poids'      => [['label' => 'Charges (kg)', 'list' => ['2 kg', '4 kg', '6 kg', '8 kg', '10 kg', '12 kg', '16 kg', '20 kg']]],
        'Résistance' => [['label' => 'Résistances', 'list' => ['Très light', 'Light', 'Medium', 'Heavy', 'X-Heavy']]],
        'Diamètre'   => [['label' => 'Diamètres', 'list' => ['55 cm', '65 cm', '75 cm']]],
        'Taille'     => [['label' => 'Tailles', 'list' => ['S', 'M', 'L', 'XL']]],
        'Places'     => [['label' => 'Places', 'list' => ['1 place', '2 places', '3-4 places', '4-6 places']]],
        'Volume'     => [['label' => 'Volumes', 'list' => ['20 L', '30 L', '40 L', '50 L', '60 L']]],
        'Contenance' => [['label' => 'Contenances', 'list' => ['10 L', '25 L', '40 L']]],
        'Modèle'     => [],
        'Couleur'    => [],
    ],
];
