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
            'label_de'       => 'Deutschland',
            'label_es'       => 'Alemania',
            'label_it'       => 'Germania',
            'label_pt'       => 'Alemanha',
            'label_nl'       => 'Duitsland',
            'label_ar'       => 'ألمانيا',
            'impressum'      => true, // mentions légales au format §5 DDG
            'register_label' => 'Handelsregister',
            'vat_label'      => 'USt-IdNr.',
            'data_law_fr'    => 'le RGPD (UE 2016/679) et la loi fédérale BDSG',
            'data_law_en'    => 'the GDPR (EU 2016/679) and the German BDSG',
            'data_law_de'    => 'die DSGVO (EU 2016/679) und das deutsche BDSG',
            'data_law_es'    => 'el RGPD (UE 2016/679) y la ley federal alemana BDSG',
            'data_law_it'    => 'il GDPR (UE 2016/679) e la legge federale tedesca BDSG',
            'data_law_pt'    => 'o RGPD (UE 2016/679) e a lei federal alemã BDSG',
            'data_law_nl'    => 'de AVG (EU 2016/679) en de Duitse federale wet BDSG',
            'data_law_ar'    => 'الـ GDPR (EU 2016/679) والقانون الاتحادي الألماني BDSG',
            'authority_fr'   => "l'autorité de protection des données du Land d'établissement et, au niveau fédéral, le BfDI",
            'authority_en'   => 'the data-protection authority of the competent Land and, federally, the BfDI',
            'authority_de'   => 'die Datenschutzbehörde des zuständigen Landes und, auf Bundesebene, der BfDI',
            'authority_es'   => 'la autoridad de protección de datos del Land competente y, a nivel federal, el BfDI',
            'authority_it'   => "l'autorità di protezione dei dati del Land competente e, a livello federale, il BfDI",
            'authority_pt'   => 'a autoridade de proteção de dados do Land competente e, a nível federal, o BfDI',
            'authority_nl'   => 'de gegevensbeschermingsautoriteit van de bevoegde deelstaat en, op federaal niveau, de BfDI',
            'authority_ar'   => 'سلطة حماية البيانات في الولاية المختصة، وعلى المستوى الاتحادي الـ BfDI',
            'consumer_fr'    => 'le Code civil allemand (BGB §§ 312 et s., 355 et s.) transposant les directives européennes',
            'consumer_en'    => 'the German Civil Code (BGB §§ 312 et seq., 355 et seq.) implementing EU directives',
            'consumer_de'    => 'das deutsche Bürgerliche Gesetzbuch (BGB §§ 312 ff., 355 ff.) zur Umsetzung der EU-Richtlinien',
            'consumer_es'    => 'el Código Civil alemán (BGB §§ 312 y ss., 355 y ss.) que transpone las directivas de la UE',
            'consumer_it'    => 'il Codice civile tedesco (BGB §§ 312 e segg., 355 e segg.) che recepisce le direttive UE',
            'consumer_pt'    => 'o Código Civil alemão (BGB §§ 312 e segs., 355 e segs.) que transpõe as diretivas da UE',
            'consumer_nl'    => 'het Duitse Burgerlijk Wetboek (BGB §§ 312 e.v., 355 e.v.) ter omzetting van de EU-richtlijnen',
            'consumer_ar'    => 'القانون المدني الألماني (BGB §§ 312 وما يليها، 355 وما يليها) المنفِّذ لتوجيهات الاتحاد الأوروبي',
            'withdrawal'     => true,
            'dispute_fr'     => "La plateforme européenne de RLL est fermée depuis le 20 juillet 2025. En cas de litige non résolu, vous pouvez saisir un organe de règlement extrajudiciaire des litiges de consommation (Verbraucherschlichtungsstelle) ; l'éditeur n'est pas tenu d'y participer mais examinera toute demande de bonne foi.",
            'dispute_en'     => 'The EU ODR platform has been discontinued since 20 July 2025. For unresolved disputes you may turn to a consumer arbitration body (Verbraucherschlichtungsstelle); the publisher is not obliged to take part but will review any request in good faith.',
            'dispute_de'     => 'Die EU-OS-Plattform ist seit dem 20. Juli 2025 eingestellt. Bei ungelösten Streitigkeiten können Sie sich an eine Verbraucherschlichtungsstelle wenden; der Anbieter ist zur Teilnahme nicht verpflichtet, wird jede Anfrage jedoch nach Treu und Glauben prüfen.',
            'dispute_es'     => 'La plataforma europea de resolución de litigios en línea (RLL) está cerrada desde el 20 de julio de 2025. En caso de litigio no resuelto, puede dirigirse a un organismo de arbitraje de consumo (Verbraucherschlichtungsstelle); el editor no está obligado a participar, pero examinará cualquier solicitud de buena fe.',
            'dispute_it'     => "La piattaforma europea di risoluzione delle controversie online (ODR) è chiusa dal 20 luglio 2025. In caso di controversia non risolta, è possibile rivolgersi a un organismo di conciliazione dei consumatori (Verbraucherschlichtungsstelle); l'editore non è obbligato a parteciparvi, ma esaminerà ogni richiesta in buona fede.",
            'dispute_pt'     => 'A plataforma europeia de resolução de litígios em linha (RLL) está encerrada desde 20 de julho de 2025. Em caso de litígio não resolvido, pode recorrer a um organismo de arbitragem de consumo (Verbraucherschlichtungsstelle); o editor não é obrigado a participar, mas analisará qualquer pedido de boa-fé.',
            'dispute_nl'     => 'Het Europese ODR-platform is sinds 20 juli 2025 stopgezet. Bij een onopgelost geschil kunt u zich wenden tot een geschillencommissie voor consumenten (Verbraucherschlichtungsstelle); de aanbieder is niet verplicht hieraan deel te nemen, maar zal elk verzoek te goeder trouw onderzoeken.',
            'dispute_ar'     => 'توقفت منصة تسوية المنازعات عبر الإنترنت (ODR) التابعة للاتحاد الأوروبي منذ 20 July 2025. في حال وجود نزاع لم يُحَلّ، يمكنك اللجوء إلى هيئة للتحكيم الاستهلاكي (Verbraucherschlichtungsstelle)؛ الناشر غير ملزم بالمشاركة لكنه سيدرس أي طلب بحسن نية.',
            'currency_fr'    => 'Euro (EUR)',
            'currency_en'    => 'Euro (EUR)',
            'currency_de'    => 'Euro (EUR)',
            'currency_es'    => 'Euro (EUR)',
            'currency_it'    => 'Euro (EUR)',
            'currency_pt'    => 'Euro (EUR)',
            'currency_nl'    => 'Euro (EUR)',
            'currency_ar'    => 'يورو (EUR)',
        ],

        'EU' => [
            'flag'           => '🇪🇺',
            'label_fr'       => 'Union européenne / EEE',
            'label_en'       => 'European Union / EEA',
            'label_de'       => 'Europäische Union / EWR',
            'label_es'       => 'Unión Europea / EEE',
            'label_it'       => 'Unione europea / SEE',
            'label_pt'       => 'União Europeia / EEE',
            'label_nl'       => 'Europese Unie / EER',
            'label_ar'       => 'الاتحاد الأوروبي / المنطقة الاقتصادية الأوروبية',
            'impressum'      => false,
            'register_label' => 'Registre du commerce',
            'vat_label'      => 'N° TVA intracommunautaire',
            'data_law_fr'    => 'le RGPD (UE 2016/679)',
            'data_law_en'    => 'the GDPR (EU 2016/679)',
            'data_law_de'    => 'die DSGVO (EU 2016/679)',
            'data_law_es'    => 'el RGPD (UE 2016/679)',
            'data_law_it'    => 'il GDPR (UE 2016/679)',
            'data_law_pt'    => 'o RGPD (UE 2016/679)',
            'data_law_nl'    => 'de AVG (EU 2016/679)',
            'data_law_ar'    => 'الـ GDPR (EU 2016/679)',
            'authority_fr'   => "votre autorité de contrôle nationale (ex. CNIL en France, Garante en Italie, AEPD en Espagne)",
            'authority_en'   => 'your national supervisory authority (e.g. CNIL in France, Garante in Italy, AEPD in Spain)',
            'authority_de'   => 'Ihre nationale Aufsichtsbehörde (z. B. CNIL in Frankreich, Garante in Italien, AEPD in Spanien)',
            'authority_es'   => 'su autoridad de control nacional (p. ej. CNIL en Francia, Garante en Italia, AEPD en España)',
            'authority_it'   => "la vostra autorità di controllo nazionale (ad es. CNIL in Francia, Garante in Italia, AEPD in Spagna)",
            'authority_pt'   => 'a sua autoridade de controlo nacional (p. ex. CNIL em França, Garante em Itália, AEPD em Espanha)',
            'authority_nl'   => 'uw nationale toezichthoudende autoriteit (bijv. CNIL in Frankrijk, Garante in Italië, AEPD in Spanje)',
            'authority_ar'   => 'سلطة الرقابة الوطنية في بلدك (مثل CNIL في فرنسا، وGarante في إيطاليا، وAEPD في إسبانيا)',
            'consumer_fr'    => 'la directive 2011/83/UE relative aux droits des consommateurs, transposée dans votre droit national',
            'consumer_en'    => 'Directive 2011/83/EU on consumer rights, as transposed into your national law',
            'consumer_de'    => 'die Richtlinie 2011/83/EU über die Rechte der Verbraucher, umgesetzt in Ihr nationales Recht',
            'consumer_es'    => 'la Directiva 2011/83/UE sobre los derechos de los consumidores, transpuesta a su Derecho nacional',
            'consumer_it'    => 'la direttiva 2011/83/UE sui diritti dei consumatori, recepita nel vostro diritto nazionale',
            'consumer_pt'    => 'a Diretiva 2011/83/UE relativa aos direitos dos consumidores, transposta para o seu direito nacional',
            'consumer_nl'    => 'Richtlijn 2011/83/EU betreffende consumentenrechten, zoals omgezet in uw nationale recht',
            'consumer_ar'    => 'التوجيه 2011/83/EU المتعلق بحقوق المستهلكين، بصيغته المنقولة إلى قانونك الوطني',
            'withdrawal'     => true,
            'dispute_fr'     => "La plateforme européenne de RLL est fermée depuis le 20 juillet 2025. Vous pouvez recourir à un dispositif national de médiation de la consommation. Cela ne vous prive pas du droit de saisir les tribunaux.",
            'dispute_en'     => 'The EU ODR platform has been discontinued since 20 July 2025. You may use a national consumer-mediation scheme. This does not affect your right to bring court proceedings.',
            'dispute_de'     => 'Die EU-OS-Plattform ist seit dem 20. Juli 2025 eingestellt. Sie können ein nationales Verbraucherschlichtungsverfahren in Anspruch nehmen. Ihr Recht, die Gerichte anzurufen, bleibt davon unberührt.',
            'dispute_es'     => 'La plataforma europea de resolución de litigios en línea (RLL) está cerrada desde el 20 de julio de 2025. Puede recurrir a un mecanismo nacional de mediación de consumo. Esto no le priva del derecho a acudir a los tribunales.',
            'dispute_it'     => 'La piattaforma europea di risoluzione delle controversie online (ODR) è chiusa dal 20 luglio 2025. Potete ricorrere a un meccanismo nazionale di mediazione dei consumatori. Ciò non pregiudica il vostro diritto di adire le vie legali.',
            'dispute_pt'     => 'A plataforma europeia de resolução de litígios em linha (RLL) está encerrada desde 20 de julho de 2025. Pode recorrer a um mecanismo nacional de mediação de consumo. Tal não o priva do direito de recorrer aos tribunais.',
            'dispute_nl'     => 'Het Europese ODR-platform is sinds 20 juli 2025 stopgezet. U kunt gebruikmaken van een nationale regeling voor consumentenbemiddeling. Dit doet geen afbreuk aan uw recht om een gerechtelijke procedure aan te spannen.',
            'dispute_ar'     => 'توقفت منصة تسوية المنازعات عبر الإنترنت (ODR) التابعة للاتحاد الأوروبي منذ 20 July 2025. يمكنك اللجوء إلى آلية وطنية للوساطة الاستهلاكية. ولا يحرمك ذلك من الحق في اللجوء إلى المحاكم.',
            'currency_fr'    => 'Euro (EUR)',
            'currency_en'    => 'Euro (EUR)',
            'currency_de'    => 'Euro (EUR)',
            'currency_es'    => 'Euro (EUR)',
            'currency_it'    => 'Euro (EUR)',
            'currency_pt'    => 'Euro (EUR)',
            'currency_nl'    => 'Euro (EUR)',
            'currency_ar'    => 'يورو (EUR)',
        ],

        'CI' => [
            'flag'           => '🇨🇮',
            'label_fr'       => "Côte d'Ivoire",
            'label_en'       => "Côte d'Ivoire",
            'label_de'       => "Côte d'Ivoire",
            'label_es'       => "Côte d'Ivoire",
            'label_it'       => "Côte d'Ivoire",
            'label_pt'       => "Côte d'Ivoire",
            'label_nl'       => "Côte d'Ivoire",
            'label_ar'       => "Côte d'Ivoire",
            'impressum'      => false,
            'register_label' => 'RCCM',
            'vat_label'      => 'NIF (Compte contribuable)',
            'data_law_fr'    => "la loi n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel",
            'data_law_en'    => 'Law No. 2013-450 of 19 June 2013 on the protection of personal data',
            'data_law_de'    => 'das Gesetz Nr. 2013-450 vom 19. Juni 2013 über den Schutz personenbezogener Daten',
            'data_law_es'    => 'la Ley n.º 2013-450, de 19 de junio de 2013, relativa a la protección de los datos de carácter personal',
            'data_law_it'    => 'la legge n. 2013-450 del 19 giugno 2013 relativa alla protezione dei dati personali',
            'data_law_pt'    => 'a Lei n.º 2013-450, de 19 de junho de 2013, relativa à proteção dos dados pessoais',
            'data_law_nl'    => 'wet nr. 2013-450 van 19 juni 2013 betreffende de bescherming van persoonsgegevens',
            'data_law_ar'    => 'القانون رقم 2013-450 الصادر في 19 June 2013 المتعلق بحماية البيانات ذات الطابع الشخصي',
            'authority_fr'   => "l'ARTCI (Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire)",
            'authority_en'   => "the ARTCI (Telecommunications/ICT Regulatory Authority of Côte d'Ivoire)",
            'authority_de'   => "die ARTCI (Regulierungsbehörde für Telekommunikation/IKT der Côte d'Ivoire)",
            'authority_es'   => "la ARTCI (Autoridad de Regulación de las Telecomunicaciones/TIC de Côte d'Ivoire)",
            'authority_it'   => "l'ARTCI (Autorità di regolamentazione delle telecomunicazioni/TIC della Côte d'Ivoire)",
            'authority_pt'   => "a ARTCI (Autoridade de Regulação das Telecomunicações/TIC da Côte d'Ivoire)",
            'authority_nl'   => "de ARTCI (regelgevende autoriteit voor telecommunicatie/ICT van Côte d'Ivoire)",
            'authority_ar'   => "الـ ARTCI (هيئة تنظيم الاتصالات/تكنولوجيا المعلومات والاتصالات في Côte d'Ivoire)",
            'consumer_fr'    => "la loi n° 2016-412 du 15 juin 2016 relative à la consommation et la loi n° 2013-546 du 30 juillet 2013 sur les transactions électroniques",
            'consumer_en'    => 'Law No. 2016-412 of 15 June 2016 on consumption and Law No. 2013-546 of 30 July 2013 on electronic transactions',
            'consumer_de'    => 'das Gesetz Nr. 2016-412 vom 15. Juni 2016 über den Verbraucherschutz und das Gesetz Nr. 2013-546 vom 30. Juli 2013 über elektronische Transaktionen',
            'consumer_es'    => 'la Ley n.º 2016-412, de 15 de junio de 2016, relativa al consumo y la Ley n.º 2013-546, de 30 de julio de 2013, sobre las transacciones electrónicas',
            'consumer_it'    => 'la legge n. 2016-412 del 15 giugno 2016 relativa al consumo e la legge n. 2013-546 del 30 luglio 2013 sulle transazioni elettroniche',
            'consumer_pt'    => 'a Lei n.º 2016-412, de 15 de junho de 2016, relativa ao consumo e a Lei n.º 2013-546, de 30 de julho de 2013, sobre as transações eletrónicas',
            'consumer_nl'    => 'wet nr. 2016-412 van 15 juni 2016 betreffende consumptie en wet nr. 2013-546 van 30 juli 2013 betreffende elektronische transacties',
            'consumer_ar'    => 'القانون رقم 2016-412 الصادر في 15 June 2016 المتعلق بالاستهلاك والقانون رقم 2013-546 الصادر في 30 July 2013 المتعلق بالمعاملات الإلكترونية',
            'withdrawal'     => false, // pas de rétractation UE : retours selon la politique du vendeur et la loi 2016-412
            'dispute_fr'     => "En cas de litige, contactez d'abord le vendeur, puis l'éditeur. Les réclamations relatives aux données peuvent être portées devant l'ARTCI ; les litiges de consommation relèvent des juridictions compétentes (Abidjan).",
            'dispute_en'     => 'In case of dispute, first contact the seller, then the publisher. Data complaints may be brought before the ARTCI; consumer disputes fall under the competent courts (Abidjan).',
            'dispute_de'     => 'Im Streitfall wenden Sie sich zunächst an den Verkäufer und anschließend an den Anbieter. Datenschutzbeschwerden können bei der ARTCI eingereicht werden; Verbraucherstreitigkeiten fallen in die Zuständigkeit der zuständigen Gerichte (Abidjan).',
            'dispute_es'     => 'En caso de litigio, contacte primero con el vendedor y, después, con el editor. Las reclamaciones relativas a los datos pueden presentarse ante la ARTCI; los litigios de consumo son competencia de los tribunales competentes (Abiyán).',
            'dispute_it'     => "In caso di controversia, contattate prima il venditore e poi l'editore. I reclami relativi ai dati possono essere presentati all'ARTCI; le controversie in materia di consumo rientrano nella competenza dei tribunali competenti (Abidjan).",
            'dispute_pt'     => 'Em caso de litígio, contacte primeiro o vendedor e, em seguida, o editor. As reclamações relativas aos dados podem ser apresentadas à ARTCI; os litígios de consumo são da competência dos tribunais competentes (Abidjan).',
            'dispute_nl'     => 'Neem bij een geschil eerst contact op met de verkoper en daarna met de aanbieder. Klachten over gegevens kunnen bij de ARTCI worden ingediend; consumentengeschillen vallen onder de bevoegde rechtbanken (Abidjan).',
            'dispute_ar'     => "في حال وجود نزاع، تواصل أولاً مع البائع ثم مع الناشر. يمكن رفع الشكاوى المتعلقة بالبيانات أمام الـ ARTCI؛ أما منازعات الاستهلاك فتختص بها المحاكم المختصة (Abidjan).",
            'currency_fr'    => 'Franc CFA (FCFA / XOF) — paiements mobile money (Orange Money, MTN MoMo, Moov Money, Wave) selon le vendeur',
            'currency_en'    => 'CFA franc (FCFA / XOF) — mobile-money payments (Orange Money, MTN MoMo, Moov Money, Wave) per seller',
            'currency_de'    => 'CFA-Franc (FCFA / XOF) — Mobile-Money-Zahlungen (Orange Money, MTN MoMo, Moov Money, Wave) je nach Verkäufer',
            'currency_es'    => 'Franco CFA (FCFA / XOF) — pagos por mobile money (Orange Money, MTN MoMo, Moov Money, Wave) según el vendedor',
            'currency_it'    => 'Franco CFA (FCFA / XOF) — pagamenti con mobile money (Orange Money, MTN MoMo, Moov Money, Wave) a seconda del venditore',
            'currency_pt'    => 'Franco CFA (FCFA / XOF) — pagamentos por mobile money (Orange Money, MTN MoMo, Moov Money, Wave) consoante o vendedor',
            'currency_nl'    => 'CFA-frank (FCFA / XOF) — mobielgeldbetalingen (Orange Money, MTN MoMo, Moov Money, Wave) afhankelijk van de verkoper',
            'currency_ar'    => 'فرنك CFA (FCFA / XOF) — مدفوعات عبر المحفظة المالية على الهاتف المحمول (Orange Money، MTN MoMo، Moov Money، Wave) بحسب البائع',
        ],

        'INTL' => [
            'flag'           => '🌍',
            'label_fr'       => 'International',
            'label_en'       => 'International',
            'label_de'       => 'International',
            'label_es'       => 'Internacional',
            'label_it'       => 'Internazionale',
            'label_pt'       => 'Internacional',
            'label_nl'       => 'Internationaal',
            'label_ar'       => 'دولي',
            'impressum'      => false,
            'register_label' => 'Registre du commerce',
            'vat_label'      => 'Identifiant fiscal',
            'data_law_fr'    => 'la loi de protection des données applicable dans votre pays',
            'data_law_en'    => 'the data-protection law applicable in your country',
            'data_law_de'    => 'das in Ihrem Land geltende Datenschutzrecht',
            'data_law_es'    => 'la ley de protección de datos aplicable en su país',
            'data_law_it'    => 'la legge sulla protezione dei dati applicabile nel vostro paese',
            'data_law_pt'    => 'a lei de proteção de dados aplicável no seu país',
            'data_law_nl'    => 'de in uw land geldende wetgeving inzake gegevensbescherming',
            'data_law_ar'    => 'قانون حماية البيانات المعمول به في بلدك',
            'authority_fr'   => "l'autorité de protection des données compétente dans votre pays",
            'authority_en'   => 'the competent data-protection authority in your country',
            'authority_de'   => 'die zuständige Datenschutzbehörde in Ihrem Land',
            'authority_es'   => 'la autoridad de protección de datos competente en su país',
            'authority_it'   => 'l\'autorità di protezione dei dati competente nel vostro paese',
            'authority_pt'   => 'a autoridade de proteção de dados competente no seu país',
            'authority_nl'   => 'de bevoegde gegevensbeschermingsautoriteit in uw land',
            'authority_ar'   => 'سلطة حماية البيانات المختصة في بلدك',
            'consumer_fr'    => 'le droit de la consommation applicable dans votre pays',
            'consumer_en'    => 'the consumer law applicable in your country',
            'consumer_de'    => 'das in Ihrem Land geltende Verbraucherrecht',
            'consumer_es'    => 'el derecho de consumo aplicable en su país',
            'consumer_it'    => 'il diritto dei consumatori applicabile nel vostro paese',
            'consumer_pt'    => 'o direito do consumo aplicável no seu país',
            'consumer_nl'    => 'het in uw land geldende consumentenrecht',
            'consumer_ar'    => 'قانون الاستهلاك المعمول به في بلدك',
            'withdrawal'     => false,
            'dispute_fr'     => "En cas de litige, contactez d'abord le vendeur, puis l'éditeur. Le droit applicable et la juridiction compétente sont précisés dans les conditions générales.",
            'dispute_en'     => 'In case of dispute, first contact the seller, then the publisher. Governing law and jurisdiction are set out in the terms.',
            'dispute_de'     => 'Im Streitfall wenden Sie sich zunächst an den Verkäufer und anschließend an den Anbieter. Das anwendbare Recht und der Gerichtsstand sind in den Allgemeinen Geschäftsbedingungen festgelegt.',
            'dispute_es'     => 'En caso de litigio, contacte primero con el vendedor y, después, con el editor. La ley aplicable y la jurisdicción competente se indican en las condiciones generales.',
            'dispute_it'     => "In caso di controversia, contattate prima il venditore e poi l'editore. La legge applicabile e il foro competente sono indicati nelle condizioni generali.",
            'dispute_pt'     => 'Em caso de litígio, contacte primeiro o vendedor e, em seguida, o editor. A lei aplicável e a jurisdição competente estão indicadas nas condições gerais.',
            'dispute_nl'     => 'Neem bij een geschil eerst contact op met de verkoper en daarna met de aanbieder. Het toepasselijke recht en de bevoegde rechter zijn vastgelegd in de algemene voorwaarden.',
            'dispute_ar'     => 'في حال وجود نزاع، تواصل أولاً مع البائع ثم مع الناشر. ويُحدَّد القانون الواجب التطبيق والجهة القضائية المختصة في الشروط والأحكام العامة.',
            'currency_fr'    => 'Devise affichée selon le vendeur et votre région',
            'currency_en'    => 'Currency shown depends on the seller and your region',
            'currency_de'    => 'Die angezeigte Währung richtet sich nach dem Verkäufer und Ihrer Region',
            'currency_es'    => 'La moneda mostrada depende del vendedor y de su región',
            'currency_it'    => 'La valuta visualizzata dipende dal venditore e dalla vostra regione',
            'currency_pt'    => 'A moeda apresentada depende do vendedor e da sua região',
            'currency_nl'    => 'De weergegeven valuta hangt af van de verkoper en uw regio',
            'currency_ar'    => 'تعتمد العملة المعروضة على البائع ومنطقتك',
        ],
    ],
];
