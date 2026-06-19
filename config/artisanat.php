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
    'conditions'      => ['Neuf', 'Vintage', 'Ancien', 'Occasion'],
    // Poterie : valeurs d'« usage » qui déclenchent l'alerte contact alimentaire.
    'food_usages'     => ['Alimentaire (contact food)', 'Cuisson', 'Service de table'],

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

        'Poterie' => [
            'groups' => [
                'eau'   => 'Eau & contenants',
                'table' => 'Table & cuisine',
                'decor' => 'Décor & rituel',
                'autre' => 'Autre',
            ],
            'atouts' => ['Fait main', 'Tourné main', 'Pièce unique', 'Sans plomb (contact alimentaire)', 'Cuisson traditionnelle', 'Émaillé main', 'Commerce équitable', 'Soutien artisan local'],
            'fields' => [
                'matiere'    => ['label' => 'Matière', 'opts' => ['Terre cuite', 'Grès', 'Faïence', 'Argile naturelle', 'Céramique émaillée', 'Porcelaine', 'Terre vernissée']],
                'technique'  => ['label' => 'Technique', 'opts' => ['Tourné', 'Modelé à la main', 'Colombin', 'Estampé', 'Façonné au tour', 'Cuisson traditionnelle (four à bois)']],
                'finition'   => ['label' => 'Finition / émail', 'opts' => ['Brut / non émaillé', 'Émaillé', 'Engobe', 'Vernissé', 'Peint à la main', 'Patiné', 'Bruni']],
                'origine'    => ['label' => 'Origine / tradition', 'opts' => ['Afrique de l’Ouest', 'Maghreb', 'Afrique centrale', 'Sahel', 'Nubie', 'Berbère', 'Non précisé']],
                'usage'      => ['label' => 'Usage', 'opts' => ['Décoratif', 'Alimentaire (contact food)', 'Cuisson', 'Service de table', 'Jardinage', 'Rituel / parfum']],
                'piece_dim'  => ['label' => 'Taille', 'opts' => ['Petit (< 15 cm)', 'Moyen (15–40 cm)', 'Grand (40–80 cm)', 'Très grand (> 80 cm)', 'Sur mesure']],
                'contenance' => ['label' => 'Contenance', 'opts' => ['< 0,5 L', '0,5–1 L', '1–3 L', '3–5 L', '5–10 L', '> 10 L']],
                'etancheite' => ['label' => 'Étanchéité', 'opts' => ['Étanche', 'Poreux (rafraîchit l’eau)', 'Non précisé']],
            ],
            // 'food' => true : type intrinsèquement alimentaire → alerte contact alimentaire.
            'types' => [
                // Eau & contenants
                'Jarre / canari (eau)'         => ['group' => 'eau', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'piece_dim', 'contenance', 'etancheite'], 'axis' => 'Taille', 'color' => true],
                'Vase'                         => ['group' => 'eau', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'piece_dim'], 'axis' => 'Couleur', 'color' => true],
                'Pot / jardinière'             => ['group' => 'eau', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'piece_dim', 'etancheite'], 'axis' => 'Taille', 'color' => true],
                // Table & cuisine (contact alimentaire)
                'Bol / coupe'                  => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'usage', 'contenance'], 'axis' => 'Couleur', 'color' => true, 'food' => true],
                'Assiette / plat'              => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'usage', 'piece_dim'], 'axis' => 'Couleur', 'color' => true, 'food' => true],
                'Tajine'                       => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'piece_dim'], 'axis' => 'Taille', 'color' => true, 'food' => true],
                'Théière / cafetière'          => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'contenance'], 'axis' => 'Couleur', 'color' => true, 'food' => true],
                'Mug / tasse'                  => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'contenance'], 'axis' => 'Couleur', 'color' => true, 'food' => true],
                'Service de table'             => ['group' => 'table', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'usage'], 'axis' => 'Modèle', 'color' => true, 'food' => true],
                // Décor & rituel
                'Statuette en terre'           => ['group' => 'decor', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'piece_dim'], 'axis' => 'Modèle', 'color' => false],
                'Brûle-encens / photophore'    => ['group' => 'decor', 'fields' => ['matiere', 'technique', 'finition', 'origine'], 'axis' => 'Couleur', 'color' => true],
                'Carreau / azulejo décoratif'  => ['group' => 'decor', 'fields' => ['matiere', 'finition', 'origine'], 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Autre poterie'                => ['group' => 'autre', 'fields' => ['matiere', 'technique', 'finition', 'origine', 'usage', 'piece_dim'], 'axis' => 'Modèle', 'color' => true],
            ],
        ],

        'Sculptures' => [
            'groups' => [
                'figures' => 'Figures',
                'masques' => 'Masques & murs',
                'rituel'  => 'Rituel',
                'autre'   => 'Autre',
            ],
            'atouts' => ['Fait main', 'Pièce unique', 'Sculpté main', 'Bois d’ébène', 'Bronze cire perdue', 'Art tribal', 'Avec socle', 'Certificat d’authenticité', 'Soutien artisan local'],
            'fields' => [
                'matiere'   => ['label' => 'Matière', 'opts' => ['Bois (ébène)', 'Bois (iroko)', 'Bois (autre)', 'Bronze', 'Laiton', 'Pierre', 'Pierre à savon (stéatite)', 'Terre cuite', 'Ivoire végétal (tagua)', 'Os / corne', 'Métal recyclé', 'Résine']],
                'technique' => ['label' => 'Technique', 'opts' => ['Sculpté à la main', 'Cire perdue (fonte bronze)', 'Taille directe', 'Patiné', 'Pyrogravure', 'Assemblage', 'Poli']],
                'origine'   => ['label' => 'Origine / tradition', 'opts' => ['Dogon', 'Sénoufo', 'Baoulé', 'Ashanti', 'Fang', 'Makondé', 'Bambara', 'Yoruba', 'Afrique de l’Ouest', 'Afrique centrale', 'Non précisé']],
                'style'     => ['label' => 'Style', 'opts' => ['Traditionnel', 'Rituel / cérémoniel', 'Contemporain', 'Animalier', 'Abstrait', 'Réaliste']],
                'piece_dim' => ['label' => 'Taille', 'opts' => ['Petit (< 20 cm)', 'Moyen (20–50 cm)', 'Grand (50–100 cm)', 'Très grand (> 1 m)', 'Monumental']],
                'finition'  => ['label' => 'Finition', 'opts' => ['Brut / naturel', 'Poli', 'Patiné', 'Ciré', 'Peint', 'Doré']],
                'poids'     => ['label' => 'Poids', 'opts' => ['< 1 kg', '1–5 kg', '5–15 kg', '15–30 kg', '> 30 kg']],
                'support'   => ['label' => 'Support / socle', 'opts' => ['Avec socle', 'Sans socle', 'Socle intégré', 'À suspendre (mural)']],
            ],
            'types' => [
                // Figures
                'Statue / statuette'                => ['group' => 'figures', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
                'Buste'                             => ['group' => 'figures', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
                'Sculpture animalière'              => ['group' => 'figures', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
                'Figure abstraite / contemporaine'  => ['group' => 'figures', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
                // Masques & murs
                'Masque'                            => ['group' => 'masques', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
                'Bas-relief / panneau'              => ['group' => 'masques', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false],
                'Totem / colonne'                   => ['group' => 'masques', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'poids'], 'axis' => 'Modèle', 'color' => false],
                // Rituel
                'Sculpture rituelle / fétiche'      => ['group' => 'rituel', 'fields' => ['matiere', 'technique', 'origine', 'piece_dim', 'finition'], 'axis' => 'Modèle', 'color' => false],
                // Autre
                'Autre sculpture'                   => ['group' => 'autre', 'fields' => ['matiere', 'technique', 'origine', 'style', 'piece_dim', 'finition', 'support'], 'axis' => 'Modèle', 'color' => false],
            ],
        ],

        // 'mode' par type : 'metre' (tissu au mètre / coupon) ou 'confection' (pièce finie).
        'Textile & wax' => [
            'groups' => [
                'metre'      => 'Tissu au mètre / coupon',
                'confection' => 'Confectionné (pièce finie)',
                'autre'      => 'Autre',
            ],
            'atouts' => ['Wax authentique', 'Fait main', 'Cousu main', 'Bazin riche', 'Grand teint', 'Tissé main', 'Pièce unique', 'Sur mesure possible', 'Commerce équitable'],
            'fields' => [
                'matiere'     => ['label' => 'Matière', 'opts' => ['Coton wax', 'Coton imprimé', 'Bazin (damassé)', 'Bogolan (coton)', 'Kente (coton/soie)', 'Lin', 'Soie', 'Velours', 'Mélange']],
                'motif'       => ['label' => 'Motif / imprimé', 'opts' => ['Wax traditionnel', 'Géométrique', 'Floral', 'Symbolique (adinkra…)', 'Uni', 'Tie & dye', 'Brodé', 'Rayé']],
                'origine'     => ['label' => 'Origine / tradition', 'opts' => ['Afrique de l’Ouest', 'Ghana (kente)', 'Mali (bogolan)', 'Sénégal (bazin)', 'Nigeria', 'Côte d’Ivoire', 'Non précisé']],
                'laize'       => ['label' => 'Laize (largeur)', 'opts' => ['90 cm', '110 cm', '115 cm', '120 cm', '150 cm']],
                'vente_par'   => ['label' => 'Vendu par', 'opts' => ['Au mètre', 'Coupon 2 m', 'Coupon 6 yards (~5,5 m)', 'Coupon 12 yards (~11 m)']],
                'genre'       => ['label' => 'Pour', 'opts' => ['Femme', 'Homme', 'Mixte', 'Enfant', 'Unisexe']],
                'coupe'       => ['label' => 'Coupe / style', 'opts' => ['Traditionnel', 'Moderne', 'Ample', 'Ajusté', 'Décontracté']],
                'entretien'   => ['label' => 'Entretien', 'opts' => ['Lavage main', 'Lavage 30°', 'Nettoyage à sec', 'Grand teint', 'Déteint au 1er lavage']],
                'taille_conf' => ['label' => 'Taille', 'opts' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Sur mesure', 'Taille unique']],
            ],
            'types' => [
                // Tissu au mètre / coupon
                'Wax / pagne (au coupon)'      => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par', 'entretien'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Bazin'                        => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par', 'entretien'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Bogolan (mud cloth)'          => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par', 'entretien'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Kente'                        => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Tissu tissé / Kita'           => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Batik / tie & dye'            => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par', 'entretien'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                'Tissu imprimé (au mètre)'     => ['group' => 'metre', 'fields' => ['matiere', 'motif', 'origine', 'laize', 'vente_par', 'entretien'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'metre'],
                // Confectionné (pièce finie)
                'Boubou / grand boubou'        => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'genre', 'coupe', 'taille_conf', 'entretien'], 'axis' => 'Taille', 'color' => true, 'mode' => 'confection'],
                'Robe / ensemble femme'        => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'coupe', 'taille_conf', 'entretien'], 'axis' => 'Taille', 'color' => true, 'mode' => 'confection'],
                'Chemise / tunique homme'      => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'coupe', 'taille_conf', 'entretien'], 'axis' => 'Taille', 'color' => true, 'mode' => 'confection'],
                'Foulard / écharpe / turban'   => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'genre'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'confection'],
                'Nappe / linge de maison'      => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'entretien'], 'axis' => 'Taille', 'color' => true, 'mode' => 'confection'],
                'Coussin / housse'             => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'confection'],
                'Sac / accessoire en wax'      => ['group' => 'confection', 'fields' => ['matiere', 'motif', 'origine', 'genre'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'confection'],
                // Autre
                'Autre textile'                => ['group' => 'autre', 'fields' => ['matiere', 'motif', 'origine', 'genre', 'taille_conf'], 'axis' => 'Coloris', 'color' => true, 'mode' => 'confection'],
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
            ['label' => 'Tailles vêtement', 'list' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
        ],
        'Pointure' => [['label' => 'Pointures', 'list' => ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']]],
        'Couleur'  => [['label' => 'Couleurs', 'list' => ['Multicolore', 'Naturel', 'Bois', 'Terre cuite', 'Marron', 'Camel', 'Bordeaux', 'Ocre', 'Rouge', 'Noir', 'Blanc', 'Doré']]],
        'Coloris'  => [['label' => 'Coloris', 'list' => ['Multicolore', 'Indigo', 'Rouge', 'Jaune', 'Vert', 'Orange', 'Bleu', 'Noir', 'Blanc', 'Ocre', 'Or', 'Rose']]],
    ],

    /**
     * « Nouveau rayon » Artisanat : le vendeur crée un rayon hors des 6 répertoriés.
     * Le formulaire s'adapte au SLUG du nom : si connu (R), il suggère des specs, un
     * axe, la couleur, la pièce unique et le mode « au mètre » ; sinon, modèle générique
     * + specs libres. Base artisanat conservée (fait main, pièce unique, histoire, CITES).
     * « & » devient un séparateur dans le slug (pas « et »).
     */
    'autre' => [
        'rayon_suggest' => ['Peinture & art mural', 'Instruments de musique', 'Vannerie & paniers', 'Calebasses', 'Masques', 'Art de la table', 'Mobilier artisanal', 'Savons & cosmétiques naturels', 'Jouets & jeux', 'Mode & accessoires', 'Textile au mètre'],
        'generic_specs' => ['Matière', 'Technique', 'Origine / tradition', 'Style', 'Dimensions', 'Finition', 'Usage'],
        'atout_suggest' => ['Fait main', 'Pièce unique', 'Matériaux naturels', 'Commerce équitable', 'Soutien artisan local', 'Sur mesure possible', 'Wax authentique', 'Upcyclé / recyclé'],
        'R' => [
            'peinture-art-mural'          => ['specs' => ['Technique', 'Support', 'Style', 'Dimensions', 'Encadrement'], 'axis' => 'Modèle', 'color' => false, 'unique' => true, 'mode' => 'piece'],
            'instruments-de-musique'      => ['specs' => ['Type d’instrument', 'Matière', 'Origine', 'Accordage'], 'axis' => 'Modèle', 'color' => false, 'unique' => false, 'mode' => 'piece'],
            'vannerie-paniers'            => ['specs' => ['Matière', 'Technique', 'Origine', 'Taille'], 'axis' => 'Couleur', 'color' => true, 'unique' => false, 'mode' => 'piece'],
            'calebasses'                  => ['specs' => ['Technique', 'Origine', 'Taille', 'Finition'], 'axis' => 'Modèle', 'color' => false, 'unique' => false, 'mode' => 'piece'],
            'masques'                     => ['specs' => ['Matière', 'Origine', 'Style', 'Taille'], 'axis' => 'Modèle', 'color' => false, 'unique' => true, 'mode' => 'piece'],
            'art-de-la-table'             => ['specs' => ['Matière', 'Technique', 'Origine', 'Usage'], 'axis' => 'Couleur', 'color' => true, 'unique' => false, 'mode' => 'piece'],
            'mobilier-artisanal'          => ['specs' => ['Matière', 'Technique', 'Origine', 'Dimensions'], 'axis' => 'Modèle', 'color' => false, 'unique' => false, 'mode' => 'piece'],
            'savons-cosmetiques-naturels' => ['specs' => ['Type', 'Ingrédients', 'Poids', 'Origine'], 'axis' => 'Parfum', 'color' => false, 'unique' => false, 'mode' => 'piece'],
            'jouets-jeux'                 => ['specs' => ['Matière', 'Origine', 'Âge conseillé'], 'axis' => 'Couleur', 'color' => true, 'unique' => false, 'mode' => 'piece'],
            'mode-accessoires'            => ['specs' => ['Matière', 'Origine', 'Taille', 'Genre'], 'axis' => 'Couleur', 'color' => true, 'unique' => false, 'mode' => 'piece'],
            'textile-au-metre'            => ['specs' => ['Matière', 'Motif', 'Origine', 'Laize'], 'axis' => 'Coloris', 'color' => true, 'unique' => false, 'mode' => 'metre'],
        ],
    ],
];
