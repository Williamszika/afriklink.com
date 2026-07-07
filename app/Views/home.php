<?php
use App\Services\CloudinaryService;

$sponsored           = $sponsored ?? [];
$promo_products      = $promo_products ?? [];
$promo_product_mains = $promo_product_mains ?? [];
$products            = $products ?? [];
$product_mains       = $product_mains ?? [];
$recently_viewed     = $recently_viewed ?? [];
$for_you             = $for_you ?? [];
$reco_mains          = $reco_mains ?? [];
$boutiques           = $boutiques ?? [];
$restaurants         = $restaurants ?? [];
$annonces            = $annonces ?? [];
$annonce_mains       = $annonce_mains ?? [];
$verified_sellers    = $verified_sellers ?? [];
$promo_annonces      = $promo_annonces ?? [];
$promo_annonce_mains = $promo_annonce_mains ?? [];
$categories          = $categories ?? [];
$loggedIn            = current_user() !== null;

// Univers : liens vers des destinations RÉELLES uniquement. Boutiques → l'explorateur
// (le marché) ; Restaurants → la section de la page si des restaurants existent ;
// Salons/Services ne sont pas encore ouverts aux acheteurs → étiquette « Bientôt ».
$hasResto = !empty($restaurants);
$univers = [
    ['key' => 'shop',       'emj' => '🛍️', 'cls' => 'u1', 'href' => url('/explorer'),                       'soon' => false],
    ['key' => 'restaurant', 'emj' => '🍽️', 'cls' => 'u2', 'href' => $hasResto ? '#home-restaurants' : null, 'soon' => false],
    ['key' => 'salon',      'emj' => '💈', 'cls' => 'u3', 'href' => null,                                    'soon' => true],
    ['key' => 'service',    'emj' => '🛠️', 'cls' => 'u4', 'href' => null,                                    'soon' => true],
];

// Catégories « vivantes » (classées par contenu réellement publié, calc. contrôleur).
$catIcons = [
    'mode' => '👗', 'electronique' => '📱', 'maison' => '🏠', 'beaute' => '💄',
    'alimentation' => '🍲', 'auto' => '🚗', 'artisanat' => '🎨', 'bebe' => '👶',
    'sport' => '⚽', 'autres' => '🛍️',
];
$catOrder  = ['mode', 'electronique', 'maison', 'beaute', 'alimentation', 'auto', 'artisanat', 'bebe', 'sport'];
$catCounts = [];
foreach ($categories as $c) { $catCounts[(string) $c['key']] = (int) ($c['count'] ?? 0); }
$catThumbs = \App\Models\Product::categoryThumbs($catOrder);

// Coups de cœur sponsorisés : uniquement les vraies pubs des vendeurs (campagnes +
// promos), dédupliquées, en grille (l'enregistrement des impressions se fait au
// contrôleur, indépendamment de l'affichage).
$slideMains = $reco_mains + $promo_product_mains;
$deals = [];
$seen  = [];
foreach (array_merge($sponsored, $promo_products) as $sp) {
    $sid = (int) $sp['id'];
    if (isset($seen[$sid])) { continue; }
    $seen[$sid] = true;
    $deals[] = $sp;
    if (count($deals) >= 8) { break; }
}

/** Carte produit .home (image + badge promo + cœur + titre + prix + géo). */
$productCard = static function (array $p, ?string $img, ?string $href = null, bool $sponsored = false) {
    $onPromo = !empty($p['promo_price_cents']) && (int) $p['promo_price_cents'] > 0
        && (int) $p['promo_price_cents'] < (int) $p['price_cents']
        && (empty($p['promo_until']) || strtotime((string) $p['promo_until']) > time());
    $now = $onPromo ? (int) $p['promo_price_cents'] : (int) $p['price_cents'];
    $old = $onPromo ? (int) $p['price_cents'] : null;
    $cur = (string) ($p['currency'] ?? 'EUR');
    $url = $href ?? url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']);
    ob_start(); ?>
    <div class="home-pcard-wrap">
        <a class="home-pcard" href="<?= e($url) ?>">
            <span class="home-pthumb">
                <?php if ($img !== null): ?><img src="<?= e(CloudinaryService::imageUrl($img, 320, 320, true)) ?>" alt="" loading="lazy"><?php else: ?><span class="home-pthumb-empty" aria-hidden="true"><?= icon('package', ['size' => 30]) ?></span><?php endif; ?>
                <?php if ($sponsored || \App\Models\Product::isPromoted($p)): ?><span class="home-spon"><?= e(t('ads.badge')) ?></span><?php endif; ?>
            </span>
            <span class="home-pbody">
                <span class="home-ptitle"><?= e(tr_content('product', (int) $p['id'], 'name', (string) $p['name'])) ?></span>
                <span class="home-pprice"><?= render_partial('partials/price_dual', ['cents' => $now, 'cur' => $cur, 'compare' => $old]) ?></span>
                <?= render_partial('partials/card_geo', ['row' => $p]) ?>
            </span>
        </a>
        <?= render_partial('partials/wish_heart', ['pid' => (string) $p['public_id']]) ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="home">

    <!-- ===== HÉROS (design validé : carte verte cauris + publicité RÉELLE à droite) ===== -->
    <?php
    // Colonne droite du héros = bandeau publicitaire RÉEL (mises en avant payantes
    // des vendeurs : $deals = sponsorisés + promos dédupliqués) + un panneau
    // libre-service « votre pub ici ». Carrousel CSP-safe ([data-carousel] de app.js).
    $adSlides = array_slice($deals, 0, 3);
    $adGrads  = ['ad1', 'ad2', 'ad4'];
    $adTotal  = count($adSlides) + 1;
    // Chiffres du héros : comptés sur la config réelle (jamais inventés).
    $nLang = count(config('app.locales', ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'ar']));
    $nCurr = count(config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']));
    $nUni  = count($univers);
    ?>
    <section class="home-hero" aria-label="<?= e(t('home.hero_title')) ?>">
        <div class="home-herowrap">
            <div class="home-hero-in">
                <div class="home-hero-copy">
                    <span class="home-hero-badge">🌍 <?= e(t('home.hero_kicker')) ?></span>
                    <h1 class="home-hero-h1"><?= e(t('home.hero_title')) ?></h1>
                    <p class="home-hero-lead"><?= e(t('home.hero_subtitle')) ?></p>
                    <div class="home-hero-btns">
                        <a class="btn btn-gold btn-lg" href="<?= e(url('/explorer')) ?>"><?= e(t('home.cta_shop')) ?> →</a>
                        <a class="btn btn-ghost btn-lg" href="<?= e(url($loggedIn ? '/vendre' : '/register/vendeur')) ?>"><?= e(t('home.cta_sell')) ?></a>
                    </div>
                    <div class="home-hero-stats">
                        <div><span class="n"><?= (int) $nLang ?></span><span class="l"><?= e(t('home.stat_langs')) ?></span></div>
                        <div><span class="n"><?= (int) $nCurr ?></span><span class="l"><?= e(t('home.stat_curr')) ?></span></div>
                        <div><span class="n"><?= (int) $nUni ?></span><span class="l"><?= e(t('home.stat_uni')) ?></span></div>
                    </div>
                </div>
                <div class="home-hero-ad" aria-label="<?= e(t('ads.label')) ?>">
                    <div class="home-adwrap"<?= $adTotal > 1 ? ' data-carousel data-autoplay="5500"' : '' ?>>
            <span class="home-adlabel"><?= e(t('ads.label')) ?></span>
            <div class="afk-carousel__track home-adtrack">
                <?php foreach ($adSlides as $k => $p):
                    $img = $slideMains[(int) $p['id']] ?? null;
                    $onPromo = !empty($p['promo_price_cents']) && (int) $p['promo_price_cents'] > 0
                        && (int) $p['promo_price_cents'] < (int) $p['price_cents']
                        && (empty($p['promo_until']) || strtotime((string) $p['promo_until']) > time());
                    $now  = $onPromo ? (int) $p['promo_price_cents'] : (int) $p['price_cents'];
                    $old  = $onPromo ? (int) $p['price_cents'] : null;
                    $cur  = (string) ($p['currency'] ?? 'EUR');
                    $href = !empty($p['campaign_pid'])
                        ? url('/sp/' . $p['campaign_pid'])
                        : url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']);
                ?>
                <a class="afk-carousel__slide home-adslide <?= $adGrads[$k % 3] ?>" href="<?= e($href) ?>">
                    <span class="home-ad-txt">
                        <span class="home-ad-k"><?= e(t('ads.badge')) ?></span>
                        <span class="home-ad-h"><?= e(tr_content('product', (int) $p['id'], 'name', (string) $p['name'])) ?></span>
                        <span class="home-ad-price"><?= render_partial('partials/price_dual', ['cents' => $now, 'cur' => $cur, 'compare' => $old]) ?></span>
                        <span class="btn btn-light home-ad-cta"><?= e(t('home.ad_cta')) ?> →</span>
                    </span>
                    <span class="home-ad-visual" aria-hidden="true"><?php if ($img !== null): ?><img src="<?= e(CloudinaryService::imageUrl($img, 360, 360, true)) ?>" alt="" loading="lazy"><?php else: ?>🛍️<?php endif; ?></span>
                </a>
                <?php endforeach; ?>
                <a class="afk-carousel__slide home-adslide ad3" href="<?= e(url('/vendeur/publicite')) ?>">
                    <span class="home-ad-txt">
                        <span class="home-ad-k"><?= e(t('home.ad_self_k')) ?></span>
                        <span class="home-ad-h"><?= e(t('home.ad_self_title')) ?></span>
                        <span class="home-ad-p"><?= e(t('home.ad_self_text')) ?></span>
                        <span class="btn btn-light home-ad-cta"><?= e(t('home.ad_self_btn')) ?> →</span>
                    </span>
                    <span class="home-ad-visual" aria-hidden="true">📣</span>
                </a>
            </div>
            <?php if ($adTotal > 1): ?>
                <button type="button" class="home-adnav prev" data-prev aria-label="<?= e(t('carousel.prev')) ?>"><?= icon('chevron', ['size' => 18]) ?></button>
                <button type="button" class="home-adnav next" data-next aria-label="<?= e(t('carousel.next')) ?>"><?= icon('chevron', ['size' => 18]) ?></button>
                <div class="home-addots">
                    <?php for ($d = 0; $d < $adTotal; $d++): ?>
                        <button type="button" data-dot="<?= $d ?>" class="<?= $d === 0 ? 'is-active' : '' ?>" aria-label="<?= e(t('carousel.go', ['n' => $d + 1])) ?>"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== UNIVERS ===== -->
    <section class="home-sec">
        <div class="home-head"><h2 class="home-h2"><?= e(t('home.hub.title')) ?></h2></div>
        <div class="home-univers">
            <?php foreach ($univers as $u): $inner = '<span class="emj" aria-hidden="true">' . $u['emj'] . '</span>'
                . '<h3>' . e(t('home.vertical.' . $u['key'] . '.title')) . '</h3>'
                . '<span class="sub">' . e(t('home.uni.' . $u['key'] . '_sub')) . '</span>'
                . ($u['soon']
                    ? '<span class="home-soon">' . e(t('home.soon')) . '</span>'
                    : '<span class="home-uni-go">' . e(t('home.uni_explore')) . ' →</span>'); ?>
                <?php if ($u['href'] !== null): ?>
                    <a class="home-uni <?= e($u['cls']) ?>" href="<?= e($u['href']) ?>"><?= $inner ?></a>
                <?php else: ?>
                    <div class="home-uni <?= e($u['cls']) ?> is-soon"><?= $inner ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== CATÉGORIES ===== -->
    <section class="home-sec" aria-label="<?= e(t('home.categories_title')) ?>">
        <div class="home-head"><h2 class="home-h2"><?= e(t('home.categories_title')) ?></h2><a class="home-seeall" href="<?= e(url('/explorer')) ?>"><?= e(t('common.see_all')) ?> →</a></div>
        <div class="home-cats">
            <?php foreach ($catOrder as $ck): $cn = $catCounts[$ck] ?? 0; $thumb = $catThumbs[$ck] ?? null; ?>
                <a class="home-cat<?= $thumb !== null ? ' has-photo' : '' ?>" href="<?= e(url('/explorer?categorie=' . $ck)) ?>">
                    <span class="home-cat-ic" aria-hidden="true"><?php if ($thumb !== null): ?><img src="<?= e(CloudinaryService::imageUrl($thumb, 160, 160)) ?>" alt="" loading="lazy" data-hide-on-error><span class="fb"><?= $catIcons[$ck] ?? '🛍️' ?></span><?php else: ?><?= $catIcons[$ck] ?? '🛍️' ?><?php endif; ?></span>
                    <span class="home-cat-n"><?= e(t('listing.cat.' . $ck)) ?></span>
                    <?php if ($cn > 0): ?><span class="home-cat-c"><?= e(t('home.cat_count', ['n' => $cn])) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== PRODUITS DU CATALOGUE ===== -->
    <?php if (!empty($products)): ?>
    <section class="home-sec">
        <div class="home-head">
            <div>
                <h2 class="home-h2"><?= e(t('home.products_title')) ?></h2>
                <?php $buyerGeo = detected_geo(); $buyerPlace = $buyerGeo['country'] ?? null; ?>
                <?php if ($buyerPlace): ?><span class="home-sub"><?= e(t('home.near_you', ['place' => $buyerPlace])) ?></span><?php endif; ?>
            </div>
            <a class="home-seeall" href="<?= e(url('/explorer')) ?>"><?= e(t('spotlight.see_all')) ?> →</a>
        </div>
        <div class="home-pgrid">
            <?php foreach ($products as $p): echo $productCard($p, $product_mains[(int) $p['id']] ?? null); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== BANDEAU PUBLICITÉ MILIEU (libre-service) ===== -->
    <section class="home-sec home-adstrip-sec">
        <a class="home-adstrip" href="<?= e(url('/vendeur/publicite')) ?>">
            <span class="home-adstrip-k"><?= e(t('ads.label')) ?></span>
            <span class="home-adstrip-m"><?= e(t('home.adstrip_title')) ?><span><?= e(t('home.adstrip_sub')) ?></span></span>
            <span class="btn btn-light home-adstrip-cta"><?= e(t('home.adstrip_btn')) ?> →</span>
        </a>
    </section>

    <!-- ===== BOUTIQUES EN VEDETTE ===== -->
    <?php if (!empty($boutiques)): ?>
    <section class="home-sec" id="home-boutiques">
        <div class="home-head"><h2 class="home-h2"><?= e(t('home.boutiques_title')) ?></h2><a class="home-seeall" href="<?= e(url('/explorer')) ?>"><?= e(t('home.see_all_boutiques')) ?> →</a></div>
        <div class="home-sgrid">
            <?php foreach ($boutiques as $b): $bl = (string) ($b['logo_public_id'] ?? ''); $bi = abs(crc32((string) $b['slug'])) % 4; ?>
                <a class="home-scard" href="<?= e(url('/boutique/' . $b['slug'])) ?>">
                    <span class="home-scover cov<?= $bi ?>"></span>
                    <span class="home-slogo"><?php if ($bl !== ''): ?><img src="<?= e(CloudinaryService::imageUrl($bl, 160, 160)) ?>" alt="" loading="lazy"><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $b['name'], 0, 1))) ?><?php endif; ?></span>
                    <span class="home-sbody">
                        <span class="home-sname"><?= e((string) $b['name']) ?><?php if (!empty($verified_sellers[(int) $b['user_id']])): ?> <span class="home-vbadge"><?= icon('check', ['size' => 10]) ?> <?= e(t('home.verified_short')) ?></span><?php endif; ?></span>
                        <?php if (!empty($b['category'])): ?><span class="home-smeta"><?= e(t('listing.cat.' . $b['category'])) ?></span><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
            <a class="home-scard home-seeall-card" href="<?= e(url('/explorer')) ?>">
                <span class="home-seeall-plus" aria-hidden="true"><?= icon('plus', ['size' => 26]) ?></span>
                <span class="home-seeall-t"><?= e(t('home.see_all_boutiques')) ?></span>
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== RESTAURANTS ===== -->
    <?php if (!empty($restaurants)): ?>
    <section class="home-sec" id="home-restaurants">
        <div class="home-head"><h2 class="home-h2"><?= e(t('home.restaurants_title')) ?></h2></div>
        <div class="home-rgrid">
            <?php foreach ($restaurants as $r): $sub = trim((string) ($r['tagline'] ?? '')) ?: trim((string) ($r['cuisine'] ?? '')); $rl = (string) ($r['logo_public_id'] ?: $r['banner_public_id']); ?>
                <a class="home-rcard" href="<?= e(url('/restaurant/' . $r['slug'])) ?>">
                    <span class="home-rthumb"><?php if ($rl !== ''): ?><img src="<?= e(CloudinaryService::imageUrl($rl, 200, 200)) ?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true">🍽️</span><?php endif; ?></span>
                    <span class="home-rbody">
                        <span class="home-rname"><?= e((string) $r['name']) ?></span>
                        <?php if ($sub !== ''): ?><span class="home-rmeta"><?= e(mb_strimwidth($sub, 0, 48, '…')) ?></span><?php endif; ?>
                        <span class="home-rstatus"><span class="home-rdot" aria-hidden="true"></span> <?= e(t('home.online')) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== ANNONCES ===== -->
    <?php $allAnnonces = array_merge($promo_annonces, $annonces); if ($allAnnonces !== []): ?>
    <section class="home-sec">
        <div class="home-head"><h2 class="home-h2"><?= e(t('home.annonces_title')) ?></h2></div>
        <div class="home-pgrid">
            <?php $shownA = []; foreach ($allAnnonces as $a): $aid = (int) $a['id']; if (isset($shownA[$aid])) { continue; } $shownA[$aid] = true;
                $am = ($promo_annonce_mains[$aid] ?? null) ?? ($annonce_mains[$aid] ?? null); ?>
                <div class="home-pcard-wrap">
                    <a class="home-pcard" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                        <span class="home-pthumb">
                            <?php if ($am !== null): ?><img src="<?= e(CloudinaryService::imageUrl($am, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="home-pthumb-empty" aria-hidden="true"><?= icon('tag', ['size' => 30]) ?></span><?php endif; ?>
                            <?php if (\App\Models\Listing::isPromoted($a)): ?><span class="home-spon"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                        </span>
                        <span class="home-pbody">
                            <span class="home-ptitle"><?= e((string) $a['title']) ?></span>
                            <span class="home-pprice"><?= render_partial('partials/price_dual', ['cents' => (int) $a['price_cents'], 'cur' => (string) $a['currency']]) ?></span>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== RECOMMANDATIONS ===== -->
    <?php if (!empty($recently_viewed) || !empty($for_you)): ?>
    <section class="home-sec home-reco">
        <?php if (!empty($recently_viewed)): ?>
            <?= render_partial('partials/product_rail', ['icon' => 'clock', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains]) ?>
        <?php endif; ?>
        <?php if (!empty($for_you)): ?>
            <?= render_partial('partials/product_rail', ['icon' => 'lightbulb', 'title' => t('reco.for_you'), 'products' => $for_you, 'mains' => $reco_mains]) ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ===== CONFIANCE ===== -->
    <section class="home-sec" aria-label="<?= e(t('home.why_title')) ?>">
        <div class="home-trust">
            <a class="home-titem" href="<?= e(url('/paiements-securises')) ?>"><span class="ic"><?= icon('lock', ['size' => 24]) ?></span><span class="tx"><b><?= e(t('home.why.secure_t')) ?></b><span><?= e(t('home.why.secure_d')) ?></span></span></a>
            <a class="home-titem" href="<?= e(url('/vendeurs-verifies')) ?>"><span class="ic"><?= icon('shield', ['size' => 24]) ?></span><span class="tx"><b><?= e(t('home.why.verified_t')) ?></b><span><?= e(t('home.why.verified_d')) ?></span></span></a>
            <a class="home-titem" href="<?= e(url('/local-international')) ?>"><span class="ic"><?= icon('globe', ['size' => 24]) ?></span><span class="tx"><b><?= e(t('home.why.ship_t')) ?></b><span><?= e(t('home.why.ship_d')) ?></span></span></a>
            <a class="home-titem" href="<?= e(url('/assistance')) ?>"><span class="ic"><?= icon('chat', ['size' => 24]) ?></span><span class="tx"><b><?= e(t('home.why.support_t')) ?></b><span><?= e(t('home.why.support_d')) ?></span></span></a>
        </div>
    </section>

    <!-- ===== CTA VENDEUR ===== -->
    <section class="home-sec">
        <div class="home-vcta">
            <div><h2><?= e(t('home.seller_cta_title')) ?></h2><p><?= e(t('home.seller_cta_text')) ?></p></div>
            <a class="btn btn-green btn-lg" href="<?= e(url($loggedIn ? '/vendre' : '/register/vendeur')) ?>"><?= e(t('home.seller_cta_btn')) ?></a>
        </div>
    </section>

</div>
