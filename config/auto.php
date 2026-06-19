<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Auto & pièces » (catégorie boutique
 * « auto »). Même philosophie que config/cuisine.php et config/alimentation.php :
 * le RAYON pilote la liste de types, le TYPE pilote les caractéristiques, l'axe de
 * déclinaison (Couleur / Taille / Modèle / Parfum), le mode « électrique »
 * (flag `elec` → garantie + rappel CE/tension). Signature auto : un bloc
 * COMPATIBILITÉ VÉHICULE (universel / véhicules compatibles). Specs dans
 * products.attributes (JSON) — aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group, fields(list), axis, color(bool), elec(bool) ]
 */
return [
    'shop_categories' => ['auto'],
    'conditions'      => ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion'],

    'rayons' => [
        'Accessoires' => [
            'groups' => [
                'interieur'    => 'Intérieur',
                'exterieur'    => 'Extérieur & protection',
                'electronique' => 'Électronique embarquée',
                'entretien'    => 'Entretien & sécurité',
                'autre'        => 'Autre',
            ],
            'atouts' => ['Universel', 'Installation facile', 'Imperméable', 'Antidérapant', 'Pliable / compact', 'Garantie incluse', 'Marque premium', 'Sur mesure'],
            'fields' => [
                'matiere'      => ['label' => 'Matière', 'opts' => ['Tissu', 'Cuir / similicuir', 'Caoutchouc', 'Plastique', 'Silicone', 'Métal', 'Mousse', 'Velours']],
                'taille'       => ['label' => 'Taille / format', 'opts' => ['Universel', 'S', 'M', 'L', 'XL', 'Sur mesure']],
                'alimentation' => ['label' => 'Alimentation', 'opts' => ['Allume-cigare 12 V', 'USB', 'Batterie rechargeable', 'Sans alimentation']],
                'norme'        => ['label' => 'Norme / sécurité', 'opts' => ['CE', 'ECE / homologué', 'NF', 'Aucune / non précisée']],
                'emplacement'  => ['label' => 'Emplacement', 'opts' => ['Avant', 'Arrière', 'Avant + arrière', 'Coffre', 'Universel']],
                'connectivite' => ['label' => 'Connectivité', 'opts' => ['Bluetooth', 'USB', 'Jack 3.5', 'Wi-Fi', 'Filaire', 'NFC']],
                'garantie'     => ['label' => 'Garantie', 'opts' => ['Aucune', '3 mois', '6 mois', '1 an', '2 ans']],
            ],
            'types' => [
                // Intérieur
                'Tapis de sol'                  => ['group' => 'interieur', 'fields' => ['matiere', 'emplacement'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Housses de siège'              => ['group' => 'interieur', 'fields' => ['matiere', 'emplacement'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Couvre-volant'                 => ['group' => 'interieur', 'fields' => ['matiere', 'taille'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Organiseur / rangement'        => ['group' => 'interieur', 'fields' => ['matiere', 'emplacement'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
                'Parfum / désodorisant'         => ['group' => 'interieur', 'fields' => ['emplacement'], 'axis' => 'Parfum', 'color' => false, 'elec' => false],
                // Extérieur & protection
                'Pare-soleil'                   => ['group' => 'exterieur', 'fields' => ['taille', 'emplacement'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Housse de protection voiture'  => ['group' => 'exterieur', 'fields' => ['matiere', 'taille', 'norme'], 'axis' => 'Taille', 'color' => true, 'elec' => false],
                'Barres de toit / coffre'       => ['group' => 'exterieur', 'fields' => ['matiere', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                // Électronique embarquée
                'Support téléphone'             => ['group' => 'electronique', 'fields' => ['matiere', 'emplacement'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Chargeur / adaptateur'         => ['group' => 'electronique', 'fields' => ['alimentation', 'connectivite', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Caméra de recul / dashcam'     => ['group' => 'electronique', 'fields' => ['alimentation', 'connectivite', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Autoradio / multimédia'        => ['group' => 'electronique', 'fields' => ['alimentation', 'connectivite', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Kit mains libres'              => ['group' => 'electronique', 'fields' => ['alimentation', 'connectivite', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'GPS'                           => ['group' => 'electronique', 'fields' => ['alimentation', 'connectivite', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                // Entretien & sécurité
                'Gonfleur / compresseur'        => ['group' => 'entretien', 'fields' => ['alimentation', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Kit de nettoyage'              => ['group' => 'entretien', 'fields' => ['matiere'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Câbles de démarrage'           => ['group' => 'entretien', 'fields' => ['taille', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Triangle / gilet / sécurité'   => ['group' => 'entretien', 'fields' => ['norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Chaînes neige'                 => ['group' => 'entretien', 'fields' => ['taille', 'norme'], 'axis' => 'Taille', 'color' => false, 'elec' => false],
                'Glacière auto'                 => ['group' => 'entretien', 'fields' => ['alimentation', 'taille', 'garantie'], 'axis' => 'Modèle', 'color' => true, 'elec' => true],
                // Autre
                'Autre accessoire'              => ['group' => 'autre', 'fields' => ['matiere', 'taille'], 'axis' => 'Modèle', 'color' => true, 'elec' => false],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons selon l'axe (Taille / Couleur).
    'size_systems' => [
        'Taille'  => [['label' => 'Tailles', 'list' => ['Universel', 'S', 'M', 'L', 'XL']]],
        'Couleur' => [['label' => 'Couleurs', 'list' => ['Noir', 'Gris', 'Beige', 'Marron', 'Rouge', 'Bleu', 'Blanc', 'Argent']]],
    ],
];
