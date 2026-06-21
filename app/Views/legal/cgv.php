<?php
/**
 * Conditions générales (CGU/CGV) — adaptées au pays détecté.
 *  EU/DE → rétractation 14 j + médiation ; CI → loi 2016-412 + transactions
 *  électroniques. Droit applicable = siège de l'éditeur. À faire valider.
 */
$en = current_locale() !== 'fr'; // de/es/it : repli sur l'anglais
$L  = legal_ctx($forced_cc ?? null);
$op = $L['operator'];
$rg = $L['data'];
$seat     = country_name($op['country_code'] ?? 'DE');
$consumer = rg_field($rg, 'consumer');
$dispute  = rg_field($rg, 'dispute');
$currency = rg_field($rg, 'currency');

$TX = [
    'en' => [
        'purpose'         => 'Purpose',
        'purpose_p'       => 'These terms govern the use of the Afriklink marketplace and the sales concluded through it.',
        'role'            => 'Role of Afriklink',
        'role_p'          => 'Afriklink is an intermediary. The sales contract is formed directly between the seller and the buyer. The seller is responsible for their products, prices, descriptions, stock, delivery and after-sales service.',
        'account'         => 'Account',
        'account_p'       => 'You must provide accurate information and keep your credentials confidential. Professional sellers complete an identity verification (KYC).',
        'p2b'             => 'Professional sellers (P2B)',
        'p2b_p'           => 'In accordance with Regulation (EU) 2019/1150 (P2B), the main ranking parameters of listings (relevance, availability, seller reputation, sponsorship) are disclosed, access to a seller\'s own data is provided, and account-suspension decisions are stated with reasons and an internal complaint channel.',
        'orders'          => 'Orders, prices & payment',
        'orders_p'        => 'Prices are shown in the displayed currency, inclusive of taxes where applicable, with delivery costs indicated before the order. An order is firm once confirmed. Payment terms and methods are those offered by each seller.',
        'currency_region' => 'Currency for your region:',
        'delivery'        => 'Delivery',
        'delivery_p'      => 'Delivery times and fees are indicated by the seller per zone (local / international). Risks transfer upon delivery.',
        'withdrawal'      => 'Right of withdrawal / returns',
        'withdrawal_yes'  => 'As a consumer you have 14 days from receipt to withdraw from an eligible distance purchase, without giving a reason, save for legal exceptions (perishables, made-to-order items, unsealed hygiene products, etc.).',
        'withdrawal_link' => 'See the withdrawal policy and model form',
        'withdrawal_no'   => 'Returns and refunds follow each seller\'s published policy and the consumer law applicable in your country. Contact the seller first; Afriklink helps in good faith.',
        'guarantees'      => 'Legal guarantees',
        'guarantees_p'    => 'Consumers benefit from the legal guarantees of conformity and against hidden defects provided by ',
        'complaints'      => 'Complaints & dispute resolution',
        'contact'         => 'Contact:',
        'liability'       => 'Liability',
        'liability_p'     => 'As an intermediary, Afriklink is not a party to the sale and is not liable for the sellers\' performance, within the limits permitted by law.',
        'law'             => 'Governing law',
        'law_p1'          => 'These terms are governed by the law of the publisher\'s place of establishment (',
        'law_p2'          => '). Consumers also retain the mandatory protections of the law of their country of residence. Competent courts are determined by applicable law.',
    ],
    'fr' => [
        'purpose'         => 'Objet',
        'purpose_p'       => "Les présentes conditions régissent l'utilisation de la marketplace Afriklink et les ventes qui y sont conclues.",
        'role'            => "Rôle d'Afriklink",
        'role_p'          => "Afriklink est un intermédiaire. Le contrat de vente est formé directement entre le vendeur et l'acheteur. Le vendeur est responsable de ses produits, prix, descriptions, stocks, livraison et service après-vente.",
        'account'         => 'Compte',
        'account_p'       => "Vous devez fournir des informations exactes et garder vos identifiants confidentiels. Les vendeurs professionnels réalisent une vérification d'identité (KYC).",
        'p2b'             => 'Vendeurs professionnels (P2B)',
        'p2b_p'           => "Conformément au règlement (UE) 2019/1150 (P2B), les principaux paramètres de classement des annonces (pertinence, disponibilité, réputation du vendeur, sponsoring) sont communiqués, l'accès du vendeur à ses propres données est assuré, et les décisions de suspension de compte sont motivées avec une voie de réclamation interne.",
        'orders'          => 'Commandes, prix & paiement',
        'orders_p'        => "Les prix sont affichés dans la devise indiquée, toutes taxes comprises le cas échéant, les frais de livraison étant indiqués avant la commande. La commande est ferme une fois confirmée. Les conditions et moyens de paiement sont ceux proposés par chaque vendeur.",
        'currency_region' => 'Devise pour votre région :',
        'delivery'        => 'Livraison',
        'delivery_p'      => "Les délais et frais de livraison sont indiqués par le vendeur selon la zone (locale / internationale). Les risques sont transférés à la livraison.",
        'withdrawal'      => 'Droit de rétractation / retours',
        'withdrawal_yes'  => "En tant que consommateur, vous disposez de 14 jours à compter de la réception pour vous rétracter d'un achat à distance éligible, sans motif, sous réserve des exceptions légales (denrées périssables, produits sur mesure, articles d'hygiène descellés, etc.).",
        'withdrawal_link' => 'Voir le détail et le formulaire-type de rétractation',
        'withdrawal_no'   => "Les retours et remboursements suivent la politique publiée par chaque vendeur et le droit de la consommation applicable dans votre pays. Contactez d'abord le vendeur ; Afriklink facilite la résolution de bonne foi.",
        'guarantees'      => 'Garanties légales',
        'guarantees_p'    => "Les consommateurs bénéficient des garanties légales de conformité et contre les vices cachés prévues par ",
        'complaints'      => 'Réclamations & résolution des litiges',
        'contact'         => 'Contact :',
        'liability'       => 'Responsabilité',
        'liability_p'     => "En tant qu'intermédiaire, Afriklink n'est pas partie à la vente et n'est pas responsable de l'exécution par les vendeurs, dans les limites permises par la loi.",
        'law'             => 'Droit applicable',
        'law_p1'          => "Les présentes conditions sont régies par le droit du lieu d'établissement de l'éditeur (",
        'law_p2'          => "). Les consommateurs conservent en outre les protections impératives du droit de leur pays de résidence. Les juridictions compétentes sont déterminées par la loi applicable.",
    ],
    'de' => [
        'purpose'         => 'Gegenstand',
        'purpose_p'       => 'Diese Bedingungen regeln die Nutzung des Afriklink-Marktplatzes und die darüber abgeschlossenen Verkäufe.',
        'role'            => 'Rolle von Afriklink',
        'role_p'          => 'Afriklink ist ein Vermittler. Der Kaufvertrag kommt unmittelbar zwischen dem Verkäufer und dem Käufer zustande. Der Verkäufer ist für seine Produkte, Preise, Beschreibungen, Bestände, Lieferung und Kundendienst verantwortlich.',
        'account'         => 'Konto',
        'account_p'       => 'Sie müssen zutreffende Angaben machen und Ihre Zugangsdaten vertraulich behandeln. Gewerbliche Verkäufer führen eine Identitätsprüfung (KYC) durch.',
        'p2b'             => 'Gewerbliche Verkäufer (P2B)',
        'p2b_p'           => 'Gemäß der Verordnung (EU) 2019/1150 (P2B) werden die wesentlichen Ranking-Parameter der Angebote (Relevanz, Verfügbarkeit, Reputation des Verkäufers, Sponsoring) offengelegt, der Zugang des Verkäufers zu seinen eigenen Daten gewährleistet, und Entscheidungen über die Kontosperrung werden begründet und mit einem internen Beschwerdeweg versehen.',
        'orders'          => 'Bestellungen, Preise & Zahlung',
        'orders_p'        => 'Die Preise werden in der angegebenen Währung ausgewiesen, gegebenenfalls einschließlich Steuern, wobei die Lieferkosten vor der Bestellung angegeben werden. Eine Bestellung ist nach ihrer Bestätigung verbindlich. Die Zahlungsbedingungen und -mittel sind diejenigen, die der jeweilige Verkäufer anbietet.',
        'currency_region' => 'Währung für Ihre Region:',
        'delivery'        => 'Lieferung',
        'delivery_p'      => 'Lieferzeiten und -kosten werden vom Verkäufer je nach Zone (lokal / international) angegeben. Die Gefahr geht mit der Lieferung über.',
        'withdrawal'      => 'Widerrufsrecht / Rückgaben',
        'withdrawal_yes'  => 'Als Verbraucher haben Sie 14 Tage ab Erhalt das Recht, einen zulässigen Fernabsatzkauf ohne Angabe von Gründen zu widerrufen, vorbehaltlich der gesetzlichen Ausnahmen (verderbliche Waren, maßgefertigte Artikel, entsiegelte Hygieneprodukte usw.).',
        'withdrawal_link' => 'Die Widerrufsbelehrung und das Muster-Widerrufsformular ansehen',
        'withdrawal_no'   => 'Rückgaben und Erstattungen richten sich nach der von jedem Verkäufer veröffentlichten Politik und dem in Ihrem Land geltenden Verbraucherrecht. Kontaktieren Sie zuerst den Verkäufer; Afriklink unterstützt die Lösung nach Treu und Glauben.',
        'guarantees'      => 'Gesetzliche Gewährleistung',
        'guarantees_p'    => 'Verbraucher genießen die gesetzliche Gewährleistung für Mängel und gegen verdeckte Mängel gemäß ',
        'complaints'      => 'Beschwerden & Streitbeilegung',
        'contact'         => 'Kontakt:',
        'liability'       => 'Haftung',
        'liability_p'     => 'Als Vermittler ist Afriklink nicht Partei des Verkaufs und haftet im gesetzlich zulässigen Rahmen nicht für die Erfüllung durch die Verkäufer.',
        'law'             => 'Anwendbares Recht',
        'law_p1'          => 'Diese Bedingungen unterliegen dem Recht des Niederlassungsorts des Herausgebers (',
        'law_p2'          => '). Verbraucher behalten zudem den zwingenden Schutz des Rechts ihres Wohnsitzlandes. Die zuständigen Gerichte bestimmen sich nach dem anwendbaren Recht.',
    ],
    'es' => [
        'purpose'         => 'Objeto',
        'purpose_p'       => 'Las presentes condiciones rigen el uso del marketplace Afriklink y las ventas que se concluyen a través de él.',
        'role'            => 'Función de Afriklink',
        'role_p'          => 'Afriklink es un intermediario. El contrato de venta se formaliza directamente entre el vendedor y el comprador. El vendedor es responsable de sus productos, precios, descripciones, existencias, entrega y servicio posventa.',
        'account'         => 'Cuenta',
        'account_p'       => 'Debe facilitar información exacta y mantener la confidencialidad de sus credenciales. Los vendedores profesionales realizan una verificación de identidad (KYC).',
        'p2b'             => 'Vendedores profesionales (P2B)',
        'p2b_p'           => 'De conformidad con el Reglamento (UE) 2019/1150 (P2B), se comunican los principales parámetros de clasificación de los anuncios (pertinencia, disponibilidad, reputación del vendedor, patrocinio), se garantiza el acceso del vendedor a sus propios datos y las decisiones de suspensión de cuenta se motivan con una vía de reclamación interna.',
        'orders'          => 'Pedidos, precios y pago',
        'orders_p'        => 'Los precios se muestran en la moneda indicada, impuestos incluidos cuando proceda, indicándose los gastos de entrega antes del pedido. El pedido es firme una vez confirmado. Las condiciones y medios de pago son los ofrecidos por cada vendedor.',
        'currency_region' => 'Moneda para su región:',
        'delivery'        => 'Entrega',
        'delivery_p'      => 'Los plazos y gastos de entrega son indicados por el vendedor según la zona (local / internacional). Los riesgos se transfieren con la entrega.',
        'withdrawal'      => 'Derecho de desistimiento / devoluciones',
        'withdrawal_yes'  => 'Como consumidor, dispone de 14 días desde la recepción para desistir de una compra a distancia elegible, sin necesidad de justificación, salvo las excepciones legales (productos perecederos, artículos hechos a medida, productos de higiene sin precinto, etc.).',
        'withdrawal_link' => 'Ver el detalle y el formulario tipo de desistimiento',
        'withdrawal_no'   => 'Las devoluciones y reembolsos siguen la política publicada por cada vendedor y el derecho de consumo aplicable en su país. Contacte primero con el vendedor; Afriklink facilita la resolución de buena fe.',
        'guarantees'      => 'Garantías legales',
        'guarantees_p'    => 'Los consumidores se benefician de las garantías legales de conformidad y contra los vicios ocultos previstas por ',
        'complaints'      => 'Reclamaciones y resolución de litigios',
        'contact'         => 'Contacto:',
        'liability'       => 'Responsabilidad',
        'liability_p'     => 'Como intermediario, Afriklink no es parte en la venta y no es responsable de la ejecución por parte de los vendedores, dentro de los límites permitidos por la ley.',
        'law'             => 'Ley aplicable',
        'law_p1'          => 'Las presentes condiciones se rigen por la ley del lugar de establecimiento del editor (',
        'law_p2'          => '). Los consumidores conservan además las protecciones imperativas de la ley de su país de residencia. Los tribunales competentes se determinan según la ley aplicable.',
    ],
    'it' => [
        'purpose'         => 'Oggetto',
        'purpose_p'       => 'Le presenti condizioni disciplinano l\'utilizzo del marketplace Afriklink e le vendite ivi concluse.',
        'role'            => 'Ruolo di Afriklink',
        'role_p'          => 'Afriklink è un intermediario. Il contratto di vendita si forma direttamente tra il venditore e l\'acquirente. Il venditore è responsabile dei propri prodotti, prezzi, descrizioni, scorte, consegna e servizio post-vendita.',
        'account'         => 'Account',
        'account_p'       => 'Dovete fornire informazioni esatte e mantenere riservate le vostre credenziali. I venditori professionali effettuano una verifica dell\'identità (KYC).',
        'p2b'             => 'Venditori professionali (P2B)',
        'p2b_p'           => 'In conformità al regolamento (UE) 2019/1150 (P2B), sono comunicati i principali parametri di posizionamento degli annunci (pertinenza, disponibilità, reputazione del venditore, sponsorizzazione), è garantito l\'accesso del venditore ai propri dati e le decisioni di sospensione dell\'account sono motivate con una via di reclamo interna.',
        'orders'          => 'Ordini, prezzi e pagamento',
        'orders_p'        => 'I prezzi sono indicati nella valuta visualizzata, comprensivi delle imposte ove applicabile, con le spese di consegna indicate prima dell\'ordine. L\'ordine è fermo una volta confermato. Le condizioni e i mezzi di pagamento sono quelli proposti da ciascun venditore.',
        'currency_region' => 'Valuta per la vostra regione:',
        'delivery'        => 'Consegna',
        'delivery_p'      => 'I tempi e le spese di consegna sono indicati dal venditore in base alla zona (locale / internazionale). I rischi sono trasferiti al momento della consegna.',
        'withdrawal'      => 'Diritto di recesso / resi',
        'withdrawal_yes'  => 'In quanto consumatore, disponete di 14 giorni dalla ricezione per recedere da un acquisto a distanza ammissibile, senza fornire motivazione, fatte salve le eccezioni di legge (beni deperibili, articoli su misura, prodotti igienici non sigillati, ecc.).',
        'withdrawal_link' => 'Vedere il dettaglio e il modulo tipo di recesso',
        'withdrawal_no'   => 'I resi e i rimborsi seguono la politica pubblicata da ciascun venditore e il diritto dei consumatori applicabile nel vostro paese. Contattate prima il venditore; Afriklink facilita la risoluzione in buona fede.',
        'guarantees'      => 'Garanzie legali',
        'guarantees_p'    => 'I consumatori beneficiano delle garanzie legali di conformità e contro i vizi occulti previste da ',
        'complaints'      => 'Reclami e risoluzione delle controversie',
        'contact'         => 'Contatto:',
        'liability'       => 'Responsabilità',
        'liability_p'     => 'In quanto intermediario, Afriklink non è parte della vendita e non è responsabile dell\'esecuzione da parte dei venditori, nei limiti consentiti dalla legge.',
        'law'             => 'Legge applicabile',
        'law_p1'          => 'Le presenti condizioni sono disciplinate dalla legge del luogo di stabilimento dell\'editore (',
        'law_p2'          => '). I consumatori conservano inoltre le protezioni imperative della legge del loro paese di residenza. I fori competenti sono determinati dalla legge applicabile.',
    ],
    'pt' => [
        'purpose'         => 'Objeto',
        'purpose_p'       => 'As presentes condições regem a utilização do marketplace Afriklink e as vendas nele concluídas.',
        'role'            => 'Papel da Afriklink',
        'role_p'          => 'A Afriklink é um intermediário. O contrato de venda é formado diretamente entre o vendedor e o comprador. O vendedor é responsável pelos seus produtos, preços, descrições, stocks, entrega e serviço pós-venda.',
        'account'         => 'Conta',
        'account_p'       => 'Deve fornecer informações exatas e manter as suas credenciais confidenciais. Os vendedores profissionais realizam uma verificação de identidade (KYC).',
        'p2b'             => 'Vendedores profissionais (P2B)',
        'p2b_p'           => 'Em conformidade com o Regulamento (UE) 2019/1150 (P2B), são comunicados os principais parâmetros de classificação dos anúncios (pertinência, disponibilidade, reputação do vendedor, patrocínio), é assegurado o acesso do vendedor aos seus próprios dados e as decisões de suspensão de conta são fundamentadas com uma via de reclamação interna.',
        'orders'          => 'Encomendas, preços e pagamento',
        'orders_p'        => 'Os preços são apresentados na moeda indicada, com impostos incluídos quando aplicável, sendo os custos de entrega indicados antes da encomenda. A encomenda é firme após a confirmação. As condições e meios de pagamento são os propostos por cada vendedor.',
        'currency_region' => 'Moeda para a sua região:',
        'delivery'        => 'Entrega',
        'delivery_p'      => 'Os prazos e custos de entrega são indicados pelo vendedor consoante a zona (local / internacional). Os riscos são transferidos no momento da entrega.',
        'withdrawal'      => 'Direito de retratação / devoluções',
        'withdrawal_yes'  => 'Enquanto consumidor, dispõe de 14 dias a contar da receção para se retratar de uma compra à distância elegível, sem necessidade de justificação, sob reserva das exceções legais (bens perecíveis, artigos feitos por medida, produtos de higiene sem selo, etc.).',
        'withdrawal_link' => 'Ver o detalhe e o formulário-tipo de retratação',
        'withdrawal_no'   => 'As devoluções e reembolsos seguem a política publicada por cada vendedor e o direito do consumo aplicável no seu país. Contacte primeiro o vendedor; a Afriklink facilita a resolução de boa-fé.',
        'guarantees'      => 'Garantias legais',
        'guarantees_p'    => 'Os consumidores beneficiam das garantias legais de conformidade e contra os defeitos ocultos previstas por ',
        'complaints'      => 'Reclamações e resolução de litígios',
        'contact'         => 'Contacto:',
        'liability'       => 'Responsabilidade',
        'liability_p'     => 'Enquanto intermediário, a Afriklink não é parte na venda e não é responsável pela execução por parte dos vendedores, dentro dos limites permitidos pela lei.',
        'law'             => 'Lei aplicável',
        'law_p1'          => 'As presentes condições são regidas pela lei do local de estabelecimento do editor (',
        'law_p2'          => '). Os consumidores conservam ainda as proteções imperativas da lei do seu país de residência. Os tribunais competentes são determinados pela lei aplicável.',
    ],
    'nl' => [
        'purpose'         => 'Voorwerp',
        'purpose_p'       => 'Deze voorwaarden regelen het gebruik van het Afriklink-marktplaats en de daarop gesloten verkopen.',
        'role'            => 'Rol van Afriklink',
        'role_p'          => 'Afriklink is een tussenpersoon. De koopovereenkomst komt rechtstreeks tot stand tussen de verkoper en de koper. De verkoper is verantwoordelijk voor zijn producten, prijzen, beschrijvingen, voorraden, levering en klantenservice.',
        'account'         => 'Account',
        'account_p'       => 'U dient juiste informatie te verstrekken en uw inloggegevens vertrouwelijk te houden. Professionele verkopers voeren een identiteitsverificatie (KYC) uit.',
        'p2b'             => 'Professionele verkopers (P2B)',
        'p2b_p'           => 'Overeenkomstig Verordening (EU) 2019/1150 (P2B) worden de belangrijkste rangschikkingsparameters van de aanbiedingen (relevantie, beschikbaarheid, reputatie van de verkoper, sponsoring) meegedeeld, wordt de toegang van de verkoper tot zijn eigen gegevens gewaarborgd, en worden beslissingen tot accountschorsing gemotiveerd met een interne klachtenprocedure.',
        'orders'          => 'Bestellingen, prijzen en betaling',
        'orders_p'        => 'De prijzen worden weergegeven in de aangegeven valuta, in voorkomend geval inclusief belastingen, waarbij de leveringskosten vóór de bestelling worden vermeld. Een bestelling is vast zodra zij is bevestigd. De betalingsvoorwaarden en -middelen zijn die welke door elke verkoper worden aangeboden.',
        'currency_region' => 'Valuta voor uw regio:',
        'delivery'        => 'Levering',
        'delivery_p'      => 'De levertijden en -kosten worden door de verkoper aangegeven naargelang de zone (lokaal / internationaal). De risico\'s gaan over bij de levering.',
        'withdrawal'      => 'Herroepingsrecht / retourzendingen',
        'withdrawal_yes'  => 'Als consument beschikt u over 14 dagen vanaf de ontvangst om een in aanmerking komende koop op afstand te herroepen, zonder opgave van redenen, behoudens de wettelijke uitzonderingen (bederfelijke waren, op maat gemaakte artikelen, ontzegelde hygiëneproducten, enz.).',
        'withdrawal_link' => 'Bekijk de details en het modelformulier voor herroeping',
        'withdrawal_no'   => 'Retourzendingen en terugbetalingen volgen het door elke verkoper gepubliceerde beleid en het consumentenrecht dat in uw land van toepassing is. Neem eerst contact op met de verkoper; Afriklink vergemakkelijkt de oplossing te goeder trouw.',
        'guarantees'      => 'Wettelijke garanties',
        'guarantees_p'    => 'Consumenten genieten de wettelijke garanties van conformiteit en tegen verborgen gebreken voorzien in ',
        'complaints'      => 'Klachten en geschillenbeslechting',
        'contact'         => 'Contact:',
        'liability'       => 'Aansprakelijkheid',
        'liability_p'     => 'Als tussenpersoon is Afriklink geen partij bij de verkoop en is zij, binnen de door de wet toegestane grenzen, niet aansprakelijk voor de uitvoering door de verkopers.',
        'law'             => 'Toepasselijk recht',
        'law_p1'          => 'Deze voorwaarden worden beheerst door het recht van de plaats van vestiging van de uitgever (',
        'law_p2'          => '). Consumenten behouden bovendien de dwingende bescherming van het recht van hun land van verblijf. De bevoegde rechtbanken worden bepaald door het toepasselijke recht.',
    ],
    'ar' => [
        'purpose'         => 'الموضوع',
        'purpose_p'       => 'تحكم هذه الشروط استخدام سوق Afriklink والمبيعات المبرمة من خلاله.',
        'role'            => 'دور Afriklink',
        'role_p'          => 'Afriklink وسيط. يُبرم عقد البيع مباشرةً بين البائع والمشتري. البائع مسؤول عن منتجاته وأسعاره وأوصافه ومخزونه والتسليم وخدمة ما بعد البيع.',
        'account'         => 'الحساب',
        'account_p'       => 'يجب عليك تقديم معلومات صحيحة والحفاظ على سرية بيانات الدخول الخاصة بك. يُجري البائعون المحترفون التحقق من الهوية (KYC).',
        'p2b'             => 'البائعون المحترفون (P2B)',
        'p2b_p'           => 'وفقًا للائحة (الاتحاد الأوروبي) 2019/1150 (P2B)، يتم الإفصاح عن المعايير الرئيسية لترتيب الإعلانات (الملاءمة، التوافر، سمعة البائع، الرعاية)، ويُضمن وصول البائع إلى بياناته الخاصة، وتُعلَّل قرارات تعليق الحساب مع توفير قناة شكوى داخلية.',
        'orders'          => 'الطلبات والأسعار والدفع',
        'orders_p'        => 'تُعرض الأسعار بالعملة المبيّنة، شاملةً الضرائب عند الاقتضاء، مع بيان تكاليف التسليم قبل الطلب. يصبح الطلب نهائيًا بمجرد تأكيده. شروط ووسائل الدفع هي تلك التي يقترحها كل بائع.',
        'currency_region' => 'العملة المعتمدة في منطقتك:',
        'delivery'        => 'التسليم',
        'delivery_p'      => 'يحدد البائع آجال التسليم وتكاليفه حسب المنطقة (محلية / دولية). تنتقل المخاطر عند التسليم.',
        'withdrawal'      => 'حق الانسحاب / الإرجاع',
        'withdrawal_yes'  => 'بصفتك مستهلكًا، تتوفر لديك مهلة 14 يومًا من تاريخ الاستلام للانسحاب من عملية شراء عن بُعد مؤهلة، دون إبداء الأسباب، مع مراعاة الاستثناءات القانونية (المواد القابلة للتلف، المنتجات المصنوعة حسب الطلب، منتجات النظافة غير المختومة، إلخ.).',
        'withdrawal_link' => 'الاطلاع على التفاصيل ونموذج الانسحاب',
        'withdrawal_no'   => 'تخضع عمليات الإرجاع والاسترداد للسياسة المنشورة من قِبل كل بائع ولقانون الاستهلاك المعمول به في بلدك. اتصل أولًا بالبائع؛ تيسّر Afriklink الحل بحسن نية.',
        'guarantees'      => 'الضمانات القانونية',
        'guarantees_p'    => 'يستفيد المستهلكون من الضمانات القانونية للمطابقة وضد العيوب الخفية المنصوص عليها في ',
        'complaints'      => 'الشكاوى وتسوية النزاعات',
        'contact'         => 'للتواصل:',
        'liability'       => 'المسؤولية',
        'liability_p'     => 'بصفتها وسيطًا، ليست Afriklink طرفًا في عملية البيع وليست مسؤولة عن تنفيذ البائعين لالتزاماتهم، في الحدود التي يسمح بها القانون.',
        'law'             => 'القانون الواجب التطبيق',
        'law_p1'          => 'تخضع هذه الشروط لقانون مكان إقامة الناشر (',
        'law_p2'          => '). كما يحتفظ المستهلكون بأوجه الحماية الإلزامية المقررة في قانون بلد إقامتهم. تُحدَّد المحاكم المختصة وفقًا للقانون الواجب التطبيق.',
    ],
];
$T = $TX[current_locale()] ?? $TX['en'];
?>
<section class="legal-page">
    <h1><?= e(t('legal.terms.title')) ?><?php if ($L['is_eu']): ?> <span class="legal-aka">/ AGB</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/cgv']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <h2>1. <?= e($T['purpose']) ?></h2>
    <p><?= e($T['purpose_p']) ?></p>

    <h2>2. <?= e($T['role']) ?></h2>
    <p><?= e($T['role_p']) ?></p>

    <h2>3. <?= e($T['account']) ?></h2>
    <p><?= e($T['account_p']) ?></p>

    <h2>4. <?= e($T['p2b']) ?></h2>
    <p><?= e($T['p2b_p']) ?></p>

    <h2>5. <?= e($T['orders']) ?></h2>
    <p><?= e($T['orders_p']) ?></p>
    <?php if ($currency !== ''): ?><p class="muted"><?= e($T['currency_region']) ?> <?= e($currency) ?>.</p><?php endif; ?>

    <h2>6. <?= e($T['delivery']) ?></h2>
    <p><?= e($T['delivery_p']) ?></p>

    <h2>7. <?= e($T['withdrawal']) ?></h2>
    <?php if ($rg['withdrawal'] ?? false): ?>
        <p><?= e($T['withdrawal_yes']) ?>
            <a href="<?= e(url('/retractation')) ?>"><?= e($T['withdrawal_link']) ?></a>.</p>
    <?php else: ?>
        <p><?= e($T['withdrawal_no']) ?></p>
    <?php endif; ?>

    <h2>8. <?= e($T['guarantees']) ?></h2>
    <p><?= e($T['guarantees_p']) ?><?= e($consumer) ?>.</p>

    <h2>9. <?= e($T['complaints']) ?></h2>
    <p><?= e($dispute) ?> <?= e($T['contact']) ?> <strong><?= e($op['email'] ?? '') ?></strong>.</p>

    <h2>10. <?= e($T['liability']) ?></h2>
    <p><?= e($T['liability_p']) ?></p>

    <h2>11. <?= e($T['law']) ?></h2>
    <p><?= e($T['law_p1']) . e($seat) . e($T['law_p2']) ?></p>
</section>
