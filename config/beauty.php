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

    // ----- Rayon « Parfums » : déclinaison par CONTENANCE (ml). -----
    // Concentration (product_type) + genre + famille olfactive + pyramide (tête/cœur/fond).
    // Specs dans products.attributes ; variantes = contenance (size). Aucune couleur.
    'parfum' => [
        'concentrations' => [
            'Eau de Cologne', 'Eau de Toilette', 'Eau de Parfum', 'Extrait de Parfum',
            'Eau Fraîche', 'Brume parfumée', 'Huile parfumée / Attar',
        ],
        'genres'   => ['Femme', 'Homme', 'Mixte / Unisexe'],
        'familles' => ['Floral', 'Boisé', 'Oriental / Ambré', 'Hespéridé / Agrumes', 'Fougère', 'Chypré', 'Gourmand', 'Aromatique', 'Cuir', 'Musqué', 'Aquatique'],
        'formats'  => ['Vaporisateur / spray', 'Splash', 'Roll-on', 'Solide', 'Recharge / refill', 'Coffret'],
        'alcool'   => ['Avec alcool', 'Sans alcool'],
        'sillages' => ['Léger', 'Modéré', 'Puissant', 'Très puissant'],
        'tenues'   => ['Moins de 4h', '4 à 6h', '6 à 8h', '8 à 12h', 'Plus de 12h'],
        'pao'      => ['12M', '24M', '36M'],
        'tailles'  => ['10 ml', '30 ml', '50 ml', '75 ml', '100 ml', '150 ml', '200 ml'],
        'occasions' => ['Jour', 'Soir', 'Été', 'Hiver', 'Printemps', 'Automne', 'Toutes saisons'],
        'atouts'   => ['Vegan', 'Halal', 'Rechargeable', 'Coffret cadeau', 'Testé dermatologiquement', 'Édition limitée'],
    ],

    // ----- Rayon « Perruque » : déclinaison LONGUEUR × COULEUR. -----
    // Champs adaptatifs : qualité/origine si cheveux naturels ; couleur de lace si lace wig.
    'perruque' => [
        'constructions' => ['Lace frontal', 'Lace closure', 'Full lace', 'HD lace', '360 lace', 'U-part', 'Headband (bandeau)', 'Glueless', 'Sans lace / capless'],
        // Constructions « lace » qui affichent la couleur de lace.
        'lace_types' => ['Lace frontal', 'Lace closure', 'Full lace', 'HD lace', '360 lace'],
        'hair_types' => ['Cheveux naturels (humains)', 'Synthétique', 'Fibre haute température', 'Mélange'],
        // Type de cheveux qui affiche qualité + origine (cheveux naturels).
        'human_type' => 'Cheveux naturels (humains)',
        'textures'   => ['Lisse / Straight', 'Body wave', 'Deep wave', 'Water wave', 'Loose wave', 'Bouclé / Curly', 'Crépu / Kinky', 'Kinky straight', 'Yaki', 'Afro'],
        'densites'   => ['130%', '150%', '180%', '200%', '250%'],
        'qualites'   => ['Remy', 'Virgin / Vierge', 'Non-Remy'],
        'origines'   => ['Brésilien', 'Péruvien', 'Indien', 'Malaisien', 'Cambodgien', 'Européen'],
        'cap_sizes'  => ['Small (~21")', 'Medium (~22.5")', 'Large (~24")'],
        'lace_colors' => ['Transparent', 'HD', 'Medium brown', 'Dark brown'],
        'longueurs'  => ['10', '12', '14', '16', '18', '20', '22', '24', '26', '30'],
        'couleurs'   => [
            ['Naturel 1B', '#1A1410'], ['Noir Jet 1', '#0D0B09'], ['Brun foncé 2', '#3A261C'],
            ['Châtain 4', '#5B3A25'], ['Brun 6', '#6E4A2E'], ['Miel 27', '#B5824A'],
            ['Blond 613', '#E4C98A'], ['Ombré', '#7A4A2E'], ['Burgundy 99J', '#5C1B2A'],
            ['Rouge', '#8A1F2B'], ['Highlight', '#A06A3C'], ['Gris / Argent', '#B8B8B8'],
        ],
        'atouts' => ['Pre-plucked', 'Baby hair', 'Nœuds décolorés', 'Glueless', 'Coloriable', 'Lissable / bouclable', '100% Remy', 'Coupe possible'],
    ],

    // ----- Rayons « Soins visage / corps » : ADAPTATIFS au type de produit. -----
    // Comme le maquillage : le type pilote les champs (D) ; + actifs (multi) + rappel
    // conformité (warn). Déclinaison par contenance. Specs dans products.attributes.
    'soins' => [
        // PAO commun aux deux rayons de soins.
        'pao' => ['3M', '6M', '12M', '18M', '24M', '36M'],

        // ----- Rayon « Soins corps » -----
        'corps' => [
            'fields' => [
                'format'   => ['label' => 'Texture / format', 'opts' => ['Crème', 'Lait', 'Gel', 'Huile', 'Sérum', 'Baume / beurre', 'Lotion', 'Mousse', 'Stick', 'Pain / savon']],
                'peau'     => ['label' => 'Type de peau',      'opts' => ['Tous types', 'Sèche', 'Normale', 'Grasse', 'Mixte', 'Sensible', 'À tendance acnéique', 'Mature']],
                'bienfait' => ['label' => 'Bienfait principal', 'opts' => ['Hydratant', 'Nourrissant', 'Matifiant', 'Anti-âge', 'Anti-rides', 'Éclaircissant / unifiant', 'Anti-taches', 'Anti-acné', 'Apaisant', 'Éclat', 'Purifiant', 'Exfoliant', 'Raffermissant', 'Anti-vergetures', 'Déodorant', 'Protecteur solaire']],
                'senteur'  => ['label' => 'Senteur',           'opts' => ['Sans parfum', 'Karité naturel', 'Vanille', 'Coco', 'Monoï', 'Beurre de cacao', 'Fleur', 'Agrumes', 'Thé vert']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Flacon pompe', 'Tube', 'Pot', 'Flacon', 'Stick', 'Pain / savon', 'Recharge', 'Spray', 'Compte-gouttes']],
                'grain'    => ['label' => 'Type de grain',     'opts' => ['Fin', 'Moyen', 'Gros']],
                'frequence' => ['label' => 'Fréquence d’usage', 'opts' => ['Quotidien', '2-3×/semaine', 'Hebdomadaire']],
                'deo_format' => ['label' => 'Format',          'opts' => ['Roll-on', 'Spray', 'Stick', 'Crème']],
                'deo_efficacite' => ['label' => 'Efficacité',  'opts' => ['24h', '48h', '72h']],
                'deo_alcool' => ['label' => 'Alcool',          'opts' => ['Avec alcool', 'Sans alcool']],
                'spf'      => ['label' => 'SPF / FPS',         'opts' => ['SPF 15', 'SPF 30', 'SPF 50', 'SPF 50+']],
                'protection' => ['label' => 'Protection',      'opts' => ['UVA + UVB', 'Large spectre']],
                'waterproof' => ['label' => 'Résistant à l’eau', 'opts' => ['Non', 'Oui']],
            ],
            'actifs' => ['Karité', 'Coco', 'Argan', 'Aloe vera', 'Vitamine C', 'Acide hyaluronique', 'Niacinamide', 'Rétinol', 'Acide salicylique', 'Glycérine', 'Miel', 'Carotte', 'Cacao', 'Olive', 'Avoine', 'Thé vert'],
            'atouts' => ['Bio', 'Naturel', 'Vegan', 'Sans paraben', 'Sans sulfate', 'Fait main / artisanal', 'Hypoallergénique', 'Halal', 'Made in Africa', 'Non testé sur animaux'],
            'tailles' => ['30 ml', '50 ml', '100 ml', '150 ml', '200 ml', '250 ml', '400 ml', '500 ml'],
            'groups' => ['hydratation' => 'Hydratation & nutrition', 'nettoyage' => 'Nettoyage', 'cible' => 'Soins ciblés'],
            'types' => [
                'Crème / lait corporel'            => ['group' => 'hydratation', 'fields' => ['format', 'peau', 'bienfait', 'senteur', 'conditionnement'], 'actifs' => true,  'unit' => 'ml'],
                'Beurre corporel'                  => ['group' => 'hydratation', 'fields' => ['peau', 'bienfait', 'senteur', 'conditionnement'], 'actifs' => true, 'unit' => 'g'],
                'Huile corporelle'                 => ['group' => 'hydratation', 'fields' => ['bienfait', 'senteur', 'conditionnement'], 'actifs' => true, 'unit' => 'ml'],
                'Beurre de karité brut'            => ['group' => 'hydratation', 'fields' => ['conditionnement'], 'actifs' => true, 'unit' => 'g'],
                'Gel douche'                       => ['group' => 'nettoyage', 'fields' => ['peau', 'bienfait', 'senteur', 'conditionnement'], 'actifs' => false, 'unit' => 'ml'],
                'Savon (pain)'                     => ['group' => 'nettoyage', 'fields' => ['peau', 'bienfait', 'senteur'], 'actifs' => true, 'unit' => 'g'],
                'Savon noir africain'              => ['group' => 'nettoyage', 'fields' => ['peau', 'bienfait', 'senteur'], 'actifs' => true, 'unit' => 'g'],
                'Gommage / exfoliant'              => ['group' => 'nettoyage', 'fields' => ['grain', 'peau', 'frequence', 'senteur'], 'actifs' => true, 'unit' => 'g'],
                'Déodorant'                        => ['group' => 'cible', 'fields' => ['deo_format', 'deo_efficacite', 'deo_alcool', 'senteur'], 'actifs' => false, 'unit' => 'ml'],
                'Crème mains'                      => ['group' => 'cible', 'fields' => ['bienfait', 'senteur', 'conditionnement'], 'actifs' => true, 'unit' => 'ml'],
                'Crème pieds'                      => ['group' => 'cible', 'fields' => ['bienfait', 'senteur', 'conditionnement'], 'actifs' => true, 'unit' => 'ml'],
                'Crème anti-vergetures'            => ['group' => 'cible', 'fields' => ['format', 'frequence', 'senteur'], 'actifs' => true, 'unit' => 'ml'],
                'Crème éclaircissante / unifiante' => ['group' => 'cible', 'fields' => ['format', 'peau', 'senteur'], 'actifs' => true, 'warn' => true, 'unit' => 'ml'],
                'Crème solaire corps'              => ['group' => 'cible', 'fields' => ['spf', 'protection', 'waterproof', 'format', 'peau'], 'actifs' => false, 'unit' => 'ml'],
            ],
        ],

        // ----- Rayon « Soins visage » -----
        'visage' => [
            'fields' => [
                'format_visage' => ['label' => 'Texture / format', 'opts' => ['Gel', 'Crème', 'Lait', 'Mousse', 'Huile', 'Sérum', 'Lotion', 'Eau', 'Baume']],
                'peau'      => ['label' => 'Type de peau',  'opts' => ['Tous types', 'Normale', 'Sèche', 'Grasse', 'Mixte', 'Sensible', 'Acnéique', 'Mature', 'Déshydratée']],
                'concern'   => ['label' => 'Préoccupation principale', 'opts' => ['Hydratation', 'Anti-âge / rides', 'Anti-imperfections / acné', 'Éclat / anti-taches', 'Apaisant', 'Matifiant', 'Raffermissant', 'Purifiant', 'Nourrissant', 'Anti-rougeurs']],
                'moment'    => ['label' => 'Moment d’application', 'opts' => ['Jour', 'Nuit', 'Jour & nuit']],
                'spf'       => ['label' => 'SPF / FPS',     'opts' => ['SPF 15', 'SPF 30', 'SPF 50', 'SPF 50+']],
                'protection' => ['label' => 'Protection',   'opts' => ['UVA + UVB', 'Large spectre']],
                'fini'      => ['label' => 'Fini',          'opts' => ['Mat', 'Naturel', 'Lumineux']],
                'masque_type' => ['label' => 'Type de masque', 'opts' => ['Tissu', 'Argile', 'Crème', 'Peel-off', 'Hydrogel', 'Exfoliant']],
                'pose'      => ['label' => 'Temps de pose', 'opts' => ['5 min', '10 min', '15 min', '20 min', 'Toute la nuit']],
                'gommage_type' => ['label' => 'Type d’exfoliation', 'opts' => ['Grain mécanique', 'Enzymatique', 'Acide (AHA/BHA)']],
                'frequence' => ['label' => 'Fréquence',     'opts' => ['Quotidien', '2-3×/semaine', 'Hebdomadaire']],
                'nettoyant_format' => ['label' => 'Format', 'opts' => ['Gel', 'Mousse', 'Lait', 'Huile', 'Eau micellaire', 'Pain / savon']],
                'tonique_fonction' => ['label' => 'Fonction', 'opts' => ['Hydratant', 'Astringent', 'Exfoliant', 'Apaisant']],
                'contour_concern' => ['label' => 'Cible',   'opts' => ['Cernes', 'Poches', 'Rides', 'Hydratation']],
            ],
            'actifs' => ['Acide hyaluronique', 'Vitamine C', 'Niacinamide', 'Rétinol', 'Acide salicylique', 'AHA / BHA', 'Acide azélaïque', 'Aloe vera', 'Collagène', 'Centella (Cica)', 'Karité', 'Argan', 'Argile', 'Thé vert', 'Peptides', 'Céramides'],
            'atouts' => ['Bio', 'Naturel', 'Vegan', 'Sans paraben', 'Sans alcool', 'Non comédogène', 'Hypoallergénique', 'Testé dermatologiquement', 'Halal'],
            'tailles' => ['15 ml', '30 ml', '50 ml', '75 ml', '100 ml', '150 ml', '200 ml'],
            'groups' => ['nettoyage' => 'Nettoyage & démaquillage', 'hydratation' => 'Hydratation & soin', 'cible' => 'Soins ciblés'],
            // Rappel conformité piloté par la préoccupation (pas par le type).
            'warn_field' => 'concern',
            'warn_value' => 'Éclat / anti-taches',
            'types' => [
                'Nettoyant visage'        => ['group' => 'nettoyage', 'fields' => ['nettoyant_format', 'peau', 'concern'], 'actifs' => false, 'unit' => 'ml'],
                'Démaquillant'            => ['group' => 'nettoyage', 'fields' => ['format_visage', 'peau'], 'actifs' => false, 'unit' => 'ml'],
                'Eau micellaire'          => ['group' => 'nettoyage', 'fields' => ['peau', 'concern'], 'actifs' => false, 'unit' => 'ml'],
                'Tonique / lotion'        => ['group' => 'nettoyage', 'fields' => ['tonique_fonction', 'peau'], 'actifs' => true, 'unit' => 'ml'],
                'Crème hydratante'        => ['group' => 'hydratation', 'fields' => ['format_visage', 'peau', 'moment'], 'actifs' => true, 'unit' => 'ml'],
                'Crème de jour'           => ['group' => 'hydratation', 'fields' => ['format_visage', 'peau', 'concern', 'spf'], 'actifs' => true, 'unit' => 'ml'],
                'Crème de nuit'           => ['group' => 'hydratation', 'fields' => ['format_visage', 'peau', 'concern'], 'actifs' => true, 'unit' => 'ml'],
                'Sérum'                   => ['group' => 'hydratation', 'fields' => ['concern', 'peau', 'moment'], 'actifs' => true, 'unit' => 'ml'],
                'Huile visage'            => ['group' => 'hydratation', 'fields' => ['concern', 'peau', 'moment'], 'actifs' => true, 'unit' => 'ml'],
                'Brume / mist'            => ['group' => 'hydratation', 'fields' => ['concern', 'peau'], 'actifs' => true, 'unit' => 'ml'],
                'Contour des yeux'        => ['group' => 'hydratation', 'fields' => ['contour_concern', 'format_visage'], 'actifs' => true, 'unit' => 'ml'],
                'Masque visage'           => ['group' => 'cible', 'fields' => ['masque_type', 'concern', 'peau', 'pose'], 'actifs' => true, 'unit' => 'ml'],
                'Gommage visage'          => ['group' => 'cible', 'fields' => ['gommage_type', 'peau', 'frequence'], 'actifs' => true, 'unit' => 'ml'],
                'Soin anti-imperfections' => ['group' => 'cible', 'fields' => ['concern', 'peau', 'frequence'], 'actifs' => true, 'unit' => 'ml'],
                'Soin anti-âge'           => ['group' => 'cible', 'fields' => ['format_visage', 'peau', 'moment'], 'actifs' => true, 'unit' => 'ml'],
                'Crème solaire visage'    => ['group' => 'cible', 'fields' => ['spf', 'protection', 'fini', 'peau'], 'actifs' => false, 'unit' => 'ml'],
            ],
        ],
    ],

    // ----- « Autre / nouveau rayon » beauté : formulaire GÉNÉRIQUE ADAPTATIF. -----
    // Caractéristiques LIBRES (libellé → valeur), axe de déclinaison libre (+ pastille
    // couleur option.), atouts personnalisés. S'adapte à l'identifiant (slug) du rayon
    // tapé (config 'R') : suggestions de specs, axe, unité, couleur, conformité.
    'autre' => [
        'rayon_suggest' => ['Soin des cheveux', 'Bain & douche', 'Cils & sourcils', 'Accessoires & outils', 'Pinceaux & éponges', 'Coffrets beauté', 'Hygiène & intime', 'Soins bébé', 'Compléments beauté', 'Bougies & parfum d’ambiance'],
        'generic_specs' => ['Contenance', 'Type de peau', 'Type de cheveux', 'Senteur', 'Texture', 'Zone d’application', 'Format', 'Couleur', 'Actif principal'],
        'atout_suggest' => ['Vegan', 'Cruelty-free', 'Bio', 'Naturel', 'Sans paraben', 'Halal', 'Fait main', 'Made in Africa', 'Hypoallergénique'],
        'axes' => ['Teinte', 'Couleur', 'Contenance', 'Format', 'Senteur', 'Taille', 'Modèle', 'Type', 'Coffret'],
        'warn_texts' => [
            'cosmetic'   => 'Produits cosmétiques : renseigne la composition (INCI) plus bas. Les actifs réglementés (hydroquinone, corticoïdes, mercure, fortes teneurs en rétinol/acides) sont interdits ou restreints dans l’UE.',
            'supplement' => 'Compléments alimentaires : règles spécifiques (allégations santé encadrées, ingrédients autorisés, étiquetage). Vérifie la conformité avant la vente dans l’UE.',
            'safety'     => 'Bougies / parfums d’ambiance : étiquetage des allergènes de parfum et mentions de sécurité (CLP) requis pour la vente dans l’UE.',
        ],
        // Config par slug de rayon : specs suggérées, axe, unité, couleur, conformité.
        'R' => [
            'soin-des-cheveux'    => ['specs' => ['Type de cheveux', 'Texture', 'Bienfait', 'Senteur', 'Actif principal'], 'axis' => 'Contenance', 'unit' => 'ml', 'color' => false, 'warn' => 'cosmetic'],
            'bain-douche'         => ['specs' => ['Type de peau', 'Senteur', 'Format', 'Actif principal'], 'axis' => 'Contenance', 'unit' => 'ml', 'color' => false, 'warn' => 'cosmetic'],
            'cils-sourcils'       => ['specs' => ['Effet', 'Tenue', 'Format'], 'axis' => 'Couleur', 'unit' => 'ml', 'color' => true, 'warn' => 'cosmetic'],
            'accessoires-outils'  => ['specs' => ['Matière', 'Usage', 'Dimensions'], 'axis' => 'Modèle', 'unit' => 'pcs', 'color' => false, 'warn' => 'none'],
            'pinceaux-eponges'    => ['specs' => ['Type', 'Forme', 'Matière des poils', 'Usage'], 'axis' => 'Type', 'unit' => 'pcs', 'color' => false, 'warn' => 'none'],
            'coffrets-beaute'     => ['specs' => ['Contenu', 'Nombre de pièces', 'Occasion'], 'axis' => 'Coffret', 'unit' => 'pcs', 'color' => false, 'warn' => 'cosmetic'],
            'hygiene-intime'      => ['specs' => ['Type de peau', 'pH', 'Senteur', 'Format'], 'axis' => 'Contenance', 'unit' => 'ml', 'color' => false, 'warn' => 'cosmetic'],
            'soins-bebe'          => ['specs' => ['Âge', 'Type de peau', 'Bienfait', 'Senteur'], 'axis' => 'Contenance', 'unit' => 'ml', 'color' => false, 'warn' => 'cosmetic'],
            'complements-beaute'  => ['specs' => ['Bienfait', 'Forme (gélule/poudre)', 'Nombre', 'Durée de cure'], 'axis' => 'Format', 'unit' => 'pcs', 'color' => false, 'warn' => 'supplement'],
            'bougies-parfum-d-ambiance' => ['specs' => ['Senteur', 'Cire', 'Durée de combustion', 'Format'], 'axis' => 'Senteur', 'unit' => 'g', 'color' => false, 'warn' => 'safety'],
        ],
    ],
];




