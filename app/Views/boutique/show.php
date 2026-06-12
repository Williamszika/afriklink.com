<?php
/** @var array $boutique  @var array $seller  @var bool $is_owner */
use App\Services\CloudinaryService;

$logo   = $boutique['logo_public_id'] ?? null;
$banner = $boutique['banner_public_id'] ?? null;
$cc     = strtoupper((string) ($seller['country_code'] ?? ''));
$waPhone = preg_replace('/\D+/', '', (string) ($seller['phone'] ?? ''));
$zones = array_filter(explode(',', (string) ($boutique['delivery_zones'] ?? '')));
$methods = array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? '')));
?>
<section class="shop-page">
    <?php if ($is_owner && ($boutique['status'] ?? '') !== 'published'): ?>
        <div class="notice notice-info"><p><?= e(t('shop.owner_draft')) ?> — <a href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a></p></div>
    <?php endif; ?>

    <div class="shop-hero">
        <?php if ($banner !== null): ?>
            <img class="shop-hero-banner" src="<?= e(CloudinaryService::imageUrl($banner, 1100, 300)) ?>" alt="">
        <?php else: ?>
            <div class="shop-hero-banner shop-banner--empty"></div>
        <?php endif; ?>
        <div class="shop-hero-id">
            <?php if ($logo !== null): ?>
                <img class="shop-logo" src="<?= e(CloudinaryService::imageUrl($logo, 160, 160)) ?>" alt="" width="80" height="80">
            <?php else: ?>
                <div class="shop-logo shop-logo--empty" aria-hidden="true">🛍️</div>
            <?php endif; ?>
            <div>
                <h1><?= e((string) $boutique['name']) ?></h1>
                <?php if (!empty($boutique['tagline'])): ?><p class="lead"><?= e((string) $boutique['tagline']) ?></p><?php endif; ?>
                <p class="muted">
                    <?php if (!empty($boutique['category'])): ?><span class="badge badge-neutral"><?= e(t('listing.cat.' . $boutique['category'])) ?></span><?php endif; ?>
                    <?php if ($cc !== ''): ?> <?= flag_emoji($cc) ?> <?= e(country_name($cc)) ?><?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="shop-body">
        <div class="panel">
            <h2 class="panel-title">📦 <?= e(t('shop.products_title')) ?></h2>
            <?php if (empty($products)): ?>
                <div class="empty-state"><p><?= e(t('shop.no_products_public')) ?></p></div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $pr): ?>
                        <?php $m = $mains[(int) $pr['id']] ?? null; ?>
                        <a class="product-card" href="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $pr['public_id'])) ?>">
                            <span class="product-card-img">
                                <?php if ($m !== null): ?>
                                    <img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy">
                                <?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                            </span>
                            <span class="product-card-name"><?= e((string) $pr['name']) ?></span>
                            <span class="product-card-price"><?= e(format_price((int) $pr['price_cents'], (string) $boutique['currency'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="shop-aside">
            <?php if (!empty($boutique['description'])): ?>
                <div class="panel">
                    <h2 class="panel-title"><?= e(t('shop.about')) ?></h2>
                    <p class="listing-description"><?= nl2br(e((string) $boutique['description'])) ?></p>
                </div>
            <?php endif; ?>
            <div class="panel">
                <h2 class="panel-title"><?= e(t('shop.infos')) ?></h2>
                <dl class="meta">
                    <dt><?= e(t('shop.f.type')) ?></dt>
                    <dd><?= ($boutique['shop_type'] ?? 'online') === 'physical' ? '🏬 ' . e(t('shop.type.physical')) : '🌐 ' . e(t('shop.type.online')) ?></dd>
                    <?php if (($boutique['shop_type'] ?? '') === 'physical' && !empty($boutique['address'])): ?>
                        <dt><?= e(t('shop.f.address')) ?></dt><dd>📍 <?= e((string) $boutique['address']) ?></dd>
                    <?php endif; ?>
                    <?php if ($zones): ?>
                        <dt><?= e(t('shop.f.zones')) ?></dt>
                        <dd><?= e(implode(' · ', array_map(static fn ($z) => t('shop.zone.' . $z), $zones))) ?></dd>
                    <?php endif; ?>
                    <?php if ($methods): ?>
                        <dt><?= e(t('shop.f.methods')) ?></dt>
                        <dd><?= e(implode(' · ', array_map(static fn ($m) => t('shop.method.' . $m), $methods))) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($boutique['prep_time'])): ?>
                        <dt><?= e(t('shop.f.prep')) ?></dt><dd><?= e(t('shop.prep.' . $boutique['prep_time'])) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($boutique['cod_enabled'])): ?>
                        <dt><?= e(t('shop.f.payment')) ?></dt><dd>💵 <?= e(t('shop.f.cod')) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ($waPhone !== ''): ?>
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>">💬 <?= e(t('listing.contact_whatsapp')) ?></a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
