<?php
/** Page « À propos » — éditoriale, narrative, professionnelle. Bilingue (inline).
 *  Design intégré au site (en-tête/pied fournis par le layout), scopé à .about-page. */
$en = current_locale() === 'en';
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

$C = $en ? [
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
] : [
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
];
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
        <div class="ab-avatar">
          <img src="<?= e(url('/assets/img/founder.jpg')) ?>" alt="<?= e($C['founder_name']) ?>" loading="lazy" onerror="this.remove()">
          <span class="ab-avatar__i" aria-hidden="true"><?= e(mb_substr($C['founder_name'], 0, 1)) ?></span>
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
