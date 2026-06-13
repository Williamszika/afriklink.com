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
    ['key' => 'shop',       'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];
$catIcons = [
    'mode' => '👗', 'electronique' => '📱', 'maison' => '🏠', 'beaute' => '💄',
    'alimentation' => '🍲', 'auto' => '🚗', 'artisanat' => '🎨', 'bebe' => '👶',
    'sport' => '⚽', 'autres' => '🛍️',
];
$categories = config('listings.categories', []);
$loggedIn   = current_user() !== null;
?>
<section class="hero hero--compact">
    <div class="hero-logo"><?= render_partial('partials/logo', ['uid' => 'hero']) ?></div>
    <p class="hero-wordmark">Afrik<span>link</span></p>
    <h1><?= e(t('home.hero_title')) ?></h1>
    <p class="lead"><?= e(t('home.hero_subtitle')) ?></p>

    <form class="home-search" method="get" action="<?= e(url('/explorer')) ?>" role="search">
        <input type="search" name="q" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
        <button type="submit" class="btn btn-primary">🔎 <?= e(t('explore.search_btn')) ?></button>
    </form>

    <div class="hero-actions">
        <?php if ($loggedIn): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/vendre')) ?>"><?= e(t('dash.action.sell_title')) ?></a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>"><?= e(t('home.cta_register')) ?></a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/login')) ?>"><?= e(t('home.cta_login')) ?></a>
        <?php endif; ?>
    </div>
</section>

<?php /* On tombe directement sur le contenu réel : produits en vedette, vitrines, restaurants, annonces. */ ?>
<?php if (!empty($sponsored)): ?>
<section class="reco-rails">
    <?= render_partial('partials/product_rail', ['icon' => '✨', 'title' => t('reco.sponsored'), 'products' => $sponsored, 'mains' => $reco_mains]) ?>
</section>
<?php endif; ?>

<?php if (!empty($promo_annonces)): ?>
<section class="live-section">
    <h2>✨ <?= e(t('home.featured_annonces')) ?></h2>
    <div class="product-grid">
        <?php foreach ($promo_annonces as $a): $am = $promo_annonce_mains[(int) $a['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($am !== null): ?><img src="<?= e(CloudinaryService::imageUrl($am, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">🏷️</span><?php endif; ?>
                    <span class="promo-badge"><?= e(t('ads.badge')) ?></span>
                </span>
                <span class="product-card-name"><?= e((string) $a['title']) ?></span>
                <span class="product-card-price"><?= e(format_price((int) $a['price_cents'], (string) $a['currency'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($boutiques)): ?>
<section class="live-section">
    <h2>🏪 <?= e(t('home.boutiques_title')) ?></h2>
    <div class="vendor-grid">
        <?php foreach ($boutiques as $b): ?>
            <a class="vendor-card" href="<?= e(url('/boutique/' . $b['slug'])) ?>">
                <span class="vendor-logo"><?php if (!empty($b['logo_public_id'])): ?><img src="<?= e(CloudinaryService::imageUrl((string) $b['logo_public_id'], 160, 160)) ?>" alt="" loading="lazy"><?php else: ?>🏪<?php endif; ?></span>
                <span class="vendor-name"><?= e((string) $b['name']) ?></span>
                <?php if (!empty($verified_sellers[(int) $b['user_id']])): ?><span class="vendor-verified" title="<?= e(t('shop.verified_seller')) ?>">✓ <?= e(t('home.verified_short')) ?></span><?php endif; ?>
                <?php if (!empty($b['category'])): ?><span class="vendor-sub muted"><?= e(t('listing.cat.' . $b['category'])) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($restaurants)): ?>
<section class="live-section">
    <h2>🍽️ <?= e(t('home.restaurants_title')) ?></h2>
    <div class="vendor-grid">
        <?php foreach ($restaurants as $r): $sub = trim((string) ($r['tagline'] ?? '')) ?: trim((string) ($r['cuisine'] ?? '')); ?>
            <a class="vendor-card" href="<?= e(url('/restaurant/' . $r['slug'])) ?>">
                <span class="vendor-logo"><?php $rlogo = $r['logo_public_id'] ?: $r['banner_public_id']; if (!empty($rlogo)): ?><img src="<?= e(CloudinaryService::imageUrl((string) $rlogo, 160, 160)) ?>" alt="" loading="lazy"><?php else: ?>🍽️<?php endif; ?></span>
                <span class="vendor-name"><?= e((string) $r['name']) ?></span>
                <?php if ($sub !== ''): ?><span class="vendor-sub muted"><?= e(mb_strimwidth($sub, 0, 40, '…')) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($annonces)): ?>
<section class="live-section">
    <h2>🏷️ <?= e(t('home.annonces_title')) ?></h2>
    <div class="product-grid">
        <?php foreach ($annonces as $a): $am = $annonce_mains[(int) $a['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($am !== null): ?><img src="<?= e(CloudinaryService::imageUrl($am, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">🏷️</span><?php endif; ?>
                    <?php if (\App\Models\Listing::isPromoted($a)): ?><span class="promo-badge"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                </span>
                <span class="product-card-name"><?= e((string) $a['title']) ?></span>
                <span class="product-card-price"><?= e(format_price((int) $a['price_cents'], (string) $a['currency'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recently_viewed) || !empty($for_you)): ?>
<section class="reco-rails">
    <?php if (!empty($recently_viewed)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '🕒', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
    <?php if (!empty($for_you)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '💡', 'title' => t('reco.for_you'), 'products' => $for_you, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($categories !== []): ?>
<section class="cat-section">
    <h2><?= e(t('home.categories_title')) ?></h2>
    <div class="cat-tiles">
        <?php foreach ($categories as $c): ?>
            <a class="cat-tile" href="<?= e(url('/explorer?categorie=' . $c)) ?>">
                <span class="cat-tile-ico" aria-hidden="true"><?= $catIcons[$c] ?? '🛍️' ?></span>
                <span class="cat-tile-name"><?= e(t('listing.cat.' . $c)) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section id="verticals" class="verticals">
    <h2><?= e(t('home.verticals_title')) ?></h2>
    <div class="vertical-grid">
        <?php foreach ($verticals as $v): ?>
            <article class="vertical-card">
                <div class="vertical-icon" aria-hidden="true"><?= $v['icon'] ?></div>
                <h3><?= e(t('home.vertical.' . $v['key'] . '.title')) ?></h3>
                <p><?= e(t('home.vertical.' . $v['key'] . '.desc')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="why-afk">
    <h2><?= e(t('home.why_title')) ?></h2>
    <div class="why-grid">
        <div class="why-card"><div class="why-ico" aria-hidden="true">🔒</div><h3><?= e(t('home.why.secure_t')) ?></h3><p><?= e(t('home.why.secure_d')) ?></p></div>
        <div class="why-card"><div class="why-ico" aria-hidden="true">🌍</div><h3><?= e(t('home.why.ship_t')) ?></h3><p><?= e(t('home.why.ship_d')) ?></p></div>
        <div class="why-card"><div class="why-ico" aria-hidden="true">💬</div><h3><?= e(t('home.why.support_t')) ?></h3><p><?= e(t('home.why.support_d')) ?></p></div>
        <div class="why-card"><div class="why-ico" aria-hidden="true">✅</div><h3><?= e(t('home.why.verified_t')) ?></h3><p><?= e(t('home.why.verified_d')) ?></p></div>
    </div>
</section>

<section class="seller-band">
    <h2><?= e(t('home.seller_cta_title')) ?></h2>
    <p><?= e(t('home.seller_cta_text')) ?></p>
    <a class="btn btn-primary btn-lg" href="<?= e(url($loggedIn ? '/vendre' : '/register/vendeur')) ?>"><?= e(t('home.seller_cta_btn')) ?></a>
</section>
