<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Maison & meubles » (catégorie boutique
 * « maison ») : Cuisine, Décoration, … (un rayon ajouté = config seule). Même
 * philosophie que config/electronics.php : le RAYON pilote la liste de types, et
 * le TYPE pilote les caractéristiques affichées, la nature de la déclinaison et le
 * mode « électrique » (flag `elec` → garantie + rappel CE/tension). Les specs sont
 * stockées dans products.attributes (JSON) — aucune migration.
 *
 * (Le fichier garde le nom « cuisine » pour des raisons historiques ; il couvre
 *  désormais tous les rayons Maison adaptatifs.)
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

        'Décoration' => [
            'groups' => [
                'murs'    => 'Murs & cadres',
                'lum'     => 'Luminaires',
                'textile' => 'Textile déco',
                'objets'  => 'Objets déco',
                'autre'   => 'Autre',
            ],
            'atouts' => ['Wax / pagne', 'Fait main / artisanal', 'Style africain', 'LED', 'Lot / ensemble', 'Éco-responsable', 'Pièce unique', 'Lavable'],
            'fields' => [
                'matiere'     => ['label' => 'Matière', 'opts' => ['Bois', 'Métal', 'Verre', 'Céramique', 'Rotin / osier', 'Tissu', 'Coton', 'Velours', 'Wax / pagne', 'Plastique', 'Résine', 'Pierre', 'Bambou', 'Autre']],
                'dimensions'  => ['label' => 'Dimensions', 'opts' => ['Petit', 'Moyen', 'Grand', 'Très grand']],
                'forme'       => ['label' => 'Forme', 'opts' => ['Rond', 'Carré', 'Rectangulaire', 'Ovale', 'Irrégulier']],
                'style'       => ['label' => 'Style', 'opts' => ['Moderne', 'Bohème', 'Scandinave', 'Industriel', 'Ethnique / africain', 'Vintage', 'Classique', 'Minimaliste']],
                'ampoule'     => ['label' => 'Type d’ampoule', 'opts' => ['LED intégrée', 'E27', 'E14', 'GU10', 'Sans ampoule fournie']],
                'puissance'   => ['label' => 'Puissance (W)', 'opts' => ['< 10 W', '10–25 W', '25–40 W', '40–60 W', '> 60 W']],
                'tension'     => ['label' => 'Alimentation', 'opts' => ['220–240 V', 'Pile', 'USB / rechargeable', 'Solaire', 'Sans']],
                'nb_lumieres' => ['label' => 'Nombre de lumières', 'opts' => ['1', '2', '3', '4', '5 et +']],
                'pieces'      => ['label' => 'Nombre de pièces / lot', 'opts' => ['1', '2', '3', '4', '6', '+']],
                'motif'       => ['label' => 'Motif', 'opts' => ['Uni', 'Wax / imprimé africain', 'Géométrique', 'Floral', 'Abstrait', 'Animal', 'Texte']],
                'remplissage' => ['label' => 'Garnissage', 'opts' => ['Avec garniture', 'Housse seule']],
                'pose'        => ['label' => 'Pose', 'opts' => ['À poser', 'À suspendre', 'Murale', 'Sur pied']],
                'parfum'      => ['label' => 'Parfum', 'opts' => ['Sans parfum', 'Vanille', 'Fleuri', 'Boisé', 'Agrumes', 'Épicé']],
            ],
            'types' => [
                // Murs & cadres
                'Cadre / tableau'           => ['group' => 'murs', 'fields' => ['matiere', 'dimensions', 'forme', 'style', 'motif'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Miroir'                    => ['group' => 'murs', 'fields' => ['matiere', 'dimensions', 'forme', 'style', 'pose'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Sticker mural / poster'    => ['group' => 'murs', 'fields' => ['dimensions', 'motif', 'style'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Tenture / tapisserie'      => ['group' => 'murs', 'fields' => ['matiere', 'dimensions', 'motif', 'style'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                // Luminaires (électriques → garantie + rappel CE/ampoule/tension)
                'Lampe / lampe de table'    => ['group' => 'lum', 'fields' => ['matiere', 'ampoule', 'puissance', 'tension', 'style'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Suspension / plafonnier'   => ['group' => 'lum', 'fields' => ['matiere', 'ampoule', 'nb_lumieres', 'tension', 'style'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Lampadaire'                => ['group' => 'lum', 'fields' => ['matiere', 'ampoule', 'puissance', 'tension', 'style'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Guirlande lumineuse'       => ['group' => 'lum', 'fields' => ['nb_lumieres', 'tension', 'dimensions'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Applique murale'           => ['group' => 'lum', 'fields' => ['matiere', 'ampoule', 'tension', 'style'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Textile déco
                'Coussin'                   => ['group' => 'textile', 'fields' => ['matiere', 'dimensions', 'motif', 'remplissage', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Plaid / jeté'              => ['group' => 'textile', 'fields' => ['matiere', 'dimensions', 'motif', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Rideau / voilage'          => ['group' => 'textile', 'fields' => ['matiere', 'dimensions', 'motif', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Tapis'                     => ['group' => 'textile', 'fields' => ['matiere', 'dimensions', 'forme', 'motif', 'style'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Nappe / chemin de table'   => ['group' => 'textile', 'fields' => ['matiere', 'dimensions', 'motif'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Objets déco
                'Vase'                      => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'forme', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Bougie / bougeoir'         => ['group' => 'objets', 'fields' => ['matiere', 'parfum', 'dimensions', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Photophore'                => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'pieces', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Statue / figurine'         => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'style'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Horloge'                   => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'forme', 'style', 'tension'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Plante artificielle'       => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'pose'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Panier déco / corbeille'   => ['group' => 'objets', 'fields' => ['matiere', 'dimensions', 'pieces', 'style'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                // Autre
                'Autre objet déco'          => ['group' => 'autre', 'fields' => ['matiere', 'dimensions', 'style', 'motif'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],

        'Jardin' => [
            'groups' => [
                'mobilier'  => 'Mobilier de jardin',
                'cuisson'   => 'Barbecue & cuisson',
                'plantes'   => 'Plantes & jardinage',
                'outils'    => 'Outils & arrosage',
                'moto'      => 'Motoculture',
                'eclairage' => 'Éclairage & déco extérieure',
                'piscine'   => 'Piscine & abris',
                'autre'     => 'Autre',
            ],
            'atouts' => ['Résistant aux UV', 'Étanche / waterproof', 'Pliable', 'Solaire', 'Sans fil / batterie', 'Inox / anti-rouille', 'Montage facile', 'Garantie incluse'],
            'fields' => [
                'matiere'         => ['label' => 'Matière', 'opts' => ['Acier', 'Aluminium', 'Fer forgé', 'Bois', 'Bois composite', 'Résine tressée', 'Rotin synthétique', 'Plastique', 'Textilène', 'Béton', 'Terre cuite', 'Inox', 'Verre', 'Autre']],
                'dimensions'      => ['label' => 'Dimensions / taille', 'opts' => ['Petit', 'Moyen', 'Grand', 'Très grand']],
                'places'          => ['label' => 'Nombre de places', 'opts' => ['1', '2', '4', '6', '8', '10 et +']],
                'pieces'          => ['label' => 'Nombre de pièces / lot', 'opts' => ['1', '2', '3', '4', '6', '+']],
                'alim_cuisson'    => ['label' => 'Énergie', 'opts' => ['Charbon', 'Gaz', 'Électrique', 'Bois']],
                'surface_cuisson' => ['label' => 'Surface de cuisson', 'opts' => ['< 1500 cm²', '1500–2500 cm²', '2500–4000 cm²', '> 4000 cm²']],
                'puissance'       => ['label' => 'Puissance', 'opts' => ['< 500 W', '500–1000 W', '1000–1800 W', '1800–2500 W', '> 2500 W / thermique']],
                'alimentation'    => ['label' => 'Alimentation', 'opts' => ['Filaire 220–240 V', 'Batterie / sans fil', 'Thermique / essence', 'Solaire', 'Manuel']],
                'capacite'        => ['label' => 'Capacité / contenance', 'opts' => ['< 5 L', '5–10 L', '10–20 L', '20–50 L', '> 50 L']],
                'largeur_coupe'   => ['label' => 'Largeur de coupe', 'opts' => ['< 30 cm', '30–40 cm', '40–50 cm', '> 50 cm']],
                'montage'         => ['label' => 'Montage', 'opts' => ['Monté', 'À monter', 'Pliable']],
                'usage_plante'    => ['label' => 'Exposition', 'opts' => ['Plein soleil', 'Mi-ombre', 'Ombre', 'Intérieur / extérieur']],
                'saison_semis'    => ['label' => 'Période', 'opts' => ['Printemps', 'Été', 'Automne', 'Toute saison']],
                'longueur'        => ['label' => 'Longueur', 'opts' => ['10 m', '15 m', '20 m', '25 m', '50 m']],
            ],
            'types' => [
                // Mobilier de jardin
                'Salon de jardin'                     => ['group' => 'mobilier', 'fields' => ['matiere', 'places', 'montage', 'dimensions'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Table de jardin'                     => ['group' => 'mobilier', 'fields' => ['matiere', 'places', 'montage', 'dimensions'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Chaise / fauteuil de jardin'         => ['group' => 'mobilier', 'fields' => ['matiere', 'montage', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Parasol'                             => ['group' => 'mobilier', 'fields' => ['matiere', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Hamac / balancelle'                  => ['group' => 'mobilier', 'fields' => ['matiere', 'places', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Coussin d’extérieur'                 => ['group' => 'mobilier', 'fields' => ['matiere', 'dimensions', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Coffre / rangement extérieur'        => ['group' => 'mobilier', 'fields' => ['matiere', 'capacite', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Barbecue & cuisson
                'Barbecue'                            => ['group' => 'cuisson', 'fields' => ['alim_cuisson', 'surface_cuisson', 'matiere', 'montage'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Plancha'                             => ['group' => 'cuisson', 'fields' => ['alim_cuisson', 'surface_cuisson', 'matiere'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Brasero / fumoir'                    => ['group' => 'cuisson', 'fields' => ['matiere', 'dimensions', 'alim_cuisson'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                // Plantes & jardinage
                'Pot / jardinière'                    => ['group' => 'plantes', 'fields' => ['matiere', 'dimensions', 'pieces', 'usage_plante'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Bac / carré potager'                 => ['group' => 'plantes', 'fields' => ['matiere', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Terreau / substrat'                  => ['group' => 'plantes', 'fields' => ['capacite', 'usage_plante'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Graines / semences'                  => ['group' => 'plantes', 'fields' => ['saison_semis', 'usage_plante', 'pieces'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Plante / arbuste'                    => ['group' => 'plantes', 'fields' => ['usage_plante', 'dimensions', 'saison_semis'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Engrais'                             => ['group' => 'plantes', 'fields' => ['capacite', 'usage_plante'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                // Outils & arrosage
                'Outil à main (bêche, râteau…)'       => ['group' => 'outils', 'fields' => ['matiere', 'dimensions', 'pieces'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Sécateur / cisaille'                 => ['group' => 'outils', 'fields' => ['matiere', 'dimensions'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Brouette'                            => ['group' => 'outils', 'fields' => ['matiere', 'capacite', 'montage'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Arrosoir'                            => ['group' => 'outils', 'fields' => ['matiere', 'capacite', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Tuyau d’arrosage'                    => ['group' => 'outils', 'fields' => ['longueur', 'matiere', 'pieces'], 'elec' => false, 'axis' => 'Longueur', 'color' => false],
                'Système goutte-à-goutte'             => ['group' => 'outils', 'fields' => ['longueur', 'pieces'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                'Programmateur d’arrosage'            => ['group' => 'outils', 'fields' => ['alimentation', 'puissance'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Pompe'                               => ['group' => 'outils', 'fields' => ['puissance', 'alimentation', 'capacite'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                // Motoculture (motorisé/électrique → garantie + rappel CE)
                'Tondeuse'                            => ['group' => 'moto', 'fields' => ['largeur_coupe', 'puissance', 'alimentation', 'capacite'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Taille-haie'                         => ['group' => 'moto', 'fields' => ['largeur_coupe', 'puissance', 'alimentation'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Débroussailleuse'                    => ['group' => 'moto', 'fields' => ['puissance', 'alimentation', 'largeur_coupe'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Souffleur / aspirateur de feuilles'  => ['group' => 'moto', 'fields' => ['puissance', 'alimentation'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Tronçonneuse'                        => ['group' => 'moto', 'fields' => ['puissance', 'alimentation', 'dimensions'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                // Éclairage & déco extérieure
                'Lampe solaire / extérieure'          => ['group' => 'eclairage', 'fields' => ['alimentation', 'puissance', 'matiere'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Guirlande extérieure'                => ['group' => 'eclairage', 'fields' => ['longueur', 'alimentation'], 'elec' => true, 'axis' => 'Modèle', 'color' => false],
                'Statue / fontaine de jardin'         => ['group' => 'eclairage', 'fields' => ['matiere', 'dimensions', 'alimentation'], 'elec' => false, 'axis' => 'Modèle', 'color' => false],
                // Piscine & abris
                'Piscine / spa'                       => ['group' => 'piscine', 'fields' => ['matiere', 'places', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Bâche / couverture'                  => ['group' => 'piscine', 'fields' => ['matiere', 'dimensions'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Serre / abri de jardin'              => ['group' => 'piscine', 'fields' => ['matiere', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                // Autre
                'Autre article de jardin'             => ['group' => 'autre', 'fields' => ['matiere', 'dimensions'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],
    ],
];
