<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Alimentation » (catégorie boutique
 * « alimentation »). Même philosophie que config/cuisine.php (Maison), mais avec
 * des champs communs propres à l'alimentaire : CONSERVATION (ambiante / frais /
 * surgelé, par défaut selon le type), DLC/DDM + date limite, ALLERGÈNES, et un
 * rappel « bio certifié ». Le TYPE pilote les caractéristiques + la conservation
 * par défaut + l'axe de déclinaison (Poids / Contenance). Specs dans
 * products.attributes (JSON) — aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group?, fields(list), conserv(défaut), axis ]
 */
return [
    'shop_categories' => ['alimentation'],
    'conservations'   => ['Ambiante / sèche', 'Au frais (réfrigéré)', 'Surgelé / congelé'],
    'dlc_types'       => ['DLC (à consommer jusqu’au)', 'DDM (de préférence avant le)'],
    'allergenes'      => ['Gluten', 'Lait', 'Œuf', 'Fruits à coque', 'Arachide', 'Soja', 'Sésame', 'Sulfites', 'Moutarde', 'Poisson', 'Crustacés', 'Mollusques', 'Céleri', 'Lupin'],

    'rayons' => [
        'Bio & naturel' => [
            'groups' => [], // liste de types à plat (sans optgroups)
            'atouts' => ['Bio certifié AB', 'Vegan', 'Sans gluten', 'Sans additifs', 'Commerce équitable', 'Local / circuit court', 'Sans sucre ajouté', 'Fait maison'],
            'fields' => [
                'contenance'      => ['label' => 'Poids / contenance', 'opts' => ['< 100 g', '100 g', '250 g', '500 g', '1 kg', '2 kg', '5 kg', '25 cl', '50 cl', '75 cl', '1 L']],
                'labels'          => ['label' => 'Label bio', 'opts' => ['AB (Agriculture Biologique)', 'Eurofeuille UE', 'Demeter', 'Nature & Progrès', 'Aucun / en conversion']],
                'origine'         => ['label' => 'Origine', 'opts' => ['France', 'Union européenne', 'Afrique de l’Ouest', 'Hors UE', 'Origines multiples']],
                'regime'          => ['label' => 'Régime', 'opts' => ['Vegan', 'Végétarien', 'Sans gluten', 'Sans lactose', 'Halal', 'Cru / raw', 'Aucun']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Sachet', 'Bocal', 'Bouteille', 'Boîte', 'Vrac', 'Lot']],
                'ingredients_pct' => ['label' => '% ingrédients bio', 'opts' => ['95–100 %', '90–95 %', '< 90 %']],
                'forme'           => ['label' => 'Forme', 'opts' => ['Solide', 'Liquide', 'Poudre', 'Gélules', 'Pâte']],
                'transformation'  => ['label' => 'Transformation', 'opts' => ['Brut / non transformé', 'Peu transformé', 'Transformé']],
            ],
            'types' => [
                'Épicerie sèche bio'                    => ['fields' => ['contenance', 'conditionnement', 'labels', 'origine', 'regime', 'ingredients_pct'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Fruits & légumes bio'                  => ['fields' => ['contenance', 'origine', 'labels', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Produits frais bio (laitiers, œufs…)'  => ['fields' => ['contenance', 'labels', 'origine', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Contenance'],
                'Boissons bio (jus, kombucha…)'         => ['fields' => ['contenance', 'labels', 'origine', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance'],
                'Thés & tisanes bio'                    => ['fields' => ['contenance', 'origine', 'labels', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Miel & produits de la ruche'           => ['fields' => ['contenance', 'origine', 'conditionnement', 'transformation'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Huiles & condiments bio'               => ['fields' => ['contenance', 'labels', 'origine', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance'],
                'Snacks & barres bio'                   => ['fields' => ['contenance', 'labels', 'regime', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Compléments & superaliments'           => ['fields' => ['forme', 'contenance', 'labels', 'origine', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Produits sans gluten'                  => ['fields' => ['contenance', 'conditionnement', 'labels', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Surgelés bio'                          => ['fields' => ['contenance', 'labels', 'origine', 'regime'], 'conserv' => 'Surgelé / congelé', 'axis' => 'Poids'],
                'Autre produit bio'                     => ['fields' => ['contenance', 'labels', 'origine', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
            ],
        ],

        'Boissons' => [
            'groups' => [
                'sans_alcool' => 'Sans alcool',
                'chaudes'     => 'Boissons chaudes (à préparer)',
                'alcool'      => 'Alcoolisé (18+)',
                'autre'       => 'Autre',
            ],
            'atouts' => ['Bio', 'Sans sucre ajouté', 'Artisanal', 'Local / circuit court', 'Vegan', 'Sans alcool', 'Commerce équitable', 'Fait maison'],
            'fields' => [
                'contenance'      => ['label' => 'Contenance', 'opts' => ['20 cl', '25 cl', '33 cl', '50 cl', '75 cl', '1 L', '1,5 L', '2 L', '5 L']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Bouteille verre', 'Bouteille plastique', 'Canette', 'Brique', 'Bag-in-box', 'Sachet', 'Boîte', 'Lot / pack']],
                'gaz'             => ['label' => 'Gazéification', 'opts' => ['Plate / sans gaz', 'Pétillante / gazeuse', 'Légèrement pétillante']],
                'sucre'           => ['label' => 'Teneur en sucre', 'opts' => ['Sans sucre', 'Sans sucre ajouté', 'Allégé', 'Normal']],
                'origine'         => ['label' => 'Origine', 'opts' => ['France', 'Union européenne', 'Afrique de l’Ouest', 'Hors UE', 'Origines multiples']],
                'labels'          => ['label' => 'Label / qualité', 'opts' => ['Bio (AB / Eurofeuille)', 'Commerce équitable', 'AOP / IGP', 'Artisanal', 'Aucun']],
                'regime'          => ['label' => 'Régime', 'opts' => ['Vegan', 'Sans gluten', 'Sans lactose', 'Halal', 'Aucun']],
                'format_cafe'     => ['label' => 'Format', 'opts' => ['Grains', 'Moulu', 'Dosettes', 'Capsules', 'Soluble']],
                'intensite'       => ['label' => 'Intensité', 'opts' => ['Doux', 'Équilibré', 'Corsé', 'Très corsé']],
                'degre'           => ['label' => 'Degré d’alcool', 'opts' => ['< 5°', '5–10°', '10–15°', '15–20°', '> 20°']],
                'type_vin'        => ['label' => 'Type', 'opts' => ['Rouge', 'Blanc', 'Rosé', 'Pétillant / mousseux']],
            ],
            'types' => [
                // Sans alcool
                'Eau (plate / gazeuse)'                     => ['group' => 'sans_alcool', 'fields' => ['contenance', 'gaz', 'conditionnement', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Jus de fruits / nectar'                    => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'labels', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Soda / limonade'                           => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'gaz', 'sucre'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Boisson végétale (amande, soja…)'          => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'regime', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Sirop'                                     => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Boisson africaine (bissap, gingembre…)'    => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'origine', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Thé / café prêt à boire'                   => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                'Kombucha / boisson fermentée'              => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'labels'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Contenance', 'alcool' => false],
                'Smoothie'                                  => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Contenance', 'alcool' => false],
                'Boisson énergisante'                       => ['group' => 'sans_alcool', 'fields' => ['contenance', 'conditionnement', 'sucre'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
                // Boissons chaudes (à préparer)
                'Café (grains / moulu)'                     => ['group' => 'chaudes', 'fields' => ['format_cafe', 'intensite', 'contenance', 'origine', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids', 'alcool' => false],
                'Thé / infusion'                            => ['group' => 'chaudes', 'fields' => ['contenance', 'origine', 'labels', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids', 'alcool' => false],
                'Chocolat / poudre chaude'                  => ['group' => 'chaudes', 'fields' => ['contenance', 'sucre', 'origine', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids', 'alcool' => false],
                // Alcoolisé (18+) → avertissement + degré
                'Bière'                                     => ['group' => 'alcool', 'fields' => ['contenance', 'degre', 'conditionnement', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => true],
                'Vin'                                       => ['group' => 'alcool', 'fields' => ['type_vin', 'degre', 'contenance', 'origine', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => true],
                'Spiritueux / liqueur'                      => ['group' => 'alcool', 'fields' => ['degre', 'contenance', 'conditionnement', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => true],
                'Cidre'                                     => ['group' => 'alcool', 'fields' => ['contenance', 'degre', 'conditionnement', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => true],
                // Autre
                'Autre boisson'                             => ['group' => 'autre', 'fields' => ['contenance', 'conditionnement', 'sucre', 'origine'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Contenance', 'alcool' => false],
            ],
        ],

        'Produits frais' => [
            'groups' => [
                'boucherie'   => 'Boucherie & volaille',
                'poisson'     => 'Poissonnerie',
                'charcuterie' => 'Charcuterie & traiteur',
                'cremerie'    => 'Crèmerie',
                'fl'          => 'Fruits & légumes frais',
                'boulangerie' => 'Boulangerie & pâtisserie fraîche',
                'autre'       => 'Autre',
            ],
            'atouts' => ['Bio', 'Halal', 'Local / circuit court', 'De saison', 'Élevage plein air', 'Pêche durable', 'Fait maison', 'Sans additifs'],
            'fields' => [
                'contenance'      => ['label' => 'Poids / quantité', 'opts' => ['< 250 g', '250 g', '500 g', '1 kg', '2 kg', 'À la pièce', 'Au kg']],
                'origine'         => ['label' => 'Origine', 'opts' => ['France', 'Union européenne', 'Afrique de l’Ouest', 'Hors UE', 'Origines multiples']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Barquette', 'Sous vide', 'Sachet', 'Vrac', 'À la coupe', 'Filet', 'Boîte']],
                'halal'           => ['label' => 'Certification', 'opts' => ['Halal', 'Casher', 'Aucune']],
                'coupe'           => ['label' => 'Découpe', 'opts' => ['Entier', 'En morceaux', 'Tranché', 'Haché', 'Filet', 'Émincé', 'Escalope']],
                'maturation'      => ['label' => 'Préparation', 'opts' => ['Cru / frais', 'Mariné', 'Cuit', 'Fumé', 'Séché']],
                'lait_type'       => ['label' => 'Type de lait', 'opts' => ['Vache', 'Chèvre', 'Brebis', 'Bufflonne', 'Mélange']],
                'fromage_type'    => ['label' => 'Type de fromage', 'opts' => ['Pâte molle', 'Pâte pressée', 'Pâte persillée', 'Frais', 'À tartiner']],
                'maturite'        => ['label' => 'Maturité', 'opts' => ['À consommer rapidement', 'Mûr à point', 'À faire mûrir']],
                'calibre'         => ['label' => 'Calibre', 'opts' => ['Petit', 'Moyen', 'Gros', 'Extra gros']],
                'regime'          => ['label' => 'Régime / qualité', 'opts' => ['Bio', 'Sans gluten', 'Sans lactose', 'Halal', 'Vegan', 'Aucun']],
            ],
            'types' => [
                // Boucherie & volaille
                'Viande (bœuf, agneau…)'      => ['group' => 'boucherie', 'fields' => ['coupe', 'contenance', 'halal', 'origine', 'maturation'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Volaille'                    => ['group' => 'boucherie', 'fields' => ['coupe', 'contenance', 'halal', 'origine'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Viande hachée'               => ['group' => 'boucherie', 'fields' => ['contenance', 'halal', 'origine'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                // Poissonnerie
                'Poisson frais'               => ['group' => 'poisson', 'fields' => ['coupe', 'contenance', 'origine', 'maturation'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Fruits de mer / crustacés'   => ['group' => 'poisson', 'fields' => ['contenance', 'origine', 'maturation'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Poisson fumé / transformé'   => ['group' => 'poisson', 'fields' => ['contenance', 'origine', 'maturation', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                // Charcuterie & traiteur
                'Charcuterie'                 => ['group' => 'charcuterie', 'fields' => ['contenance', 'halal', 'origine', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Plat préparé frais'          => ['group' => 'charcuterie', 'fields' => ['contenance', 'regime', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Traiteur / salade'           => ['group' => 'charcuterie', 'fields' => ['contenance', 'regime', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                // Crèmerie
                'Lait frais'                  => ['group' => 'cremerie', 'fields' => ['contenance', 'lait_type', 'origine', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Contenance'],
                'Fromage'                     => ['group' => 'cremerie', 'fields' => ['fromage_type', 'lait_type', 'contenance', 'origine'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Yaourt / dessert lacté'      => ['group' => 'cremerie', 'fields' => ['contenance', 'lait_type', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Beurre / crème'              => ['group' => 'cremerie', 'fields' => ['contenance', 'lait_type', 'origine'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Œufs'                        => ['group' => 'cremerie', 'fields' => ['contenance', 'calibre', 'origine', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                // Fruits & légumes frais
                'Fruits frais'                => ['group' => 'fl', 'fields' => ['contenance', 'origine', 'maturite', 'calibre'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Légumes frais'               => ['group' => 'fl', 'fields' => ['contenance', 'origine', 'calibre', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Herbes aromatiques'          => ['group' => 'fl', 'fields' => ['contenance', 'origine', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                // Boulangerie & pâtisserie fraîche
                'Pâtes fraîches'              => ['group' => 'boulangerie', 'fields' => ['contenance', 'regime', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Pâtisserie / dessert frais'  => ['group' => 'boulangerie', 'fields' => ['contenance', 'regime', 'conditionnement'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
                'Pain frais'                  => ['group' => 'boulangerie', 'fields' => ['contenance', 'regime', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                // Autre
                'Autre produit frais'         => ['group' => 'autre', 'fields' => ['contenance', 'origine', 'regime'], 'conserv' => 'Au frais (réfrigéré)', 'axis' => 'Poids'],
            ],
        ],

        'Snacks' => [
            'groups' => [
                'sale'  => 'Salé',
                'sucre' => 'Sucré',
                'monde' => 'Du monde & diététique',
                'autre' => 'Autre',
            ],
            'atouts' => ['Bio', 'Vegan', 'Sans gluten', 'Sans huile de palme', 'Artisanal', 'Fait maison', 'Sans sucre ajouté', 'Local / circuit court'],
            'fields' => [
                'contenance'      => ['label' => 'Poids / contenance', 'opts' => ['< 50 g', '50 g', '100 g', '150 g', '200 g', '250 g', '500 g']],
                'conditionnement' => ['label' => 'Conditionnement', 'opts' => ['Sachet', 'Paquet', 'Boîte', 'Étui', 'Vrac', 'Lot / pack', 'Sachets individuels']],
                'gout'            => ['label' => 'Goût / saveur', 'opts' => ['Nature', 'Salé', 'Sucré', 'Épicé', 'Barbecue', 'Fromage', 'Chocolat', 'Fruité', 'Caramel']],
                'sucre'           => ['label' => 'Teneur en sucre', 'opts' => ['Sans sucre', 'Sans sucre ajouté', 'Allégé', 'Normal']],
                'labels'          => ['label' => 'Label / qualité', 'opts' => ['Bio (AB / Eurofeuille)', 'Commerce équitable', 'Artisanal', 'Aucun']],
                'regime'          => ['label' => 'Régime', 'opts' => ['Vegan', 'Sans gluten', 'Sans lactose', 'Halal', 'Bio', 'Aucun']],
                'origine'         => ['label' => 'Origine', 'opts' => ['France', 'Union européenne', 'Afrique de l’Ouest', 'Hors UE', 'Origines multiples']],
                'proteine'        => ['label' => 'Teneur en protéines', 'opts' => ['Standard', 'Riche en protéines (> 20 %)']],
                'cacao'           => ['label' => '% cacao', 'opts' => ['Lait', '< 50 %', '50–70 %', '70–85 %', '> 85 %']],
                'piece'           => ['label' => 'Conditionnement par', 'opts' => ['À l’unité', 'Lot de 3', 'Lot de 6', 'Lot de 12', 'Format familial']],
            ],
            'types' => [
                // Salé
                'Chips / tuiles'                            => ['group' => 'sale', 'fields' => ['contenance', 'gout', 'conditionnement', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Crackers / biscuits salés'                 => ['group' => 'sale', 'fields' => ['contenance', 'gout', 'conditionnement', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Snacks apéritifs'                          => ['group' => 'sale', 'fields' => ['contenance', 'gout', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Popcorn'                                   => ['group' => 'sale', 'fields' => ['contenance', 'gout', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Fruits secs / oléagineux'                  => ['group' => 'sale', 'fields' => ['contenance', 'gout', 'origine', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                // Sucré
                'Biscuits / cookies'                        => ['group' => 'sucre', 'fields' => ['contenance', 'sucre', 'conditionnement', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Gâteaux / biscuiterie'                     => ['group' => 'sucre', 'fields' => ['contenance', 'sucre', 'conditionnement', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Barres céréalières'                        => ['group' => 'sucre', 'fields' => ['contenance', 'sucre', 'regime', 'piece'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Chocolat / tablette'                       => ['group' => 'sucre', 'fields' => ['contenance', 'cacao', 'origine', 'labels'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Confiserie / bonbons'                      => ['group' => 'sucre', 'fields' => ['contenance', 'gout', 'sucre', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                // Du monde & diététique
                'Snacks africains (chin-chin, plantain…)'   => ['group' => 'monde', 'fields' => ['contenance', 'gout', 'origine', 'conditionnement'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                'Snacks protéinés / sportifs'               => ['group' => 'monde', 'fields' => ['contenance', 'proteine', 'sucre', 'piece'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
                // Autre
                'Autre snack'                               => ['group' => 'autre', 'fields' => ['contenance', 'gout', 'conditionnement', 'regime'], 'conserv' => 'Ambiante / sèche', 'axis' => 'Poids'],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons selon l'axe (Poids / Contenance).
    'size_systems' => [
        'Poids'      => [['label' => 'Poids', 'list' => ['100 g', '250 g', '500 g', '1 kg', '2 kg', '5 kg']]],
        'Contenance' => [['label' => 'Contenances', 'list' => ['25 cl', '33 cl', '50 cl', '75 cl', '1 L', '2 L']]],
    ],
];
