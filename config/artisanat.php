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
    ],

    // Remplissage rapide des déclinaisons selon l'axe (Taille bague / Couleur).
    'size_systems' => [
        'Taille'  => [['label' => 'Tailles bague', 'list' => ['48', '50', '52', '54', '56', '58', '60', 'Réglable']]],
        'Couleur' => [['label' => 'Couleurs', 'list' => ['Multicolore', 'Rouge', 'Jaune', 'Vert', 'Bleu', 'Turquoise', 'Noir', 'Blanc', 'Ocre', 'Doré', 'Argenté', 'Ambre']]],
    ],
];
