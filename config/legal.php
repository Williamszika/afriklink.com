<?php
declare(strict_types=1);

/**
 * Conformité légale — SOURCE UNIQUE.
 *
 * L'éditeur (« operator ») est établi en Allemagne ; les « régimes » adaptent
 * le contenu légal affiché au pays détecté du visiteur :
 *   DE  → Allemagne (Impressum §5 DDG, AGB, Widerrufsrecht, DSGVO)
 *   EU  → autre pays UE/EEE + UK (RGPD, rétractation 14 j, médiation)
 *   CI  → Côte d'Ivoire (ARTCI, Loi 2013-450 / 2013-546 / 2016-412, RCCM, FCFA)
 *   INTL→ international par défaut (intermédiaire, droit local applicable)
 *
 * Renseigner les coordonnées RÉELLES de l'éditeur via l'environnement (.env /
 * Vercel) avant toute exploitation commerciale, PUIS faire valider l'ensemble
 * par un conseil juridique. Voir lang/*.php pour l'avertissement affiché.
 */

$env = static function (string $key, string $default = ''): string {
    $v = trim((string) ($_ENV[$key] ?? ''));
    return $v !== '' ? $v : $default;
};

return [
    // ------------------------------------------------------------------ //
    // Éditeur du site (à compléter — surchargé par l'environnement)        //
    // ------------------------------------------------------------------ //
    'operator' => [
        'name'           => $env('LEGAL_NAME', 'Afriklink'),
        'legal_form'     => $env('LEGAL_FORM', ''),           // ex. UG (haftungsbeschränkt), GmbH, Auto-entrepreneur
        'representative' => $env('LEGAL_REP', ''),            // Geschäftsführer / directeur de la publication
        'address'        => $env('LEGAL_ADDRESS', ''),        // rue + numéro
        'postal_code'    => $env('LEGAL_POSTAL', ''),
        'city'           => $env('LEGAL_CITY', ''),
        'country_code'   => strtoupper($env('LEGAL_COUNTRY', 'DE')),
        'register'       => $env('LEGAL_REGISTER', ''),       // n° Handelsregister (HRB…) / RCCM
        'register_court' => $env('LEGAL_REGISTER_COURT', ''), // Amtsgericht … / Greffe du tribunal …
        'vat'            => $env('LEGAL_VAT', ''),            // USt-IdNr. / NIF (Compte contribuable)
        'phone'          => $env('LEGAL_PHONE', ''),
        'email'          => $env('LEGAL_EMAIL', '') ?: ($env('MAIL_FROM', '') ?: 'contact@afriklink.com'),
        'dpo_email'      => $env('LEGAL_DPO_EMAIL', ''),      // délégué à la protection des données (si désigné)
    ],

    // Hébergeur
    'host' => [
        'name'    => 'Vercel Inc.',
        'address' => '340 S Lemon Ave #4133, Walnut, CA 91789, États-Unis',
        'url'     => 'vercel.com',
    ],

    // Sous-traitants (RGPD art. 28 — un AVV/DPA doit être signé avec chacun)
    'processors' => [
        ['name' => 'Vercel Inc.',        'role_fr' => 'Hébergement de la plateforme', 'role_en' => 'Platform hosting',            'loc' => 'USA'],
        ['name' => 'Cloudflare, Inc.',   'role_fr' => 'CDN, sécurité, anti-abus',      'role_en' => 'CDN, security, abuse defense', 'loc' => 'USA / UE'],
        ['name' => 'Cloudinary',         'role_fr' => 'Hébergement des médias',        'role_en' => 'Media hosting',               'loc' => 'USA / UE'],
        ['name' => 'Brevo (Sendinblue)', 'role_fr' => 'E-mails transactionnels & marketing', 'role_en' => 'Transactional & marketing email', 'loc' => 'UE (France)'],
        ['name' => 'Stripe',             'role_fr' => 'Paiements (Stripe Connect)',    'role_en' => 'Payments (Stripe Connect)',   'loc' => 'USA / UE'],
    ],

    // Union européenne (EU-27). L'EEE et le Royaume-Uni sont rattachés au
    // régime « EU » (rétractation 14 j + protection des données équivalente).
    'eu_member_states' => ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'],
    'eea_states'       => ['IS', 'LI', 'NO'],

    // ------------------------------------------------------------------ //
    // Régimes juridiques par pays détecté                                  //
    // ------------------------------------------------------------------ //
    'regimes' => [

        'DE' => [
            'flag'           => '🇩🇪',
            'label_fr'       => 'Allemagne',
            'label_en'       => 'Germany',
            'impressum'      => true, // mentions légales au format §5 DDG
            'register_label' => 'Handelsregister',
            'vat_label'      => 'USt-IdNr.',
            'data_law_fr'    => 'le RGPD (UE 2016/679) et la loi fédérale BDSG',
            'data_law_en'    => 'the GDPR (EU 2016/679) and the German BDSG',
            'authority_fr'   => "l'autorité de protection des données du Land d'établissement et, au niveau fédéral, le BfDI",
            'authority_en'   => 'the data-protection authority of the competent Land and, federally, the BfDI',
            'consumer_fr'    => 'le Code civil allemand (BGB §§ 312 et s., 355 et s.) transposant les directives européennes',
            'consumer_en'    => 'the German Civil Code (BGB §§ 312 et seq., 355 et seq.) implementing EU directives',
            'withdrawal'     => true,
            'dispute_fr'     => "La plateforme européenne de RLL est fermée depuis le 20 juillet 2025. En cas de litige non résolu, vous pouvez saisir un organe de règlement extrajudiciaire des litiges de consommation (Verbraucherschlichtungsstelle) ; l'éditeur n'est pas tenu d'y participer mais examinera toute demande de bonne foi.",
            'dispute_en'     => 'The EU ODR platform has been discontinued since 20 July 2025. For unresolved disputes you may turn to a consumer arbitration body (Verbraucherschlichtungsstelle); the publisher is not obliged to take part but will review any request in good faith.',
            'currency_fr'    => 'Euro (EUR)',
            'currency_en'    => 'Euro (EUR)',
        ],

        'EU' => [
            'flag'           => '🇪🇺',
            'label_fr'       => 'Union européenne / EEE',
            'label_en'       => 'European Union / EEA',
            'impressum'      => false,
            'register_label' => 'Registre du commerce',
            'vat_label'      => 'N° TVA intracommunautaire',
            'data_law_fr'    => 'le RGPD (UE 2016/679)',
            'data_law_en'    => 'the GDPR (EU 2016/679)',
            'authority_fr'   => "votre autorité de contrôle nationale (ex. CNIL en France, Garante en Italie, AEPD en Espagne)",
            'authority_en'   => 'your national supervisory authority (e.g. CNIL in France, Garante in Italy, AEPD in Spain)',
            'consumer_fr'    => 'la directive 2011/83/UE relative aux droits des consommateurs, transposée dans votre droit national',
            'consumer_en'    => 'Directive 2011/83/EU on consumer rights, as transposed into your national law',
            'withdrawal'     => true,
            'dispute_fr'     => "La plateforme européenne de RLL est fermée depuis le 20 juillet 2025. Vous pouvez recourir à un dispositif national de médiation de la consommation. Cela ne vous prive pas du droit de saisir les tribunaux.",
            'dispute_en'     => 'The EU ODR platform has been discontinued since 20 July 2025. You may use a national consumer-mediation scheme. This does not affect your right to bring court proceedings.',
            'currency_fr'    => 'Euro (EUR)',
            'currency_en'    => 'Euro (EUR)',
        ],

        'CI' => [
            'flag'           => '🇨🇮',
            'label_fr'       => "Côte d'Ivoire",
            'label_en'       => "Côte d'Ivoire",
            'impressum'      => false,
            'register_label' => 'RCCM',
            'vat_label'      => 'NIF (Compte contribuable)',
            'data_law_fr'    => "la loi n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel",
            'data_law_en'    => 'Law No. 2013-450 of 19 June 2013 on the protection of personal data',
            'authority_fr'   => "l'ARTCI (Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire)",
            'authority_en'   => "the ARTCI (Telecommunications/ICT Regulatory Authority of Côte d'Ivoire)",
            'consumer_fr'    => "la loi n° 2016-412 du 15 juin 2016 relative à la consommation et la loi n° 2013-546 du 30 juillet 2013 sur les transactions électroniques",
            'consumer_en'    => 'Law No. 2016-412 of 15 June 2016 on consumption and Law No. 2013-546 of 30 July 2013 on electronic transactions',
            'withdrawal'     => false, // pas de rétractation UE : retours selon la politique du vendeur et la loi 2016-412
            'dispute_fr'     => "En cas de litige, contactez d'abord le vendeur, puis l'éditeur. Les réclamations relatives aux données peuvent être portées devant l'ARTCI ; les litiges de consommation relèvent des juridictions compétentes (Abidjan).",
            'dispute_en'     => 'In case of dispute, first contact the seller, then the publisher. Data complaints may be brought before the ARTCI; consumer disputes fall under the competent courts (Abidjan).',
            'currency_fr'    => 'Franc CFA (FCFA / XOF) — paiements mobile money (Orange Money, MTN MoMo, Moov Money, Wave) selon le vendeur',
            'currency_en'    => 'CFA franc (FCFA / XOF) — mobile-money payments (Orange Money, MTN MoMo, Moov Money, Wave) per seller',
        ],

        'INTL' => [
            'flag'           => '🌍',
            'label_fr'       => 'International',
            'label_en'       => 'International',
            'impressum'      => false,
            'register_label' => 'Registre du commerce',
            'vat_label'      => 'Identifiant fiscal',
            'data_law_fr'    => 'la loi de protection des données applicable dans votre pays',
            'data_law_en'    => 'the data-protection law applicable in your country',
            'authority_fr'   => "l'autorité de protection des données compétente dans votre pays",
            'authority_en'   => 'the competent data-protection authority in your country',
            'consumer_fr'    => 'le droit de la consommation applicable dans votre pays',
            'consumer_en'    => 'the consumer law applicable in your country',
            'withdrawal'     => false,
            'dispute_fr'     => "En cas de litige, contactez d'abord le vendeur, puis l'éditeur. Le droit applicable et la juridiction compétente sont précisés dans les conditions générales.",
            'dispute_en'     => 'In case of dispute, first contact the seller, then the publisher. Governing law and jurisdiction are set out in the terms.',
            'currency_fr'    => 'Devise affichée selon le vendeur et votre région',
            'currency_en'    => 'Currency shown depends on the seller and your region',
        ],
    ],
];
