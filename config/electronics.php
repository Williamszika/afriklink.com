<?php

/**
 * Électronique & téléphones — rayons ADAPTATIFS au type de produit. Le rayon choisit
 * la sous-config ('rayons' => libellé => {fields, groups, types, atouts}). Le type
 * (clé de 'types') pilote les champs techniques (clés de 'fields'), l'axe de
 * déclinaison et la pastille couleur. Specs dans products.attributes (JSON) ;
 * variantes = option × couleur(hex) (axe libre). Communs : état, garantie, axes.
 */
return [
    'shop_categories' => ['electronique'],

    'conditions' => ['Neuf', 'Comme neuf', 'Reconditionné', 'Occasion'],
    'garanties'  => ['3 mois', '6 mois', '1 an', '2 ans'],
    'axes'       => ['Couleur', 'Capacité', 'Longueur', 'Puissance', 'Modèle', 'Taille', 'Taille du boîtier', 'Bracelet', 'Pack', 'Configuration', 'Stockage', 'RAM'],

    'rayons' => [
        // =================== Accessoires ===================
        'Accessoires' => [
            'groups' => ['protection' => 'Protection', 'energie' => 'Énergie & connectique', 'audio' => 'Audio', 'connecte' => 'Connecté & stockage', 'autre' => 'Autre'],
            'atouts' => ['Charge rapide', 'Sans fil', 'Étanche', 'Antichoc', 'Compatible MagSafe', 'Original / authentique', 'Universel', 'Garantie incluse'],
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
        ],

        // =================== Audio & écouteurs ===================
        'Audio & écouteurs' => [
            'groups' => ['ecouteurs' => 'Écouteurs', 'casques' => 'Casques', 'enceintes' => 'Enceintes', 'studio' => 'Studio & autre'],
            'atouts' => ['Réduction de bruit (ANC)', 'Sans fil', 'Étanche', 'Micro intégré', 'Charge rapide', 'Pliable', 'Assistant vocal', 'Original / authentique', 'Garantie incluse'],
            'fields' => [
                'connexion'     => ['label' => 'Connexion', 'opts' => ['Filaire', 'Bluetooth', 'Sans fil 2.4G (dongle)']],
                'bt_version'    => ['label' => 'Version Bluetooth', 'opts' => ['4.2', '5.0', '5.1', '5.2', '5.3', '5.4']],
                'anc'           => ['label' => 'Réduction de bruit', 'opts' => ['Active (ANC)', 'ANC + mode transparence', 'Passive', 'Non']],
                'autonomie'     => ['label' => 'Autonomie', 'opts' => ['Jusqu’à 5h', 'Jusqu’à 10h', 'Jusqu’à 20h', 'Jusqu’à 30h', '40h+']],
                'etancheite'    => ['label' => 'Résistance à l’eau', 'opts' => ['Aucune', 'IPX4', 'IPX5', 'IPX7', 'IP67']],
                'connecteur'    => ['label' => 'Connecteur', 'opts' => ['Jack 3.5mm', 'USB-C', 'Lightning', 'USB-A', 'Sans fil']],
                'micro'         => ['label' => 'Micro', 'opts' => ['Intégré', 'Détachable', 'Sans micro']],
                'casque_type'   => ['label' => 'Type de casque', 'opts' => ['Supra-auriculaire', 'Circum-auriculaire (over-ear)', 'Tour de cou']],
                'pliable'       => ['label' => 'Pliable', 'opts' => ['Oui', 'Non']],
                'impedance'     => ['label' => 'Impédance', 'opts' => ['16 Ω', '32 Ω', '64 Ω', '250 Ω', 'Autre']],
                'surround'      => ['label' => 'Son surround', 'opts' => ['Stéréo', 'Virtuel 7.1', 'Réel 7.1']],
                'plateforme'    => ['label' => 'Plateforme', 'opts' => ['PC', 'PS5 / PS4', 'Xbox', 'Nintendo Switch', 'Multi-plateforme']],
                'rgb'           => ['label' => 'Éclairage RGB', 'opts' => ['Oui', 'Non']],
                'charge_boitier' => ['label' => 'Boîtier de charge', 'opts' => ['Charge filaire', 'Charge sans fil', 'Filaire + sans fil']],
                'puissance_audio' => ['label' => 'Puissance', 'opts' => ['5W', '10W', '20W', '40W', '60W', '100W+', '200W+']],
                'canaux'        => ['label' => 'Canaux', 'opts' => ['2.0', '2.1', '3.1', '5.1', '7.1']],
                'caisson'       => ['label' => 'Caisson de basses', 'opts' => ['Intégré', 'Inclus (séparé)', 'Non']],
                'connectique_sb' => ['label' => 'Connectique', 'opts' => ['HDMI ARC', 'Optique', 'Bluetooth', 'AUX / Jack', 'Multi']],
                'dolby'         => ['label' => 'Audio immersif', 'opts' => ['Dolby Atmos', 'Dolby Digital', 'DTS', 'Stéréo']],
                'assistant'     => ['label' => 'Assistant vocal', 'opts' => ['Alexa', 'Google Assistant', 'Siri', 'Aucun']],
                'wifi'          => ['label' => 'Connexion réseau', 'opts' => ['Wi-Fi + Bluetooth', 'Bluetooth seul']],
                'multiroom'     => ['label' => 'Multiroom', 'opts' => ['Oui', 'Non']],
                'mic_type'      => ['label' => 'Type de micro', 'opts' => ['USB', 'XLR', 'Sans fil', 'Cravate (lavalier)', 'Canon (shotgun)']],
                'directivite'   => ['label' => 'Directivité', 'opts' => ['Cardioïde', 'Omnidirectionnel', 'Bidirectionnel', 'Supercardioïde']],
                'usage_mic'     => ['label' => 'Usage', 'opts' => ['Streaming / podcast', 'Studio', 'Réunion / visio', 'Scène']],
            ],
            'types' => [
                'Écouteurs sans fil (TWS)'    => ['group' => 'ecouteurs', 'fields' => ['bt_version', 'anc', 'autonomie', 'etancheite', 'charge_boitier', 'micro'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Écouteurs filaires'          => ['group' => 'ecouteurs', 'fields' => ['connecteur', 'micro', 'impedance'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Casque sans fil (Bluetooth)' => ['group' => 'casques', 'fields' => ['casque_type', 'bt_version', 'anc', 'autonomie', 'pliable', 'micro'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Casque filaire'              => ['group' => 'casques', 'fields' => ['casque_type', 'connecteur', 'impedance', 'micro'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Casque gaming'               => ['group' => 'casques', 'fields' => ['connexion', 'surround', 'plateforme', 'micro', 'rgb'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Enceinte Bluetooth'          => ['group' => 'enceintes', 'fields' => ['puissance_audio', 'bt_version', 'autonomie', 'etancheite'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Enceinte connectée'          => ['group' => 'enceintes', 'fields' => ['assistant', 'puissance_audio', 'wifi', 'multiroom'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Barre de son'                => ['group' => 'enceintes', 'fields' => ['puissance_audio', 'canaux', 'caisson', 'connectique_sb', 'dolby'], 'compat' => false, 'axis' => 'Modèle', 'color' => false],
                'Microphone'                  => ['group' => 'studio', 'fields' => ['mic_type', 'directivite', 'usage_mic', 'connecteur'], 'compat' => false, 'axis' => 'Modèle', 'color' => false],
                'Autre audio'                 => ['group' => 'studio', 'fields' => [], 'compat' => false, 'axis' => 'Couleur', 'color' => false],
            ],
        ],

        // =================== Montres connectées ===================
        'Montres connectées' => [
            'groups' => [], // liste à plat (sans optgroups)
            'atouts' => ['GPS intégré', 'Appels Bluetooth', 'Paiement NFC', 'Étanche', 'Toujours affiché (AOD)', 'Original / authentique', 'Garantie incluse'],
            // Capteurs santé (multi-sélection) affichés quand le type le permet (sensors=true).
            'sensors' => ['Cardio', 'SpO2 (oxygène)', 'Sommeil', 'ECG', 'Tension artérielle', 'Température', 'Stress', 'Cycle menstruel'],
            'fields' => [
                'compat'         => ['label' => 'Compatibilité', 'opts' => ['Android', 'iOS', 'Android & iOS']],
                'systeme'        => ['label' => 'Système', 'opts' => ['Wear OS', 'watchOS', 'HarmonyOS', 'Propriétaire / autre']],
                'forme'          => ['label' => 'Forme', 'opts' => ['Ronde', 'Carrée', 'Rectangulaire']],
                'ecran_type'     => ['label' => 'Type d’écran', 'opts' => ['AMOLED', 'LCD', 'TFT', 'E-ink']],
                'boitier'        => ['label' => 'Taille du boîtier', 'opts' => ['38 mm', '40 mm', '41 mm', '42 mm', '44 mm', '45 mm', '46 mm', '49 mm']],
                'autonomie_j'    => ['label' => 'Autonomie', 'opts' => ['1 jour', '2-3 jours', '5-7 jours', '2 semaines', '1 mois+']],
                'etancheite'     => ['label' => 'Étanchéité', 'opts' => ['Aucune', 'IP67', 'IP68', '3 ATM', '5 ATM', '10 ATM']],
                'gps'            => ['label' => 'GPS', 'opts' => ['Intégré', 'Via téléphone', 'Non']],
                'appels'         => ['label' => 'Appels', 'opts' => ['Bluetooth', 'eSIM / SIM', 'Non']],
                'nfc'            => ['label' => 'Paiement NFC', 'opts' => ['Oui', 'Non']],
                'charge'         => ['label' => 'Charge', 'opts' => ['Magnétique', 'Sans fil (Qi)', 'Câble propriétaire']],
                'boitier_mat'    => ['label' => 'Matière du boîtier', 'opts' => ['Plastique', 'Aluminium', 'Acier inoxydable', 'Titane']],
                'bracelet_mat'   => ['label' => 'Matière du bracelet', 'opts' => ['Silicone', 'Cuir', 'Métal / maille', 'Nylon', 'Plastique']],
                'bracelet_inter' => ['label' => 'Bracelet interchangeable', 'opts' => ['Oui', 'Non']],
                'modes_sport'    => ['label' => 'Modes sportifs', 'opts' => ['Moins de 20', '20 à 50', '50 à 100', '100+']],
                'camera'         => ['label' => 'Appareil photo', 'opts' => ['Oui', 'Non']],
                'sos'            => ['label' => 'Bouton SOS', 'opts' => ['Oui', 'Non']],
            ],
            'types' => [
                'Montre connectée'   => ['fields' => ['compat', 'systeme', 'forme', 'ecran_type', 'boitier', 'autonomie_j', 'etancheite', 'gps', 'appels', 'nfc', 'boitier_mat', 'bracelet_mat', 'bracelet_inter', 'charge'], 'sensors' => true,  'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Bracelet connecté'  => ['fields' => ['compat', 'ecran_type', 'autonomie_j', 'etancheite', 'gps', 'appels', 'bracelet_mat'], 'sensors' => true, 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Montre sport / GPS' => ['fields' => ['compat', 'forme', 'ecran_type', 'boitier', 'autonomie_j', 'etancheite', 'gps', 'modes_sport', 'boitier_mat', 'bracelet_mat', 'charge'], 'sensors' => true, 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Montre enfant'      => ['fields' => ['compat', 'appels', 'gps', 'etancheite', 'camera', 'sos', 'autonomie_j'], 'sensors' => false, 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Montre hybride'     => ['fields' => ['compat', 'autonomie_j', 'etancheite', 'boitier_mat', 'bracelet_mat', 'bracelet_inter'], 'sensors' => true, 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Autre montre'       => ['fields' => ['compat'], 'sensors' => false, 'compat' => false, 'axis' => 'Couleur', 'color' => true],
            ],
        ],

        // =================== Ordinateurs ===================
        'Ordinateurs' => [
            'groups' => [], // liste à plat (sans optgroups)
            'atouts' => ['SSD rapide', 'Reconditionné garanti', 'Léger / ultraportable', 'Écran tactile', 'Clavier rétroéclairé', 'Lecteur d’empreinte', 'Original / authentique', 'Garantie incluse'],
            'fields' => [
                'os'            => ['label' => 'Système d’exploitation', 'opts' => ['Windows 11', 'Windows 10', 'macOS', 'ChromeOS', 'Linux', 'Sans OS / FreeDOS']],
                'cpu'           => ['label' => 'Processeur', 'opts' => ['Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'Intel Celeron / Pentium', 'AMD Ryzen 3', 'AMD Ryzen 5', 'AMD Ryzen 7', 'AMD Ryzen 9', 'Apple M1', 'Apple M2', 'Apple M3', 'Apple M4', 'Autre']],
                'ram'           => ['label' => 'Mémoire RAM', 'opts' => ['4 Go', '8 Go', '12 Go', '16 Go', '32 Go', '64 Go']],
                'stockage_type' => ['label' => 'Type de stockage', 'opts' => ['SSD', 'HDD', 'SSD + HDD', 'eMMC']],
                'stockage_cap'  => ['label' => 'Capacité de stockage', 'opts' => ['64 Go', '128 Go', '256 Go', '512 Go', '1 To', '2 To', '4 To']],
                'gpu'           => ['label' => 'Carte graphique', 'opts' => ['Graphique intégré', 'NVIDIA GeForce RTX', 'NVIDIA GeForce GTX', 'NVIDIA (autre)', 'AMD Radeon', 'Apple GPU intégré', 'Autre dédiée']],
                'ecran_taille'  => ['label' => 'Taille de l’écran', 'opts' => ['11"', '12"', '13"', '14"', '15"', '16"', '17"', '19" et +']],
                'ecran_reso'    => ['label' => 'Résolution', 'opts' => ['HD (1366×768)', 'Full HD (1920×1080)', '2K / QHD', '4K / UHD', 'Retina']],
                'refresh'       => ['label' => 'Taux de rafraîchissement', 'opts' => ['60 Hz', '120 Hz', '144 Hz', '165 Hz', '240 Hz']],
                'tactile'       => ['label' => 'Écran tactile', 'opts' => ['Oui', 'Non']],
                'autonomie_h'   => ['label' => 'Autonomie', 'opts' => ['Jusqu’à 4h', 'Jusqu’à 8h', 'Jusqu’à 12h', '15h+']],
                'clavier'       => ['label' => 'Clavier', 'opts' => ['AZERTY rétroéclairé', 'AZERTY', 'QWERTY', 'Autre']],
                'format_pc'     => ['label' => 'Format', 'opts' => ['Portable', 'Tour / desktop']],
            ],
            'types' => [
                'PC portable'         => ['fields' => ['os', 'cpu', 'ram', 'stockage_type', 'stockage_cap', 'gpu', 'ecran_taille', 'ecran_reso', 'tactile', 'autonomie_h', 'clavier'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'PC de bureau (tour)' => ['fields' => ['os', 'cpu', 'ram', 'stockage_type', 'stockage_cap', 'gpu'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Tout-en-un (AIO)'    => ['fields' => ['os', 'cpu', 'ram', 'stockage_type', 'stockage_cap', 'gpu', 'ecran_taille', 'ecran_reso', 'tactile'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Mini PC'             => ['fields' => ['os', 'cpu', 'ram', 'stockage_type', 'stockage_cap', 'gpu'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'PC gamer'            => ['fields' => ['format_pc', 'os', 'cpu', 'ram', 'stockage_type', 'stockage_cap', 'gpu', 'ecran_taille', 'ecran_reso', 'refresh'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Chromebook'          => ['fields' => ['cpu', 'ram', 'stockage_type', 'stockage_cap', 'ecran_taille', 'ecran_reso', 'tactile', 'autonomie_h'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Autre ordinateur'    => ['fields' => ['os', 'cpu', 'ram', 'stockage_cap'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
            ],
        ],
    ],
];
