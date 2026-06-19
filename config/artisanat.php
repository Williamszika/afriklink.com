<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Artisanat & Art africains » (catégorie
 * boutique « artisanat »). Même philosophie que config/auto.php : le RAYON pilote
 * les types, le TYPE pilote les caractéristiques et l'axe de déclinaison. Signatures
 * artisanat communes à tous les rayons : FAIT MAIN + PIÈCE UNIQUE (→ stock 1, sans
 * déclinaison), HISTOIRE de la pièce, et rappel CITES (espèces protégées). Specs dans
 * products.attributes (JSON) — aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group?, fields(list), axis, color(bool) ]
 */
return [
    'shop_categories' => ['artisanat'],
    'conditions'      => ['Neuf', 'Vintage', 'Occasion'],

    'rayons' => [
        'Bijoux' => [
            'groups' => [], // liste de types à plat (sans optgroups)
            'atouts' => ['Fait main', 'Pièce unique', 'Commerce équitable', 'Matériaux naturels', 'Soutien artisan local', 'Cauris authentiques', 'Perles Krobo', 'Personnalisable'],
            'fields' => [
                'matiere'      => ['label' => 'Matière principale', 'opts' => ['Perles de verre', 'Perles Krobo', 'Rocaille', 'Laiton / bronze', 'Argent', 'Or', 'Cuivre', 'Cauris', 'Bois', 'Os / corne', 'Ambre', 'Pierres naturelles', 'Wax / tissu', 'Raphia', 'Cuir', 'Mélange']],
                'technique'    => ['label' => 'Technique', 'opts' => ['Perlage', 'Tissage', 'Cire perdue (fonte)', 'Filigrane', 'Martelé', 'Macramé', 'Assemblage', 'Sculpté']],
                'origine'      => ['label' => 'Origine / tradition', 'opts' => ['Touareg', 'Krobo (Ghana)', 'Massaï', 'Peul / Fulani', 'Akan', 'Dogon', 'Sénoufo', 'Afrique de l’Ouest', 'Afrique de l’Est', 'Non précisé']],
                'genre'        => ['label' => 'Pour', 'opts' => ['Femme', 'Homme', 'Mixte', 'Enfant']],
                'taille_bijou' => ['label' => 'Taille / longueur', 'opts' => ['Réglable', 'Ras-de-cou', '40 cm', '45 cm', '50 cm', '60 cm', 'S', 'M', 'L', 'Sur mesure']],
                'fermoir'      => ['label' => 'Fermoir / fixation', 'opts' => ['Mousqueton', 'Fermoir à vis', 'Élastique', 'Nœud coulissant', 'Clip', 'Tige (boucle)', 'Sans fermoir']],
                'finition'     => ['label' => 'Finition', 'opts' => ['Brut / naturel', 'Poli', 'Doré', 'Argenté', 'Patiné', 'Émaillé']],
                'pierre'       => ['label' => 'Pierre / ornement', 'opts' => ['Aucune', 'Ambre', 'Turquoise', 'Agate', 'Corail', 'Onyx', 'Lapis-lazuli', 'Œil de tigre']],
            ],
            'types' => [
                'Collier'                     => ['fields' => ['matiere', 'technique', 'origine', 'genre', 'taille_bijou', 'fermoir'], 'axis' => 'Couleur', 'color' => true],
                'Bracelet'                    => ['fields' => ['matiere', 'technique', 'origine', 'genre', 'taille_bijou', 'fermoir'], 'axis' => 'Couleur', 'color' => true],
                'Boucles d’oreilles'          => ['fields' => ['matiere', 'technique', 'origine', 'genre', 'finition'], 'axis' => 'Couleur', 'color' => true],
                'Bague'                       => ['fields' => ['matiere', 'technique', 'origine', 'genre', 'taille_bijou', 'finition'], 'axis' => 'Taille', 'color' => false],
                'Parure / ensemble'           => ['fields' => ['matiere', 'technique', 'origine', 'genre'], 'axis' => 'Couleur', 'color' => true],
                'Pendentif / amulette'        => ['fields' => ['matiere', 'technique', 'origine', 'pierre', 'finition'], 'axis' => 'Modèle', 'color' => true],
                'Chevillère'                  => ['fields' => ['matiere', 'technique', 'origine', 'genre', 'taille_bijou'], 'axis' => 'Couleur', 'color' => true],
                'Parure de tête / headpiece'  => ['fields' => ['matiere', 'technique', 'origine', 'genre'], 'axis' => 'Couleur', 'color' => true],
                'Broche'                      => ['fields' => ['matiere', 'technique', 'origine', 'finition'], 'axis' => 'Modèle', 'color' => true],
                'Perles / fournitures'        => ['fields' => ['matiere', 'origine', 'finition'], 'axis' => 'Couleur', 'color' => true],
                'Autre bijou'                 => ['fields' => ['matiere', 'technique', 'origine', 'genre'], 'axis' => 'Modèle', 'color' => true],
            ],
        ],

        'Décoration' => [
            'groups' => [
                'vannerie'  => 'Vannerie & fibres',
                'poterie'   => 'Poterie & céramique',
                'sculpture' => 'Sculpture & objets',
                'murs'      => 'Murs & textiles',
                'lumiere'   => 'Lumière & table',
                'autre'     => 'Autre',
            ],
            'atouts' => ['Fait main', 'Pièce unique', 'Commerce équitable', 'Matériaux naturels', 'Soutien artisan local', 'Décor ethnique', 'Personnalisable', 'Upcyclé / recyclé'],
            'fields' => [
                'matiere'   => ['label' => 'Matière', 'opts' => ['Bois', 'Bronze / laiton', 'Terre cuite / céramique', 'Raphia', 'Sisal / paille', 'Calebasse', 'Pierre', 'Wax / tissu', 'Bogolan', 'Cuir', 'Métal recyclé', 'Perles', 'Verre']],
                'technique' => ['label' => 'Technique', 'opts' => ['Sculpté', 'Tissé / vannerie', 'Tourné (poterie)', 'Peint à la main', 'Cire perdue (fonte)', 'Pyrogravure', 'Assemblage', 'Teinture']],
                'origine'   => ['label' => 'Origine / tradition', 'opts' => ['Afrique de l’Ouest', 'Afrique centrale', 'Afrique de l’Est', 'Touareg', 'Dogon', 'Sénoufo', 'Ashanti', 'Zoulou', 'Non précisé']],
                'style'     => ['label' => 'Style', 'opts' => ['Traditionnel', 'Contemporain', 'Ethnique', 'Bohème', 'Minimaliste']],
                'piece_dim' => ['label' => 'Taille', 'opts' => ['Petit (< 20 cm)', 'Moyen (20–50 cm)', 'Grand (50–100 cm)', 'Très grand (> 1 m)', 'Sur mesure']],
                'usage'     => ['label' => 'Pièce / usage', 'opts' => ['Salon', 'Chambre', 'Cuisine / table', 'Entrée', 'Bureau', 'Extérieur', 'Mural']],
                'finition'  => ['label' => 'Finition', 'opts' => ['Brut / naturel', 'Poli', 'Verni', 'Patiné', 'Peint', 'Ciré']],
            ],
            'types' => [
                // Vannerie & fibres
                'Panier / corbeille'             => ['group' => 'vannerie', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'usage'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Set de table / dessous de plat' => ['group' => 'vannerie', 'fields' => ['matiere', 'technique', 'origine', 'usage'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Tapis / natte'                  => ['group' => 'vannerie', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim'], 'axis' => 'Taille', 'color' => true, 'elec' => false],
                // Poterie & céramique
                'Poterie / céramique'            => ['group' => 'poterie', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Vase'                           => ['group' => 'poterie', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Calebasse décorée'              => ['group' => 'poterie', 'fields' => ['technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => true, 'elec' => false],
                // Sculpture & objets
                'Sculpture / statuette'          => ['group' => 'sculpture', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Masque décoratif'               => ['group' => 'sculpture', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Objet en bronze'                => ['group' => 'sculpture', 'fields' => ['technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                // Murs & textiles
                'Tableau / peinture'             => ['group' => 'murs', 'fields' => ['technique', 'origine', 'style', 'piece_dim'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Tenture / textile mural'        => ['group' => 'murs', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Miroir décoré'                  => ['group' => 'murs', 'fields' => ['matiere', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Cadre'                          => ['group' => 'murs', 'fields' => ['matiere', 'origine', 'piece_dim', 'finition'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                // Lumière & table
                'Bougeoir / photophore'          => ['group' => 'lumiere', 'fields' => ['matiere', 'technique', 'origine', 'finition'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Luminaire / lampe artisanale'   => ['group' => 'lumiere', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim'], 'axis' => 'Modèle', 'color' => true, 'elec' => true],
                // Autre
                'Autre objet déco'               => ['group' => 'autre', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'usage'], 'axis' => 'Modèle', 'color' => true, 'elec' => false],
            ],
        ],

        'Maroquinerie' => [
            'groups' => [
                'sacs'   => 'Sacs',
                'petite' => 'Petite maroquinerie',
                'autres' => 'Autres pièces',
                'autre'  => 'Autre',
            ],
            'atouts' => ['Fait main', 'Cuir véritable', 'Tannage végétal', 'Pièce unique', 'Cousu main', 'Commerce équitable', 'Soutien artisan local', 'Wax authentique'],
            'fields' => [
                'matiere'      => ['label' => 'Cuir / matière', 'opts' => ['Cuir de vachette', 'Cuir de chèvre', 'Cuir de mouton', 'Cuir tanné végétal', 'Cuir pleine fleur', 'Daim / nubuck', 'Wax + cuir', 'Cuir recyclé', 'Simili / vegan']],
                'tannage'      => ['label' => 'Tannage', 'opts' => ['Végétal', 'Traditionnel (artisanal)', 'Minéral', 'Non précisé']],
                'technique'    => ['label' => 'Technique', 'opts' => ['Cousu main', 'Cousu sellier', 'Tressé', 'Repoussé / gravé', 'Teinture artisanale', 'Assemblage wax']],
                'origine'      => ['label' => 'Origine / tradition', 'opts' => ['Afrique de l’Ouest', 'Maroc', 'Touareg', 'Peul', 'Sahel', 'Afrique de l’Est', 'Non précisé']],
                'genre'        => ['label' => 'Pour', 'opts' => ['Femme', 'Homme', 'Mixte', 'Enfant']],
                'couleur_cuir' => ['label' => 'Couleur dominante', 'opts' => ['Naturel / fauve', 'Marron', 'Noir', 'Camel', 'Bordeaux', 'Tan', 'Wax multicolore']],
                'fermeture'    => ['label' => 'Fermeture', 'opts' => ['Zip', 'Rabat / boucle', 'Cordon', 'Aimant', 'Bouton-pression', 'Sans fermeture']],
                'doublure'     => ['label' => 'Doublure', 'opts' => ['Wax / tissu', 'Cuir', 'Coton', 'Non doublé']],
            ],
            'types' => [
                // Sacs
                'Sac à main'                   => ['group' => 'sacs', 'fields' => ['matiere', 'tannage', 'technique', 'origine', 'genre', 'couleur_cuir', 'fermeture', 'doublure'], 'axis' => 'Couleur', 'color' => true],
                'Sacoche / besace'             => ['group' => 'sacs', 'fields' => ['matiere', 'tannage', 'technique', 'origine', 'genre', 'couleur_cuir', 'fermeture', 'doublure'], 'axis' => 'Couleur', 'color' => true],
                'Pochette / clutch'            => ['group' => 'sacs', 'fields' => ['matiere', 'technique', 'origine', 'genre', 'couleur_cuir', 'fermeture'], 'axis' => 'Couleur', 'color' => true],
                'Cabas / panier cuir'          => ['group' => 'sacs', 'fields' => ['matiere', 'technique', 'origine', 'couleur_cuir'], 'axis' => 'Couleur', 'color' => true],
                'Sac à dos'                    => ['group' => 'sacs', 'fields' => ['matiere', 'tannage', 'technique', 'origine', 'genre', 'couleur_cuir', 'fermeture', 'doublure'], 'axis' => 'Couleur', 'color' => true],
                // Petite maroquinerie
                'Portefeuille / porte-cartes'  => ['group' => 'petite', 'fields' => ['matiere', 'tannage', 'technique', 'origine', 'genre', 'couleur_cuir'], 'axis' => 'Couleur', 'color' => true],
                'Trousse / étui'               => ['group' => 'petite', 'fields' => ['matiere', 'technique', 'origine', 'couleur_cuir', 'fermeture'], 'axis' => 'Couleur', 'color' => true],
                'Ceinture'                     => ['group' => 'petite', 'fields' => ['matiere', 'tannage', 'technique', 'origine', 'genre', 'couleur_cuir'], 'axis' => 'Taille', 'color' => true],
                'Bracelet en cuir'             => ['group' => 'petite', 'fields' => ['matiere', 'technique', 'origine', 'genre', 'couleur_cuir'], 'axis' => 'Taille', 'color' => true],
                // Autres pièces
                'Sandales / chaussures cuir'   => ['group' => 'autres', 'fields' => ['matiere', 'technique', 'origine', 'genre', 'couleur_cuir'], 'axis' => 'Pointure', 'color' => true],
                'Pouf (peau)'                  => ['group' => 'autres', 'fields' => ['matiere', 'technique', 'origine', 'couleur_cuir'], 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Autre maroquinerie'           => ['group' => 'autre', 'fields' => ['matiere', 'technique', 'origine', 'genre', 'couleur_cuir'], 'axis' => 'Modèle', 'color' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons selon l'axe. 'Taille' propose plusieurs jeux
    // (bagues / objets / ceintures / vêtement) pour couvrir tous les rayons sans conflit.
    'size_systems' => [
        'Taille'   => [
            ['label' => 'Tailles bague', 'list' => ['48', '50', '52', '54', '56', '58', '60', 'Réglable']],
            ['label' => 'Tailles objet', 'list' => ['Petit', 'Moyen', 'Grand', 'Très grand']],
            ['label' => 'Tailles ceinture', 'list' => ['85', '90', '95', '100', '105', '110', 'Réglable']],
            ['label' => 'Tailles S–XL', 'list' => ['S', 'M', 'L', 'XL']],
        ],
        'Pointure' => [['label' => 'Pointures', 'list' => ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']]],
        'Couleur'  => [['label' => 'Couleurs', 'list' => ['Multicolore', 'Naturel', 'Bois', 'Terre cuite', 'Marron', 'Camel', 'Bordeaux', 'Ocre', 'Rouge', 'Noir', 'Blanc', 'Doré']]],
    ],
];
