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

        'Audio auto' => [
            'groups' => [
                'sources' => 'Sources & multimédia',
                'hp'      => 'Haut-parleurs & caissons',
                'ampli'   => 'Amplification & traitement',
                'install' => 'Installation',
                'autre'   => 'Autre',
            ],
            'atouts' => ['Bluetooth', 'CarPlay / Android Auto', 'Mains libres', 'Installation facile', 'Garantie incluse', 'Marque premium', 'Commande au volant', 'Sortie caisson'],
            'fields' => [
                'format_din'   => ['label' => 'Format', 'opts' => ['1 DIN', '2 DIN', 'Sans façade (universel)', 'Flottant / tablette']],
                'ecran'        => ['label' => 'Écran', 'opts' => ['Sans écran', 'Écran tactile', 'CarPlay / Android Auto', 'Affichage LED']],
                'connectivite' => ['label' => 'Connectivité', 'opts' => ['Bluetooth', 'USB', 'AUX / Jack', 'Carte SD', 'Wi-Fi', 'Radio FM/AM', 'DAB+']],
                'puissance'    => ['label' => 'Puissance', 'opts' => ['< 50 W', '50–100 W', '100–200 W', '200–500 W', '> 500 W']],
                'diametre'     => ['label' => 'Diamètre', 'opts' => ['8 cm', '10 cm', '13 cm', '16 cm', '16x24 cm (ovale)', '20 cm', '25 cm', '30 cm', '38 cm']],
                'impedance'    => ['label' => 'Impédance', 'opts' => ['2 Ω', '4 Ω', '8 Ω', 'Double bobine']],
                'canaux'       => ['label' => 'Canaux', 'opts' => ['Mono (1)', '2 canaux', '4 canaux', '5 canaux', '6 canaux']],
                'alimentation' => ['label' => 'Alimentation', 'opts' => ['12 V', '12 V / 24 V', 'Allume-cigare 12 V', 'USB']],
                'garantie'     => ['label' => 'Garantie', 'opts' => ['Aucune', '3 mois', '6 mois', '1 an', '2 ans']],
                'norme'        => ['label' => 'Norme', 'opts' => ['CE', 'RoHS', 'CE + RoHS', 'Aucune / non précisée']],
            ],
            'types' => [
                // Sources & multimédia
                'Autoradio 1 DIN'                    => ['group' => 'sources', 'fields' => ['format_din', 'ecran', 'connectivite', 'puissance', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Autoradio 2 DIN / multimédia'       => ['group' => 'sources', 'fields' => ['format_din', 'ecran', 'connectivite', 'puissance', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Kit mains libres Bluetooth'         => ['group' => 'sources', 'fields' => ['connectivite', 'alimentation', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Transmetteur FM Bluetooth'          => ['group' => 'sources', 'fields' => ['connectivite', 'alimentation', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                // Haut-parleurs & caissons
                'Haut-parleurs / enceintes'          => ['group' => 'hp', 'fields' => ['diametre', 'puissance', 'impedance', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Tweeter / kit éclaté'               => ['group' => 'hp', 'fields' => ['diametre', 'puissance', 'impedance', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Caisson de basses / subwoofer'      => ['group' => 'hp', 'fields' => ['diametre', 'puissance', 'impedance', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                // Amplification & traitement
                'Amplificateur'                      => ['group' => 'ampli', 'fields' => ['canaux', 'puissance', 'impedance', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Égaliseur / processeur DSP'         => ['group' => 'ampli', 'fields' => ['canaux', 'connectivite', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                // Installation
                'Antenne auto'                       => ['group' => 'install', 'fields' => ['connectivite', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                'Câblage / faisceau / installation'  => ['group' => 'install', 'fields' => ['norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => false],
                // Autre
                'Autre matériel audio'               => ['group' => 'autre', 'fields' => ['connectivite', 'puissance', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons selon l'axe (Taille / Couleur).
    'size_systems' => [
        'Taille'  => [['label' => 'Tailles', 'list' => ['Universel', 'S', 'M', 'L', 'XL']]],
        'Couleur' => [['label' => 'Couleurs', 'list' => ['Noir', 'Gris', 'Beige', 'Marron', 'Rouge', 'Bleu', 'Blanc', 'Argent']]],
    ],
];
