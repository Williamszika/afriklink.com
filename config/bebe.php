<?php
declare(strict_types=1);

/**
 * Moteur de rayons ADAPTATIFS du domaine « Bébé & Enfant » (catégorie boutique
 * « bebe »). Même philosophie que config/alimentation.php (le TYPE pilote les
 * caractéristiques, la conservation par défaut, l'axe de déclinaison), mais avec
 * des GARDE-FOUS RÉGLEMENTAIRES propres aux aliments pour nourrissons :
 *   - age_fix    : âge minimum IMPOSÉ par le type (sinon le vendeur le choisit).
 *   - formula    : préparation infantile (lait) → note d'étiquetage réglementaire.
 *   - formula1   : préparation pour NOURRISSONS (0–6 mois) → PROMOTION INTERDITE et
 *                  aucune allégation (Règlement UE 2016/127 / Code OMS).
 *   - complement : complément / probiotique → note « avis d'un professionnel de santé ».
 *
 * Les champs « select » génériques (texture / conditionnement) vivent dans 'fields'
 * et sont rendus par le moteur. CONSERVATION, DLC/DDM + date, ALLERGÈNES (14 UE) et
 * RÉGIME/label sont des contrôles dédiés, affichés selon la liste 'fields' du type
 * (membres : 'conservation', 'allerg', 'regime'). Specs dans products.attributes
 * (JSON) — aucune migration.
 *
 * 'rayons' => libellé (aligné sur config/rayons.php) => [ groups, atouts, fields, types ].
 *   types : nom => [ group?, fields(list), conserv(défaut), axis, age_fix?, formula?, formula1?, complement? ]
 */
return [
    'shop_categories' => ['bebe'],
    'conservations'   => ['Ambiante', 'Frais (réfrigéré)', 'Surgelé'],
    'dlc_types'       => ['DLC (à consommer jusqu’au)', 'DDM (à consommer de préférence avant)'],
    // 14 allergènes à déclaration obligatoire (UE / INCO 1169/2011).
    'allergenes'      => ['Gluten', 'Crustacés', 'Œufs', 'Poisson', 'Arachides', 'Soja', 'Lait', 'Fruits à coque', 'Céleri', 'Moutarde', 'Sésame', 'Sulfites', 'Lupin', 'Mollusques'],
    'regimes'         => ['Bio', 'Sans gluten', 'Sans lactose', 'Hypoallergénique (HA)', 'Sans sucre ajouté', 'Sans sel ajouté', 'Halal', 'Casher'],
    'ages'            => ['Dès 4 mois', 'Dès 6 mois', 'Dès 8 mois', 'Dès 10 mois', 'Dès 12 mois', 'Dès 18 mois', 'Dès 3 ans'],

    'rayons' => [
        'Alimentation' => [
            'groups' => [
                'laits'   => 'Laits infantiles',
                'repas'   => 'Repas & céréales',
                'gouters' => 'Goûters & encas',
                'autres'  => 'Autres',
            ],
            'atouts' => ['Bio', 'Sans sucre ajouté', 'Sans sel ajouté', 'Hypoallergénique', 'Fabriqué en UE', 'Étape de diversification', 'Gourde nomade', 'Recette simple'],
            // Champs « select » génériques rendus par le moteur (les autres membres de
            // 'fields' — conservation / allerg / regime — pilotent des contrôles dédiés).
            'fields' => [
                'texture' => ['label' => 'Texture', 'opts' => ['Liquide', 'Lisse / mixé', 'Petits morceaux', 'Morceaux', 'Solide']],
                'portion' => ['label' => 'Conditionnement', 'opts' => ['Pot', 'Gourde', 'Sachet', 'Boîte', 'Brique', 'Bouteille', 'Lot']],
            ],
            'types' => [
                // Laits infantiles — préparations réglementées.
                'Lait infantile 1er âge (0–6 mois)' => ['group' => 'laits', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => '0–6 mois', 'formula' => true, 'formula1' => true],
                'Lait infantile 2e âge (6–12 mois)' => ['group' => 'laits', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => 'Dès 6 mois', 'formula' => true],
                'Lait de croissance (1–3 ans)'      => ['group' => 'laits', 'fields' => ['portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'age_fix' => 'Dès 12 mois', 'formula' => true],
                // Repas & céréales.
                'Petit pot / repas bébé'            => ['group' => 'repas', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
                'Céréales infantiles / bouillie'    => ['group' => 'repas', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
                // Goûters & encas.
                'Compote / gourde'                  => ['group' => 'gouters', 'fields' => ['texture', 'portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Biscuit / goûter bébé'             => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Snack / encas enfant'              => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                'Boisson enfant (jus, eau)'         => ['group' => 'gouters', 'fields' => ['portion', 'conservation', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Parfum'],
                // Autres.
                'Complément / probiotique bébé'     => ['group' => 'autres', 'fields' => ['portion', 'conservation'], 'conserv' => 'Ambiante', 'axis' => 'Lot', 'complement' => true],
                'Autre aliment bébé/enfant'         => ['group' => 'autres', 'fields' => ['texture', 'portion', 'conservation', 'allerg', 'regime'], 'conserv' => 'Ambiante', 'axis' => 'Recette'],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons : par conditionnement (quel que soit l'axe).
    'size_systems' => [
        'Lot'     => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
        'Recette' => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
        'Parfum'  => [['label' => 'Conditionnements', 'list' => ['Unité', 'Lot de 2', 'Lot de 4', 'Lot de 6']]],
    ],

    /* =================================================================
     * JOUETS — moteur SÉPARÉ (sous la même catégorie boutique « bebe »),
     * piloté par le TYPE. Garde-fous SÉCURITÉ ENFANT : marquage CE + EN71,
     * âge minimum, cohérence âge ↔ petites pièces (jouet < 3 ans = AUCUNE
     * petite pièce détachable, risque d'étouffement — EN71-1), piles bouton.
     * ================================================================= */
    'conditions'      => ['Neuf', 'Comme neuf', 'Occasion'],
    'toy_ages'        => ['0–6 mois', 'Dès 6 mois', 'Dès 10 mois', 'Dès 1 an', 'Dès 18 mois', 'Dès 2 ans', 'Dès 3 ans', 'Dès 5 ans', 'Dès 8 ans', 'Dès 10 ans'],
    // Tranche « moins de 36 mois » : déclenche l'interdiction de petites pièces.
    'toy_ages_under3' => ['0–6 mois', 'Dès 6 mois', 'Dès 10 mois', 'Dès 1 an', 'Dès 18 mois', 'Dès 2 ans'],

    'toys' => [
        'Jouets' => [
            'groups' => [
                'eveil'        => 'Premier âge & éveil',
                'construction' => 'Construction & logique',
                'imagination'  => 'Imagination',
                'electro'      => 'Électronique & créatif',
                'pleinair'     => 'Plein air',
                'autre'        => 'Autre',
            ],
            'atouts' => ['Bois certifié', 'Sans BPA', 'Lavable', 'Éveil / Montessori', 'Écologique', 'Fabriqué en UE', 'Premier âge', 'Occasion testée & complète'],
            'fields' => [
                'matiere'    => ['label' => 'Matière', 'opts' => ['Plastique', 'Bois', 'Peluche / textile', 'Métal', 'Carton', 'Mousse', 'Mixte']],
                'genre'      => ['label' => 'Public', 'opts' => ['Mixte', 'Fille', 'Garçon', 'Bébé']],
                'nb_joueurs' => ['label' => 'Nombre de joueurs', 'opts' => ['1', '1–2', '2–4', '2–6', '2+', '4+']],
                'nb_pieces'  => ['label' => 'Nombre de pièces', 'opts' => ['< 50', '50–100', '100–300', '300–500', '500–1000', '> 1000']],
                'piles'      => ['label' => 'Alimentation', 'opts' => ['Sans pile', 'Piles incluses', 'Piles non incluses', 'Rechargeable USB']],
                'competence' => ['label' => 'Compétence développée', 'opts' => ['Motricité', 'Éveil sensoriel', 'Logique', 'Créativité', 'Langage', 'Social', 'Coordination']],
                'dimension'  => ['label' => 'Taille', 'opts' => ['Petit', 'Moyen', 'Grand']],
            ],
            // types : group, fields(génériques), axis(Modèle/Couleur), color, age_fix(0–3 ans imposé).
            'types' => [
                'Peluche / doudou'                          => ['group' => 'eveil', 'fields' => ['matiere', 'dimension', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Jouet d’éveil (0–3 ans)'                   => ['group' => 'eveil', 'fields' => ['matiere', 'competence'], 'axis' => 'Couleur', 'color' => true, 'age_fix' => true],
                'Hochet / anneau de dentition'              => ['group' => 'eveil', 'fields' => ['matiere'], 'axis' => 'Couleur', 'color' => true, 'age_fix' => true],
                'Jeu de construction'                       => ['group' => 'construction', 'fields' => ['matiere', 'nb_pieces', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Puzzle'                                    => ['group' => 'construction', 'fields' => ['matiere', 'nb_pieces', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Jeu de société / cartes'                   => ['group' => 'construction', 'fields' => ['nb_joueurs', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Poupée / figurine'                         => ['group' => 'imagination', 'fields' => ['matiere', 'genre', 'dimension'], 'axis' => 'Modèle', 'color' => false],
                'Voiture / véhicule jouet'                  => ['group' => 'imagination', 'fields' => ['matiere', 'piles', 'dimension'], 'axis' => 'Couleur', 'color' => true],
                'Jouet d’imitation (dînette, déguisement)'  => ['group' => 'imagination', 'fields' => ['matiere', 'genre'], 'axis' => 'Modèle', 'color' => true],
                'Jouet musical'                             => ['group' => 'electro', 'fields' => ['matiere', 'piles', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Jouet électronique / interactif'           => ['group' => 'electro', 'fields' => ['matiere', 'piles', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Jeu éducatif / créatif'                    => ['group' => 'electro', 'fields' => ['matiere', 'competence'], 'axis' => 'Modèle', 'color' => false],
                'Jeu de plein air (vélo, ballon…)'          => ['group' => 'pleinair', 'fields' => ['matiere', 'dimension'], 'axis' => 'Couleur', 'color' => true],
                'Autre jouet'                               => ['group' => 'autre', 'fields' => ['matiere', 'piles', 'competence'], 'axis' => 'Modèle', 'color' => true],
            ],
        ],
    ],

    // Remplissage rapide des déclinaisons jouet : couleurs (axe Couleur) ; Modèle = libre.
    'toy_size_systems' => [
        'Couleur' => [['label' => 'Couleurs', 'list' => ['Rouge', 'Bleu', 'Jaune', 'Vert', 'Rose', 'Orange', 'Violet', 'Bois', 'Multicolore', 'Blanc', 'Noir', 'Turquoise']]],
        'Modèle'  => [],
    ],

    /* =================================================================
     * PUÉRICULTURE — moteur SÉPARÉ (catégorie boutique « bebe »), piloté
     * par le TYPE. Garde-fous SÉCURITÉ selon le produit : siège-auto
     * (R129/i-Size, groupe, ISOFIX, dos-route + alerte occasion), lit
     * (EN716, espacement barreaux), chaise haute (harnais 5 pts, EN14988),
     * appareils électriques (CE + garantie), biberon (sans BPA). Drapeaux :
     * carseat / bed / chair / elec / bottle.
     * ================================================================= */
    'puer_conditions' => ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion'],

    'puer' => [
        'Puériculture' => [
            'groups' => [
                'deplacement' => 'Déplacement',
                'sommeil'     => 'Sommeil',
                'repas'       => 'Repas',
                'soin'        => 'Soin & éveil',
                'autre'       => 'Autre',
            ],
            'atouts' => ['Conforme CE', 'Sans BPA', 'ISOFIX', 'Dos à la route', 'Pliable / compact', 'Garantie incluse', 'Occasion testée & complète', 'Notice fournie'],
            'fields' => [
                'age'      => ['label' => 'Âge conseillé', 'opts' => ['Naissance', 'Dès la naissance', 'Dès 3 mois', 'Dès 6 mois', 'Dès 9 mois', 'Dès 18 mois', 'Dès 3 ans']],
                'groupe'   => ['label' => 'Groupe / poids', 'opts' => ['Groupe 0+ (0–13 kg)', 'Groupe 0+/1 (0–18 kg)', 'Groupe 1 (9–18 kg)', 'Groupe 2/3 (15–36 kg)', 'i-Size 40–105 cm', 'i-Size 100–150 cm']],
                'norme'    => ['label' => 'Norme', 'opts' => ['R129 (i-Size)', 'R44/04 (ancienne)', 'Non précisé']],
                'isofix'   => ['label' => 'Fixation', 'opts' => ['ISOFIX', 'Ceinture', 'ISOFIX + ceinture']],
                'sens'     => ['label' => 'Sens', 'opts' => ['Dos à la route', 'Face à la route', 'Réversible']],
                'matiere'  => ['label' => 'Matière', 'opts' => ['Plastique', 'Plastique sans BPA', 'Métal', 'Bois', 'Textile', 'Silicone', 'Verre', 'Mixte']],
                'piles'    => ['label' => 'Alimentation', 'opts' => ['Secteur (220–240 V)', 'Piles', 'Rechargeable USB', 'Sans alimentation']],
                'pliable'  => ['label' => 'Pliage / transport', 'opts' => ['Pliable', 'Compact', 'Ombrelle', 'Non pliable']],
                'garantie' => ['label' => 'Garantie', 'opts' => ['Aucune', '6 mois', '1 an', '2 ans']],
                'capacite' => ['label' => 'Contenance', 'opts' => ['90 ml', '150 ml', '240 ml', '260 ml', '330 ml']],
                'couchage' => ['label' => 'Dimensions couchage', 'opts' => ['60×120 cm', '70×140 cm', 'Cododo', 'Berceau', 'Lit de voyage']],
            ],
            // types : group, fields, axis, color, + drapeaux sécurité carseat/bed/chair/elec/bottle.
            'types' => [
                'Siège-auto / cosy'                => ['group' => 'deplacement', 'fields' => ['groupe', 'norme', 'isofix', 'sens', 'matiere'], 'axis' => 'Couleur', 'color' => true, 'carseat' => true],
                'Réhausseur auto'                  => ['group' => 'deplacement', 'fields' => ['groupe', 'norme', 'isofix', 'matiere'], 'axis' => 'Couleur', 'color' => true, 'carseat' => true],
                'Poussette / landau'               => ['group' => 'deplacement', 'fields' => ['age', 'pliable', 'matiere'], 'axis' => 'Couleur', 'color' => true],
                'Porte-bébé / écharpe de portage'  => ['group' => 'deplacement', 'fields' => ['age', 'matiere'], 'axis' => 'Couleur', 'color' => true],
                'Lit bébé / berceau'               => ['group' => 'sommeil', 'fields' => ['age', 'couchage', 'matiere'], 'axis' => 'Couleur', 'color' => false, 'bed' => true],
                'Matelas bébé'                     => ['group' => 'sommeil', 'fields' => ['couchage', 'matiere'], 'axis' => 'Modèle', 'color' => false, 'bed' => true],
                'Parc bébé'                        => ['group' => 'sommeil', 'fields' => ['age', 'pliable', 'matiere'], 'axis' => 'Couleur', 'color' => true],
                'Transat / balancelle'             => ['group' => 'sommeil', 'fields' => ['age', 'piles', 'matiere'], 'axis' => 'Couleur', 'color' => true],
                'Chaise haute'                     => ['group' => 'repas', 'fields' => ['age', 'pliable', 'matiere'], 'axis' => 'Couleur', 'color' => true, 'chair' => true],
                'Biberon / tétine'                 => ['group' => 'repas', 'fields' => ['age', 'capacite', 'matiere'], 'axis' => 'Couleur', 'color' => true, 'bottle' => true],
                'Chauffe-biberon / stérilisateur'  => ['group' => 'repas', 'fields' => ['piles', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Tire-lait'                        => ['group' => 'repas', 'fields' => ['piles', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Table à langer'                   => ['group' => 'soin', 'fields' => ['matiere', 'pliable'], 'axis' => 'Couleur', 'color' => true],
                'Baignoire / accessoires bain'     => ['group' => 'soin', 'fields' => ['age', 'matiere'], 'axis' => 'Couleur', 'color' => true],
                'Babyphone'                        => ['group' => 'soin', 'fields' => ['piles', 'garantie'], 'axis' => 'Modèle', 'color' => false, 'elec' => true],
                'Mouche-bébé / soin'               => ['group' => 'soin', 'fields' => ['piles', 'matiere'], 'axis' => 'Modèle', 'color' => false],
                'Autre puériculture'               => ['group' => 'autre', 'fields' => ['age', 'matiere', 'piles'], 'axis' => 'Couleur', 'color' => true],
            ],
        ],
    ],

    // Valeurs par défaut appliquées au choix d'un type siège-auto (sécurité).
    'puer_carseat_defaults' => ['norme' => 'R129 (i-Size)', 'sens' => 'Dos à la route'],

    'puer_size_systems' => [
        'Couleur' => [['label' => 'Couleurs', 'list' => ['Gris', 'Noir', 'Beige', 'Bleu', 'Rose', 'Vert', 'Rouge', 'Blanc', 'Bois', 'Jaune', 'Turquoise', 'Taupe']]],
        'Modèle'  => [],
    ],
];
