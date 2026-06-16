<?php

/**
 * Beauté & cosmétiques — formulaire produit ADAPTATIF.
 *
 * Le rayon « Maquillage » affiche un sélecteur « Type de produit ». Chaque type
 * définit (table 'types') : ses champs de caractéristiques (clés de 'fields'),
 * sa palette de déclinaison (clé de 'palettes'), son libellé de déclinaison et
 * son unité par défaut. Les caractéristiques propres au type sont stockées dans
 * la colonne JSON products.attributes (souple : pas une colonne par champ).
 *
 * Les déclinaisons réutilisent la couche variantes : nom (teinte/couleur) +
 * pastille couleur (hex) + nuance (carnation, pour les produits de teint) +
 * stock + prix. Libellés en français (marché francophone).
 */
return [
    'shop_categories' => ['beaute'],

    'volume_units' => ['ml', 'g', 'pièce(s)'],
    'pao'          => ['6M', '12M', '18M', '24M', '36M'],
    'atouts'       => ['Vegan', 'Cruelty-free', 'Non comédogène', 'Sans huile', 'Sans parabène', 'Hypoallergénique', 'Halal'],

    // Nuances de carnation (du plus clair au plus foncé) — filtre « selon ma peau ».
    // À NE PAS confondre avec le type de peau (sèche, grasse…).
    'nuances' => ['Très claire', 'Claire', 'Médium', 'Mate', 'Foncée', 'Très foncée'],

    // Définition des champs de caractéristiques : clé => ['label' => …, 'opts' => […]].
    'fields' => [
        'format_fdt'    => ['label' => 'Format / texture', 'opts' => ['Fluide', 'Crème', 'Compact', 'Poudre', 'Stick', 'Mousse', 'Cushion', 'Sérum teinté']],
        'type_poudre'   => ['label' => 'Type de poudre',   'opts' => ['Libre', 'Compacte', 'Matifiante', 'Fixatrice', 'Bronzante']],
        'type_base'     => ['label' => 'Type de base',     'opts' => ['Matifiante', 'Hydratante', 'Floutante', 'Illuminatrice', 'Anti-rougeurs']],
        'format_pcl'    => ['label' => 'Format',           'opts' => ['Poudre', 'Crème', 'Liquide', 'Stick']],
        'couvrance'     => ['label' => 'Couvrance',        'opts' => ['Légère', 'Moyenne', 'Haute', 'Modulable']],
        'fini_teint'    => ['label' => 'Fini',             'opts' => ['Mat', 'Naturel', 'Satiné', 'Lumineux', 'Poudré']],
        'fini_hl'       => ['label' => 'Fini',             'opts' => ['Doré', 'Champagne', 'Rosé', 'Bronze', 'Perle', 'Holographique']],
        'fini_spray'    => ['label' => 'Fini',             'opts' => ['Mat', 'Naturel', 'Dewy / glow']],
        'peau'          => ['label' => 'Type de peau',     'opts' => ['Tous types', 'Normale', 'Sèche', 'Grasse', 'Mixte', 'Sensible', 'Mature']],
        'souston'       => ['label' => 'Sous-ton',         'opts' => ['Froid (rosé)', 'Neutre', 'Chaud (doré)']],
        'spf'           => ['label' => 'SPF / FPS',        'opts' => ['Aucun', 'SPF 15', 'SPF 25', 'SPF 30+']],
        'tenue'         => ['label' => 'Tenue',            'opts' => ['Standard', 'Longue tenue', '24h', 'Waterproof']],
        'type_levres'   => ['label' => 'Type',             'opts' => ['Classique', 'Liquide', 'Crayon', 'Baume teinté', 'Encre / tint']],
        'fini_levres'   => ['label' => 'Fini',             'opts' => ['Mat', 'Satiné', 'Brillant', 'Velours', 'Métallisé', 'Nude']],
        'fini_gloss'    => ['label' => 'Fini',             'opts' => ['Brillant', 'Pailleté', 'Effet repulpant', 'Transparent']],
        'effet_mascara' => ['label' => 'Effet',            'opts' => ['Volume', 'Longueur', 'Courbe', 'Définition', 'Volume + longueur']],
        'forme_eyeliner' => ['label' => 'Forme',           'opts' => ['Feutre', 'Pinceau', 'Crayon', 'Gel', 'Liquide']],
        'format_fard'   => ['label' => 'Format',           'opts' => ['Poudre pressée', 'Crème', 'Pigment libre']],
        'fini_fard'     => ['label' => 'Fini',             'opts' => ['Mat', 'Satiné', 'Métallisé', 'Pailleté', 'Duochrome']],
        'waterproof'    => ['label' => 'Waterproof',       'opts' => ['Non', 'Oui']],
        'nb_teintes'    => ['label' => 'Nombre de teintes', 'opts' => ['6', '9', '12', '15', '18', '24', '35']],
        'theme_palette' => ['label' => 'Thème',            'opts' => ['Nudes', 'Chauds', 'Froids', 'Smoky', 'Colorés', 'Festif']],
    ],

    // Groupes de types (pour les optgroups du sélecteur).
    'groups' => ['teint' => 'Teint', 'levres' => 'Lèvres', 'yeux' => 'Yeux'],

    // Types de produit : type => ['group', 'fields' => [...], 'decl' => clé palette|null,
    //                             'decl_label' => …, 'unit' => unité].
    'types' => [
        'Fond de teint'    => ['group' => 'teint', 'fields' => ['format_fdt', 'couvrance', 'fini_teint', 'peau', 'souston', 'spf', 'tenue'], 'decl' => 'teinte', 'decl_label' => 'Teintes',  'unit' => 'ml'],
        'Poudre'           => ['group' => 'teint', 'fields' => ['type_poudre', 'couvrance', 'fini_teint', 'peau'], 'decl' => 'teinte', 'decl_label' => 'Teintes',  'unit' => 'g'],
        'Anticernes'       => ['group' => 'teint', 'fields' => ['couvrance', 'fini_teint', 'peau', 'souston'], 'decl' => 'teinte', 'decl_label' => 'Teintes',  'unit' => 'ml'],
        'Base / primer'    => ['group' => 'teint', 'fields' => ['type_base', 'fini_teint'], 'decl' => null,      'decl_label' => '',         'unit' => 'ml'],
        'Blush'            => ['group' => 'teint', 'fields' => ['format_pcl', 'fini_teint'], 'decl' => 'joues',  'decl_label' => 'Couleurs', 'unit' => 'g'],
        'Highlighter'      => ['group' => 'teint', 'fields' => ['format_pcl', 'fini_hl'], 'decl' => 'hl',        'decl_label' => 'Teintes',  'unit' => 'g'],
        'Spray fixateur'   => ['group' => 'teint', 'fields' => ['fini_spray', 'tenue'], 'decl' => null,         'decl_label' => '',         'unit' => 'ml'],
        'Rouge à lèvres'   => ['group' => 'levres', 'fields' => ['type_levres', 'fini_levres', 'tenue'], 'decl' => 'levres', 'decl_label' => 'Couleurs', 'unit' => 'g'],
        'Gloss'            => ['group' => 'levres', 'fields' => ['fini_gloss'], 'decl' => 'levres',   'decl_label' => 'Couleurs', 'unit' => 'ml'],
        'Crayon à lèvres'  => ['group' => 'levres', 'fields' => ['fini_levres'], 'decl' => 'levres',  'decl_label' => 'Couleurs', 'unit' => 'pièce(s)'],
        'Mascara'          => ['group' => 'yeux', 'fields' => ['effet_mascara', 'waterproof'], 'decl' => 'yeux', 'decl_label' => 'Couleurs', 'unit' => 'ml'],
        'Eyeliner'         => ['group' => 'yeux', 'fields' => ['forme_eyeliner', 'waterproof'], 'decl' => 'yeux', 'decl_label' => 'Couleurs', 'unit' => 'ml'],
        'Fard à paupières' => ['group' => 'yeux', 'fields' => ['format_fard', 'fini_fard'], 'decl' => 'fard', 'decl_label' => 'Couleurs', 'unit' => 'g'],
        'Palette'          => ['group' => 'yeux', 'fields' => ['nb_teintes', 'theme_palette'], 'decl' => null,  'decl_label' => '',         'unit' => 'g'],
    ],

    // Palettes de déclinaison : clé => [ [nom, hex, nuance?], … ].
    'palettes' => [
        'teinte' => [
            ['Porcelaine', '#F6E6D6', 'Très claire'], ['Ivoire', '#F1DDC4', 'Très claire'],
            ['Beige clair', '#E7C8A3', 'Claire'], ['Sable', '#D8B489', 'Claire'],
            ['Doré', '#CD9F63', 'Médium'], ['Miel', '#BF8D4E', 'Médium'],
            ['Caramel', '#A9743C', 'Mate'], ['Noisette', '#946233', 'Mate'],
            ['Cannelle', '#82502A', 'Foncée'], ['Cacao', '#5F3A20', 'Foncée'],
            ['Ébène', '#3D2417', 'Très foncée'], ['Espresso', '#2A1810', 'Très foncée'],
        ],
        'joues' => [
            ['Pêche', '#F0A585'], ['Rose poudré', '#E6A6B0'], ['Corail', '#EF7B63'],
            ['Framboise', '#C14B6E'], ['Terracotta', '#B9603F'], ['Prune', '#7E3A55'],
        ],
        'hl' => [
            ['Champagne', '#E8D9A8'], ['Doré', '#D6AF5A'], ['Rosé', '#EDC4B8'],
            ['Bronze', '#B07A45'], ['Perle', '#EEE6DC'],
        ],
        'levres' => [
            ['Nude', '#C98D72'], ['Vieux rose', '#BD7A7E'], ['Rosé', '#D97F97'],
            ['Corail', '#EF6F5A'], ['Pêche', '#EF9A76'], ['Rouge', '#C41E3A'],
            ['Cerise', '#9E1530'], ['Framboise', '#B5295E'], ['Fuchsia', '#C2185B'],
            ['Brun', '#7D4A3A'], ['Chocolat', '#5B3326'], ['Prune', '#5D2A4E'],
            ['Bordeaux', '#6E1322'], ['Aubergine', '#43233A'],
        ],
        'yeux' => [
            ['Noir', '#16130F'], ['Brun', '#5B3A26'], ['Bleu nuit', '#1F2D54'],
            ['Prune', '#5D2A4E'], ['Vert', '#1F5E46'], ['Bordeaux', '#6E1322'],
        ],
        'fard' => [
            ['Nude', '#C79B80'], ['Doré', '#CDA64A'], ['Bronze', '#A06A3C'],
            ['Cuivre', '#B5612F'], ['Taupe', '#8A7866'], ['Prune', '#6E2A4D'],
            ['Vert', '#2F6B4F'], ['Bleu', '#2A4A8A'], ['Champagne', '#E6D3A0'], ['Noir', '#16130F'],
        ],
    ],

    // ----- Rayon « Ongles » : faux ongles (formulaire dédié). -----
    // Centré sur forme + longueur (déclinaisons), design, couleur et pose. Tout va
    // dans products.attributes (JSON) ; les variantes = forme (size) × longueur (color).
    'ongles' => [
        'product_types' => [
            'Faux ongles à coller (press-on)', 'Capsules à customiser (tips)',
            'Kit complet (avec pose)', 'Faux ongles réutilisables',
        ],
        'materials' => ['ABS', 'Gel / Soft gel', 'Acrylique', 'PET'],
        'formes'    => ['Amande', 'Carré', 'Carré arrondi', 'Ovale', 'Rond', 'Ballerine / Cercueil', 'Stiletto', 'Sirène'],
        'longueurs' => ['Court', 'Moyen', 'Long', 'Extra-long'],
        'designs'   => ['Uni / Nude', 'French', 'Ombré', 'Pailleté', 'Chromé / Aurora', 'Mat', 'Brillant', 'Nail art', 'Strass', 'Animal print', 'Marbré', 'Fleurs'],
        'couleurs'  => [
            ['Nude', '#E7C2B4'], ['French', '#F4ECE3'], ['Rouge', '#C41E3A'], ['Bordeaux', '#6E1322'],
            ['Rose', '#E8538B'], ['Fuchsia', '#C2185B'], ['Bleu nuit', '#1F2D54'], ['Émeraude', '#0F6B4F'],
            ['Noir', '#16130F'], ['Doré', '#CDA64A'], ['Argent', '#C7CCD1'], ['Lavande', '#B6A7E0'],
        ],
        'kit'    => ['Colle', 'Stickers adhésifs', 'Lime', 'Bâtonnet bois', 'Lingette alcool', 'Repousse-cuticules'],
        'atouts' => ['Réutilisable', 'Fait main', 'Vegan', 'Pose rapide', 'Waterproof', 'Sans danger'],
        // Options oui/non (cases) : clé => libellé.
        'toggles' => [
            'glue'     => 'Colle incluse',
            'stickers' => 'Adhésifs (stickers) inclus',
            'lamp'     => 'Lampe UV/LED requise',
            'reusable' => 'Réutilisable',
        ],
    ],
];

