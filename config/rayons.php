<?php

/**
 * Rayons (sous-catégories) par catégorie principale de boutique, AVEC l'axe de
 * déclinaison adapté : choisir un rayon adapte le formulaire produit (l'axe « taille »
 * devient Stockage, Contenance, Pointure, Teinte… selon le rayon).
 *
 * 'list'  : catégorie => [ libellé du rayon => clé d'axe ]
 * 'axes'  : clé d'axe => [ 'label' => libellé affiché, 'opts' => suggestions ]
 */
return [
    'list' => [
        'mode' => [
            'T-shirts & hauts'   => 'alpha',
            'Pantalons & jeans'  => 'alpha',
            'Robes & jupes'      => 'alpha',
            'Sous-vêtements'     => 'alpha',
            'Chaussures'         => 'pointure',
            'Sacs & accessoires' => 'none',
        ],
        'electronique' => [
            'Téléphones'          => 'stockage',
            'Tablettes'           => 'stockage',
            'Ordinateurs'         => 'stockage',
            'Audio & écouteurs'   => 'none',
            'Montres connectées'  => 'none',
            'Accessoires'         => 'none',
        ],
        'beaute' => [
            'Soins visage' => 'volume',
            'Soins corps'  => 'volume',
            'Maquillage'   => 'teinte',
            'Parfums'      => 'volume',
            'Perruque'     => 'pouce',
            'Ongles'       => 'teinte',
        ],
        'maison' => [
            'Meubles'        => 'none',
            'Décoration'     => 'none',
            'Cuisine'        => 'none',
            'Linge de maison' => 'dim',
            'Électroménager' => 'none',
            'Jardin'         => 'none',
        ],
        'alimentation' => [
            'Épicerie'      => 'poids',
            'Boissons'      => 'volume',
            'Produits frais' => 'poids',
            'Snacks'        => 'poids',
            'Bio & naturel' => 'poids',
        ],
        'auto' => [
            'Pièces détachées' => 'none',
            'Accessoires'      => 'none',
            'Pneus'            => 'pneu',
            'Entretien'        => 'volume',
            'Audio auto'       => 'none',
        ],
        'artisanat' => [
            'Bijoux'        => 'none',
            'Sculptures'    => 'none',
            'Textile & wax' => 'metre',
            'Poterie'       => 'none',
            'Maroquinerie'  => 'none',
            'Décoration'    => 'none',
        ],
        'bebe' => [
            'Vêtements bébé' => 'age',
            'Jouets'         => 'none',
            'Puériculture'   => 'none',
            'Alimentation'   => 'none',
            'Soins'          => 'volume',
        ],
        'sport' => [
            'Vêtements'  => 'alpha',
            'Chaussures' => 'pointure',
            'Équipement' => 'none',
            'Fitness'    => 'none',
            'Plein air'  => 'none',
        ],
        'autres' => [],
    ],

    'axes' => [
        'alpha'    => ['label' => 'Taille',     'opts' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']],
        'pointure' => ['label' => 'Pointure',   'opts' => ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46']],
        'stockage' => ['label' => 'Stockage',   'opts' => ['16 Go', '32 Go', '64 Go', '128 Go', '256 Go', '512 Go', '1 To']],
        'volume'   => ['label' => 'Contenance', 'opts' => ['30 ml', '50 ml', '100 ml', '200 ml', '500 ml', '1 L']],
        'poids'    => ['label' => 'Poids',      'opts' => ['100 g', '250 g', '500 g', '1 kg', '2 kg', '5 kg']],
        'teinte'   => ['label' => 'Teinte',     'opts' => []],
        'pouce'    => ['label' => 'Longueur',   'opts' => ['8" (20 cm)', '10" (25 cm)', '12" (30 cm)', '14" (35 cm)', '16" (40 cm)', '18" (45 cm)', '20" (50 cm)', '22" (55 cm)', '24" (60 cm)', '26" (65 cm)', '28" (70 cm)', '30" (75 cm)']],
        'age'      => ['label' => 'Âge',        'opts' => ['0-3 mois', '3-6 mois', '6-12 mois', '1 an', '2 ans', '3 ans', '4 ans']],
        'dim'      => ['label' => 'Dimension',  'opts' => ['90×190', '140×190', '160×200', '180×200']],
        'pneu'     => ['label' => 'Dimension',  'opts' => []],
        'metre'    => ['label' => 'Longueur',   'opts' => []],
        'none'     => ['label' => 'Option',     'opts' => []],
    ],
];
