<?php
use App\Services\CloudinaryService;

$sponsored       = $sponsored ?? [];
$promo_products  = $promo_products ?? [];
$promo_product_mains = $promo_product_mains ?? [];
$products        = $products ?? [];
$product_mains   = $product_mains ?? [];
$recently_viewed = $recently_viewed ?? [];
$for_you         = $for_you ?? [];
$reco_mains      = $reco_mains ?? [];
$boutiques       = $boutiques ?? [];
$restaurants     = $restaurants ?? [];
$annonces        = $annonces ?? [];
$annonce_mains   = $annonce_mains ?? [];
$verified_sellers     = $verified_sellers ?? [];
$promo_annonces       = $promo_annonces ?? [];
$promo_annonce_mains  = $promo_annonce_mains ?? [];
$verticals = [
    ['key' => 'shop',       'icon' => '🛍️', 'alt' => ''],
    ['key' => 'restaurant', 'icon' => '🍽️', 'alt' => 'afk-uni__ic--alt1'],
    ['key' => 'salon',      'icon' => '💈', 'alt' => 'afk-uni__ic--alt2'],
    ['key' => 'service',    'icon' => '🛠️', 'alt' => 'afk-uni__ic--alt3'],
];
$catIcons = [
    'mode' => '👗', 'electronique' => '📱', 'maison' => '🏠', 'beaute' => '💄',
    'alimentation' => '🍲', 'auto' => '🚗', 'artisanat' => '🎨', 'bebe' => '👶',
    'sport' => '⚽', 'autres' => '🛍️',
];
// Catégories « vivantes » : classées par contenu réellement publié (annonces +
// produits des boutiques), calculées dans le contrôleur (App\Services\Categories).
$categories = $categories ?? [];
$loggedIn   = current_user() !== null;
?>
<?php
// Carrousel de pub en TÊTE : UNIQUEMENT les vraies pubs des vendeurs (campagnes
// sponsorisées + produits en promo). L'invitation « Promouvoir mon offre » vit
// dans l'espace vendeur (Publicité), pas ici. Carrousel masqué s'il n'y a aucune pub.
$slideMains = $reco_mains + $promo_product_mains;
$dealSlides = [];
$seenSlide  = [];
foreach (array_merge($sponsored, $promo_products) as $sp) {
    $sid = (int) $sp['id'];
    if (isset($seenSlide[$sid])) { continue; }
    $seenSlide[$sid] = true;
    $dealSlides[] = $sp;
    if (count($dealSlides) >= 6) { break; }
}
$slideCount = count($dealSlides);
?>
<?php if ($dealSlides !== []): ?>
<!-- Publicité — carrousel défilant en tête de l'accueil (pubs des vendeurs) -->
<section class="afk-block afk-carousel-wrap">
<div class="afk-carousel" data-carousel<?= $slideCount > 1 ? ' data-autoplay="6000"' : '' ?> aria-roledescription="carrousel" aria-label="<?= e(t('ads.label')) ?>">
    <div class="afk-carousel__viewport">
        <div class="afk-carousel__track">
            <?php foreach ($dealSlides as $i => $p):
                $onPromo = !empty($p['promo_price_cents']) && (int) $p['promo_price_cents'] > 0
                    && (int) $p['promo_price_cents'] < (int) $p['price_cents']
                    && (empty($p['promo_until']) || strtotime((string) $p['promo_until']) > time());
                $now = $onPromo ? (int) $p['promo_price_cents'] : (int) $p['price_cents'];
                $old = $onPromo ? (int) $p['price_cents'] : null;
                $pct = $old ? (int) round(($old - $now) / max(1, $old) * 100) : 0;
                $img = $slideMains[(int) $p['id']] ?? null;
                $cur = (string) ($p['currency'] ?? 'EUR');
                $href = !empty($p['campaign_pid'])
                    ? url('/sp/' . $p['campaign_pid'])
                    : url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']);
            ?>
            <a class="afk-carousel__slide afk-ad afk-ad--t<?= $i % 4 ?>" href="<?= e($href) ?>" role="group" aria-roledescription="diapo">
                <div class="afk-ad__text">
                    <span class="afk-ad__eyebrow">⚡ <?= e(t('carousel.deal')) ?><?= $pct > 0 ? ' · −' . $pct . '%' : '' ?></span>
                    <h2 class="afk-ad__title"><?= e(mb_strimwidth((string) $p['name'], 0, 58, '…')) ?></h2>
                    <div class="afk-ad__price"><span class="afk-ad__now"><?= e(format_price($now, $cur)) ?></span><?php if ($old !== null): ?> <span class="afk-ad__old"><?= e(format_price($old, $cur)) ?></span><?php endif; ?></div>
                    <span class="afk-btn afk-btn--gold afk-btn--lg"><?= e(t('carousel.shop')) ?></span>
                </div>
                <div class="afk-ad__media">
                    <?php if ($img !== null): ?><img src="<?= e(CloudinaryService::imageUrl($img, 360, 360)) ?>" alt="" loading="lazy"><?php else: ?><span class="afk-ad__emoji" aria-hidden="true">🛍️</span><?php endif; ?>
                    <?php if ($pct > 0): ?><span class="afk-ad__badge">−<?= $pct ?>%</span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($slideCount > 1): ?>
        <button class="afk-carousel__arrow afk-carousel__arrow--prev" type="button" aria-label="<?= e(t('carousel.prev')) ?>" data-prev>‹</button>
        <button class="afk-carousel__arrow afk-carousel__arrow--next" type="button" aria-label="<?= e(t('carousel.next')) ?>" data-next>›</button>
        <div class="afk-carousel__dots">
            <?php for ($d = 0; $d < $slideCount; $d++): ?>
                <button class="afk-carousel__dot<?= $d === 0 ? ' is-active' : '' ?>" type="button" aria-label="<?= e(t('carousel.go', ['n' => $d + 1])) ?>" data-dot="<?= $d ?>"></button>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</section>
<?php endif; ?>

<!-- Hero déplacé vers la page « À propos » (/a-propos) -->

<?php
// Navigation par CATÉGORIE — toujours visible (pattern des grandes marketplaces :
// rayon/department grid juste sous le héros), avec compteur en direct si du contenu existe.
$catOrder  = ['mode', 'electronique', 'maison', 'beaute', 'alimentation', 'auto', 'artisanat', 'bebe', 'sport'];
$catCounts = [];
foreach ($categories as $c) { $catCounts[(string) $c['key']] = (int) ($c['count'] ?? 0); }
// Visuel de chaque catégorie = photo de sa meilleure vente (repli : émoji).
$catThumbs = \App\Models\Product::categoryThumbs($catOrder);
?>
<section class="afk-cats afk-block" aria-label="<?= e(t('home.categories_title')) ?>">
    <div class="afk-head">
        <h2 class="afk-h2"><?= e(t('home.categories_title')) ?></h2>
        <a class="afk-link-all" href="<?= e(url('/explorer')) ?>"><?= e(t('common.see_all')) ?> →</a>
    </div>
    <div class="cat-tiles cat-tiles--browse">
        <?php foreach ($catOrder as $ck): $cn = $catCounts[$ck] ?? 0; $thumb = $catThumbs[$ck] ?? null; ?>
            <a class="cat-tile<?= $thumb !== null ? ' cat-tile--photo' : '' ?>" href="<?= e(url('/explorer?categorie=' . $ck)) ?>">
                <span class="cat-tile-ico" aria-hidden="true"><?php if ($thumb !== null): ?><img src="<?= e(CloudinaryService::imageUrl($thumb, 220, 220)) ?>" alt="" loading="lazy" onerror="this.remove()"><span class="cat-tile-fallback"><?= $catIcons[$ck] ?? '🛍️' ?></span><?php else: ?><?= $catIcons[$ck] ?? '🛍️' ?><?php endif; ?></span>
                <span class="cat-tile-name"><?= e(t('listing.cat.' . $ck)) ?></span>
                <?php if ($cn > 0): ?><span class="cat-tile-count"><?= e(t('home.cat_count', ['n' => $cn])) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($products)): ?>
<!-- Produits du catalogue — visibles dès l'ouverture de l'accueil -->
<section class="live-section afk-block">
    <div class="afk-spotlight__bar">
        <h2><?= icon('store', ['size' => 18]) ?> <?= e(t('home.products_title')) ?></h2>
        <a class="afk-link-all" href="<?= e(url('/explorer')) ?>"><?= e(t('spotlight.see_all')) ?> →</a>
    </div>
    <div class="product-grid">
        <?php foreach ($products as $p): $pm = $product_mains[(int) $p['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($pm !== null): ?><img src="<?= e(CloudinaryService::imageUrl($pm, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span><?php endif; ?>
                    <?php if (\App\Models\Product::isPromoted($p)): ?><span class="promo-badge"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                </span>
                <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $p['price_cents'], 'cur' => (string) $p['currency']]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($promo_annonces)): ?>
<section class="live-section afk-block">
    <h2><?= icon('sparkle', ['size' => 18]) ?> <?= e(t('home.featured_annonces')) ?></h2>
    <div class="product-grid">
        <?php foreach ($promo_annonces as $a): $am = $promo_annonce_mains[(int) $a['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($am !== null): ?><img src="<?= e(CloudinaryService::imageUrl($am, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('tag') ?></span><?php endif; ?>
                    <span class="promo-badge"><?= e(t('ads.badge')) ?></span>
                </span>
                <span class="product-card-name"><?= e((string) $a['title']) ?></span>
                <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $a['price_cents'], 'cur' => (string) $a['currency']]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($boutiques)): ?>
<section class="live-section afk-block">
    <h2><?= icon('store', ['size' => 18]) ?> <?= e(t('home.boutiques_title')) ?></h2>
    <div class="vendor-grid">
        <?php foreach ($boutiques as $b): ?>
            <a class="vendor-card" href="<?= e(url('/boutique/' . $b['slug'])) ?>">
                <span class="vendor-logo"><?php if (!empty($b['logo_public_id'])): ?><img src="<?= e(CloudinaryService::imageUrl((string) $b['logo_public_id'], 160, 160)) ?>" alt="" loading="lazy"><?php else: ?><?= icon('store', ['size' => 30]) ?><?php endif; ?></span>
                <span class="vendor-name"><?= e((string) $b['name']) ?></span>
                <?php if (!empty($verified_sellers[(int) $b['user_id']])): ?><span class="vendor-verified" title="<?= e(t('shop.verified_seller')) ?>">✓ <?= e(t('home.verified_short')) ?></span><?php endif; ?>
                <?php if (!empty($b['category'])): ?><span class="vendor-sub muted"><?= e(t('listing.cat.' . $b['category'])) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($restaurants)): ?>
<section class="live-section afk-block">
    <h2><?= icon('utensils', ['size' => 18]) ?> <?= e(t('home.restaurants_title')) ?></h2>
    <div class="vendor-grid">
        <?php foreach ($restaurants as $r): $sub = trim((string) ($r['tagline'] ?? '')) ?: trim((string) ($r['cuisine'] ?? '')); ?>
            <a class="vendor-card" href="<?= e(url('/restaurant/' . $r['slug'])) ?>">
                <span class="vendor-logo"><?php $rlogo = $r['logo_public_id'] ?: $r['banner_public_id']; if (!empty($rlogo)): ?><img src="<?= e(CloudinaryService::imageUrl((string) $rlogo, 160, 160)) ?>" alt="" loading="lazy"><?php else: ?><?= icon('utensils', ['size' => 30]) ?><?php endif; ?></span>
                <span class="vendor-name"><?= e((string) $r['name']) ?></span>
                <?php if ($sub !== ''): ?><span class="vendor-sub muted"><?= e(mb_strimwidth($sub, 0, 40, '…')) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($annonces)): ?>
<section class="live-section afk-block">
    <h2><?= icon('tag', ['size' => 18]) ?> <?= e(t('home.annonces_title')) ?></h2>
    <div class="product-grid">
        <?php foreach ($annonces as $a): $am = $annonce_mains[(int) $a['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($am !== null): ?><img src="<?= e(CloudinaryService::imageUrl($am, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('tag') ?></span><?php endif; ?>
                    <?php if (\App\Models\Listing::isPromoted($a)): ?><span class="promo-badge"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                </span>
                <span class="product-card-name"><?= e((string) $a['title']) ?></span>
                <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $a['price_cents'], 'cur' => (string) $a['currency']]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recently_viewed) || !empty($for_you)): ?>
<section class="reco-rails afk-block">
    <?php if (!empty($recently_viewed)): ?>
        <?= render_partial('partials/product_rail', ['icon' => 'clock', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
    <?php if (!empty($for_you)): ?>
        <?= render_partial('partials/product_rail', ['icon' => 'lightbulb', 'title' => t('reco.for_you'), 'products' => $for_you, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="verticals" class="afk-block">
    <div class="afk-head"><div><span class="afk-kicker"><?= e(t('home.hero_kicker')) ?></span><h2 class="afk-h2"><?= e(t('home.verticals_title')) ?></h2></div></div>
    <div class="afk-grid afk-grid-4">
        <?php foreach ($verticals as $v): ?>
            <article class="afk-uni">
                <div class="afk-uni__ic <?= e($v['alt']) ?>" aria-hidden="true"><?= $v['icon'] ?></div>
                <h3 class="afk-h3"><?= e(t('home.vertical.' . $v['key'] . '.title')) ?></h3>
                <p><?= e(t('home.vertical.' . $v['key'] . '.desc')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="afk-seller afk-block">
    <div>
        <h2 class="afk-h2"><?= e(t('home.seller_cta_title')) ?></h2>
        <p><?= e(t('home.seller_cta_text')) ?></p>
    </div>
    <a class="afk-btn afk-btn--dark afk-btn--lg" href="<?= e(url($loggedIn ? '/vendre' : '/register/vendeur')) ?>"><?= e(t('home.seller_cta_btn')) ?></a>
</section>

<!-- Bandeau garanties — tout en bas, chaque pilier mène à sa page « système » -->
<section class="afk-guar afk-block" aria-label="<?= e(t('home.why_title')) ?>">
    <div class="afk-guar__grid">
        <a class="afk-guar__item" href="<?= e(url('/paiements-securises')) ?>"><span class="afk-guar__ic" aria-hidden="true"><?= icon('lock', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.secure_t')) ?></strong><span><?= e(t('home.why.secure_d')) ?></span></span></a>
        <a class="afk-guar__item" href="<?= e(url('/vendeurs-verifies')) ?>"><span class="afk-guar__ic" aria-hidden="true"><?= icon('shield', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.verified_t')) ?></strong><span><?= e(t('home.why.verified_d')) ?></span></span></a>
        <a class="afk-guar__item" href="<?= e(url('/local-international')) ?>"><span class="afk-guar__ic" aria-hidden="true"><?= icon('globe', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.ship_t')) ?></strong><span><?= e(t('home.why.ship_d')) ?></span></span></a>
        <a class="afk-guar__item" href="<?= e(url('/assistance')) ?>"><span class="afk-guar__ic" aria-hidden="true"><?= icon('chat', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.support_t')) ?></strong><span><?= e(t('home.why.support_d')) ?></span></span></a>
    </div>
</section>
