<?php
/** Page « À propos » — éditoriale, narrative, professionnelle. Bilingue (inline).
 *  Design intégré au site (en-tête/pied fournis par le layout), scopé à .about-page. */
$loggedIn = current_user() !== null;
$sellHref = $loggedIn ? url('/boutique/creer') : url('/register/vendeur');

// Icônes SVG fines réutilisées (cohérentes, jamais d'emoji).
$svg = [
    'lock'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6z"/><path d="M9.5 12l2 2 3.5-4"/></svg>',
    'globe'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>',
    'chat'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/></svg>',
    'bag'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8h12l1 12H5z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>',
    'utens'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3v8a2 2 0 0 0 4 0V3M8 11v10M18 3c-2 0-3 2-3 5s1 4 3 4v9"/></svg>',
    'scis'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.5 7.5L20 18M8.5 16.5L20 6"/></svg>',
    'tools'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.3 5.3L4 17l3 3 5.4-5.4a4 4 0 0 0 5.3-5.3l-2.5 2.5-2-2z"/></svg>',
    'check'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.5 12l2 2 3.5-4"/></svg>',
];

// Contenu éditorial par langue. Toute langue absente retombe sur l'anglais.
$ABOUT = ['en' => [
    'home' => 'Home',
    'hero_eyebrow' => 'West Africa ↔ Europe',
    'hero_h1' => 'A marketplace that connects Africa and Europe.',
    'hero_lead' => 'Shops, restaurants, salons and services on a single platform — to sell and buy, near home or worldwide, in several languages and currencies, with confidence.',
    'cta_explore' => 'Explore the marketplace', 'cta_sell' => 'Open my shop — free', 'cta_seller' => 'Become a seller',
    'trust' => ['Secure payments', 'Verified sellers', 'Local & international'],
    'thread' => ['West Africa', 'Europe'],
    'why_k' => 'Why AfrikaLink',
    'why_big' => ['Selling between West Africa and Europe shouldn’t be an obstacle course. ', 'Today, it still is.'],
    'why_p1' => 'Shifting currencies, multiple languages, complicated payments, trust that’s hard to build at a distance: too many talented artisans, restaurateurs and traders stay invisible beyond their neighbourhood.',
    'why_p2' => 'AfrikaLink exists to remove these barriers one by one — and bring together, in one place, those who create and those who seek, on both sides of the Mediterranean.',
    'founder_k' => 'A word from the founder',
    'founder_q' => '“AfrikaLink was born from a simple observation: there was no place where an artisan in Abidjan and a customer in Paris could meet in confidence. I began building this platform alone, line by line, with one belief — technology should serve people, not the other way around. Thank you for being among the first.”',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Founder of AfrikaLink',
    'mission_k' => 'Our mission',
    'mission_h' => 'Let anyone sell and buy, locally and internationally — with confidence, whatever their language and currency.',
    'uni_k' => 'One platform, four worlds', 'uni_h' => 'Everything that sells, in one place',
    'uni' => [['bag', 'Shops', 'Sell physical products, with stock management and local or international delivery.'],
        ['utens', 'Restaurants', 'Publish your menus and take orders for pickup or delivery.'],
        ['scis', 'Salons', 'Offer your services and let clients book a slot online.'],
        ['tools', 'Trades & services', 'Plumber, tailor, coach… present your services and receive requests.']],
    'how_k' => 'How it works', 'how_h' => 'Simple on both sides',
    'buyer_t' => 'Buyer', 'buyer_h' => 'Buy in 3 steps', 'seller_t' => 'Seller', 'seller_h' => 'Sell in 3 steps',
    'buyer' => [['Explore', 'Browse shops, dishes and services near you or abroad.'], ['Order or book', 'Cart, pickup, delivery or slot booking — depending on the seller.'], ['Pay with confidence', 'Secure payment, verified seller, order tracking.']],
    'seller' => [['Create your space', 'Free, in minutes. Choose your world.'], ['Publish', 'Products, menu or services, with photos, prices and availability.'], ['Sell everywhere', 'Get paid locally and internationally, in several currencies.']],
    'val_k' => 'What guides us', 'val_h' => 'Our values',
    'val' => [['Trust first', 'Verified seller identity and payments protected at every step.'],
        ['Borderless', 'Several languages, several currencies: the whole world as your market.'],
        ['For the pros', 'Simple tools and a fair commission, built for independents.'],
        ['Closeness matters', 'The shop next door as much as the other continent.']],
    'gar_k' => 'Trust & safety', 'gar_h' => 'Your guarantees', 'gar_more' => 'Learn more →',
    'gar' => [['lock', 'Secure payments', 'Your transactions are protected at every step.', '/paiements-securises'],
        ['shield', 'Verified sellers', 'Professionals’ identity is checked (KYC).', '/vendeurs-verifies'],
        ['globe', 'Local & international', 'Buy and sell near you or abroad.', '/local-international'],
        ['chat', 'Built-in support', 'An assistant and seller messaging to help you.', '/assistance']],
    'pledge_k' => 'Our commitments', 'pledge_h' => 'A young platform, clear promises',
    'pledge' => [['check', 'Free to open', 'Creating a shop costs nothing.'],
        ['shield', 'Verified sellers', 'Systematic identity check (KYC).'],
        ['lock', 'Protected payments', 'Secured at every step.'],
        ['chat', 'FR & EN support', 'Help in your language.']],
    'final_h' => 'Ready to join AfrikaLink?', 'final_lead' => 'Whether you come to buy or to sell, your place is already set.',
], 'fr' => [
    'home' => 'Accueil',
    'hero_eyebrow' => 'Afrique de l’Ouest ↔ Europe',
    'hero_h1' => 'Une place de marché qui relie l’Afrique et l’Europe.',
    'hero_lead' => 'Boutiques, restaurants, salons et services réunis sur une seule plateforme — pour vendre et acheter, près de chez vous comme à l’international, en plusieurs langues et devises, en toute confiance.',
    'cta_explore' => 'Explorer la marketplace', 'cta_sell' => 'Ouvrir ma boutique — gratuit', 'cta_seller' => 'Devenir vendeur',
    'trust' => ['Paiements sécurisés', 'Vendeurs vérifiés', 'Local & international'],
    'thread' => ['Afrique de l’Ouest', 'Europe'],
    'why_k' => 'Pourquoi AfrikaLink',
    'why_big' => ['Vendre entre l’Afrique de l’Ouest et l’Europe ne devrait pas être un parcours du combattant. ', 'Aujourd’hui, ça l’est encore.'],
    'why_p1' => 'Devises qui changent, langues multiples, paiements compliqués, confiance difficile à établir à distance : trop d’artisans, de restaurateurs et de commerçants pleins de talent restent invisibles au-delà de leur quartier.',
    'why_p2' => 'AfrikaLink existe pour lever ces barrières une par une — et réunir, au même endroit, ceux qui créent et ceux qui cherchent, des deux côtés de la Méditerranée.',
    'founder_k' => 'Le mot du fondateur',
    'founder_q' => '« AfrikaLink est née d’un constat simple : il manquait un endroit où un artisan d’Abidjan et un client de Paris pouvaient se rencontrer en confiance. J’ai commencé à construire cette plateforme seul, ligne après ligne, avec une conviction — la technologie doit servir les gens, pas l’inverse. Merci d’être parmi les premiers. »',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Fondateur d’AfrikaLink',
    'mission_k' => 'Notre mission',
    'mission_h' => 'Permettre à chacun de vendre et d’acheter, en local comme à l’international — en toute confiance, quelles que soient sa langue et sa devise.',
    'uni_k' => 'Une plateforme, quatre univers', 'uni_h' => 'Tout ce qui se vend, au même endroit',
    'uni' => [['bag', 'Boutiques', 'Vendez des produits physiques, avec gestion du stock et livraison locale ou internationale.'],
        ['utens', 'Restaurants', 'Publiez vos menus et recevez des commandes en retrait ou en livraison.'],
        ['scis', 'Salons', 'Proposez vos prestations et laissez vos clients réserver un créneau en ligne.'],
        ['tools', 'Métiers & services', 'Plombier, couturier, coach… présentez vos services et recevez des demandes.']],
    'how_k' => 'Comment ça marche', 'how_h' => 'Simple des deux côtés',
    'buyer_t' => 'Acheteur', 'buyer_h' => 'Acheter en 3 étapes', 'seller_t' => 'Vendeur', 'seller_h' => 'Vendre en 3 étapes',
    'buyer' => [['Explorez', 'Parcourez boutiques, plats et services près de chez vous ou à l’étranger.'], ['Commandez ou réservez', 'Panier, retrait, livraison ou réservation de créneau — selon le vendeur.'], ['Payez en confiance', 'Paiement sécurisé, vendeur vérifié, suivi de votre commande.']],
    'seller' => [['Créez votre espace', 'Gratuit, en quelques minutes. Choisissez votre univers.'], ['Publiez', 'Produits, menu ou prestations, avec photos, prix et disponibilités.'], ['Vendez partout', 'Encaissez en local et à l’international, en plusieurs devises.']],
    'val_k' => 'Ce qui nous guide', 'val_h' => 'Nos valeurs',
    'val' => [['La confiance d’abord', 'Identité des vendeurs vérifiée et paiements protégés à chaque étape.'],
        ['Sans frontières', 'Plusieurs langues, plusieurs devises : le monde entier comme marché.'],
        ['Au service des pros', 'Des outils simples et une commission juste, pensés pour les indépendants.'],
        ['La proximité compte', 'Le quartier d’à côté autant que l’autre continent.']],
    'gar_k' => 'Confiance & sécurité', 'gar_h' => 'Vos garanties', 'gar_more' => 'En savoir plus →',
    'gar' => [['lock', 'Paiements sécurisés', 'Vos transactions sont protégées à chaque étape.', '/paiements-securises'],
        ['shield', 'Vendeurs vérifiés', 'L’identité des professionnels est contrôlée (KYC).', '/vendeurs-verifies'],
        ['globe', 'Local & international', 'Achetez et vendez près de chez vous comme à l’étranger.', '/local-international'],
        ['chat', 'Assistance intégrée', 'Un assistant et la messagerie vendeur pour vous aider.', '/assistance']],
    'pledge_k' => 'Nos engagements', 'pledge_h' => 'Une jeune plateforme, des promesses claires',
    'pledge' => [['check', 'Ouverture gratuite', 'Créer sa boutique ne coûte rien.'],
        ['shield', 'Vendeurs vérifiés', 'Contrôle d’identité (KYC) systématique.'],
        ['lock', 'Paiements protégés', 'Sécurisés à chaque étape.'],
        ['chat', 'Support FR & EN', 'Une aide dans votre langue.']],
    'final_h' => 'Prêt à rejoindre AfrikaLink ?', 'final_lead' => 'Que vous veniez acheter ou vendre, votre place est déjà prête.',
], 'de' => [
    'home' => 'Startseite',
    'hero_eyebrow' => 'Westafrika ↔ Europa',
    'hero_h1' => 'Ein Marktplatz, der Afrika und Europa verbindet.',
    'hero_lead' => 'Shops, Restaurants, Salons und Dienstleistungen auf einer einzigen Plattform — zum Verkaufen und Kaufen, in Ihrer Nähe oder weltweit, in mehreren Sprachen und Währungen, mit Vertrauen.',
    'cta_explore' => 'Marktplatz entdecken', 'cta_sell' => 'Meinen Shop eröffnen — kostenlos', 'cta_seller' => 'Verkäufer werden',
    'trust' => ['Sichere Zahlungen', 'Verifizierte Verkäufer', 'Lokal & international'],
    'thread' => ['Westafrika', 'Europa'],
    'why_k' => 'Warum AfrikaLink',
    'why_big' => ['Der Handel zwischen Westafrika und Europa sollte kein Hindernislauf sein. ', 'Heute ist er es noch.'],
    'why_p1' => 'Wechselnde Währungen, viele Sprachen, komplizierte Zahlungen, schwer aufzubauendes Vertrauen über Distanz: Zu viele talentierte Handwerker, Gastronomen und Händler bleiben über ihr Viertel hinaus unsichtbar.',
    'why_p2' => 'AfrikaLink gibt es, um diese Barrieren eine nach der anderen abzubauen — und an einem Ort jene zusammenzubringen, die schaffen, und jene, die suchen, auf beiden Seiten des Mittelmeers.',
    'founder_k' => 'Ein Wort des Gründers',
    'founder_q' => '„AfrikaLink entstand aus einer einfachen Beobachtung: Es fehlte ein Ort, an dem sich ein Handwerker in Abidjan und ein Kunde in Paris vertrauensvoll begegnen konnten. Ich habe diese Plattform allein aufgebaut, Zeile für Zeile, mit einer Überzeugung — Technik soll den Menschen dienen, nicht umgekehrt. Danke, dass Sie zu den Ersten gehören.“',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Gründer von AfrikaLink',
    'mission_k' => 'Unsere Mission',
    'mission_h' => 'Jedem ermöglichen, lokal und international zu verkaufen und zu kaufen — mit Vertrauen, unabhängig von Sprache und Währung.',
    'uni_k' => 'Eine Plattform, vier Welten', 'uni_h' => 'Alles, was verkauft wird, an einem Ort',
    'uni' => [['bag', 'Shops', 'Verkaufen Sie physische Produkte, mit Bestandsverwaltung und lokalem oder internationalem Versand.'],
        ['utens', 'Restaurants', 'Veröffentlichen Sie Ihre Menüs und nehmen Sie Bestellungen zur Abholung oder Lieferung entgegen.'],
        ['scis', 'Salons', 'Bieten Sie Ihre Dienstleistungen an und lassen Sie Kunden online einen Termin buchen.'],
        ['tools', 'Handwerk & Dienstleistungen', 'Klempner, Schneider, Coach … präsentieren Sie Ihre Leistungen und erhalten Sie Anfragen.']],
    'how_k' => 'So funktioniert es', 'how_h' => 'Einfach auf beiden Seiten',
    'buyer_t' => 'Käufer', 'buyer_h' => 'In 3 Schritten kaufen', 'seller_t' => 'Verkäufer', 'seller_h' => 'In 3 Schritten verkaufen',
    'buyer' => [['Entdecken', 'Durchstöbern Sie Shops, Gerichte und Dienstleistungen in Ihrer Nähe oder im Ausland.'], ['Bestellen oder buchen', 'Warenkorb, Abholung, Lieferung oder Terminbuchung — je nach Verkäufer.'], ['Mit Vertrauen bezahlen', 'Sichere Zahlung, verifizierter Verkäufer, Bestellverfolgung.']],
    'seller' => [['Ihren Bereich anlegen', 'Kostenlos, in wenigen Minuten. Wählen Sie Ihre Welt.'], ['Veröffentlichen', 'Produkte, Menü oder Leistungen, mit Fotos, Preisen und Verfügbarkeit.'], ['Überall verkaufen', 'Erhalten Sie Zahlungen lokal und international, in mehreren Währungen.']],
    'val_k' => 'Was uns leitet', 'val_h' => 'Unsere Werte',
    'val' => [['Vertrauen zuerst', 'Geprüfte Verkäuferidentität und bei jedem Schritt geschützte Zahlungen.'],
        ['Ohne Grenzen', 'Mehrere Sprachen, mehrere Währungen: die ganze Welt als Ihr Markt.'],
        ['Für Profis', 'Einfache Werkzeuge und eine faire Provision, für Selbstständige gemacht.'],
        ['Nähe zählt', 'Der Laden nebenan ebenso wie der andere Kontinent.']],
    'gar_k' => 'Vertrauen & Sicherheit', 'gar_h' => 'Ihre Garantien', 'gar_more' => 'Mehr erfahren →',
    'gar' => [['lock', 'Sichere Zahlungen', 'Ihre Transaktionen sind bei jedem Schritt geschützt.', '/paiements-securises'],
        ['shield', 'Verifizierte Verkäufer', 'Die Identität der Fachleute wird geprüft (KYC).', '/vendeurs-verifies'],
        ['globe', 'Lokal & international', 'Kaufen und verkaufen Sie in Ihrer Nähe oder im Ausland.', '/local-international'],
        ['chat', 'Integrierter Support', 'Ein Assistent und Verkäufer-Messaging, die Ihnen helfen.', '/assistance']],
    'pledge_k' => 'Unsere Verpflichtungen', 'pledge_h' => 'Eine junge Plattform, klare Versprechen',
    'pledge' => [['check', 'Kostenlose Eröffnung', 'Einen Shop zu erstellen kostet nichts.'],
        ['shield', 'Verifizierte Verkäufer', 'Systematische Identitätsprüfung (KYC).'],
        ['lock', 'Geschützte Zahlungen', 'Bei jedem Schritt abgesichert.'],
        ['chat', 'Support DE & EN', 'Hilfe in Ihrer Sprache.']],
    'final_h' => 'Bereit, AfrikaLink beizutreten?', 'final_lead' => 'Ob Sie zum Kaufen oder zum Verkaufen kommen, Ihr Platz steht bereit.',
], 'es' => [
    'home' => 'Inicio',
    'hero_eyebrow' => 'África Occidental ↔ Europa',
    'hero_h1' => 'Un mercado que conecta África y Europa.',
    'hero_lead' => 'Tiendas, restaurantes, salones y servicios en una sola plataforma — para vender y comprar, cerca de casa o en todo el mundo, en varios idiomas y monedas, con confianza.',
    'cta_explore' => 'Explorar el mercado', 'cta_sell' => 'Abrir mi tienda — gratis', 'cta_seller' => 'Conviértete en vendedor',
    'trust' => ['Pagos seguros', 'Vendedores verificados', 'Local e internacional'],
    'thread' => ['África Occidental', 'Europa'],
    'why_k' => 'Por qué AfrikaLink',
    'why_big' => ['Vender entre África Occidental y Europa no debería ser una carrera de obstáculos. ', 'Hoy todavía lo es.'],
    'why_p1' => 'Monedas que cambian, idiomas múltiples, pagos complicados, confianza difícil de construir a distancia: demasiados artesanos, restauradores y comerciantes con talento siguen siendo invisibles más allá de su barrio.',
    'why_p2' => 'AfrikaLink existe para derribar estas barreras una a una — y reunir, en un mismo lugar, a quienes crean y a quienes buscan, a ambos lados del Mediterráneo.',
    'founder_k' => 'Unas palabras del fundador',
    'founder_q' => '«AfrikaLink nació de una observación sencilla: faltaba un lugar donde un artesano de Abiyán y un cliente de París pudieran encontrarse con confianza. Empecé a construir esta plataforma solo, línea a línea, con una convicción — la tecnología debe servir a las personas, no al revés. Gracias por estar entre los primeros.»',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Fundador de AfrikaLink',
    'mission_k' => 'Nuestra misión',
    'mission_h' => 'Permitir que cualquiera venda y compre, a nivel local e internacional — con confianza, sea cual sea su idioma y su moneda.',
    'uni_k' => 'Una plataforma, cuatro mundos', 'uni_h' => 'Todo lo que se vende, en un mismo lugar',
    'uni' => [['bag', 'Tiendas', 'Vende productos físicos, con gestión de stock y entrega local o internacional.'],
        ['utens', 'Restaurantes', 'Publica tus menús y recibe pedidos para recoger o entregar.'],
        ['scis', 'Salones', 'Ofrece tus servicios y deja que los clientes reserven una cita en línea.'],
        ['tools', 'Oficios y servicios', 'Fontanero, sastre, entrenador… presenta tus servicios y recibe solicitudes.']],
    'how_k' => 'Cómo funciona', 'how_h' => 'Sencillo para ambas partes',
    'buyer_t' => 'Comprador', 'buyer_h' => 'Compra en 3 pasos', 'seller_t' => 'Vendedor', 'seller_h' => 'Vende en 3 pasos',
    'buyer' => [['Explora', 'Recorre tiendas, platos y servicios cerca de ti o en el extranjero.'], ['Pide o reserva', 'Carrito, recogida, entrega o reserva de cita — según el vendedor.'], ['Paga con confianza', 'Pago seguro, vendedor verificado, seguimiento de tu pedido.']],
    'seller' => [['Crea tu espacio', 'Gratis, en pocos minutos. Elige tu mundo.'], ['Publica', 'Productos, menú o servicios, con fotos, precios y disponibilidad.'], ['Vende en todas partes', 'Cobra a nivel local e internacional, en varias monedas.']],
    'val_k' => 'Lo que nos guía', 'val_h' => 'Nuestros valores',
    'val' => [['La confianza primero', 'Identidad de los vendedores verificada y pagos protegidos en cada paso.'],
        ['Sin fronteras', 'Varios idiomas, varias monedas: el mundo entero como mercado.'],
        ['Al servicio de los profesionales', 'Herramientas sencillas y una comisión justa, pensadas para los autónomos.'],
        ['La cercanía importa', 'La tienda de al lado tanto como el otro continente.']],
    'gar_k' => 'Confianza y seguridad', 'gar_h' => 'Tus garantías', 'gar_more' => 'Saber más →',
    'gar' => [['lock', 'Pagos seguros', 'Tus transacciones están protegidas en cada paso.', '/paiements-securises'],
        ['shield', 'Vendedores verificados', 'Se comprueba la identidad de los profesionales (KYC).', '/vendeurs-verifies'],
        ['globe', 'Local e internacional', 'Compra y vende cerca de ti o en el extranjero.', '/local-international'],
        ['chat', 'Asistencia integrada', 'Un asistente y la mensajería del vendedor para ayudarte.', '/assistance']],
    'pledge_k' => 'Nuestros compromisos', 'pledge_h' => 'Una plataforma joven, promesas claras',
    'pledge' => [['check', 'Apertura gratuita', 'Crear una tienda no cuesta nada.'],
        ['shield', 'Vendedores verificados', 'Verificación de identidad (KYC) sistemática.'],
        ['lock', 'Pagos protegidos', 'Asegurados en cada paso.'],
        ['chat', 'Soporte ES e EN', 'Ayuda en tu idioma.']],
    'final_h' => '¿Listo para unirte a AfrikaLink?', 'final_lead' => 'Ya vengas a comprar o a vender, tu lugar ya está reservado.',
], 'it' => [
    'home' => 'Home',
    'hero_eyebrow' => 'Africa occidentale ↔ Europa',
    'hero_h1' => 'Un marketplace che collega Africa ed Europa.',
    'hero_lead' => 'Negozi, ristoranti, saloni e servizi su un’unica piattaforma — per vendere e comprare, vicino a casa o in tutto il mondo, in più lingue e valute, con fiducia.',
    'cta_explore' => 'Esplora il marketplace', 'cta_sell' => 'Apri il mio negozio — gratis', 'cta_seller' => 'Diventa venditore',
    'trust' => ['Pagamenti sicuri', 'Venditori verificati', 'Locale e internazionale'],
    'thread' => ['Africa occidentale', 'Europa'],
    'why_k' => 'Perché AfrikaLink',
    'why_big' => ['Vendere tra l’Africa occidentale e l’Europa non dovrebbe essere una corsa a ostacoli. ', 'Oggi lo è ancora.'],
    'why_p1' => 'Valute che cambiano, lingue diverse, pagamenti complicati, fiducia difficile da costruire a distanza: troppi artigiani, ristoratori e commercianti di talento restano invisibili oltre il loro quartiere.',
    'why_p2' => 'AfrikaLink esiste per abbattere queste barriere una a una — e riunire, in un unico luogo, chi crea e chi cerca, su entrambe le sponde del Mediterraneo.',
    'founder_k' => 'Una parola dal fondatore',
    'founder_q' => '«AfrikaLink è nata da una semplice constatazione: mancava un luogo in cui un artigiano di Abidjan e un cliente di Parigi potessero incontrarsi con fiducia. Ho iniziato a costruire questa piattaforma da solo, riga dopo riga, con una convinzione — la tecnologia deve servire le persone, non il contrario. Grazie di essere tra i primi.»',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Fondatore di AfrikaLink',
    'mission_k' => 'La nostra missione',
    'mission_h' => 'Permettere a chiunque di vendere e comprare, a livello locale e internazionale — con fiducia, qualunque sia la sua lingua e la sua valuta.',
    'uni_k' => 'Una piattaforma, quattro mondi', 'uni_h' => 'Tutto ciò che si vende, in un unico luogo',
    'uni' => [['bag', 'Negozi', 'Vendi prodotti fisici, con gestione del magazzino e consegna locale o internazionale.'],
        ['utens', 'Ristoranti', 'Pubblica i tuoi menu e ricevi ordini per il ritiro o la consegna.'],
        ['scis', 'Saloni', 'Offri i tuoi servizi e lascia che i clienti prenotino un appuntamento online.'],
        ['tools', 'Mestieri e servizi', 'Idraulico, sarto, coach… presenta i tuoi servizi e ricevi richieste.']],
    'how_k' => 'Come funziona', 'how_h' => 'Semplice da entrambe le parti',
    'buyer_t' => 'Acquirente', 'buyer_h' => 'Compra in 3 passi', 'seller_t' => 'Venditore', 'seller_h' => 'Vendi in 3 passi',
    'buyer' => [['Esplora', 'Sfoglia negozi, piatti e servizi vicino a te o all’estero.'], ['Ordina o prenota', 'Carrello, ritiro, consegna o prenotazione di un appuntamento — a seconda del venditore.'], ['Paga con fiducia', 'Pagamento sicuro, venditore verificato, tracciamento dell’ordine.']],
    'seller' => [['Crea il tuo spazio', 'Gratis, in pochi minuti. Scegli il tuo mondo.'], ['Pubblica', 'Prodotti, menu o servizi, con foto, prezzi e disponibilità.'], ['Vendi ovunque', 'Incassa a livello locale e internazionale, in più valute.']],
    'val_k' => 'Ciò che ci guida', 'val_h' => 'I nostri valori',
    'val' => [['Prima la fiducia', 'Identità dei venditori verificata e pagamenti protetti in ogni fase.'],
        ['Senza frontiere', 'Più lingue, più valute: il mondo intero come mercato.'],
        ['Al servizio dei professionisti', 'Strumenti semplici e una commissione equa, pensati per gli autonomi.'],
        ['La vicinanza conta', 'Il negozio accanto tanto quanto l’altro continente.']],
    'gar_k' => 'Fiducia e sicurezza', 'gar_h' => 'Le tue garanzie', 'gar_more' => 'Scopri di più →',
    'gar' => [['lock', 'Pagamenti sicuri', 'Le tue transazioni sono protette in ogni fase.', '/paiements-securises'],
        ['shield', 'Venditori verificati', 'L’identità dei professionisti viene controllata (KYC).', '/vendeurs-verifies'],
        ['globe', 'Locale e internazionale', 'Compra e vendi vicino a te o all’estero.', '/local-international'],
        ['chat', 'Assistenza integrata', 'Un assistente e la messaggistica del venditore per aiutarti.', '/assistance']],
    'pledge_k' => 'I nostri impegni', 'pledge_h' => 'Una piattaforma giovane, promesse chiare',
    'pledge' => [['check', 'Apertura gratuita', 'Creare un negozio non costa nulla.'],
        ['shield', 'Venditori verificati', 'Controllo dell’identità (KYC) sistematico.'],
        ['lock', 'Pagamenti protetti', 'Sicuri in ogni fase.'],
        ['chat', 'Supporto IT e EN', 'Aiuto nella tua lingua.']],
    'final_h' => 'Pronto a unirti ad AfrikaLink?', 'final_lead' => 'Che tu venga per comprare o per vendere, il tuo posto è già pronto.',
], 'pt' => [
    'home' => 'Início',
    'hero_eyebrow' => 'África Ocidental ↔ Europa',
    'hero_h1' => 'Um marketplace que liga a África e a Europa.',
    'hero_lead' => 'Lojas, restaurantes, salões e serviços numa única plataforma — para vender e comprar, perto de casa ou em todo o mundo, em várias línguas e moedas, com confiança.',
    'cta_explore' => 'Explorar o marketplace', 'cta_sell' => 'Abrir a minha loja — grátis', 'cta_seller' => 'Tornar-se vendedor',
    'trust' => ['Pagamentos seguros', 'Vendedores verificados', 'Local e internacional'],
    'thread' => ['África Ocidental', 'Europa'],
    'why_k' => 'Porquê a AfrikaLink',
    'why_big' => ['Vender entre a África Ocidental e a Europa não devia ser uma corrida de obstáculos. ', 'Hoje, ainda é.'],
    'why_p1' => 'Moedas que mudam, várias línguas, pagamentos complicados, confiança difícil de construir à distância: demasiados artesãos, restauradores e comerciantes talentosos permanecem invisíveis para além do seu bairro.',
    'why_p2' => 'A AfrikaLink existe para derrubar estas barreiras uma a uma — e reunir, num só lugar, quem cria e quem procura, em ambos os lados do Mediterrâneo.',
    'founder_k' => 'Uma palavra do fundador',
    'founder_q' => '«A AfrikaLink nasceu de uma constatação simples: faltava um lugar onde um artesão de Abidjan e um cliente de Paris pudessem encontrar-se com confiança. Comecei a construir esta plataforma sozinho, linha após linha, com uma convicção — a tecnologia deve servir as pessoas, e não o contrário. Obrigado por estar entre os primeiros.»',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Fundador da AfrikaLink',
    'mission_k' => 'A nossa missão',
    'mission_h' => 'Permitir que qualquer pessoa venda e compre, a nível local e internacional — com confiança, seja qual for a sua língua e a sua moeda.',
    'uni_k' => 'Uma plataforma, quatro mundos', 'uni_h' => 'Tudo o que se vende, num só lugar',
    'uni' => [['bag', 'Lojas', 'Venda produtos físicos, com gestão de stock e entrega local ou internacional.'],
        ['utens', 'Restaurantes', 'Publique os seus menus e receba pedidos para levantamento ou entrega.'],
        ['scis', 'Salões', 'Ofereça os seus serviços e deixe os clientes marcarem um horário online.'],
        ['tools', 'Ofícios e serviços', 'Canalizador, alfaiate, treinador… apresente os seus serviços e receba pedidos.']],
    'how_k' => 'Como funciona', 'how_h' => 'Simples dos dois lados',
    'buyer_t' => 'Comprador', 'buyer_h' => 'Compre em 3 passos', 'seller_t' => 'Vendedor', 'seller_h' => 'Venda em 3 passos',
    'buyer' => [['Explore', 'Percorra lojas, pratos e serviços perto de si ou no estrangeiro.'], ['Encomende ou marque', 'Carrinho, levantamento, entrega ou marcação de horário — consoante o vendedor.'], ['Pague com confiança', 'Pagamento seguro, vendedor verificado, acompanhamento da sua encomenda.']],
    'seller' => [['Crie o seu espaço', 'Grátis, em poucos minutos. Escolha o seu mundo.'], ['Publique', 'Produtos, menu ou serviços, com fotos, preços e disponibilidade.'], ['Venda em todo o lado', 'Receba a nível local e internacional, em várias moedas.']],
    'val_k' => 'O que nos guia', 'val_h' => 'Os nossos valores',
    'val' => [['A confiança primeiro', 'Identidade dos vendedores verificada e pagamentos protegidos em cada passo.'],
        ['Sem fronteiras', 'Várias línguas, várias moedas: o mundo inteiro como mercado.'],
        ['Ao serviço dos profissionais', 'Ferramentas simples e uma comissão justa, pensadas para os independentes.'],
        ['A proximidade conta', 'A loja ao lado tanto como o outro continente.']],
    'gar_k' => 'Confiança e segurança', 'gar_h' => 'As suas garantias', 'gar_more' => 'Saber mais →',
    'gar' => [['lock', 'Pagamentos seguros', 'As suas transações estão protegidas em cada passo.', '/paiements-securises'],
        ['shield', 'Vendedores verificados', 'A identidade dos profissionais é verificada (KYC).', '/vendeurs-verifies'],
        ['globe', 'Local e internacional', 'Compre e venda perto de si ou no estrangeiro.', '/local-international'],
        ['chat', 'Apoio integrado', 'Um assistente e a mensagaria do vendedor para o ajudar.', '/assistance']],
    'pledge_k' => 'Os nossos compromissos', 'pledge_h' => 'Uma plataforma jovem, promessas claras',
    'pledge' => [['check', 'Abertura gratuita', 'Criar uma loja não custa nada.'],
        ['shield', 'Vendedores verificados', 'Verificação de identidade (KYC) sistemática.'],
        ['lock', 'Pagamentos protegidos', 'Seguros em cada passo.'],
        ['chat', 'Apoio PT e EN', 'Ajuda na sua língua.']],
    'final_h' => 'Pronto para se juntar à AfrikaLink?', 'final_lead' => 'Quer venha comprar ou vender, o seu lugar já está reservado.',
], 'nl' => [
    'home' => 'Home',
    'hero_eyebrow' => 'West-Afrika ↔ Europa',
    'hero_h1' => 'Een marktplaats die Afrika en Europa verbindt.',
    'hero_lead' => 'Winkels, restaurants, salons en diensten op één platform — om te verkopen en te kopen, dichtbij huis of wereldwijd, in meerdere talen en valuta’s, met vertrouwen.',
    'cta_explore' => 'Ontdek de marktplaats', 'cta_sell' => 'Mijn winkel openen — gratis', 'cta_seller' => 'Word verkoper',
    'trust' => ['Veilige betalingen', 'Geverifieerde verkopers', 'Lokaal & internationaal'],
    'thread' => ['West-Afrika', 'Europa'],
    'why_k' => 'Waarom AfrikaLink',
    'why_big' => ['Handelen tussen West-Afrika en Europa zou geen hindernisbaan moeten zijn. ', 'Vandaag is het dat nog.'],
    'why_p1' => 'Wisselende valuta’s, meerdere talen, ingewikkelde betalingen, vertrouwen dat moeilijk op afstand op te bouwen is: te veel getalenteerde ambachtslieden, restauranthouders en handelaren blijven onzichtbaar buiten hun wijk.',
    'why_p2' => 'AfrikaLink bestaat om deze barrières één voor één weg te nemen — en op één plek samen te brengen wie creëert en wie zoekt, aan beide zijden van de Middellandse Zee.',
    'founder_k' => 'Een woord van de oprichter',
    'founder_q' => '„AfrikaLink is ontstaan uit een eenvoudige vaststelling: er was geen plek waar een ambachtsman in Abidjan en een klant in Parijs elkaar in vertrouwen konden ontmoeten. Ik ben dit platform alleen gaan bouwen, regel voor regel, met één overtuiging — technologie moet de mens dienen, niet andersom. Bedankt dat u bij de eersten bent.“',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'Oprichter van AfrikaLink',
    'mission_k' => 'Onze missie',
    'mission_h' => 'Iedereen laten verkopen en kopen, lokaal en internationaal — met vertrouwen, ongeacht taal en valuta.',
    'uni_k' => 'Eén platform, vier werelden', 'uni_h' => 'Alles wat verkocht wordt, op één plek',
    'uni' => [['bag', 'Winkels', 'Verkoop fysieke producten, met voorraadbeheer en lokale of internationale levering.'],
        ['utens', 'Restaurants', 'Publiceer uw menu’s en ontvang bestellingen voor afhaling of levering.'],
        ['scis', 'Salons', 'Bied uw diensten aan en laat klanten online een afspraak boeken.'],
        ['tools', 'Vakmensen & diensten', 'Loodgieter, kleermaker, coach… presenteer uw diensten en ontvang aanvragen.']],
    'how_k' => 'Hoe het werkt', 'how_h' => 'Eenvoudig voor beide kanten',
    'buyer_t' => 'Koper', 'buyer_h' => 'Koop in 3 stappen', 'seller_t' => 'Verkoper', 'seller_h' => 'Verkoop in 3 stappen',
    'buyer' => [['Ontdek', 'Blader door winkels, gerechten en diensten bij u in de buurt of in het buitenland.'], ['Bestel of boek', 'Winkelwagen, afhaling, levering of afspraak boeken — afhankelijk van de verkoper.'], ['Betaal met vertrouwen', 'Veilige betaling, geverifieerde verkoper, bestelopvolging.']],
    'seller' => [['Maak uw ruimte aan', 'Gratis, in enkele minuten. Kies uw wereld.'], ['Publiceer', 'Producten, menu of diensten, met foto’s, prijzen en beschikbaarheid.'], ['Verkoop overal', 'Ontvang betalingen lokaal en internationaal, in meerdere valuta’s.']],
    'val_k' => 'Wat ons leidt', 'val_h' => 'Onze waarden',
    'val' => [['Vertrouwen eerst', 'Geverifieerde identiteit van verkopers en betalingen die bij elke stap beschermd zijn.'],
        ['Zonder grenzen', 'Meerdere talen, meerdere valuta’s: de hele wereld als markt.'],
        ['Voor de professionals', 'Eenvoudige tools en een eerlijke commissie, gemaakt voor zelfstandigen.'],
        ['Nabijheid telt', 'De winkel om de hoek net zo goed als het andere continent.']],
    'gar_k' => 'Vertrouwen & veiligheid', 'gar_h' => 'Uw garanties', 'gar_more' => 'Meer weten →',
    'gar' => [['lock', 'Veilige betalingen', 'Uw transacties zijn bij elke stap beschermd.', '/paiements-securises'],
        ['shield', 'Geverifieerde verkopers', 'De identiteit van professionals wordt gecontroleerd (KYC).', '/vendeurs-verifies'],
        ['globe', 'Lokaal & internationaal', 'Koop en verkoop bij u in de buurt of in het buitenland.', '/local-international'],
        ['chat', 'Ingebouwde ondersteuning', 'Een assistent en verkopersberichten om u te helpen.', '/assistance']],
    'pledge_k' => 'Onze beloften', 'pledge_h' => 'Een jong platform, duidelijke beloften',
    'pledge' => [['check', 'Gratis openen', 'Een winkel aanmaken kost niets.'],
        ['shield', 'Geverifieerde verkopers', 'Systematische identiteitscontrole (KYC).'],
        ['lock', 'Beschermde betalingen', 'Bij elke stap beveiligd.'],
        ['chat', 'Ondersteuning NL & EN', 'Hulp in uw taal.']],
    'final_h' => 'Klaar om bij AfrikaLink te komen?', 'final_lead' => 'Of u nu komt om te kopen of te verkopen, uw plek staat al klaar.',
], 'ar' => [
    'home' => 'الرئيسية',
    'hero_eyebrow' => 'غرب أفريقيا ↔ أوروبا',
    'hero_h1' => 'سوق إلكتروني يربط أفريقيا وأوروبا.',
    'hero_lead' => 'متاجر ومطاعم وصالونات وخدمات على منصة واحدة — للبيع والشراء، قريبًا منك أو في أنحاء العالم، بعدة لغات وعملات، بكل ثقة.',
    'cta_explore' => 'استكشف السوق', 'cta_sell' => 'افتح متجري — مجانًا', 'cta_seller' => 'كن بائعًا',
    'trust' => ['مدفوعات آمنة', 'بائعون موثَّقون', 'محلي ودولي'],
    'thread' => ['غرب أفريقيا', 'أوروبا'],
    'why_k' => 'لماذا AfrikaLink',
    'why_big' => ['ينبغي ألا يكون البيع بين غرب أفريقيا وأوروبا سباق عقبات. ', 'لكنه لا يزال كذلك اليوم.'],
    'why_p1' => 'عملات متغيرة، ولغات متعددة، ومدفوعات معقدة، وثقة يصعب بناؤها عن بُعد: يظل كثير من الحرفيين وأصحاب المطاعم والتجار الموهوبين غير مرئيين خارج حدود حيِّهم.',
    'why_p2' => 'وُجدت AfrikaLink لإزالة هذه الحواجز واحدًا تلو الآخر — ولتجمع في مكان واحد من يبدعون ومن يبحثون، على ضفّتي البحر المتوسط.',
    'founder_k' => 'كلمة المؤسس',
    'founder_q' => '«وُلدت AfrikaLink من ملاحظة بسيطة: لم يكن هناك مكان يلتقي فيه حرفيٌّ من أبيدجان وعميلٌ من باريس بثقة. بدأتُ بناء هذه المنصة وحدي، سطرًا بعد سطر، بقناعة واحدة — التقنية يجب أن تخدم الإنسان لا العكس. شكرًا لكونكم من الأوائل.»',
    'founder_name' => 'Bi Abraham Zika', 'founder_role' => 'مؤسس AfrikaLink',
    'mission_k' => 'مهمتنا',
    'mission_h' => 'تمكين كل شخص من البيع والشراء، محليًا ودوليًا — بكل ثقة، أيًا كانت لغته وعملته.',
    'uni_k' => 'منصة واحدة، أربعة عوالم', 'uni_h' => 'كل ما يُباع، في مكان واحد',
    'uni' => [['bag', 'متاجر', 'بِع منتجات مادية، مع إدارة المخزون والتوصيل المحلي أو الدولي.'],
        ['utens', 'مطاعم', 'انشر قوائمك واستقبل الطلبات للاستلام أو التوصيل.'],
        ['scis', 'صالونات', 'قدِّم خدماتك ودع العملاء يحجزون موعدًا عبر الإنترنت.'],
        ['tools', 'حِرف وخدمات', 'سبّاك، خيّاط، مدرّب… اعرض خدماتك واستقبل الطلبات.']],
    'how_k' => 'كيف تعمل', 'how_h' => 'بسيطة من الجانبين',
    'buyer_t' => 'مشترٍ', 'buyer_h' => 'اشترِ في 3 خطوات', 'seller_t' => 'بائع', 'seller_h' => 'بِع في 3 خطوات',
    'buyer' => [['استكشف', 'تصفّح المتاجر والأطباق والخدمات قريبًا منك أو في الخارج.'], ['اطلب أو احجز', 'سلة، استلام، توصيل أو حجز موعد — حسب البائع.'], ['ادفع بثقة', 'دفع آمن، بائع موثَّق، تتبّع طلبك.']],
    'seller' => [['أنشئ مساحتك', 'مجانًا، في دقائق. اختر عالمك.'], ['انشر', 'منتجات أو قائمة أو خدمات، مع الصور والأسعار والتوافر.'], ['بِع في كل مكان', 'احصل على مدفوعاتك محليًا ودوليًا، بعدة عملات.']],
    'val_k' => 'ما يوجِّهنا', 'val_h' => 'قيمنا',
    'val' => [['الثقة أولًا', 'هوية البائعين موثَّقة والمدفوعات محمية في كل خطوة.'],
        ['بلا حدود', 'عدة لغات، عدة عملات: العالم كله سوقك.'],
        ['في خدمة المحترفين', 'أدوات بسيطة وعمولة عادلة، مصممة للمستقلين.'],
        ['القرب مهم', 'المتجر المجاور بقدر القارة الأخرى.']],
    'gar_k' => 'الثقة والأمان', 'gar_h' => 'ضماناتك', 'gar_more' => 'اعرف المزيد →',
    'gar' => [['lock', 'مدفوعات آمنة', 'معاملاتك محمية في كل خطوة.', '/paiements-securises'],
        ['shield', 'بائعون موثَّقون', 'يتم التحقق من هوية المحترفين (KYC).', '/vendeurs-verifies'],
        ['globe', 'محلي ودولي', 'اشترِ وبِع قريبًا منك أو في الخارج.', '/local-international'],
        ['chat', 'دعم مدمج', 'مساعد ومراسلة البائع لمساعدتك.', '/assistance']],
    'pledge_k' => 'التزاماتنا', 'pledge_h' => 'منصة فتية، وعود واضحة',
    'pledge' => [['check', 'فتح مجاني', 'إنشاء متجر لا يكلّف شيئًا.'],
        ['shield', 'بائعون موثَّقون', 'تحقق منهجي من الهوية (KYC).'],
        ['lock', 'مدفوعات محمية', 'مؤمَّنة في كل خطوة.'],
        ['chat', 'دعم بالعربية والإنجليزية', 'مساعدة بلغتك.']],
    'final_h' => 'هل أنت مستعد للانضمام إلى AfrikaLink؟', 'final_lead' => 'سواء جئت للشراء أو للبيع، مكانك جاهز بالفعل.',
]];
$C = $ABOUT[current_locale()] ?? $ABOUT['en'];
?>
<div class="about-page">

  <!-- HERO -->
  <section class="ab-band ab-band--wax ab-hero">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['hero_eyebrow']) ?></p>
      <h1><?= e($C['hero_h1']) ?></h1>
      <p class="ab-lead"><?= e($C['hero_lead']) ?></p>
      <div class="ab-actions">
        <a class="ab-btn ab-btn-primary" href="<?= e(url('/explorer')) ?>"><?= e($C['cta_explore']) ?></a>
        <a class="ab-btn ab-btn-gold" href="<?= e($sellHref) ?>"><?= e($C['cta_sell']) ?></a>
      </div>
      <div class="ab-hero-trust">
        <span><?= $svg['lock'] ?><?= e($C['trust'][0]) ?></span>
        <span><?= $svg['shield'] ?><?= e($C['trust'][1]) ?></span>
        <span><?= $svg['globe'] ?><?= e($C['trust'][2]) ?></span>
      </div>
      <div class="ab-thread" aria-hidden="true">
        <span class="ab-node"><?= e($C['thread'][0]) ?></span><span class="ab-rail"></span><span class="ab-node"><?= e($C['thread'][1]) ?></span>
      </div>
    </div>
  </section>

  <!-- POURQUOI -->
  <section class="ab-band ab-band--paper">
    <div class="ab-wrap ab-split reveal">
      <div>
        <p class="ab-eyebrow"><?= e($C['why_k']) ?></p>
        <p class="ab-big"><?= e($C['why_big'][0]) ?><em><?= e($C['why_big'][1]) ?></em></p>
      </div>
      <div><p><?= e($C['why_p1']) ?></p><p><?= e($C['why_p2']) ?></p></div>
    </div>
  </section>

  <!-- MOT DU FONDATEUR -->
  <section class="ab-band">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['founder_k']) ?></p>
      <div class="ab-founder">
        <div class="ab-portrait">
          <div class="ab-avatar">
            <img src="<?= e(url('/assets/img/founder.jpeg')) ?>" alt="<?= e($C['founder_name']) ?>" loading="lazy" data-hide-on-error>
            <span class="ab-avatar__i" aria-hidden="true"><?= e(mb_substr($C['founder_name'], 0, 1)) ?></span>
          </div>
          <span class="ab-portrait__seal" aria-hidden="true"><?= render_partial('partials/logo', ['uid' => 'founder-seal']) ?></span>
          <span class="ab-portrait__tag"><?= e($C['founder_role']) ?></span>
        </div>
        <div>
          <blockquote><?= e($C['founder_q']) ?></blockquote>
          <p class="ab-sig"><?= e($C['founder_name']) ?><small><?= e($C['founder_role']) ?></small></p>
        </div>
      </div>
    </div>
  </section>

  <!-- MISSION -->
  <section class="ab-band ab-band--green ab-band--wax">
    <div class="ab-wrap reveal" style="max-width:880px">
      <p class="ab-eyebrow"><?= e($C['mission_k']) ?></p>
      <h2><?= e($C['mission_h']) ?></h2>
    </div>
  </section>

  <!-- QUATRE UNIVERS -->
  <section class="ab-band ab-band--paper">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['uni_k']) ?></p>
      <h2><?= e($C['uni_h']) ?></h2>
      <div class="ab-grid4">
        <?php foreach ($C['uni'] as $u): ?>
          <article class="ab-card"><div class="ab-ic"><?= $svg[$u[0]] ?></div><h3><?= e($u[1]) ?></h3><p><?= e($u[2]) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- COMMENT ÇA MARCHE -->
  <section class="ab-band">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['how_k']) ?></p>
      <h2><?= e($C['how_h']) ?></h2>
      <div class="ab-how">
        <?php foreach ([['buyer_t', 'buyer_h', 'buyer'], ['seller_t', 'seller_h', 'seller']] as [$tk, $hk, $sk]): ?>
          <div class="ab-col">
            <h3><span class="ab-tag"><?= e($C[$tk]) ?></span> <?= e($C[$hk]) ?></h3>
            <?php foreach ($C[$sk] as $i => $st): ?>
              <div class="ab-step"><span class="ab-num"><?= (int) $i + 1 ?></span><div><h4><?= e($st[0]) ?></h4><p><?= e($st[1]) ?></p></div></div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- VALEURS -->
  <section class="ab-band ab-band--paper">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['val_k']) ?></p>
      <h2><?= e($C['val_h']) ?></h2>
      <div class="ab-values">
        <?php foreach ($C['val'] as $v): ?>
          <div class="ab-value"><h3><?= e($v[0]) ?></h3><p><?= e($v[1]) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CONFIANCE & SÉCURITÉ -->
  <section class="ab-band">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['gar_k']) ?></p>
      <h2><?= e($C['gar_h']) ?></h2>
      <div class="ab-grid4">
        <?php foreach ($C['gar'] as $gg): ?>
          <a class="ab-card ab-card--link" href="<?= e(url($gg[3])) ?>"><div class="ab-ic"><?= $svg[$gg[0]] ?></div><h3><?= e($gg[1]) ?></h3><p><?= e($gg[2]) ?></p><span class="ab-more"><?= e($C['gar_more']) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ENGAGEMENTS -->
  <section class="ab-band ab-band--green ab-band--wax">
    <div class="ab-wrap reveal">
      <p class="ab-eyebrow"><?= e($C['pledge_k']) ?></p>
      <h2 style="max-width:20ch"><?= e($C['pledge_h']) ?></h2>
      <div class="ab-pledges">
        <?php foreach ($C['pledge'] as $pl): ?>
          <div class="ab-pledge"><?= $svg[$pl[0]] ?><strong><?= e($pl[1]) ?></strong><span><?= e($pl[2]) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CTA FINAL -->
  <section class="ab-band ab-cta-final">
    <div class="ab-wrap reveal">
      <h2><?= e($C['final_h']) ?></h2>
      <p class="ab-lead"><?= e($C['final_lead']) ?></p>
      <div class="ab-row">
        <a class="ab-btn ab-btn-primary" href="<?= e(url('/explorer')) ?>"><?= e($C['cta_explore']) ?></a>
        <a class="ab-btn ab-btn-gold" href="<?= e($sellHref) ?>"><?= e($C['cta_seller']) ?></a>
      </div>
    </div>
  </section>

</div>
