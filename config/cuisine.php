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

        'Linge de maison' => [
            'groups' => [
                'lit'   => 'Linge de lit',
                'bain'  => 'Linge de bain',
                'table' => 'Linge de table',
                'autre' => 'Autre',
            ],
            'atouts' => ['Coton bio', 'Wax / pagne', 'Anti-acariens', 'Doux / moelleux', 'Lavable en machine', 'Grande taille', 'Lot / parure', 'Éco-responsable'],
            'fields' => [
                'matiere'            => ['label' => 'Matière', 'opts' => ['Coton', 'Coton bio', 'Percale de coton', 'Satin de coton', 'Lin', 'Polyester', 'Microfibre', 'Polaire', 'Éponge', 'Bambou', 'Flanelle', 'Wax / pagne', 'Satin', 'Velours', 'Autre']],
                'taille_lit'         => ['label' => 'Taille de lit', 'opts' => ['1 place (90×190)', '2 places (140×190)', 'Queen (160×200)', 'King (180×200)', 'Bébé / berceau']],
                'dim_housse'         => ['label' => 'Dimensions housse', 'opts' => ['140×200', '200×200', '220×240', '240×260', '260×240']],
                'dim_drap'           => ['label' => 'Dimensions', 'opts' => ['90×190', '140×190', '160×200', '180×200', '240×300']],
                'dim_taie'           => ['label' => 'Dimensions taie', 'opts' => ['50×70', '60×60', '65×65', '40×60']],
                'garnissage'         => ['label' => 'Garnissage', 'opts' => ['Synthétique', 'Plume / duvet', 'Microfibre', 'Mémoire de forme', 'Latex', 'Sans (housse seule)']],
                'grammage'           => ['label' => 'Grammage (éponge)', 'opts' => ['< 400 g/m²', '400–500 g/m²', '500–600 g/m²', '> 600 g/m²']],
                'pieces'             => ['label' => 'Nombre de pièces', 'opts' => ['1', '2', '3', '4', '6', '8', '+']],
                'forme'              => ['label' => 'Forme', 'opts' => ['Rectangulaire', 'Carré', 'Rond', 'Ovale']],
                'dim_nappe'          => ['label' => 'Dimensions', 'opts' => ['140×140', '150×250', '150×300', '180×300', 'Ronde Ø180']],
                'dim_serviette_bain' => ['label' => 'Dimensions', 'opts' => ['Gant 15×21', 'Invité 30×50', 'Toilette 50×90', 'Drap de bain 70×140', 'Drap XL 100×150']],
                'motif'              => ['label' => 'Motif', 'opts' => ['Uni', 'Wax / imprimé africain', 'Rayé', 'Fleuri', 'Géométrique', 'Brodé', 'Carreaux']],
                'saison'             => ['label' => 'Saison / chaleur', 'opts' => ['Toutes saisons', 'Été / léger', 'Hiver / chaud', 'Tempéré']],
            ],
            'types' => [
                // Linge de lit
                'Parure de lit'         => ['group' => 'lit', 'fields' => ['matiere', 'taille_lit', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Housse de couette'     => ['group' => 'lit', 'fields' => ['matiere', 'dim_housse', 'motif'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Drap plat'             => ['group' => 'lit', 'fields' => ['matiere', 'dim_drap', 'motif'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Drap-housse'           => ['group' => 'lit', 'fields' => ['matiere', 'dim_drap', 'motif'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Taie d’oreiller'       => ['group' => 'lit', 'fields' => ['matiere', 'dim_taie', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Couette'               => ['group' => 'lit', 'fields' => ['matiere', 'garnissage', 'dim_housse', 'saison'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Oreiller / traversin'  => ['group' => 'lit', 'fields' => ['matiere', 'garnissage', 'dim_taie', 'pieces'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Couverture / plaid'    => ['group' => 'lit', 'fields' => ['matiere', 'dim_drap', 'motif', 'saison'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Couvre-lit / jeté'     => ['group' => 'lit', 'fields' => ['matiere', 'dim_housse', 'motif'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Protège-matelas'       => ['group' => 'lit', 'fields' => ['matiere', 'dim_drap'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Moustiquaire'          => ['group' => 'lit', 'fields' => ['matiere', 'taille_lit'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                // Linge de bain
                'Serviette de toilette' => ['group' => 'bain', 'fields' => ['matiere', 'grammage', 'dim_serviette_bain', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Drap de bain'          => ['group' => 'bain', 'fields' => ['matiere', 'grammage', 'dim_serviette_bain', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Gant de toilette'      => ['group' => 'bain', 'fields' => ['matiere', 'grammage', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Peignoir'              => ['group' => 'bain', 'fields' => ['matiere', 'grammage', 'saison'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Tapis de bain'         => ['group' => 'bain', 'fields' => ['matiere', 'forme', 'dim_serviette_bain'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Lot de serviettes'     => ['group' => 'bain', 'fields' => ['matiere', 'grammage', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Linge de table
                'Nappe'                 => ['group' => 'table', 'fields' => ['matiere', 'forme', 'dim_nappe', 'motif'], 'elec' => false, 'axis' => 'Taille', 'color' => true],
                'Set de table'          => ['group' => 'table', 'fields' => ['matiere', 'forme', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Serviette de table'    => ['group' => 'table', 'fields' => ['matiere', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Chemin de table'       => ['group' => 'table', 'fields' => ['matiere', 'motif', 'dim_nappe'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Torchon'               => ['group' => 'table', 'fields' => ['matiere', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Autre linge de maison' => ['group' => 'autre', 'fields' => ['matiere', 'motif', 'pieces'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],

        'Meubles' => [
            'groups' => [
                'salon'     => 'Salon',
                'sam'       => 'Salle à manger',
                'chambre'   => 'Chambre',
                'bureau'    => 'Bureau',
                'rangement' => 'Rangement & entrée',
                'autre'     => 'Autre',
            ],
            'atouts' => ['Bois massif', 'Fait main / artisanal', 'Style africain', 'Montage facile', 'Convertible', 'Rangement intégré', 'Éco-responsable', 'Pièce unique'],
            'fields' => [
                'matiere'     => ['label' => 'Matière principale', 'opts' => ['Bois massif', 'Bois / panneau', 'MDF', 'Métal', 'Verre', 'Rotin / osier', 'Tissu', 'Cuir', 'Simili cuir', 'Velours', 'Plastique', 'Marbre', 'Bambou', 'Autre']],
                'places'      => ['label' => 'Nombre de places', 'opts' => ['1', '2', '3', '4', '5', '6 et +']],
                'dimensions'  => ['label' => 'Dimensions', 'opts' => ['Petit', 'Moyen', 'Grand', 'Sur mesure']],
                'montage'     => ['label' => 'Montage', 'opts' => ['Livré monté', 'À monter', 'Pliable', 'Modulable']],
                'style'       => ['label' => 'Style', 'opts' => ['Moderne', 'Scandinave', 'Industriel', 'Classique', 'Ethnique / africain', 'Vintage', 'Rustique', 'Minimaliste']],
                'portes'      => ['label' => 'Portes / tiroirs', 'opts' => ['Sans', '1', '2', '3', '4', '5 et +']],
                'couchage'    => ['label' => 'Couchage', 'opts' => ['1 place (90)', '2 places (140)', 'Queen (160)', 'King (180)', 'Bébé']],
                'assise'      => ['label' => 'Garnissage / assise', 'opts' => ['Mousse', 'Mousse HR', 'Ressorts', 'Plumes', 'Latex', 'Non précisé']],
                'convertible' => ['label' => 'Convertible', 'opts' => ['Oui', 'Non']],
                'rangement'   => ['label' => 'Rangement intégré', 'opts' => ['Oui', 'Non']],
                'reglable'    => ['label' => 'Réglable en hauteur', 'opts' => ['Oui', 'Non']],
            ],
            'types' => [
                // Salon
                'Canapé'                          => ['group' => 'salon', 'fields' => ['matiere', 'places', 'convertible', 'assise', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Fauteuil'                        => ['group' => 'salon', 'fields' => ['matiere', 'assise', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Table basse'                     => ['group' => 'salon', 'fields' => ['matiere', 'dimensions', 'montage', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Meuble TV'                       => ['group' => 'salon', 'fields' => ['matiere', 'dimensions', 'portes', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Pouf / repose-pieds'             => ['group' => 'salon', 'fields' => ['matiere', 'rangement', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Salle à manger
                'Table à manger'                  => ['group' => 'sam', 'fields' => ['matiere', 'places', 'montage', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Chaise'                          => ['group' => 'sam', 'fields' => ['matiere', 'assise', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Buffet / vaisselier'             => ['group' => 'sam', 'fields' => ['matiere', 'portes', 'dimensions', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Banc'                            => ['group' => 'sam', 'fields' => ['matiere', 'places', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Tabouret / tabouret de bar'      => ['group' => 'sam', 'fields' => ['matiere', 'reglable', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Chambre
                'Lit'                             => ['group' => 'chambre', 'fields' => ['matiere', 'couchage', 'rangement', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Matelas'                         => ['group' => 'chambre', 'fields' => ['couchage', 'assise', 'dimensions'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Sommier'                         => ['group' => 'chambre', 'fields' => ['couchage', 'matiere'], 'elec' => false, 'axis' => 'Taille', 'color' => false],
                'Armoire / dressing'              => ['group' => 'chambre', 'fields' => ['matiere', 'portes', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Commode'                         => ['group' => 'chambre', 'fields' => ['matiere', 'portes', 'dimensions', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Table de chevet'                 => ['group' => 'chambre', 'fields' => ['matiere', 'portes', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Bureau
                'Bureau'                          => ['group' => 'bureau', 'fields' => ['matiere', 'dimensions', 'reglable', 'portes', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Chaise de bureau'                => ['group' => 'bureau', 'fields' => ['matiere', 'reglable', 'assise'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Bibliothèque / étagère'          => ['group' => 'bureau', 'fields' => ['matiere', 'dimensions', 'montage', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Rangement & entrée
                'Meuble de rangement'             => ['group' => 'rangement', 'fields' => ['matiere', 'portes', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Meuble d’entrée / vestiaire'     => ['group' => 'rangement', 'fields' => ['matiere', 'portes', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                'Étagère murale'                  => ['group' => 'rangement', 'fields' => ['matiere', 'dimensions', 'montage'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Autre meuble'                    => ['group' => 'autre', 'fields' => ['matiere', 'dimensions', 'style'], 'elec' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],

        'Électroménager' => [
            'groups' => [
                'lavage'    => 'Lavage',
                'froid'     => 'Froid',
                'cuisson'   => 'Cuisson',
                'clim'      => 'Climatisation & air',
                'entretien' => 'Entretien',
                'autre'     => 'Autre',
            ],
            'atouts' => ['Classe A', 'No Frost', 'Faible consommation', 'Silencieux', 'Connecté / WiFi', 'Reconditionné', 'Garantie incluse', 'Livraison + installation'],
            'fields' => [
                'capacite_kg'  => ['label' => 'Capacité (kg)', 'opts' => ['< 6 kg', '6 kg', '7 kg', '8 kg', '9 kg', '10 kg et +']],
                'capacite_l'   => ['label' => 'Capacité (litres)', 'opts' => ['< 100 L', '100–200 L', '200–300 L', '300–400 L', '> 400 L']],
                'couverts'     => ['label' => 'Couverts', 'opts' => ['6', '8', '10', '12', '14', '16']],
                'energie'      => ['label' => 'Classe énergie', 'opts' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'Non précisé']],
                'tension'      => ['label' => 'Tension', 'opts' => ['220–240 V', '110 V', 'Bi-tension']],
                'puissance'    => ['label' => 'Puissance', 'opts' => ['< 1000 W', '1000–1500 W', '1500–2000 W', '2000–2500 W', '> 2500 W']],
                'pose'         => ['label' => 'Installation', 'opts' => ['Pose libre', 'Encastrable', 'Sous plan', 'Mural']],
                'programmes'   => ['label' => 'Programmes', 'opts' => ['1–5', '6–10', '11–15', '> 15']],
                'essorage'     => ['label' => 'Essorage (tr/min)', 'opts' => ['800', '1000', '1200', '1400', '1600']],
                'froid_type'   => ['label' => 'Type de froid', 'opts' => ['Statique', 'Brassé', 'No Frost / ventilé']],
                'btu'          => ['label' => 'Puissance frigorifique', 'opts' => ['< 9000 BTU', '9000 BTU', '12000 BTU', '18000 BTU', '24000 BTU et +']],
                'surface'      => ['label' => 'Surface couverte', 'opts' => ['< 15 m²', '15–25 m²', '25–40 m²', '> 40 m²']],
                'foyers'       => ['label' => 'Nombre de foyers', 'opts' => ['1', '2', '3', '4', '5 et +']],
                'type_energie' => ['label' => 'Énergie de cuisson', 'opts' => ['Électrique', 'Gaz', 'Mixte', 'Induction', 'Vitrocéramique']],
                'autonomie'    => ['label' => 'Autonomie batterie', 'opts' => ['< 30 min', '30–45 min', '45–60 min', '> 60 min']],
                'reservoir'    => ['label' => 'Capacité réservoir', 'opts' => ['< 1 L', '1–2 L', '2–3 L', '> 3 L']],
            ],
            'types' => [
                // Lavage (tous électriques → garantie + classe énergie + rappel CE)
                'Lave-linge'                         => ['group' => 'lavage', 'fields' => ['capacite_kg', 'essorage', 'programmes', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Sèche-linge'                        => ['group' => 'lavage', 'fields' => ['capacite_kg', 'programmes', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Lave-linge séchant'                 => ['group' => 'lavage', 'fields' => ['capacite_kg', 'essorage', 'programmes', 'energie'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Lave-vaisselle'                     => ['group' => 'lavage', 'fields' => ['couverts', 'programmes', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Froid
                'Réfrigérateur'                      => ['group' => 'froid', 'fields' => ['capacite_l', 'froid_type', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Réfrigérateur combiné'              => ['group' => 'froid', 'fields' => ['capacite_l', 'froid_type', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Congélateur'                        => ['group' => 'froid', 'fields' => ['capacite_l', 'froid_type', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Cave à vin'                         => ['group' => 'froid', 'fields' => ['capacite_l', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Cuisson
                'Cuisinière'                         => ['group' => 'cuisson', 'fields' => ['foyers', 'type_energie', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Four encastrable'                   => ['group' => 'cuisson', 'fields' => ['capacite_l', 'programmes', 'energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Plaque de cuisson'                  => ['group' => 'cuisson', 'fields' => ['foyers', 'type_energie', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Hotte aspirante'                    => ['group' => 'cuisson', 'fields' => ['puissance', 'pose', 'programmes'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Micro-ondes'                        => ['group' => 'cuisson', 'fields' => ['capacite_l', 'puissance', 'programmes'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Climatisation & air
                'Climatiseur'                        => ['group' => 'clim', 'fields' => ['btu', 'surface', 'energie', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Ventilateur'                        => ['group' => 'clim', 'fields' => ['puissance', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Chauffage / radiateur'              => ['group' => 'clim', 'fields' => ['puissance', 'surface', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Purificateur d’air'                 => ['group' => 'clim', 'fields' => ['surface', 'puissance', 'programmes'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Déshumidificateur'                  => ['group' => 'clim', 'fields' => ['reservoir', 'surface', 'puissance'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Entretien
                'Aspirateur'                         => ['group' => 'entretien', 'fields' => ['puissance', 'reservoir', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Aspirateur robot'                   => ['group' => 'entretien', 'fields' => ['autonomie', 'programmes', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Nettoyeur vapeur'                   => ['group' => 'entretien', 'fields' => ['puissance', 'reservoir', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Centrale vapeur / fer à repasser'   => ['group' => 'entretien', 'fields' => ['puissance', 'reservoir', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                // Autre
                'Chauffe-eau'                        => ['group' => 'autre', 'fields' => ['capacite_l', 'puissance', 'pose'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Machine à coudre'                   => ['group' => 'autre', 'fields' => ['programmes', 'puissance', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
                'Autre appareil'                     => ['group' => 'autre', 'fields' => ['puissance', 'energie', 'tension'], 'elec' => true, 'axis' => 'Couleur', 'color' => true],
            ],
        ],
    ],

    /**
     * « Nouveau rayon » Maison : le vendeur crée un rayon hors des 6 répertoriés.
     * Le formulaire s'adapte au SLUG du nom : si connu (R), il suggère des specs,
     * un axe et le mode électrique ; sinon, modèle générique + specs libres +
     * interrupteur « appareil électrique » manuel.
     */
    'autre' => [
        'rayon_suggest' => ['Salle de bain', 'Rangement & organisation', 'Luminaires', 'Petit électroménager', 'Arts de la table', 'Tapis & sols', 'Sécurité & domotique', 'Bricolage & outillage', 'Chambre bébé'],
        'generic_specs' => ['Matière', 'Dimensions', 'Couleur', 'Montage', 'Style', 'Capacité', 'Puissance'],
        'atout_suggest' => ['Fait main / artisanal', 'Wax / pagne', 'Style africain', 'Montage facile', 'Éco-responsable', 'Garantie incluse', 'Connecté', 'Pièce unique'],
        'warn_text'     => 'Produits maison : pour un article électrique, le marquage CE et la notice sont obligatoires à l’import dans l’UE ; la garantie légale de conformité s’applique. Pour le mobilier volumineux, précise les modalités de livraison / montage dans la description.',
        // Remplissage rapide de tailles : un système de tailles (clé) → boutons qui
        // pré-remplissent l'éditeur de déclinaisons. Référencé par 'sizes' dans R.
        'size_systems' => [
            'gm' => [
                ['label' => 'Petit · Moyen · Grand', 'list' => ['Petit', 'Moyen', 'Grand', 'Très grand']],
                ['label' => 'Contenance 5–50 L', 'list' => ['5 L', '10 L', '20 L', '50 L']],
            ],
            'tapis' => [
                ['label' => 'Tapis 120×170 / 160×230', 'list' => ['120×170 cm', '160×230 cm', '200×290 cm']],
                ['label' => 'Petit · Moyen · Grand', 'list' => ['Petit', 'Moyen', 'Grand']],
            ],
        ],
        'R' => [
            'salle-de-bain'          => ['specs' => ['Type (meuble, robinet, miroir…)', 'Matière', 'Dimensions', 'Montage'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
            'rangement-organisation' => ['specs' => ['Type', 'Matière', 'Dimensions', 'Nombre de compartiments'], 'axis' => 'Taille', 'color' => true, 'elec' => false, 'sizes' => 'gm'],
            'luminaires'             => ['specs' => ['Type', 'Type d’ampoule', 'Nombre de lumières', 'Alimentation'], 'axis' => 'Couleur', 'color' => true, 'elec' => true],
            'petit-electromenager'   => ['specs' => ['Type', 'Puissance', 'Capacité', 'Tension'], 'axis' => 'Couleur', 'color' => true, 'elec' => true],
            'arts-de-la-table'       => ['specs' => ['Type', 'Matière', 'Nombre de pièces'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
            'tapis-sols'          => ['specs' => ['Type', 'Matière', 'Dimensions', 'Forme'], 'axis' => 'Taille', 'color' => true, 'elec' => false, 'sizes' => 'tapis'],
            'securite-domotique'  => ['specs' => ['Type', 'Connectivité', 'Alimentation', 'Portée'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
            'bricolage-outillage' => ['specs' => ['Type', 'Puissance / manuel', 'Alimentation', 'Accessoires inclus'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
            'chambre-bebe'           => ['specs' => ['Type', 'Matière', 'Dimensions', 'Norme de sécurité'], 'axis' => 'Couleur', 'color' => true, 'elec' => false],
        ],
    ],
];
