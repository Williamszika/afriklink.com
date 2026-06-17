<?php
declare(strict_types=1);

/**
 * Rayon adaptatif « Cuisine » du domaine « Maison & meubles » (catégorie boutique
 * « maison »). Même philosophie que config/electronics.php : le TYPE pilote les
 * caractéristiques affichées, la nature de la déclinaison et le mode « appareil
 * électrique » (flag `elec` → garantie + rappel CE/tension). Les specs sont
 * stockées dans products.attributes (JSON) — aucune migration.
 *
 * 'shop_categories' : catégories de boutique concernées.
 * 'rayons' => libellé du rayon => [ groups, atouts, fields, types ].
 *   fields : clé => [label, opts]
 *   types  : nom => [ group, fields(list de clés), elec(bool), axis, color(bool) ]
 */
return [
    'shop_categories' => ['maison'],
    'conditions' => ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion'],
    'garanties'  => ['3 mois', '6 mois', '1 an', '2 ans'],

    'rayons' => [
        'Cuisine' => [
            'groups' => [
                'electro'    => 'Électroménager',
                'ustensiles' => 'Ustensiles & cuisson',
                'table'      => 'Arts de la table',
                'rangement'  => 'Rangement & mobilier',
                'autre'      => 'Autre',
            ],
            'atouts' => ['Sans BPA', 'Va au lave-vaisselle', 'Compatible induction', 'Antiadhésif', 'Inox', 'Économe en énergie', 'Garantie incluse', 'Fait main / artisanal'],
            'fields' => [
                'matiere'    => ['label' => 'Matière', 'opts' => ['Inox', 'Aluminium', 'Fonte', 'Acier émaillé', 'Antiadhésif', 'Céramique', 'Verre', 'Plastique sans BPA', 'Bois', 'Bambou', 'Silicone', 'Porcelaine', 'Faïence', 'Autre']],
                'capacite'   => ['label' => 'Capacité / contenance', 'opts' => ['< 1 L', '1 L', '1,5 L', '2 L', '3 L', '4 L', '5 L et +']],
                'puissance'  => ['label' => 'Puissance (W)', 'opts' => ['< 500 W', '500–1000 W', '1000–1500 W', '1500–2000 W', '> 2000 W']],
                'tension'    => ['label' => 'Tension / alimentation', 'opts' => ['220–240 V', '110 V', 'Bi-tension', 'Batterie / sans fil', 'Gaz', 'Sans alimentation']],
                'compat_feu' => ['label' => 'Compatibilité plaques', 'opts' => ['Tous feux', 'Induction', 'Gaz', 'Électrique', 'Vitrocéramique']],
                'pieces'     => ['label' => 'Nombre de pièces', 'opts' => ['1', '2', '3', '4', '6', '8', '12', '+']],
                'diametre'   => ['label' => 'Diamètre / dimension', 'opts' => ['16 cm', '18 cm', '20 cm', '24 cm', '26 cm', '28 cm', '30 cm', '32 cm']],
                'programmes' => ['label' => 'Programmes / fonctions', 'opts' => ['1', '2', '3–5', '6–10', '> 10']],
                'couverts'   => ['label' => 'Couverts', 'opts' => ['6', '8', '10', '12', '14', '16']],
                'energie'    => ['label' => 'Classe énergie', 'opts' => ['A', 'B', 'C', 'D', 'E', 'Non précisé']],
                'revetement' => ['label' => 'Revêtement', 'opts' => ['Antiadhésif', 'Céramique', 'Émaillé', 'Inox brossé', 'Aucun']],
                'montage'    => ['label' => 'Montage', 'opts' => ['Monté', 'À monter', 'Pose libre', 'Encastrable']],
            ],
            'types' => [
                // Électroménager (appareils électriques → garantie + rappel CE/tension)
                'Blender / mixeur'            => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'tension', 'matiere'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Robot culinaire / pétrin'    => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Micro-ondes'                 => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Four / mini-four'            => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Plaque de cuisson / réchaud' => ['group' => 'electro', 'fields' => ['compat_feu', 'puissance', 'pieces', 'tension'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Bouilloire'                  => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'matiere', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Grille-pain'                 => ['group' => 'electro', 'fields' => ['puissance', 'pieces', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Cafetière / machine à café'  => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Friteuse / Air fryer'        => ['group' => 'electro', 'fields' => ['capacite', 'puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Réfrigérateur / congélateur' => ['group' => 'electro', 'fields' => ['capacite', 'energie', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Lave-vaisselle'              => ['group' => 'electro', 'fields' => ['couverts', 'energie', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Ustensiles & cuisson
                'Casserole / marmite'         => ['group' => 'ustensiles', 'fields' => ['matiere', 'capacite', 'diametre', 'compat_feu', 'revetement'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Poêle'                       => ['group' => 'ustensiles', 'fields' => ['matiere', 'diametre', 'compat_feu', 'revetement'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Cocotte / faitout'           => ['group' => 'ustensiles', 'fields' => ['matiere', 'capacite', 'compat_feu', 'revetement'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Set de casseroles'           => ['group' => 'ustensiles', 'fields' => ['matiere', 'pieces', 'compat_feu', 'revetement'], 'elec' => false, 'axis' => 'Pièces', 'color' => false],
                'Couteau / set de couteaux'   => ['group' => 'ustensiles', 'fields' => ['matiere', 'pieces'], 'elec' => false, 'axis' => 'Pièces', 'color' => false],
                'Ustensiles de cuisine'       => ['group' => 'ustensiles', 'fields' => ['matiere', 'pieces'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Planche à découper'          => ['group' => 'ustensiles', 'fields' => ['matiere', 'diametre'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                // Arts de la table
                'Assiettes / vaisselle'       => ['group' => 'table', 'fields' => ['matiere', 'pieces', 'diametre'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Verres / tasses / mugs'      => ['group' => 'table', 'fields' => ['matiere', 'capacite', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Couverts'                    => ['group' => 'table', 'fields' => ['matiere', 'pieces'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Service de table'            => ['group' => 'table', 'fields' => ['matiere', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Rangement & mobilier
                'Boîtes de conservation'      => ['group' => 'rangement', 'fields' => ['matiere', 'capacite', 'pieces'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Bocaux / contenants'         => ['group' => 'rangement', 'fields' => ['matiere', 'capacite', 'pieces'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Meuble de cuisine / étagère' => ['group' => 'rangement', 'fields' => ['matiere', 'montage', 'diametre'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Table & chaises cuisine'     => ['group' => 'rangement', 'fields' => ['matiere', 'pieces', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Textile cuisine'             => ['group' => 'rangement', 'fields' => ['matiere', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Autre article de cuisine'    => ['group' => 'autre', 'fields' => ['matiere', 'capacite'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],
    ],
];
