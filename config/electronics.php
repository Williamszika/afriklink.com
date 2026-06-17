<?php

/**
 * Électronique & téléphones — rayon « Accessoires » : formulaire ADAPTATIF au type
 * d'accessoire. Le type (clé de 'types') pilote les champs techniques (clés de
 * 'fields'), l'axe de déclinaison et la pastille couleur. Specs dans products.attributes.
 * Variantes = option × couleur(hex) (axe libre). Libellés en français.
 */
return [
    'shop_categories' => ['electronique'],

    // Champs techniques : clé => ['label' => …, 'opts' => […]].
    'fields' => [
        'type_coque'     => ['label' => 'Type de coque', 'opts' => ['Souple', 'Rigide', 'Antichoc', 'Portefeuille / folio', 'Transparente', 'Avec anneau']],
        'matiere_coque'  => ['label' => 'Matière', 'opts' => ['Silicone', 'TPU souple', 'Plastique rigide', 'Cuir', 'Cuir synthétique', 'Métal', 'Hybride antichoc', 'Bois']],
        'prot_type'      => ['label' => 'Type de protection', 'opts' => ['Verre trempé', 'Film hydrogel', 'Film plastique', 'Verre privacy (anti-espion)']],
        'prot_finition'  => ['label' => 'Finition', 'opts' => ['Transparent', 'Mat / anti-reflet', 'Anti-lumière bleue', 'Privacy']],
        'durete'         => ['label' => 'Dureté', 'opts' => ['9H', '8H', 'Autre']],
        'pack'           => ['label' => 'Nombre par pack', 'opts' => ['1', '2', '3', '5']],
        'chargeur_type'  => ['label' => 'Type de chargeur', 'opts' => ['Secteur USB-A', 'Secteur USB-C', 'Sans fil / induction', 'Voiture', 'Multi-ports']],
        'puissance'      => ['label' => 'Puissance', 'opts' => ['5W', '10W', '15W', '18W', '20W', '30W', '45W', '65W', '100W+']],
        'ports'          => ['label' => 'Nombre de ports', 'opts' => ['1', '2', '3', '4+']],
        'charge_rapide'  => ['label' => 'Charge rapide', 'opts' => ['Oui', 'Non']],
        'connecteur'     => ['label' => 'Connecteur', 'opts' => ['USB-C', 'Lightning', 'Micro-USB', 'USB-A', 'Jack 3.5mm', 'Multi']],
        'cable_long'     => ['label' => 'Longueur', 'opts' => ['0,5 m', '1 m', '1,5 m', '2 m', '3 m']],
        'cable_matiere'  => ['label' => 'Matière', 'opts' => ['PVC', 'Nylon tressé', 'Silicone']],
        'capacite_mah'   => ['label' => 'Capacité', 'opts' => ['5000 mAh', '10000 mAh', '20000 mAh', '30000 mAh+']],
        'ecout_type'     => ['label' => 'Type', 'opts' => ['Intra-auriculaire', 'Supra-auriculaire', 'Circum-auriculaire', 'True Wireless (TWS)', 'Filaire']],
        'connexion'      => ['label' => 'Connexion', 'opts' => ['Filaire', 'Bluetooth', 'Sans fil 2.4G']],
        'bt_version'     => ['label' => 'Version Bluetooth', 'opts' => ['4.2', '5.0', '5.1', '5.2', '5.3']],
        'anc'            => ['label' => 'Réduction de bruit', 'opts' => ['Oui (ANC)', 'Réduction passive', 'Non']],
        'autonomie'      => ['label' => 'Autonomie', 'opts' => ['Jusqu’à 5h', 'Jusqu’à 10h', 'Jusqu’à 20h', 'Jusqu’à 30h', '40h+']],
        'etancheite'     => ['label' => 'Étanchéité', 'opts' => ['Aucune', 'IPX4', 'IPX5', 'IPX7', 'IP67', 'IP68']],
        'montre_compat'  => ['label' => 'Compatible avec', 'opts' => ['Android', 'iOS', 'Android & iOS']],
        'support_type'   => ['label' => 'Type de support', 'opts' => ['Voiture (grille)', 'Voiture (ventouse)', 'Bureau', 'Vélo / moto', 'Trépied', 'Magnétique']],
        'carte_type'     => ['label' => 'Type', 'opts' => ['microSD', 'SD', 'Clé USB', 'Compact Flash']],
        'carte_capacite' => ['label' => 'Capacité', 'opts' => ['16 Go', '32 Go', '64 Go', '128 Go', '256 Go', '512 Go', '1 To']],
        'carte_vitesse'  => ['label' => 'Classe de vitesse', 'opts' => ['Class 10', 'U1', 'U3', 'V30', 'V60']],
        'adapt_type'     => ['label' => 'Type d’adaptateur', 'opts' => ['USB-C vers Jack', 'USB-C vers HDMI', 'OTG', 'Hub multiport', 'Secteur universel']],
    ],

    'conditions' => ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion'],
    'garanties'  => ['3 mois', '6 mois', '1 an', '2 ans'],
    'atouts'     => ['Charge rapide', 'Sans fil', 'Étanche', 'Antichoc', 'Compatible MagSafe', 'Original / authentique', 'Universel', 'Garantie incluse'],
    'axes'       => ['Couleur', 'Capacité', 'Longueur', 'Puissance', 'Modèle', 'Taille', 'Pack'],

    // Groupes d'accessoires (optgroups). '' = sans groupe.
    'groups' => ['protection' => 'Protection', 'energie' => 'Énergie & connectique', 'audio' => 'Audio', 'connecte' => 'Connecté & stockage', 'autre' => 'Autre'],

    // Type d'accessoire : ['group', 'fields' => […], 'compat' => bool, 'axis' => …, 'color' => bool].
    'types' => [
        'Coque / étui'                   => ['group' => 'protection', 'fields' => ['type_coque', 'matiere_coque'], 'compat' => true,  'axis' => 'Couleur',   'color' => true],
        'Protection écran'               => ['group' => 'protection', 'fields' => ['prot_type', 'prot_finition', 'durete', 'pack'], 'compat' => true, 'axis' => 'Modèle', 'color' => false],
        'Support / fixation'             => ['group' => 'protection', 'fields' => ['support_type'], 'compat' => true, 'axis' => 'Modèle', 'color' => false],
        'Chargeur'                       => ['group' => 'energie', 'fields' => ['chargeur_type', 'puissance', 'ports', 'charge_rapide', 'connecteur'], 'compat' => false, 'axis' => 'Puissance', 'color' => false],
        'Câble'                          => ['group' => 'energie', 'fields' => ['connecteur', 'cable_long', 'cable_matiere', 'charge_rapide'], 'compat' => false, 'axis' => 'Longueur', 'color' => true],
        'Batterie externe / Power bank'  => ['group' => 'energie', 'fields' => ['capacite_mah', 'puissance', 'ports', 'charge_rapide'], 'compat' => false, 'axis' => 'Capacité', 'color' => true],
        'Adaptateur'                     => ['group' => 'energie', 'fields' => ['adapt_type', 'connecteur'], 'compat' => true, 'axis' => 'Modèle', 'color' => false],
        'Écouteurs / casque'             => ['group' => 'audio', 'fields' => ['ecout_type', 'connexion', 'bt_version', 'anc', 'autonomie'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
        'Enceinte Bluetooth'             => ['group' => 'audio', 'fields' => ['puissance', 'connexion', 'bt_version', 'autonomie', 'etancheite'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
        'Montre connectée / bracelet'    => ['group' => 'connecte', 'fields' => ['montre_compat', 'autonomie', 'etancheite'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
        'Carte mémoire'                  => ['group' => 'connecte', 'fields' => ['carte_type', 'carte_capacite', 'carte_vitesse'], 'compat' => false, 'axis' => 'Capacité', 'color' => false],
        'Stylet'                         => ['group' => 'connecte', 'fields' => ['connexion'], 'compat' => true, 'axis' => 'Couleur', 'color' => true],
        'Autre accessoire'               => ['group' => 'autre', 'fields' => [], 'compat' => true, 'axis' => 'Modèle', 'color' => false],
    ],
];
