<?php
/** @var list<array> $products  @var array<int,string> $product_mains
 *  @var list<array> $annonces  @var array<int,string> $annonce_mains
 *  @var list<array> $boutiques  @var array<int,bool> $verified_sellers */
use App\Services\CloudinaryService;

$empty = $products === [] && $annonces === [] && $boutiques === [];
?>
<section class="spotlight-page">
    <header class="spotlight-head">
        <span class="afk-ad-tag">📣 <?= e(t('ads.label')) ?></span>
        <h1 class="afk-h1"><?= e(t('spotlight.title')) ?></h1>
        <p class="afk-lede"><?= e(t('spotlight.lead')) ?></p>
    </header>

    <?php if ($empty): ?>
        <div class="panel empty-state">
            <p style="font-size:2.4rem;margin:0 0 8px" aria-hidden="true">📣</p>
            <p><?= e(t('spotlight.empty')) ?></p>
            <a class="afk-btn afk-btn--gold" href="<?= e(url('/vendeur/publicite')) ?>"><?= e(t('spotlight.seller_cta_btn')) ?></a>
        </div>
    <?php else: ?>

        <?php if (!empty($boutiques)): ?>
        <section class="afk-block">
            <div class="afk-head"><h2 class="afk-h2">🏪 <?= e(t('spotlight.boutiques')) ?></h2></div>
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

        <?php if (!empty($products)): ?>
        <section class="afk-block">
            <div class="afk-head"><h2 class="afk-h2">✨ <?= e(t('spotlight.products')) ?></h2></div>
            <div class="product-grid">
                <?php foreach ($products as $p): $m = $product_mains[(int) $p['id']] ?? null; ?>
                    <a class="product-card" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                        <span class="product-card-img">
                            <?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                            <span class="promo-badge"><?= e(t('ads.badge')) ?></span>
                        </span>
                        <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                        <span class="product-card-price"><?= e(format_price((int) $p['price_cents'], (string) $p['currency'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($annonces)): ?>
        <section class="afk-block">
            <div class="afk-head"><h2 class="afk-h2">🏷️ <?= e(t('spotlight.annonces')) ?></h2></div>
            <div class="product-grid">
                <?php foreach ($annonces as $a): $m = $annonce_mains[(int) $a['id']] ?? null; ?>
                    <a class="product-card" href="<?= e(url('/annonce/' . $a['public_id'])) ?>">
                        <span class="product-card-img">
                            <?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">🏷️</span><?php endif; ?>
                            <span class="promo-badge"><?= e(t('ads.badge')) ?></span>
                        </span>
                        <span class="product-card-name"><?= e((string) $a['title']) ?></span>
                        <span class="product-card-price"><?= e(format_price((int) $a['price_cents'], (string) $a['currency'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="afk-seller afk-block">
            <div>
                <h2 class="afk-h2"><?= e(t('spotlight.seller_cta_title')) ?></h2>
                <p><?= e(t('spotlight.seller_cta_text')) ?></p>
            </div>
            <a class="afk-btn afk-btn--dark afk-btn--lg" href="<?= e(url('/vendeur/publicite')) ?>"><?= e(t('spotlight.seller_cta_btn')) ?></a>
        </section>

    <?php endif; ?>
</section>
