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
    'axes'       => ['Couleur', 'Capacité', 'Longueur', 'Puissance', 'Modèle', 'Taille', 'Taille du boîtier', 'Bracelet', 'Pack', 'Configuration', 'Stockage', 'RAM', 'Connectivité', 'Édition'],

    'rayons' => [
        // =================== Téléphones ===================
        'Téléphones' => [
            'groups' => [], // liste à plat (sans optgroups)
            'atouts' => ['Double SIM', '5G', 'Charge rapide', 'Étanche', 'NFC', 'Débloqué tout opérateur', 'Reconditionné garanti', 'Original / authentique', 'Garantie incluse'],
            'fields' => [
                'os'                => ['label' => 'Système', 'opts' => ['Android', 'iOS', 'HarmonyOS', 'KaiOS', 'Autre']],
                'ecran_taille'      => ['label' => 'Taille de l’écran', 'opts' => ['Moins de 5"', '5"', '6,1"', '6,5"', '6,7"', '6,8" et +']],
                'ecran_type'        => ['label' => 'Type d’écran', 'opts' => ['LCD', 'IPS', 'AMOLED', 'OLED', 'Dynamic AMOLED']],
                'refresh'           => ['label' => 'Taux de rafraîchissement', 'opts' => ['60 Hz', '90 Hz', '120 Hz', '144 Hz']],
                'cpu'               => ['label' => 'Processeur', 'opts' => ['Qualcomm Snapdragon', 'MediaTek Dimensity / Helio', 'Apple A', 'Samsung Exynos', 'Kirin', 'Unisoc', 'Autre']],
                'ram'               => ['label' => 'Mémoire RAM', 'opts' => ['2 Go', '3 Go', '4 Go', '6 Go', '8 Go', '12 Go', '16 Go']],
                'stockage_cap'      => ['label' => 'Stockage', 'opts' => ['16 Go', '32 Go', '64 Go', '128 Go', '256 Go', '512 Go', '1 To']],
                'extensible'        => ['label' => 'Stockage extensible (microSD)', 'opts' => ['Oui', 'Non']],
                'batterie'          => ['label' => 'Batterie', 'opts' => ['Moins de 3000 mAh', '3000-4000 mAh', '4000-5000 mAh', '5000-6000 mAh', '6000 mAh et +']],
                'charge_rapide'     => ['label' => 'Charge rapide', 'opts' => ['Non', '18W', '25W', '33W', '45W', '67W', '100W+', 'Charge sans fil']],
                'camera_mp'         => ['label' => 'Appareil photo principal', 'opts' => ['Moins de 13 MP', '13 MP', '48 MP', '50 MP', '64 MP', '108 MP', '200 MP']],
                'camera_nb'         => ['label' => 'Caméras arrière', 'opts' => ['1 (simple)', '2 (double)', '3 (triple)', '4 (quad)']],
                'sim'               => ['label' => 'SIM', 'opts' => ['Simple SIM', 'Double SIM', 'eSIM', 'Double SIM + eSIM']],
                'reseau'            => ['label' => 'Réseau', 'opts' => ['2G', '3G', '4G / LTE', '5G']],
                'nfc'               => ['label' => 'NFC', 'opts' => ['Oui', 'Non']],
                'biometrie'         => ['label' => 'Déverrouillage', 'opts' => ['Empreinte', 'Reconnaissance faciale', 'Empreinte + visage', 'Code uniquement']],
                'etancheite'        => ['label' => 'Étanchéité', 'opts' => ['Aucune', 'IP53', 'IP67', 'IP68', 'IP69']],
                'norme_mil'         => ['label' => 'Norme militaire', 'opts' => ['MIL-STD-810G', 'MIL-STD-810H', 'Non']],
                'clavier_phys'      => ['label' => 'Clavier physique', 'opts' => ['Oui (touches)', 'Non']],
                'radio_fm'          => ['label' => 'Radio FM', 'opts' => ['Oui', 'Non']],
                'torche'            => ['label' => 'Lampe torche', 'opts' => ['Oui', 'Non']],
                'bouton_sos'        => ['label' => 'Bouton SOS', 'opts' => ['Oui', 'Non']],
                'grandes_touches'   => ['label' => 'Grandes touches', 'opts' => ['Oui', 'Non']],
                'base_charge'       => ['label' => 'Base de chargement', 'opts' => ['Incluse', 'Non']],
                'appareil_auditif'  => ['label' => 'Compatible appareils auditifs', 'opts' => ['Oui', 'Non']],
            ],
            'types' => [
                'Smartphone'                    => ['fields' => ['os', 'ecran_taille', 'ecran_type', 'refresh', 'cpu', 'ram', 'stockage_cap', 'extensible', 'batterie', 'charge_rapide', 'camera_mp', 'camera_nb', 'sim', 'reseau', 'nfc', 'biometrie'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Téléphone à touches'           => ['fields' => ['reseau', 'sim', 'batterie', 'ecran_taille', 'clavier_phys', 'radio_fm', 'torche'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Téléphone senior'              => ['fields' => ['reseau', 'sim', 'grandes_touches', 'bouton_sos', 'base_charge', 'appareil_auditif', 'torche'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Smartphone rugged / antichoc'  => ['fields' => ['os', 'ecran_taille', 'cpu', 'ram', 'stockage_cap', 'batterie', 'charge_rapide', 'camera_mp', 'sim', 'reseau', 'etancheite', 'norme_mil'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Autre téléphone'               => ['fields' => ['os', 'reseau', 'ram', 'stockage_cap'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
            ],
        ],

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

        // =================== Tablettes ===================
        'Tablettes' => [
            'groups' => [], // liste à plat (sans optgroups)
            'atouts' => ['Stylet inclus', 'Clavier inclus', '4G / 5G', 'Léger', 'Reconditionné garanti', 'Contrôle parental', 'Original / authentique', 'Garantie incluse'],
            'fields' => [
                'os'                => ['label' => 'Système', 'opts' => ['Android', 'iPadOS', 'Windows', 'HarmonyOS', 'Fire OS', 'Autre']],
                'ecran_taille'      => ['label' => 'Taille de l’écran', 'opts' => ['6"', '7"', '8"', '9"', '10"', '11"', '12,9"', '13" et +']],
                'ecran_type'        => ['label' => 'Type d’écran', 'opts' => ['LCD', 'IPS', 'AMOLED', 'E-ink', 'Retina']],
                'ecran_reso'        => ['label' => 'Résolution', 'opts' => ['HD', 'Full HD', '2K / QHD', '4K / UHD', 'Retina']],
                'cpu'               => ['label' => 'Puce / processeur', 'opts' => ['Apple A / M', 'Qualcomm Snapdragon', 'MediaTek', 'Samsung Exynos', 'Intel', 'Autre']],
                'ram'               => ['label' => 'Mémoire RAM', 'opts' => ['2 Go', '3 Go', '4 Go', '6 Go', '8 Go', '12 Go']],
                'stockage_cap'      => ['label' => 'Stockage', 'opts' => ['16 Go', '32 Go', '64 Go', '128 Go', '256 Go', '512 Go', '1 To']],
                'extensible'        => ['label' => 'Stockage extensible (microSD)', 'opts' => ['Oui', 'Non']],
                'connectivite'      => ['label' => 'Connectivité', 'opts' => ['Wi-Fi', 'Wi-Fi + 4G/LTE', 'Wi-Fi + 5G']],
                'autonomie_h'       => ['label' => 'Autonomie', 'opts' => ['Jusqu’à 6h', 'Jusqu’à 10h', 'Jusqu’à 15h', 'Plusieurs jours', 'Plusieurs semaines']],
                'stylet'            => ['label' => 'Stylet', 'opts' => ['Inclus', 'Compatible (non fourni)', 'Non']],
                'clavier'           => ['label' => 'Clavier', 'opts' => ['Inclus', 'Compatible (non fourni)', 'Non']],
                'camera'            => ['label' => 'Appareil photo', 'opts' => ['Oui', 'Non']],
                'controle_parental' => ['label' => 'Contrôle parental', 'opts' => ['Oui', 'Non']],
                'etui'              => ['label' => 'Étui / coque renforcée', 'opts' => ['Inclus', 'Non']],
                'eclairage'         => ['label' => 'Éclairage frontal', 'opts' => ['Oui (réglable)', 'Oui', 'Non']],
                'etancheite'        => ['label' => 'Étanchéité', 'opts' => ['Aucune', 'IPX7', 'IPX8']],
                'formats'           => ['label' => 'Formats supportés', 'opts' => ['EPUB / PDF', 'Kindle (AZW)', 'Multi-formats']],
                'surface'           => ['label' => 'Surface active', 'opts' => ['4 × 3"', '6 × 4"', '8 × 5"', '10 × 6"', 'Plus grand']],
                'pression'          => ['label' => 'Niveaux de pression', 'opts' => ['2048', '4096', '8192']],
                'ecran_integre'     => ['label' => 'Écran intégré', 'opts' => ['Oui', 'Non']],
                'connexion'         => ['label' => 'Connexion', 'opts' => ['USB', 'USB-C', 'Bluetooth / sans fil']],
            ],
            'types' => [
                'Tablette tactile'               => ['fields' => ['os', 'ecran_taille', 'ecran_type', 'ecran_reso', 'cpu', 'ram', 'stockage_cap', 'extensible', 'connectivite', 'autonomie_h', 'stylet'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Tablette 2-en-1 / avec clavier' => ['fields' => ['os', 'ecran_taille', 'ecran_type', 'ecran_reso', 'cpu', 'ram', 'stockage_cap', 'connectivite', 'clavier', 'stylet'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
                'Liseuse (e-reader)'             => ['fields' => ['ecran_taille', 'eclairage', 'stockage_cap', 'etancheite', 'formats', 'autonomie_h'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Tablette enfant'                => ['fields' => ['os', 'ecran_taille', 'ram', 'stockage_cap', 'controle_parental', 'etui', 'camera', 'autonomie_h'], 'compat' => false, 'axis' => 'Couleur', 'color' => true],
                'Tablette graphique'             => ['fields' => ['surface', 'pression', 'ecran_integre', 'connexion', 'stylet'], 'compat' => false, 'axis' => 'Modèle', 'color' => false],
                'Autre tablette'                 => ['fields' => ['os', 'ecran_taille', 'ram', 'stockage_cap'], 'compat' => false, 'axis' => 'Configuration', 'color' => false],
            ],
        ],
    ],

    // « Autre / nouveau rayon » — le vendeur crée son rayon électronique. Le formulaire s'adapte
    // à l'identifiant (slug) du rayon tapé (config 'R') : specs suggérées, axe, pastille couleur.
    // Rayon libre/inconnu => mode générique. Specs libres (libellé→valeur) dans attributes(JSON).
    'autre' => [
        'rayon_suggest' => ['TV & vidéo', 'Consoles & jeux vidéo', 'Photo & caméras', 'Objets connectés / domotique', 'Drones', 'Stockage & disques', 'Réseau & Wi-Fi', 'Imprimantes & scanners', 'Composants PC', 'Énergie & solaire'],
        'generic_specs' => ['Compatibilité', 'Puissance', 'Capacité', 'Connectique', 'Dimensions', 'Couleur', 'Norme / certification', 'Alimentation'],
        'atout_suggest' => ['Sans fil', 'Étanche', 'Charge rapide', 'Reconditionné garanti', 'Original / authentique', 'Compatible universel', 'Garantie incluse'],
        'warn_text'     => 'Produits électroniques : marquage CE et conformité DEEE (déchets électroniques) requis pour la vente dans l’UE ; la garantie légale de conformité s’applique.',
        // Config par slug de rayon : specs suggérées, axe de déclinaison, pastille couleur.
        'R' => [
            'tv-video'                    => ['specs' => ['Taille (pouces)', 'Résolution', 'Type de dalle', 'Smart TV', 'Connectique', 'Fréquence (Hz)'], 'axis' => 'Taille', 'color' => false],
            'consoles-jeux-video'         => ['specs' => ['Plateforme', 'Stockage', 'Édition / pack', 'Manettes incluses', 'État du jeu'], 'axis' => 'Édition', 'color' => false],
            'photo-cameras'               => ['specs' => ['Type', 'Résolution (MP)', 'Zoom', 'Stabilisation', 'Étanchéité', 'Écran'], 'axis' => 'Couleur', 'color' => true],
            'objets-connectes-domotique'  => ['specs' => ['Type d’objet', 'Protocole (Wi-Fi/Zigbee)', 'Compatibilité (Alexa/Google)', 'Alimentation'], 'axis' => 'Modèle', 'color' => false],
            'drones'                      => ['specs' => ['Autonomie de vol', 'Portée', 'Caméra', 'Poids', 'Stabilisation', 'Nombre de batteries'], 'axis' => 'Pack', 'color' => false],
            'stockage-disques'            => ['specs' => ['Type', 'Capacité', 'Interface', 'Vitesse de lecture'], 'axis' => 'Capacité', 'color' => false],
            'reseau-wi-fi'                => ['specs' => ['Type', 'Norme Wi-Fi', 'Débit', 'Nombre de ports', 'Bandes'], 'axis' => 'Modèle', 'color' => false],
            'imprimantes-scanners'        => ['specs' => ['Technologie', 'Couleur / Monochrome', 'Fonctions', 'Connectivité', 'Vitesse (ppm)'], 'axis' => 'Modèle', 'color' => false],
            'composants-pc'               => ['specs' => ['Type de composant', 'Socket / format', 'Capacité / fréquence', 'Connectique'], 'axis' => 'Modèle', 'color' => false],
            'energie-solaire'             => ['specs' => ['Type', 'Puissance (W)', 'Capacité (Wh/mAh)', 'Entrées / sorties'], 'axis' => 'Puissance', 'color' => false],
        ],
    ],
];
