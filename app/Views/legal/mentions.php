<?php
/**
 * Mentions légales / Impressum — adaptées au pays détecté du visiteur.
 *  DE → format §5 DDG + responsable §18 MStV ; CI → Loi 2013-546 + RCCM/ARTCI ;
 *  sinon mentions génériques. Coordonnées réelles dans config/legal.php (.env).
 *  Modèle à faire valider juridiquement.
 */
$L    = legal_ctx($forced_cc ?? null);
$op   = $L['operator'];
$rg   = $L['data'];
$host = config('legal.host');

// Traductions du contenu rédactionnel, par locale UI (mêmes clés partout).
// FR/EN sont repris VERBATIM des ternaires d'origine : la sortie reste
// inchangée pour ces deux langues ; de/es/it/pt/nl/ar = registre juridique
// formel, avec n° de loi, §, sigles (DDG, MStV, RCCM, ARTCI, DSA…) conservés.
$TX = [
    'en' => [
        'publisher'      => 'Site publisher',
        'email'          => 'Email',
        'phone'          => 'Phone',
        'representative' => 'Legal representative',
        'rep_content'    => 'also responsible for content under § 18 (2) MStV',
        'hosting'        => 'Hosting',
        'hosted_by'      => 'The platform is hosted by',
        'role'           => 'Role of the platform',
        'role_body'      => 'is an online marketplace that connects independent sellers (shops, restaurants, salons, services) with buyers in Africa and Europe. It acts as an intermediary: sales contracts are concluded directly between the seller and the buyer. Each professional seller is identified on their storefront.',
        'ci_law'         => 'Electronic transactions are governed by Law No. 2013-546 of 30 July 2013; each seller must be identifiable (name, contact, RCCM where applicable).',
        'dsa'            => 'Contact point & reporting (DSA)',
        'dsa_single'     => 'Single point of contact for authorities and users:',
        'dsa_langs'      => 'languages: French, English',
        'dsa_report'     => 'To report illegal content or a non-compliant listing, use the',
        'dsa_form'       => 'reporting form',
        'dsa_process'    => 'We process notices under a notice-and-action procedure and reply to the reporter.',
        'ip'             => 'Intellectual property',
        'ip_body'        => 'The Afriklink brand, logo and interface are protected. Product content (texts, images) is published under the responsibility of each seller.',
        'data'           => 'Data protection',
        'data_desc'      => 'Personal-data processing is described in our',
        'privacy_policy' => 'privacy policy',
        'in_accordance'  => 'in accordance with',
        'todo'           => '[to be completed]',
    ],
    'fr' => [
        'publisher'      => 'Éditeur du site',
        'email'          => 'Courriel',
        'phone'          => 'Téléphone',
        'representative' => 'Représentant légal',
        'rep_content'    => 'également responsable du contenu au sens du § 18 al. 2 MStV',
        'hosting'        => 'Hébergement',
        'hosted_by'      => 'La plateforme est hébergée par',
        'role'           => 'Rôle de la plateforme',
        'role_body'      => "est une place de marché en ligne qui met en relation des vendeurs indépendants (boutiques, restaurants, salons, services) avec des acheteurs, en Afrique et en Europe. Elle agit en qualité d'intermédiaire : les contrats de vente sont conclus directement entre le vendeur et l'acheteur. Chaque vendeur professionnel est identifié sur sa vitrine.",
        'ci_law'         => "Les transactions électroniques sont régies par la loi n° 2013-546 du 30 juillet 2013 ; chaque vendeur doit être identifiable (dénomination, contact, RCCM le cas échéant).",
        'dsa'            => 'Point de contact & signalement (DSA)',
        'dsa_single'     => 'Point de contact unique pour les autorités et les utilisateurs :',
        'dsa_langs'      => 'langues : français, anglais',
        'dsa_report'     => 'Pour signaler un contenu illicite ou une vitrine non conforme, utilisez le',
        'dsa_form'       => 'formulaire de signalement',
        'dsa_process'    => "Nous traitons les signalements selon une procédure « notification et action » et répondons à l'auteur du signalement.",
        'ip'             => 'Propriété intellectuelle',
        'ip_body'        => "La marque, le logo et l'interface d'Afriklink sont protégés. Les contenus produits (textes, images) sont publiés sous la responsabilité de chaque vendeur.",
        'data'           => 'Protection des données',
        'data_desc'      => 'Le traitement des données personnelles est décrit dans notre',
        'privacy_policy' => 'politique de confidentialité',
        'in_accordance'  => 'conformément à',
        'todo'           => '[à compléter]',
    ],
    'de' => [
        'publisher'      => 'Anbieter der Website',
        'email'          => 'E-Mail',
        'phone'          => 'Telefon',
        'representative' => 'Gesetzlicher Vertreter',
        'rep_content'    => 'zugleich verantwortlich für den Inhalt gemäß § 18 Abs. 2 MStV',
        'hosting'        => 'Hosting',
        'hosted_by'      => 'Die Plattform wird gehostet von',
        'role'           => 'Rolle der Plattform',
        'role_body'      => 'ist ein Online-Marktplatz, der unabhängige Verkäufer (Geschäfte, Restaurants, Friseursalons, Dienstleistungen) mit Käufern in Afrika und Europa zusammenbringt. Sie handelt als Vermittler: Kaufverträge werden unmittelbar zwischen dem Verkäufer und dem Käufer geschlossen. Jeder gewerbliche Verkäufer ist auf seiner Verkaufsseite identifiziert.',
        'ci_law'         => 'Elektronische Transaktionen unterliegen dem Gesetz Nr. 2013-546 vom 30. Juli 2013; jeder Verkäufer muss identifizierbar sein (Bezeichnung, Kontakt, gegebenenfalls RCCM).',
        'dsa'            => 'Kontaktstelle & Meldung (DSA)',
        'dsa_single'     => 'Zentrale Kontaktstelle für Behörden und Nutzer:',
        'dsa_langs'      => 'Sprachen: Französisch, Englisch',
        'dsa_report'     => 'Um rechtswidrige Inhalte oder eine nicht konforme Verkaufsseite zu melden, verwenden Sie das',
        'dsa_form'       => 'Meldeformular',
        'dsa_process'    => 'Wir bearbeiten Meldungen im Rahmen eines Melde- und Abhilfeverfahrens und antworten dem Hinweisgeber.',
        'ip'             => 'Geistiges Eigentum',
        'ip_body'        => 'Die Marke, das Logo und die Benutzeroberfläche von Afriklink sind geschützt. Die Produktinhalte (Texte, Bilder) werden unter der Verantwortung des jeweiligen Verkäufers veröffentlicht.',
        'data'           => 'Datenschutz',
        'data_desc'      => 'Die Verarbeitung personenbezogener Daten wird in unserer',
        'privacy_policy' => 'Datenschutzerklärung',
        'in_accordance'  => 'beschrieben, in Übereinstimmung mit',
        'todo'           => '[zu ergänzen]',
    ],
    'es' => [
        'publisher'      => 'Editor del sitio',
        'email'          => 'Correo electrónico',
        'phone'          => 'Teléfono',
        'representative' => 'Representante legal',
        'rep_content'    => 'asimismo responsable del contenido conforme al § 18 apdo. 2 MStV',
        'hosting'        => 'Alojamiento',
        'hosted_by'      => 'La plataforma está alojada por',
        'role'           => 'Función de la plataforma',
        'role_body'      => 'es un mercado en línea que pone en contacto a vendedores independientes (tiendas, restaurantes, peluquerías, servicios) con compradores en África y Europa. Actúa en calidad de intermediario: los contratos de venta se celebran directamente entre el vendedor y el comprador. Cada vendedor profesional está identificado en su escaparate.',
        'ci_law'         => 'Las transacciones electrónicas se rigen por la Ley n.º 2013-546 de 30 de julio de 2013; cada vendedor debe ser identificable (denominación, contacto, RCCM cuando proceda).',
        'dsa'            => 'Punto de contacto y notificación (DSA)',
        'dsa_single'     => 'Punto de contacto único para las autoridades y los usuarios:',
        'dsa_langs'      => 'idiomas: francés, inglés',
        'dsa_report'     => 'Para notificar un contenido ilícito o un escaparate no conforme, utilice el',
        'dsa_form'       => 'formulario de notificación',
        'dsa_process'    => 'Tratamos las notificaciones con arreglo a un procedimiento de «notificación y acción» y respondemos al notificante.',
        'ip'             => 'Propiedad intelectual',
        'ip_body'        => 'La marca, el logotipo y la interfaz de Afriklink están protegidos. Los contenidos de los productos (textos, imágenes) se publican bajo la responsabilidad de cada vendedor.',
        'data'           => 'Protección de datos',
        'data_desc'      => 'El tratamiento de los datos personales se describe en nuestra',
        'privacy_policy' => 'política de privacidad',
        'in_accordance'  => 'de conformidad con',
        'todo'           => '[por completar]',
    ],
    'it' => [
        'publisher'      => 'Editore del sito',
        'email'          => 'E-mail',
        'phone'          => 'Telefono',
        'representative' => 'Rappresentante legale',
        'rep_content'    => 'altresì responsabile del contenuto ai sensi del § 18 comma 2 MStV',
        'hosting'        => 'Hosting',
        'hosted_by'      => 'La piattaforma è ospitata da',
        'role'           => 'Ruolo della piattaforma',
        'role_body'      => 'è un mercato online che mette in contatto venditori indipendenti (negozi, ristoranti, saloni, servizi) con acquirenti in Africa e in Europa. Agisce in qualità di intermediario: i contratti di vendita sono conclusi direttamente tra il venditore e l\'acquirente. Ogni venditore professionale è identificato sulla propria vetrina.',
        'ci_law'         => 'Le transazioni elettroniche sono disciplinate dalla legge n. 2013-546 del 30 luglio 2013; ogni venditore deve essere identificabile (denominazione, contatto, RCCM ove applicabile).',
        'dsa'            => 'Punto di contatto e segnalazione (DSA)',
        'dsa_single'     => 'Punto di contatto unico per le autorità e gli utenti:',
        'dsa_langs'      => 'lingue: francese, inglese',
        'dsa_report'     => 'Per segnalare un contenuto illecito o una vetrina non conforme, utilizzare il',
        'dsa_form'       => 'modulo di segnalazione',
        'dsa_process'    => 'Trattiamo le segnalazioni secondo una procedura di «notifica e azione» e rispondiamo al segnalante.',
        'ip'             => 'Proprietà intellettuale',
        'ip_body'        => 'Il marchio, il logo e l\'interfaccia di Afriklink sono protetti. I contenuti dei prodotti (testi, immagini) sono pubblicati sotto la responsabilità di ciascun venditore.',
        'data'           => 'Protezione dei dati',
        'data_desc'      => 'Il trattamento dei dati personali è descritto nella nostra',
        'privacy_policy' => 'informativa sulla privacy',
        'in_accordance'  => 'in conformità con',
        'todo'           => '[da completare]',
    ],
    'pt' => [
        'publisher'      => 'Editor do sítio',
        'email'          => 'E-mail',
        'phone'          => 'Telefone',
        'representative' => 'Representante legal',
        'rep_content'    => 'igualmente responsável pelo conteúdo nos termos do § 18 n.º 2 MStV',
        'hosting'        => 'Alojamento',
        'hosted_by'      => 'A plataforma é alojada por',
        'role'           => 'Função da plataforma',
        'role_body'      => 'é um mercado em linha que coloca em contacto vendedores independentes (lojas, restaurantes, salões, serviços) com compradores em África e na Europa. Atua na qualidade de intermediário: os contratos de venda são celebrados diretamente entre o vendedor e o comprador. Cada vendedor profissional está identificado na sua montra.',
        'ci_law'         => 'As transações eletrónicas regem-se pela Lei n.º 2013-546, de 30 de julho de 2013; cada vendedor deve ser identificável (denominação, contacto, RCCM se aplicável).',
        'dsa'            => 'Ponto de contacto e denúncia (DSA)',
        'dsa_single'     => 'Ponto de contacto único para as autoridades e os utilizadores:',
        'dsa_langs'      => 'línguas: francês, inglês',
        'dsa_report'     => 'Para denunciar um conteúdo ilícito ou uma montra não conforme, utilize o',
        'dsa_form'       => 'formulário de denúncia',
        'dsa_process'    => 'Tratamos as denúncias segundo um procedimento de «notificação e ação» e respondemos ao denunciante.',
        'ip'             => 'Propriedade intelectual',
        'ip_body'        => 'A marca, o logótipo e a interface da Afriklink estão protegidos. Os conteúdos dos produtos (textos, imagens) são publicados sob a responsabilidade de cada vendedor.',
        'data'           => 'Proteção de dados',
        'data_desc'      => 'O tratamento dos dados pessoais está descrito na nossa',
        'privacy_policy' => 'política de privacidade',
        'in_accordance'  => 'em conformidade com',
        'todo'           => '[a completar]',
    ],
    'nl' => [
        'publisher'      => 'Uitgever van de website',
        'email'          => 'E-mail',
        'phone'          => 'Telefoon',
        'representative' => 'Wettelijke vertegenwoordiger',
        'rep_content'    => 'tevens verantwoordelijk voor de inhoud overeenkomstig § 18 lid 2 MStV',
        'hosting'        => 'Hosting',
        'hosted_by'      => 'Het platform wordt gehost door',
        'role'           => 'Rol van het platform',
        'role_body'      => 'is een onlinemarktplaats die onafhankelijke verkopers (winkels, restaurants, kapsalons, diensten) in contact brengt met kopers in Afrika en Europa. Zij treedt op als tussenpersoon: de koopovereenkomsten worden rechtstreeks tussen de verkoper en de koper gesloten. Elke professionele verkoper is op zijn etalage geïdentificeerd.',
        'ci_law'         => 'Elektronische transacties worden geregeld door wet nr. 2013-546 van 30 juli 2013; elke verkoper moet identificeerbaar zijn (benaming, contact, RCCM indien van toepassing).',
        'dsa'            => 'Contactpunt en melding (DSA)',
        'dsa_single'     => 'Centraal contactpunt voor de autoriteiten en de gebruikers:',
        'dsa_langs'      => 'talen: Frans, Engels',
        'dsa_report'     => 'Om illegale inhoud of een niet-conforme etalage te melden, gebruikt u het',
        'dsa_form'       => 'meldingsformulier',
        'dsa_process'    => 'Wij behandelen meldingen volgens een kennisgevings- en actieprocedure en antwoorden de melder.',
        'ip'             => 'Intellectuele eigendom',
        'ip_body'        => 'Het merk, het logo en de interface van Afriklink zijn beschermd. De productinhoud (teksten, afbeeldingen) wordt onder de verantwoordelijkheid van elke verkoper gepubliceerd.',
        'data'           => 'Gegevensbescherming',
        'data_desc'      => 'De verwerking van persoonsgegevens wordt beschreven in ons',
        'privacy_policy' => 'privacybeleid',
        'in_accordance'  => 'in overeenstemming met',
        'todo'           => '[aan te vullen]',
    ],
    'ar' => [
        'publisher'      => 'ناشر الموقع',
        'email'          => 'البريد الإلكتروني',
        'phone'          => 'الهاتف',
        'representative' => 'الممثل القانوني',
        'rep_content'    => 'كما أنه مسؤول عن المحتوى وفقًا للمادة § 18 (2) MStV',
        'hosting'        => 'الاستضافة',
        'hosted_by'      => 'تتم استضافة المنصة لدى',
        'role'           => 'دور المنصة',
        'role_body'      => 'هي سوق إلكترونية تربط بائعين مستقلين (متاجر، مطاعم، صالونات، خدمات) بمشترين في إفريقيا وأوروبا. وهي تعمل بصفة وسيط: تُبرم عقود البيع مباشرةً بين البائع والمشتري. ويُعرَّف كل بائع محترف على واجهة متجره.',
        'ci_law'         => 'تخضع المعاملات الإلكترونية للقانون رقم 2013-546 المؤرخ في 30 يوليو 2013؛ ويجب أن يكون كل بائع قابلاً للتعريف (التسمية، جهة الاتصال، RCCM عند الاقتضاء).',
        'dsa'            => 'نقطة الاتصال والإبلاغ (DSA)',
        'dsa_single'     => 'نقطة اتصال موحدة للسلطات والمستخدمين:',
        'dsa_langs'      => 'اللغتان: الفرنسية والإنجليزية',
        'dsa_report'     => 'للإبلاغ عن محتوى غير مشروع أو عن واجهة غير مطابقة، استخدم',
        'dsa_form'       => 'نموذج الإبلاغ',
        'dsa_process'    => 'نعالج البلاغات وفق إجراء «الإخطار واتخاذ الإجراء» ونرد على مُقدِّم البلاغ.',
        'ip'             => 'الملكية الفكرية',
        'ip_body'        => 'إن علامة Afriklink وشعارها وواجهتها محمية. وتُنشَر محتويات المنتجات (النصوص، الصور) تحت مسؤولية كل بائع.',
        'data'           => 'حماية البيانات',
        'data_desc'      => 'تُوصَف معالجة البيانات الشخصية في',
        'privacy_policy' => 'سياسة الخصوصية الخاصة بنا',
        'in_accordance'  => 'وفقًا لـ',
        'todo'           => '[يُستكمَل لاحقًا]',
    ],
];
$T    = $TX[current_locale()] ?? $TX['en'];
$ph   = '<em class="legal-todo">' . e($T['todo']) . '</em>';
$F    = static fn (string $v): string => trim($v) !== '' ? e(trim($v)) : $ph;
$identity = trim(($op['name'] ?? '') . ($op['legal_form'] !== '' ? ' — ' . $op['legal_form'] : ''));
$addr1 = trim((string) ($op['address'] ?? ''));
$addr2 = trim(($op['postal_code'] ?? '') . ' ' . ($op['city'] ?? '') . ' (' . country_name($op['country_code'] ?? 'DE') . ')');
?>
<section class="legal-page">
    <h1><?= e(t('legal.notice.title')) ?><?php if ($rg['impressum'] ?? false): ?> <span class="legal-aka">/ Impressum</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/mentions-legales']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <h2>1. <?= e($T['publisher']) ?><?php if ($rg['impressum'] ?? false): ?> <span class="muted">(Angaben gemäß § 5 DDG)</span><?php endif; ?></h2>
    <p>
        <strong><?= $identity !== '' ? e($identity) : $ph ?></strong><br>
        <?= $addr1 !== '' ? e($addr1) : $ph ?><br>
        <?= e($addr2) ?>
    </p>
    <p>
        <?= e($T['email']) ?> : <?= $F((string) ($op['email'] ?? '')) ?><br>
        <?php if (trim((string) ($op['phone'] ?? '')) !== ''): ?><?= e($T['phone']) ?> : <?= e($op['phone']) ?><br><?php endif; ?>
        <?= e($rg['register_label'] ?? 'Registre') ?> : <?= $F((string) ($op['register'] ?? '')) ?><?php if (trim((string) ($op['register_court'] ?? '')) !== ''): ?> — <?= e($op['register_court']) ?><?php endif; ?><br>
        <?= e($rg['vat_label'] ?? 'TVA') ?> : <?= $F((string) ($op['vat'] ?? '')) ?>
    </p>

    <h2>2. <?php if ($rg['impressum'] ?? false): ?>Vertretungsberechtigte / <?php endif; ?><?= e($T['representative']) ?></h2>
    <p><?= $F((string) ($op['representative'] ?? '')) ?><?php if ($rg['impressum'] ?? false): ?> — <span class="muted"><?= e($T['rep_content']) ?></span><?php endif; ?></p>

    <h2>3. <?= e($T['hosting']) ?></h2>
    <p><?= e($T['hosted_by']) ?> <strong><?= e($host['name']) ?></strong>, <?= e($host['address']) ?> — <?= e($host['url']) ?>.</p>

    <h2>4. <?= e($T['role']) ?></h2>
    <p>
        <strong><?= e($op['name'] ?? 'Afriklink') ?></strong> <?= e($T['role_body']) ?>
    </p>
    <?php if ($L['is_ci']): ?>
        <p class="muted"><?= e($T['ci_law']) ?></p>
    <?php endif; ?>

    <h2>5. <?= e($T['dsa']) ?></h2>
    <p>
        <?= e($T['dsa_single']) ?>
        <strong><?= $F((string) ($op['email'] ?? '')) ?></strong> (<?= e($T['dsa_langs']) ?>).
        <?= e($T['dsa_report']) ?>
        <a href="<?= e(url('/signaler-vitrine')) ?>"><?= e($T['dsa_form']) ?></a>.
        <?= e($T['dsa_process']) ?>
    </p>

    <h2>6. <?= e($T['ip']) ?></h2>
    <p><?= e($T['ip_body']) ?></p>

    <h2>7. <?= e($T['data']) ?></h2>
    <p>
        <?= e($T['data_desc']) ?>
        <a href="<?= e(url('/confidentialite')) ?>"><?= e($T['privacy_policy']) ?></a>,
        <?= e($T['in_accordance']) ?> <?= e(rg_field($rg, 'data_law')) ?>.
    </p>
</section>
