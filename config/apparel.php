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
    'couleurs'   => ['Noir', 'Blanc', 'Gris', 'Beige', 'Marron', 'Bleu', 'Bleu jean', 'Rouge', 'Vert', 'Kaki', 'Jaune', 'Rose', 'Violet', 'Orange', 'Multicolore', 'Doré', 'Argenté', 'Autre'],
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

        // =================== Pantalons & jeans ===================
        'Pantalons & jeans' => [
            'garment'   => 'pants',
            'axis'      => 'Taille',
            'genres'    => ['Femme', 'Homme', 'Mixte / unisexe', 'Fille', 'Garçon', 'Bébé'],
            // Remplissage rapide DÉPENDANT du genre (map genre => boutons). Types de bouton :
            // 'range' (de..à, pas), 'jeans' (préfixe W, pas 2 par défaut), 'list' (valeurs explicites).
            'quickfill' => [
                'Femme' => [
                    ['label' => 'FR 34–46', 'kind' => 'range', 'from' => 34, 'to' => 46, 'step' => 2],
                    ['label' => 'XS → XXL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                    ['label' => 'Jeans W26–W34', 'kind' => 'jeans', 'from' => 26, 'to' => 34],
                ],
                'Homme' => [
                    ['label' => 'FR 38–52', 'kind' => 'range', 'from' => 38, 'to' => 52, 'step' => 2],
                    ['label' => 'XS → 3XL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
                    ['label' => 'Jeans W28–W40', 'kind' => 'jeans', 'from' => 28, 'to' => 40],
                ],
                'Mixte / unisexe' => [
                    ['label' => 'XS → 3XL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
                    ['label' => 'FR 34–50', 'kind' => 'range', 'from' => 34, 'to' => 50, 'step' => 2],
                    ['label' => 'Jeans W26–W40', 'kind' => 'jeans', 'from' => 26, 'to' => 40],
                ],
                'Fille' => [
                    ['label' => 'Enfant 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Garçon' => [
                    ['label' => 'Enfant 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Bébé' => [
                    ['label' => 'Bébé 0–24 mois', 'kind' => 'list', 'list' => ['1 mois', '3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois']],
                    ['label' => 'Petit 2–5 ans', 'kind' => 'list', 'list' => ['2 ans', '3 ans', '4 ans', '5 ans']],
                ],
            ],
            'atouts' => ['Coton bio', 'Stretch confort', 'Taille haute', 'Grande taille', 'Fait main', 'Éco-responsable', 'Neuf avec étiquette', 'Édition limitée'],
            'groups' => [],
            'fields' => [
                'coupe'             => ['label' => 'Coupe', 'opts' => ['Slim', 'Skinny', 'Regular / droit', 'Bootcut', 'Large / baggy', 'Mom', 'Boyfriend', 'Carotte', 'Évasé / flare'], 'exclude' => ['Homme' => ['Mom', 'Boyfriend', 'Évasé / flare'], 'Garçon' => ['Mom', 'Boyfriend', 'Évasé / flare']]],
                'taille_haut'       => ['label' => 'Hauteur de taille', 'opts' => ['Taille haute', 'Taille mi-haute', 'Taille basse']],
                'matiere'           => ['label' => 'Matière principale', 'opts' => ['Coton', 'Denim (jean)', 'Lin', 'Laine', 'Polyester', 'Velours', 'Molleton', 'Mélange élasthanne (stretch)', 'Cuir / simili', 'Autre']],
                'lavage'            => ['label' => 'Lavage / délavage', 'opts' => ['Brut (raw)', 'Bleu clair', 'Bleu foncé', 'Délavé', 'Noir', 'Gris', 'Blanc']],
                'stretch'           => ['label' => 'Élasthanne (stretch)', 'opts' => ['Oui', 'Non']],
                'longueur_pantalon' => ['label' => 'Longueur', 'opts' => ['Standard', 'Court / 7-8e', 'Long', 'Cheville']],
                'fermeture'         => ['label' => 'Fermeture', 'opts' => ['Zip + bouton', 'Boutons', 'Élastique', 'Cordon', 'Élastique + cordon']],
                'poches'            => ['label' => 'Poches', 'opts' => ['Classiques', 'Multipoches / cargo', 'Sans poche']],
                'pinces'            => ['label' => 'Pinces / plis', 'opts' => ['Avec pinces', 'Sans pinces']],
                'cheville'          => ['label' => 'Bas resserré', 'opts' => ['Oui (élastique)', 'Non']],
                'short_longueur'    => ['label' => 'Longueur du short', 'opts' => ['Court', 'Mi-cuisse', 'Au genou', 'Bermuda']],
                'norme_travail'     => ['label' => 'Renforts / spécificité', 'opts' => ['Genoux renforcés', 'Poches porte-outils', 'Haute visibilité', 'Aucun']],
                'saison'            => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Hiver', 'Mi-saison']],
                'age_enfant'        => ['label' => 'Tranche d’âge', 'opts' => ['0-2 ans', '3-5 ans', '6-9 ans', '10-14 ans']],
            ],
            'types' => [
                'Jean'                          => ['group' => '', 'fields' => ['coupe', 'lavage', 'taille_haut', 'stretch', 'longueur_pantalon', 'matiere']],
                'Pantalon chino'                => ['group' => '', 'fields' => ['coupe', 'matiere', 'taille_haut', 'pinces', 'longueur_pantalon']],
                'Pantalon de costume / habillé' => ['group' => '', 'fields' => ['coupe', 'matiere', 'pinces', 'taille_haut', 'longueur_pantalon']],
                'Pantalon cargo'                => ['group' => '', 'fields' => ['coupe', 'matiere', 'poches', 'longueur_pantalon']],
                'Jogging / survêtement'         => ['group' => '', 'fields' => ['matiere', 'fermeture', 'cheville', 'poches']],
                'Legging'                       => ['group' => '', 'fields' => ['matiere', 'taille_haut', 'longueur_pantalon']],
                'Short / bermuda'               => ['group' => '', 'fields' => ['short_longueur', 'matiere', 'fermeture', 'poches']],
                'Pantalon de travail'           => ['group' => '', 'fields' => ['matiere', 'poches', 'norme_travail', 'coupe']],
                'Pantalon enfant'               => ['group' => '', 'fields' => ['matiere', 'fermeture', 'age_enfant', 'saison']],
                'Autre pantalon'                => ['group' => '', 'fields' => ['matiere', 'coupe', 'saison']],
            ],
        ],

        // =================== Robes & jupes (VERROUILLÉ public féminin) ===================
        'Robes & jupes' => [
            'garment'    => 'dress',
            'axis'       => 'Taille',
            'public'     => 'feminin', // verrou : stocké en attributes + restreint le public
            'lock_label' => 'Rayon réservé au public féminin',
            'genres'     => ['Femme', 'Fille', 'Bébé (fille)'],
            'couleurs'   => ['Noir', 'Blanc', 'Crème', 'Beige', 'Rouge', 'Bordeaux', 'Rose', 'Fuchsia', 'Bleu', 'Bleu marine', 'Vert', 'Kaki', 'Jaune', 'Orange', 'Violet', 'Doré', 'Argenté', 'Multicolore', 'Wax / imprimé', 'Autre'],
            'quickfill'  => [
                'Femme' => [
                    ['label' => 'FR 34–46', 'kind' => 'range', 'from' => 34, 'to' => 46, 'step' => 2],
                    ['label' => 'XS → XXL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                    ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
                ],
                'Fille' => [
                    ['label' => 'Fille 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Bébé (fille)' => [
                    ['label' => 'Bébé 0–24 mois', 'kind' => 'list', 'list' => ['1 mois', '3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois']],
                    ['label' => 'Petite 2–5 ans', 'kind' => 'list', 'list' => ['2 ans', '3 ans', '4 ans', '5 ans']],
                ],
            ],
            'atouts' => ['Wax / pagne', 'Fait main', 'Cousu main', 'Doublé', 'Grande taille', 'Éco-responsable', 'Neuf avec étiquette', 'Pièce unique'],
            'groups' => ['robes' => 'Robes', 'jupes' => 'Jupes', 'autre' => 'Autre'],
            'fields' => [
                'longueur'    => ['label' => 'Longueur', 'opts' => ['Mini / courte', 'Au genou', 'Midi', 'Longue / maxi', 'Cheville']],
                'coupe'       => ['label' => 'Coupe', 'opts' => ['Ajustée / moulante', 'Droite', 'Trapèze / évasée', 'Patineuse', 'Crayon', 'Portefeuille', 'Empire', 'Fourreau']],
                'manches'     => ['label' => 'Manches', 'opts' => ['Sans manches', 'Bretelles', 'Manches courtes', 'Manches 3/4', 'Manches longues', 'Bustier']],
                'encolure'    => ['label' => 'Encolure', 'opts' => ['Ronde', 'En V', 'Bateau', 'Carrée', 'Dos nu', 'Col montant', 'Cache-cœur']],
                'matiere'     => ['label' => 'Matière principale', 'opts' => ['Coton', 'Lin', 'Viscose', 'Polyester', 'Satin', 'Dentelle', 'Jean / denim', 'Maille', 'Mousseline', 'Cuir / simili', 'Wax / pagne', 'Autre']],
                'taille_haut' => ['label' => 'Hauteur de taille', 'opts' => ['Taille haute', 'Taille normale', 'Taille basse', 'Taille élastiquée']],
                'fermeture'   => ['label' => 'Fermeture', 'opts' => ['Zip', 'Boutons', 'Élastique', 'À enfiler', 'Laçage', 'Nœud']],
                'motif'       => ['label' => 'Motif', 'opts' => ['Uni', 'Wax / imprimé africain', 'Fleuri', 'Rayé', 'À pois', 'Carreaux', 'Animal', 'Géométrique']],
                'doublure'    => ['label' => 'Doublure', 'opts' => ['Doublé', 'Non doublé']],
                'occasion'    => ['label' => 'Occasion', 'opts' => ['Quotidien', 'Travail', 'Soirée / fête', 'Cérémonie / mariage', 'Plage / vacances']],
                'saison'      => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Hiver', 'Mi-saison']],
            ],
            'types' => [
                'Robe de jour / casual'     => ['group' => 'robes', 'fields' => ['longueur', 'coupe', 'manches', 'encolure', 'matiere', 'motif', 'saison']],
                'Robe d’été'                => ['group' => 'robes', 'fields' => ['longueur', 'coupe', 'manches', 'encolure', 'matiere', 'motif']],
                'Robe de soirée / cocktail' => ['group' => 'robes', 'fields' => ['longueur', 'coupe', 'manches', 'encolure', 'matiere', 'fermeture', 'doublure']],
                'Robe longue / maxi'        => ['group' => 'robes', 'fields' => ['coupe', 'manches', 'encolure', 'matiere', 'motif', 'occasion']],
                'Robe de cérémonie'         => ['group' => 'robes', 'fields' => ['longueur', 'coupe', 'manches', 'encolure', 'matiere', 'doublure', 'occasion']],
                'Robe de mariée'            => ['group' => 'robes', 'fields' => ['longueur', 'coupe', 'manches', 'encolure', 'matiere', 'doublure']],
                'Jupe courte / mini'        => ['group' => 'jupes', 'fields' => ['coupe', 'taille_haut', 'matiere', 'motif', 'fermeture']],
                'Jupe midi'                 => ['group' => 'jupes', 'fields' => ['coupe', 'taille_haut', 'matiere', 'motif', 'fermeture']],
                'Jupe longue'               => ['group' => 'jupes', 'fields' => ['coupe', 'taille_haut', 'matiere', 'motif', 'fermeture']],
                'Jupe crayon'               => ['group' => 'jupes', 'fields' => ['taille_haut', 'matiere', 'fermeture', 'doublure']],
                'Jupe plissée'              => ['group' => 'jupes', 'fields' => ['longueur', 'taille_haut', 'matiere', 'motif']],
                'Jupe en jean'              => ['group' => 'jupes', 'fields' => ['longueur', 'coupe', 'taille_haut', 'fermeture', 'matiere']],
                'Combinaison'               => ['group' => 'autre', 'fields' => ['longueur', 'manches', 'encolure', 'matiere', 'fermeture', 'occasion']],
                'Autre robe / jupe'         => ['group' => 'autre', 'fields' => ['longueur', 'matiere', 'motif', 'saison']],
            ],
        ],

        // =================== Sacs & accessoires (déclinaison COULEUR ou TAILLE selon le type) ===================
        'Sacs & accessoires' => [
            'garment'     => 'accessory',
            'axis'        => 'Couleur', // défaut rayon ; chaque type impose son axe (Couleur/Taille)
            'type_public' => true,      // le public proposé dépend du type ('pub')
            'type_decl'   => true,      // la déclinaison (couleur ⇄ taille) dépend du type
            'genres'      => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant'],
            'couleurs'    => ['Noir', 'Blanc', 'Gris', 'Beige', 'Marron', 'Camel', 'Rouge', 'Bordeaux', 'Bleu', 'Bleu marine', 'Vert', 'Kaki', 'Doré', 'Argenté', 'Rose', 'Multicolore', 'Wax / imprimé', 'Autre'],
            // Pastilles de coloris (mode couleur) : [libellé, hex].
            'palette' => [
                ['Noir', '#222222'], ['Blanc', '#ffffff'], ['Gris', '#9aa0a6'], ['Beige', '#d8c3a5'],
                ['Marron', '#6b4423'], ['Rouge', '#c0392b'], ['Bleu', '#2c3e8f'], ['Vert', '#2e7d4f'],
                ['Doré', '#c7922e'], ['Argenté', '#bdc3c7'], ['Rose', '#d6749a'], ['Multicolore', '#888888'],
            ],
            // Jeux de tailles (mode taille) par clé 'sizes' du type.
            'sizesets' => [
                'belt'   => [
                    ['label' => 'Ceinture 85–115 cm', 'kind' => 'range', 'from' => 85, 'to' => 115, 'step' => 5, 'suffix' => ' cm'],
                    ['label' => 'S → XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL']],
                ],
                'letter' => [
                    ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
                    ['label' => 'S → XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL']],
                ],
            ],
            'atouts' => ['Cuir véritable', 'Fait main', 'Wax / pagne', 'Pièce unique', 'Grande contenance', 'Éco-responsable', 'Neuf avec étiquette', 'Édition limitée'],
            'groups' => ['sacs' => 'Sacs & maroquinerie', 'acc_taille' => 'Accessoires (taille)', 'acc_couleur' => 'Accessoires (couleur)'],
            'fields' => [
                'matiere'          => ['label' => 'Matière principale', 'opts' => ['Cuir', 'Cuir synthétique / simili', 'Toile', 'Nylon', 'Daim', 'Paille / raphia', 'Wax / pagne', 'Métal', 'Tissu', 'Plastique', 'Autre']],
                'fermeture'        => ['label' => 'Fermeture', 'opts' => ['Zip', 'Aimant', 'Bouton-pression', 'Rabat', 'Cordon', 'Sans fermeture']],
                'poches'           => ['label' => 'Poches / compartiments', 'opts' => ['1', '2', '3', '4 et +']],
                'bandouliere'      => ['label' => 'Bandoulière', 'opts' => ['Fixe', 'Amovible / réglable', 'Sans']],
                'dimensions'       => ['label' => 'Taille du sac', 'opts' => ['Mini', 'Petit', 'Moyen', 'Grand', 'XXL / voyage']],
                'largeur_ceinture' => ['label' => 'Largeur', 'opts' => ['Fine (< 2 cm)', 'Moyenne (2-4 cm)', 'Large (> 4 cm)']],
                'boucle'           => ['label' => 'Type de boucle', 'opts' => ['Ardillon', 'Automatique', 'Décorative', 'Sans boucle']],
                'forme_lunettes'   => ['label' => 'Forme', 'opts' => ['Aviateur', 'Ronde', 'Carrée', 'Papillon', 'Rectangulaire', 'Œil de chat', 'Masque']],
                'uv'               => ['label' => 'Protection UV', 'opts' => ['UV400', 'Catégorie 3', 'Catégorie 2', 'Polarisé', 'Non précisé']],
                'monture'          => ['label' => 'Matière de la monture', 'opts' => ['Plastique / acétate', 'Métal', 'Mixte', 'Bois']],
                'saison'           => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Hiver', 'Mi-saison']],
                'type_bijou'       => ['label' => 'Type de bijou', 'opts' => ['Collier', 'Bracelet', 'Bague', 'Boucles d’oreilles', 'Parure', 'Pendentif', 'Chaîne de cheville']],
                'matiere_bijou'    => ['label' => 'Matière', 'opts' => ['Plaqué or', 'Or', 'Argent', 'Acier inoxydable', 'Laiton', 'Perles', 'Pierres', 'Fantaisie', 'Wax / tissu']],
                'chapeau_type'     => ['label' => 'Type', 'opts' => ['Casquette', 'Bob', 'Chapeau', 'Bonnet', 'Béret', 'Bandeau']],
                'echarpe_type'     => ['label' => 'Type', 'opts' => ['Écharpe', 'Foulard', 'Châle / étole', 'Snood']],
            ],
            'types' => [
                'Sac à main'                   => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches', 'bandouliere', 'dimensions'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Femme', 'Mixte / unisexe']],
                'Sac à dos'                    => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches', 'dimensions'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Sac bandoulière'              => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches', 'bandouliere', 'dimensions'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme']],
                'Pochette / clutch'            => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'bandouliere'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Femme', 'Mixte / unisexe']],
                'Cabas / tote'                 => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches', 'dimensions'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme']],
                'Sac de voyage'                => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches', 'dimensions'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme']],
                'Portefeuille / porte-monnaie' => ['group' => 'sacs', 'fields' => ['matiere', 'fermeture', 'poches'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme']],
                'Ceinture'                     => ['group' => 'acc_taille', 'fields' => ['matiere', 'largeur_ceinture', 'boucle'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'belt', 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Chapeau / casquette / bonnet' => ['group' => 'acc_taille', 'fields' => ['chapeau_type', 'matiere', 'saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'letter', 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant']],
                'Gants'                        => ['group' => 'acc_taille', 'fields' => ['matiere', 'saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'letter', 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Écharpe / foulard / châle'    => ['group' => 'acc_couleur', 'fields' => ['echarpe_type', 'matiere', 'saison'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Lunettes de soleil'           => ['group' => 'acc_couleur', 'fields' => ['forme_lunettes', 'uv', 'monture'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Bijoux'                       => ['group' => 'acc_couleur', 'fields' => ['type_bijou', 'matiere_bijou'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Femme', 'Mixte / unisexe', 'Homme', 'Fille']],
                'Montre (mode)'                => ['group' => 'acc_couleur', 'fields' => ['matiere', 'boucle'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme']],
                'Autre accessoire'             => ['group' => '', 'fields' => ['matiere', 'saison'], 'axis' => 'Couleur', 'color' => true, 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant']],
            ],
        ],

        // =================== Sous-vêtements (public & tailles par type ; NEUF only — hygiène) ===================
        'Sous-vêtements' => [
            'axis'           => 'Taille',
            'type_public'    => true, // le public autorisé dépend du type
            'type_sizes'     => true, // le système de tailles dépend du type ('sizes')
            'genres'         => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant', 'Bébé'],
            'couleurs'       => ['Noir', 'Blanc', 'Gris', 'Beige', 'Nude / chair', 'Rouge', 'Bordeaux', 'Bleu', 'Bleu marine', 'Vert', 'Rose', 'Violet', 'Imprimé', 'Multicolore', 'Autre'],
            'conditions'     => ['Neuf avec étiquette', 'Neuf sous emballage'], // hygiène : neuf uniquement
            'condition_note' => 'neuf uniquement (hygiène)',
            'sizesets' => [
                'letter'   => [
                    ['label' => 'XS → 3XL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
                    ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
                ],
                'bra'      => [
                    ['label' => 'Soutien-gorge 80–100', 'kind' => 'list', 'list' => ['80B', '85B', '85C', '90B', '90C', '90D', '95C', '95D', '100D']],
                    ['label' => 'S → XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL']],
                ],
                'collants' => [
                    ['label' => 'T1 → T4', 'kind' => 'list', 'list' => ['T1', 'T2', 'T3', 'T4']],
                    ['label' => 'S → XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL']],
                ],
                'socks'    => [
                    ['label' => 'Pointures', 'kind' => 'list', 'list' => ['35-38', '39-42', '43-46', '47+']],
                    ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
                    ['label' => 'Lot', 'kind' => 'list', 'list' => ['Lot de 3', 'Lot de 5', 'Lot de 10']],
                ],
                'kids'     => [
                    ['label' => 'Enfant 2–14 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans']],
                    ['label' => 'Bébé 0–24 mois', 'kind' => 'list', 'list' => ['1 mois', '3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois']],
                ],
            ],
            'atouts' => ['Coton bio', 'Sans couture', 'Lot économique', 'Confort', 'Maintien', 'Éco-responsable', 'Neuf avec étiquette'],
            'groups' => ['femme' => 'Femme', 'homme' => 'Homme', 'mixte' => 'Mixte', 'enfant' => 'Enfant'],
            'fields' => [
                'matiere'              => ['label' => 'Matière', 'opts' => ['Coton', 'Coton bio', 'Microfibre', 'Dentelle', 'Satin', 'Soie', 'Modal', 'Bambou', 'Polyester', 'Laine', 'Autre']],
                'doublure_rembourrage' => ['label' => 'Rembourrage', 'opts' => ['Non rembourré', 'Légèrement rembourré', 'Push-up', 'Coques']],
                'armatures'            => ['label' => 'Armatures', 'opts' => ['Avec armatures', 'Sans armatures']],
                'type_culotte'         => ['label' => 'Forme', 'opts' => ['Culotte classique', 'Tanga', 'String', 'Shorty', 'Taille haute', 'Brésilienne']],
                'type_boxer'           => ['label' => 'Forme', 'opts' => ['Boxer', 'Caleçon', 'Slip']],
                'lot'                  => ['label' => 'Conditionnement', 'opts' => ['À l’unité', 'Lot de 2', 'Lot de 3', 'Lot de 5']],
                'taille_haut'          => ['label' => 'Hauteur', 'opts' => ['Taille haute', 'Taille normale', 'Taille basse']],
                'transparence'         => ['label' => 'Opacité', 'opts' => ['Opaque', 'Semi-opaque', 'Voile / fin', 'Résille']],
                'denier'               => ['label' => 'Épaisseur (deniers)', 'opts' => ['20D', '40D', '60D', '80D', '100D et +']],
                'maintien'             => ['label' => 'Maintien', 'opts' => ['Léger', 'Moyen', 'Fort / gainant']],
                'saison'               => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Hiver']],
                'manches'              => ['label' => 'Coupe', 'opts' => ['Manches courtes', 'Manches longues', 'Short', 'Pantalon long', 'Débardeur']],
            ],
            'types' => [
                'Culotte / string'             => ['group' => 'femme', 'fields' => ['type_culotte', 'matiere', 'taille_haut', 'lot'], 'sizes' => 'letter', 'pub' => ['Femme']],
                'Soutien-gorge'                => ['group' => 'femme', 'fields' => ['doublure_rembourrage', 'armatures', 'matiere'], 'sizes' => 'bra', 'pub' => ['Femme']],
                'Ensemble lingerie'            => ['group' => 'femme', 'fields' => ['matiere', 'doublure_rembourrage', 'armatures'], 'sizes' => 'letter', 'pub' => ['Femme']],
                'Nuisette / déshabillé'        => ['group' => 'femme', 'fields' => ['matiere', 'manches'], 'sizes' => 'letter', 'pub' => ['Femme']],
                'Collants / bas'               => ['group' => 'femme', 'fields' => ['transparence', 'denier', 'maintien', 'matiere'], 'sizes' => 'collants', 'pub' => ['Femme']],
                'Boxer / caleçon'              => ['group' => 'homme', 'fields' => ['type_boxer', 'matiere', 'lot'], 'sizes' => 'letter', 'pub' => ['Homme']],
                'Slip'                         => ['group' => 'homme', 'fields' => ['matiere', 'lot'], 'sizes' => 'letter', 'pub' => ['Homme', 'Mixte / unisexe']],
                'Maillot de corps / débardeur' => ['group' => 'homme', 'fields' => ['matiere', 'manches', 'lot'], 'sizes' => 'letter', 'pub' => ['Homme', 'Femme', 'Mixte / unisexe']],
                'Chaussettes'                  => ['group' => 'mixte', 'fields' => ['matiere', 'saison', 'lot'], 'sizes' => 'socks', 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Pyjama / vêtement de nuit'    => ['group' => 'mixte', 'fields' => ['matiere', 'manches', 'saison'], 'sizes' => 'letter', 'pub' => ['Femme', 'Homme', 'Mixte / unisexe']],
                'Sous-vêtements enfant'        => ['group' => 'enfant', 'fields' => ['matiere', 'lot', 'saison'], 'sizes' => 'kids', 'pub' => ['Fille', 'Garçon', 'Enfant', 'Bébé']],
                'Pyjama enfant'                => ['group' => 'enfant', 'fields' => ['matiere', 'manches', 'saison'], 'sizes' => 'kids', 'pub' => ['Fille', 'Garçon', 'Enfant', 'Bébé']],
                'Autre sous-vêtement'          => ['group' => '', 'fields' => ['matiere', 'lot'], 'sizes' => 'letter', 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
            ],
        ],

        // =================== T-shirts & hauts (public par type ; tailles par genre) ===================
        'T-shirts & hauts' => [
            'garment'     => 'tshirt',
            'axis'        => 'Taille',
            'type_public' => true, // le public autorisé dépend du type
            'genres'      => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant', 'Bébé'],
            'couleurs'    => ['Noir', 'Blanc', 'Crème', 'Gris', 'Beige', 'Rouge', 'Bordeaux', 'Rose', 'Bleu', 'Bleu marine', 'Vert', 'Kaki', 'Jaune', 'Orange', 'Violet', 'Marron', 'Multicolore', 'Wax / imprimé', 'Autre'],
            // Tailles DÉPENDANTES DU GENRE (le public est imposé par le type, mais les tailles suivent le genre choisi).
            'quickfill' => [
                'Femme' => [
                    ['label' => 'XS → XXL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                    ['label' => 'FR 34–46', 'kind' => 'range', 'from' => 34, 'to' => 46, 'step' => 2],
                ],
                'Homme' => [
                    ['label' => 'S → 3XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL', 'XXL', '3XL']],
                ],
                'Mixte / unisexe' => [
                    ['label' => 'XS → 3XL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
                    ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
                ],
                'Fille' => [
                    ['label' => 'Fille 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Garçon' => [
                    ['label' => 'Garçon 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Enfant' => [
                    ['label' => 'Enfant 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']],
                ],
                'Bébé' => [
                    ['label' => 'Bébé 0–24 mois', 'kind' => 'list', 'list' => ['1 mois', '3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois']],
                    ['label' => 'Petit 2–5 ans', 'kind' => 'list', 'list' => ['2 ans', '3 ans', '4 ans', '5 ans']],
                ],
            ],
            'atouts' => ['Wax / pagne', 'Coton bio', 'Fait main', 'Oversize', 'Unisexe', 'Éco-responsable', 'Neuf avec étiquette', 'Édition limitée'],
            'groups' => ['tshirts' => 'T-shirts & basiques', 'chemises' => 'Chemises', 'mailles' => 'Mailles & sweats', 'autre' => 'Autre'],
            'fields' => [
                'matiere'   => ['label' => 'Matière', 'opts' => ['Coton', 'Coton bio', 'Lin', 'Jersey', 'Maille', 'Laine', 'Polyester', 'Viscose', 'Modal', 'Molleton', 'Wax / pagne', 'Autre']],
                'col'       => ['label' => 'Col / encolure', 'opts' => ['Col rond', 'Col V', 'Col montant', 'Col chemise', 'Col polo', 'Col roulé', 'Col bénitier', 'Bateau / sans col']],
                'manches'   => ['label' => 'Manches', 'opts' => ['Sans manches', 'Manches courtes', 'Manches 3/4', 'Manches longues']],
                'coupe'     => ['label' => 'Coupe', 'opts' => ['Ajustée', 'Droite / regular', 'Oversize', 'Cintrée', 'Crop / court']],
                'motif'     => ['label' => 'Motif', 'opts' => ['Uni', 'Wax / imprimé africain', 'Rayé', 'À carreaux', 'Logo / texte', 'Fleuri', 'Graphique']],
                'fermeture' => ['label' => 'Fermeture', 'opts' => ['Sans', 'Boutons', 'Zip', 'Capuche + cordon']],
                'epaisseur' => ['label' => 'Épaisseur', 'opts' => ['Léger', 'Moyen', 'Épais / chaud']],
                'saison'    => ['label' => 'Saison', 'opts' => ['Toutes saisons', 'Été', 'Mi-saison', 'Hiver']],
            ],
            'types' => [
                'T-shirt'            => ['group' => 'tshirts', 'fields' => ['col', 'manches', 'coupe', 'matiere', 'motif'], 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant']],
                'Débardeur'          => ['group' => 'tshirts', 'fields' => ['coupe', 'matiere', 'motif'], 'pub' => ['Femme', 'Homme', 'Mixte / unisexe', 'Enfant']],
                'Polo'               => ['group' => 'tshirts', 'fields' => ['manches', 'coupe', 'matiere', 'motif'], 'pub' => ['Homme', 'Femme', 'Mixte / unisexe', 'Enfant']],
                'Crop top'           => ['group' => 'tshirts', 'fields' => ['manches', 'coupe', 'matiere', 'motif'], 'pub' => ['Femme', 'Fille']],
                'Chemise'            => ['group' => 'chemises', 'fields' => ['col', 'manches', 'coupe', 'matiere', 'motif'], 'pub' => ['Homme', 'Femme', 'Mixte / unisexe', 'Garçon']],
                'Chemisier / blouse' => ['group' => 'chemises', 'fields' => ['col', 'manches', 'coupe', 'matiere', 'motif'], 'pub' => ['Femme', 'Fille']],
                'Pull'               => ['group' => 'mailles', 'fields' => ['col', 'manches', 'matiere', 'epaisseur', 'motif'], 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Enfant']],
                'Gilet / cardigan'   => ['group' => 'mailles', 'fields' => ['fermeture', 'matiere', 'epaisseur', 'motif'], 'pub' => ['Femme', 'Homme', 'Mixte / unisexe', 'Enfant']],
                'Sweat / hoodie'     => ['group' => 'mailles', 'fields' => ['fermeture', 'coupe', 'matiere', 'epaisseur', 'motif'], 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant']],
                'Body'               => ['group' => 'autre', 'fields' => ['col', 'manches', 'matiere', 'coupe'], 'pub' => ['Femme', 'Bébé']],
                'Top habillé'        => ['group' => 'autre', 'fields' => ['col', 'manches', 'matiere', 'motif'], 'pub' => ['Femme']],
                'Autre haut'         => ['group' => 'autre', 'fields' => ['manches', 'matiere', 'motif', 'saison'], 'pub' => ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant']],
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────────
    // « Nouveau rayon » mode : le vendeur crée son rayon ; le formulaire s'adapte à
    // l'identifiant (slug) tapé (config 'R') : specs suggérées, axe, système de tailles
    // (par genre ou au mètre) et public autorisé. Rayon inconnu => modèle générique.
    // Specs libres (libellé→valeur) dans products.attributes. 'pub' : all|femme|none.
    // ─────────────────────────────────────────────────────────────────────────────
    'autre' => [
        'rayon_suggest' => ['Vestes & manteaux', 'Vêtements de sport', 'Maillots de bain', 'Vêtements traditionnels', 'Tissus & pagnes', 'Vêtements de grossesse', 'Vêtements de travail', 'Déguisements'],
        'generic_specs' => ['Matière', 'Coupe', 'Manches', 'Motif', 'Fermeture', 'Doublure', 'Saison', 'Dimensions'],
        'atout_suggest' => ['Wax / pagne', 'Fait main', 'Cousu main', 'Éco-responsable', 'Grande taille', 'Unisexe', 'Neuf avec étiquette', 'Pièce unique'],
        'couleurs'      => ['Noir', 'Blanc', 'Crème', 'Beige', 'Rouge', 'Bordeaux', 'Rose', 'Bleu', 'Bleu marine', 'Vert', 'Kaki', 'Jaune', 'Orange', 'Violet', 'Marron', 'Doré', 'Multicolore', 'Wax / imprimé', 'Autre'],
        // Remplissage rapide par genre (sizes='genre') — réutilisé par tous les rayons « autre ».
        'genre_sizes' => [
            'Femme' => [
                ['label' => 'XS → XXL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                ['label' => 'FR 34–46', 'kind' => 'range', 'from' => 34, 'to' => 46, 'step' => 2],
            ],
            'Homme' => [['label' => 'S → 3XL', 'kind' => 'list', 'list' => ['S', 'M', 'L', 'XL', 'XXL', '3XL']]],
            'Mixte / unisexe' => [
                ['label' => 'XS → 3XL', 'kind' => 'list', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
                ['label' => 'Taille unique', 'kind' => 'list', 'list' => ['Taille unique']],
            ],
            'Fille'  => [['label' => 'Fille 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']]],
            'Garçon' => [['label' => 'Garçon 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']]],
            'Enfant' => [['label' => 'Enfant 2–16 ans', 'kind' => 'list', 'list' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans', '16 ans']]],
            'Bébé'   => [['label' => 'Bébé 0–24 mois', 'kind' => 'list', 'list' => ['1 mois', '3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois']]],
        ],
        // Remplissage rapide au mètre / coupon (sizes='metre').
        'metre_sizes' => [
            ['label' => '1 → 6 mètres', 'kind' => 'list', 'list' => ['1 m', '2 m', '3 m', '4 m', '5 m', '6 m']],
            ['label' => 'Coupon 6 yards', 'kind' => 'list', 'list' => ['6 yards']],
            ['label' => 'Coupon 12 yards', 'kind' => 'list', 'list' => ['12 yards']],
        ],
        // Config par slug : specs suggérées, axe, pastille couleur, système de tailles, public.
        'R' => [
            'vestes-manteaux'         => ['specs' => ['Type (blazer, manteau, doudoune…)', 'Matière', 'Coupe', 'Fermeture', 'Doublure', 'Saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'all'],
            'vetements-de-sport'      => ['specs' => ['Discipline', 'Matière', 'Coupe', 'Respirant / technique', 'Saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'all'],
            'maillots-de-bain'        => ['specs' => ['Type (1 pièce, bikini, short de bain…)', 'Matière', 'Doublure', 'Maintien'], 'axis' => 'Taille', 'color' => true, 'sizes' => 'genre', 'pub' => 'all'],
            'vetements-traditionnels' => ['specs' => ['Type (boubou, kaftan, ensemble pagne…)', 'Matière', 'Motif / wax', 'Manches', 'Longueur', 'Fait main'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'all'],
            'tissus-pagnes'           => ['specs' => ['Type (wax, bazin, kente…)', 'Largeur / laize', 'Motif', 'Composition'], 'axis' => 'Longueur', 'color' => false, 'sizes' => 'metre', 'pub' => 'none'],
            'vetements-de-grossesse'  => ['specs' => ['Type', 'Matière', 'Allaitement (oui/non)', 'Coupe', 'Saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'femme'],
            'vetements-de-travail'    => ['specs' => ['Type (blouse, combinaison, tablier…)', 'Matière', 'Fermeture', 'Norme / EPI', 'Saison'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'all'],
            'deguisements'            => ['specs' => ['Thème / personnage', 'Matière', 'Pièces incluses', 'Accessoires inclus'], 'axis' => 'Taille', 'color' => false, 'sizes' => 'genre', 'pub' => 'all'],
        ],
    ],
];
