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
    'conditions'      => ['Neuf', 'Comme neuf', 'Reconditionné', 'Échange standard', 'Occasion'],

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

        'Entretien' => [
            'groups' => [
                'liquides'   => 'Lubrifiants & liquides',
                'filtration' => 'Filtration',
                'allumage'   => 'Allumage & électrique',
                'visibilite' => 'Visibilité & nettoyage',
                'autre'      => 'Autre',
            ],
            'atouts' => ['100 % synthèse', 'Longue durée', 'Référence OEM', 'Compatible plusieurs modèles', 'Garantie incluse', 'Marque premium', 'Économie de carburant', 'Lot avantageux'],
            'fields' => [
                'viscosite'       => ['label' => 'Viscosité', 'opts' => ['0W-20', '0W-30', '5W-30', '5W-40', '10W-40', '15W-40', '75W-90', '80W-90', 'Autre']],
                'norme_huile'     => ['label' => 'Norme / homologation', 'opts' => ['ACEA', 'API', 'VW 504/507', 'MB-Approval', 'dexos', 'BMW LL', 'Autre']],
                'contenance'      => ['label' => 'Contenance', 'opts' => ['250 ml', '500 ml', '1 L', '2 L', '4 L', '5 L', '20 L']],
                'dot'             => ['label' => 'Type / DOT', 'opts' => ['DOT 3', 'DOT 4', 'DOT 5.1', 'LHM', 'Autre']],
                'filtre_type'     => ['label' => 'Type de filtre', 'opts' => ['Huile', 'Air moteur', 'Habitacle', 'Carburant', 'Habitacle charbon actif']],
                'batterie_cap'    => ['label' => 'Capacité', 'opts' => ['40 Ah', '45 Ah', '60 Ah', '70 Ah', '80 Ah', '100 Ah', '> 100 Ah']],
                'batterie_tech'   => ['label' => 'Technologie', 'opts' => ['Plomb / liquide', 'AGM', 'EFB', 'Gel', 'Lithium']],
                'ampoule_type'    => ['label' => 'Type d’ampoule', 'opts' => ['H1', 'H4', 'H7', 'H11', 'LED', 'Xénon / HID', 'W5W', 'P21W']],
                'bougie_type'     => ['label' => 'Type', 'opts' => ['Nickel', 'Platine', 'Iridium', 'Préchauffage']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Unité', 'Bidon', 'Spray / aérosol', 'Lot / pack', 'Coffret']],
                'garantie'        => ['label' => 'Garantie', 'opts' => ['Aucune', '3 mois', '6 mois', '1 an', '2 ans']],
                'norme'           => ['label' => 'Norme', 'opts' => ['CE', 'ECE', 'CE + ECE', 'Aucune / non précisée']],
            ],
            // 'oil' => rappel viscosité/homologations ; 'specific' => pièce liée à un véhicule
            // précis (la compatibilité passe par défaut sur « non universel »).
            'types' => [
                // Lubrifiants & liquides
                'Huile moteur'                     => ['group' => 'liquides', 'fields' => ['viscosite', 'norme_huile', 'contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false, 'oil' => true],
                'Liquide de frein'                 => ['group' => 'liquides', 'fields' => ['dot', 'contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false, 'oil' => true],
                'Liquide de refroidissement'       => ['group' => 'liquides', 'fields' => ['contenance', 'conditionnement', 'norme_huile'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                'Liquide de direction assistée'    => ['group' => 'liquides', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                'Liquide lave-glace'               => ['group' => 'liquides', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                'Additif carburant / huile'        => ['group' => 'liquides', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                'Graisse / lubrifiant'             => ['group' => 'liquides', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                // Filtration (pièces propres au véhicule → 'specific')
                'Filtre à huile'                   => ['group' => 'filtration', 'fields' => ['filtre_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Filtre à air'                     => ['group' => 'filtration', 'fields' => ['filtre_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Filtre habitacle'                 => ['group' => 'filtration', 'fields' => ['filtre_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Filtre à carburant'               => ['group' => 'filtration', 'fields' => ['filtre_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                // Allumage & électrique
                'Bougie d’allumage'                => ['group' => 'allumage', 'fields' => ['bougie_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Bougie de préchauffage'           => ['group' => 'allumage', 'fields' => ['bougie_type', 'conditionnement'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Batterie'                         => ['group' => 'allumage', 'fields' => ['batterie_cap', 'batterie_tech', 'garantie', 'norme'], 'axis' => 'Modèle', 'color' => false, 'elec' => true, 'specific' => true],
                'Ampoule / éclairage'              => ['group' => 'allumage', 'fields' => ['ampoule_type', 'conditionnement', 'norme'], 'axis' => 'Type', 'color' => false, 'elec' => true, 'specific' => true],
                // Visibilité & nettoyage
                'Balai d’essuie-glace'             => ['group' => 'visibilite', 'fields' => ['conditionnement'], 'axis' => 'Longueur', 'color' => false, 'elec' => false, 'specific' => true],
                'Produit de nettoyage / lustrage'  => ['group' => 'visibilite', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                'Cire / polish'                    => ['group' => 'visibilite', 'fields' => ['contenance', 'conditionnement'], 'axis' => 'Contenance', 'color' => false, 'elec' => false],
                // Autre
                'Autre produit d’entretien'        => ['group' => 'autre', 'fields' => ['contenance', 'conditionnement', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false],
            ],
        ],

        'Pièces détachées' => [
            'groups' => [
                'moteur'         => 'Moteur',
                'freinage'       => 'Freinage',
                'transmission'   => 'Transmission & embrayage',
                'suspension'     => 'Suspension & direction',
                'echappement'    => 'Échappement',
                'refroidissement' => 'Refroidissement',
                'electrique'     => 'Électrique & démarrage',
                'carrosserie'    => 'Carrosserie & optique',
                'autre'          => 'Autre',
            ],
            'atouts' => ['Origine constructeur (OE)', 'Équipementier', 'Référence OEM fournie', 'Échange standard', 'Garantie incluse', 'Neuf sous emballage', 'Montage atelier conseillé', 'Forte rotation'],
            'fields' => [
                'position' => ['label' => 'Position', 'opts' => ['Avant', 'Arrière', 'Avant gauche', 'Avant droit', 'Arrière gauche', 'Arrière droit', 'Gauche', 'Droit', 'Indifférent']],
                'matiere'  => ['label' => 'Matière', 'opts' => ['Acier', 'Fonte', 'Aluminium', 'Composite / céramique', 'Plastique', 'Caoutchouc', 'Inox']],
                'norme'    => ['label' => 'Qualité / origine', 'opts' => ['Origine constructeur (OE)', 'Équipementier (OEM)', 'Adaptable / aftermarket', 'Reconditionné']],
                'garantie' => ['label' => 'Garantie', 'opts' => ['Aucune', '3 mois', '6 mois', '1 an', '2 ans']],
            ],
            // 'specific' => true sur toutes les pièces : la compatibilité passe par défaut
            // sur « non universel » (une pièce détachée est propre à un véhicule).
            'types' => [
                // Moteur
                'Pièces moteur (bloc, piston…)'        => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Joint de culasse'                     => ['group' => 'moteur', 'fields' => ['norme', 'matiere'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Pompe à eau'                          => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Pompe à huile'                        => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Turbo'                                => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Injecteur'                            => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Kit / courroie de distribution'       => ['group' => 'moteur', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Courroie accessoire'                  => ['group' => 'moteur', 'fields' => ['norme'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                // Freinage
                'Plaquettes de frein'                  => ['group' => 'freinage', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Disques de frein'                     => ['group' => 'freinage', 'fields' => ['position', 'matiere', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Étrier de frein'                      => ['group' => 'freinage', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Maître-cylindre'                      => ['group' => 'freinage', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Flexible / durite de frein'           => ['group' => 'freinage', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Tambour / mâchoires'                  => ['group' => 'freinage', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                // Transmission & embrayage
                'Kit d’embrayage'                      => ['group' => 'transmission', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Volant moteur'                        => ['group' => 'transmission', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Cardan / transmission'                => ['group' => 'transmission', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                // Suspension & direction
                'Amortisseur'                          => ['group' => 'suspension', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Ressort de suspension'                => ['group' => 'suspension', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Rotule / biellette'                   => ['group' => 'suspension', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Triangle / bras de suspension'        => ['group' => 'suspension', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Crémaillère de direction'             => ['group' => 'suspension', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Roulement de roue'                    => ['group' => 'suspension', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                // Échappement
                'Ligne / silencieux d’échappement'     => ['group' => 'echappement', 'fields' => ['matiere', 'norme'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Catalyseur'                           => ['group' => 'echappement', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Sonde lambda'                         => ['group' => 'echappement', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Filtre à particules (FAP)'            => ['group' => 'echappement', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                // Refroidissement
                'Radiateur'                            => ['group' => 'refroidissement', 'fields' => ['matiere', 'norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Thermostat'                           => ['group' => 'refroidissement', 'fields' => ['norme'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Durite / ventilateur'                 => ['group' => 'refroidissement', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                // Électrique & démarrage
                'Alternateur'                          => ['group' => 'electrique', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Démarreur'                            => ['group' => 'electrique', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Bobine d’allumage'                    => ['group' => 'electrique', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                'Capteur / sonde'                      => ['group' => 'electrique', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
                // Carrosserie & optique
                'Aile / pare-chocs'                    => ['group' => 'carrosserie', 'fields' => ['position', 'matiere', 'norme'], 'axis' => 'Position', 'color' => true, 'elec' => false, 'specific' => true],
                'Rétroviseur'                          => ['group' => 'carrosserie', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => true, 'elec' => false, 'specific' => true],
                'Phare / feu'                          => ['group' => 'carrosserie', 'fields' => ['position', 'norme', 'garantie'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                'Capot / élément de carrosserie'       => ['group' => 'carrosserie', 'fields' => ['position', 'matiere', 'norme'], 'axis' => 'Position', 'color' => true, 'elec' => false, 'specific' => true],
                'Vitre / lunette'                      => ['group' => 'carrosserie', 'fields' => ['position', 'norme'], 'axis' => 'Position', 'color' => false, 'elec' => false, 'specific' => true],
                // Autre
                'Autre pièce détachée'                 => ['group' => 'autre', 'fields' => ['norme', 'garantie'], 'axis' => 'Référence', 'color' => false, 'elec' => false, 'specific' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons selon l'axe (Taille / Couleur / Contenance / Longueur / Position).
    'size_systems' => [
        'Taille'     => [['label' => 'Tailles', 'list' => ['Universel', 'S', 'M', 'L', 'XL']]],
        'Couleur'    => [['label' => 'Couleurs', 'list' => ['Noir', 'Gris', 'Beige', 'Marron', 'Rouge', 'Bleu', 'Blanc', 'Argent']]],
        'Contenance' => [['label' => 'Contenances', 'list' => ['250 ml', '500 ml', '1 L', '2 L', '4 L', '5 L', '20 L']]],
        'Longueur'   => [['label' => 'Longueurs balai', 'list' => ['350 mm', '400 mm', '450 mm', '500 mm', '550 mm', '600 mm', '650 mm']]],
        'Position'   => [['label' => 'Positions', 'list' => ['Avant', 'Arrière', 'Avant gauche', 'Avant droit', 'Arrière gauche', 'Arrière droit']]],
    ],
];
