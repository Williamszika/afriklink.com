<?php
use App\Services\CloudinaryService;

$sponsored       = $sponsored ?? [];
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
<!-- Hero — accroche, recherche, confiance + panneau wax -->
<section class="afk-hero">
    <div class="afk-hero__text">
        <span class="afk-eyebrow">◆ <?= e(t('home.hero_kicker')) ?></span>
        <h1 class="afk-h1"><?= e(t('home.hero_title')) ?></h1>
        <p class="afk-lede"><?= e(t('home.hero_subtitle')) ?></p>
        <form class="afk-hero-search" method="get" action="<?= e(url('/explorer')) ?>" role="search">
            <div class="afk-search">
                <span class="afk-ic" aria-hidden="true"><?= icon('search', ['size' => 18]) ?></span>
                <input type="search" name="q" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
            </div>
            <button class="afk-btn afk-btn--gold" type="submit"><?= e(t('explore.search_btn')) ?></button>
        </form>
        <div class="afk-trust">
            <span class="afk-trust__item"><?= icon('lock', ['size' => 15]) ?> <?= e(t('home.why.secure_t')) ?></span>
            <span class="afk-trust__item"><?= icon('shield', ['size' => 15]) ?> <?= e(t('home.why.verified_t')) ?></span>
            <span class="afk-trust__item"><?= icon('globe', ['size' => 15]) ?> <?= e(t('home.why.ship_t')) ?></span>
        </div>
    </div>
    <aside class="afk-waxpanel" aria-hidden="true">
        <div class="afk-waxpanel__emblem"><span class="brand-logo"><?= render_partial('partials/logo', ['uid' => 'hero']) ?></span></div>
        <div class="afk-waxpanel__verts">
            <?php foreach ($verticals as $v): ?>
                <span><?= $v['icon'] ?> <?= e(t('home.vertical.' . $v['key'] . '.title')) ?></span>
            <?php endforeach; ?>
        </div>
    </aside>
</section>

<?php
// Navigation par CATÉGORIE — toujours visible (pattern des grandes marketplaces :
// rayon/department grid juste sous le héros), avec compteur en direct si du contenu existe.
$catOrder  = ['mode', 'electronique', 'maison', 'beaute', 'alimentation', 'auto', 'artisanat', 'bebe', 'sport'];
$catCounts = [];
foreach ($categories as $c) { $catCounts[(string) $c['key']] = (int) ($c['count'] ?? 0); }
?>
<section class="afk-cats afk-block" aria-label="<?= e(t('home.categories_title')) ?>">
    <div class="afk-head">
        <h2 class="afk-h2"><?= e(t('home.categories_title')) ?></h2>
        <a class="afk-link-all" href="<?= e(url('/explorer')) ?>"><?= e(t('common.see_all')) ?> →</a>
    </div>
    <div class="cat-tiles cat-tiles--browse">
        <?php foreach ($catOrder as $ck): $cn = $catCounts[$ck] ?? 0; ?>
            <a class="cat-tile" href="<?= e(url('/explorer?categorie=' . $ck)) ?>">
                <span class="cat-tile-ico" aria-hidden="true"><?= $catIcons[$ck] ?? '🛍️' ?></span>
                <span class="cat-tile-name"><?= e(t('listing.cat.' . $ck)) ?></span>
                <?php if ($cn > 0): ?><span class="cat-tile-count"><?= e(t('home.cat_count', ['n' => $cn])) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Bandeau garanties — confiance forte (style Trade Assurance) -->
<section class="afk-guar afk-block" aria-label="<?= e(t('home.why_title')) ?>">
    <div class="afk-guar__grid">
        <div class="afk-guar__item"><span class="afk-guar__ic" aria-hidden="true"><?= icon('lock', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.secure_t')) ?></strong><span><?= e(t('home.why.secure_d')) ?></span></span></div>
        <div class="afk-guar__item"><span class="afk-guar__ic" aria-hidden="true"><?= icon('shield', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.verified_t')) ?></strong><span><?= e(t('home.why.verified_d')) ?></span></span></div>
        <div class="afk-guar__item"><span class="afk-guar__ic" aria-hidden="true"><?= icon('globe', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.ship_t')) ?></strong><span><?= e(t('home.why.ship_d')) ?></span></span></div>
        <div class="afk-guar__item"><span class="afk-guar__ic" aria-hidden="true"><?= icon('chat', ['size' => 24]) ?></span><span class="afk-guar__txt"><strong><?= e(t('home.why.support_t')) ?></strong><span><?= e(t('home.why.support_d')) ?></span></span></div>
    </div>
</section>

<section class="afk-spotlight afk-block">
    <div class="afk-spotlight__bar">
        <span class="afk-ad-tag"><?= icon('megaphone', ['size' => 15]) ?> <?= e(t('ads.label')) ?></span>
        <?php if (!empty($sponsored)): ?>
            <a class="afk-link-all" href="<?= e(url('/mise-en-avant')) ?>"><?= e(t('spotlight.see_all')) ?> →</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($sponsored)): ?>
        <?= render_partial('partials/product_rail', ['icon' => 'sparkle', 'title' => t('reco.sponsored'), 'products' => $sponsored, 'mains' => $reco_mains]) ?>
    <?php else: ?>
        <div class="afk-spotlight__empty">
            <p><?= e(t('spotlight.home_empty')) ?></p>
            <a class="afk-btn afk-btn--gold" href="<?= e(url('/vendeur/publicite')) ?>"><?= e(t('spotlight.seller_cta_btn')) ?></a>
        </div>
    <?php endif; ?>
</section>

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
