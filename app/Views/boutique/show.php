<?php
/** @var array $boutique  @var array $seller  @var bool $is_owner  @var bool $seller_verified  @var list<string> $banners */
use App\Services\CloudinaryService;

$logo   = $boutique['logo_public_id'] ?? null;
$banners = $banners ?? array_filter([$boutique['banner_public_id'] ?? null]);
$cc     = strtoupper((string) ($seller['country_code'] ?? ''));
$waPhone = preg_replace('/\D+/', '', (string) ($seller['phone'] ?? ''));
$zones = array_filter(explode(',', (string) ($boutique['delivery_zones'] ?? '')));
$methods = array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? '')));
$shopUrl = url('/boutique/' . $boutique['slug']);
?>
<section class="shop-page">
    <?php if ($is_owner && ($boutique['status'] ?? '') !== 'published'): ?>
        <div class="notice notice-info"><p><?= e(t('shop.owner_draft')) ?> — <a href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a></p></div>
    <?php endif; ?>

    <div class="shop-hero">
        <?= render_partial('partials/shop_banner', ['images' => $banners, 'w' => 1100, 'h' => 300]) ?>
        <div class="shop-hero-id">
            <?php if ($logo !== null): ?>
                <img class="shop-logo" src="<?= e(CloudinaryService::imageUrl($logo, 160, 160)) ?>" alt="" width="80" height="80">
            <?php else: ?>
                <div class="shop-logo shop-logo--empty" aria-hidden="true">🛍️</div>
            <?php endif; ?>
            <div>
                <h1><?= e((string) $boutique['name']) ?>
                    <?php if (!empty($seller_verified)): ?>
                        <span class="badge badge-verified" title="<?= e(t('shop.verified_hint')) ?>">✅ <?= e(t('shop.verified_seller')) ?></span>
                    <?php endif; ?>
                </h1>
                <?php if (!empty($boutique['tagline'])): ?><p class="lead"><?= e((string) $boutique['tagline']) ?></p><?php endif; ?>
                <p class="muted">
                    <?php if (!empty($boutique['category'])): ?><span class="badge badge-neutral"><?= e(t('listing.cat.' . $boutique['category'])) ?></span><?php endif; ?>
                    <?php if ($cc !== ''): ?> <?= flag_emoji($cc) ?> <?= e(country_name($cc)) ?><?php endif; ?>
                </p>
                <?= render_partial('partials/share_row', [
                    'share_url'  => $shopUrl,
                    'share_text' => t('share.shop_text', ['name' => (string) $boutique['name']]),
                ]) ?>
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
                    <?php if (!empty($boutique['city']) || !empty($boutique['country_code'])): ?>
                        <dt><?= e(t('shop.f.location')) ?></dt>
                        <dd>🌍 <?= e(implode(' · ', array_filter([
                            (string) ($boutique['city'] ?? '') ?: null,
                            !empty($boutique['country_code']) ? trim(flag_emoji((string) $boutique['country_code']) . ' ' . country_name((string) $boutique['country_code'])) : null,
                            !empty($boutique['continent']) ? t('geo.continent.' . $boutique['continent']) : null,
                        ]))) ?></dd>
                    <?php endif; ?>
                    <?php if ($zones): ?>
                        <dt><?= e(t('shop.f.zones')) ?></dt>
                        <dd class="zones-list">
                            <?php
                            // « Ma ville » / « Mon pays » deviennent les noms réellement
                            // détectés : ceux de la boutique (géolocalisation vérifiée),
                            // sinon ceux du profil du vendeur, sinon le libellé générique.
                            $zoneCity = (string) ($boutique['city'] ?? '') ?: (string) ($seller['city'] ?? '');
                            $zoneCc   = (string) ($boutique['country_code'] ?? '') ?: $cc;
                            foreach ($zones as $z) {
                                echo '<span>' . e(shop_zone_label($z, $zoneCity, $zoneCc)) . '</span>';
                            }
                            ?>
                        </dd>
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
                <?php
                [$ctSet, $ctPrimaries] = \App\Services\ContactChannels::forBoutique($boutique);
                ?>
                <?php if ($ctSet !== []): ?>
                    <div class="contact-buttons">
                        <?php foreach ($ctPrimaries as $ch): $pm = \App\Services\ContactChannels::meta($ch); ?>
                            <a class="btn btn-primary btn-block contact-btn contact--<?= e($pm['class']) ?>" rel="noopener" target="_blank"
                               href="<?= e(\App\Services\ContactChannels::url($ch, $ctSet[$ch])) ?>">
                                <span aria-hidden="true"><?= $pm['icon'] ?></span> <?= e(t('contact.reach', ['channel' => $pm['label']])) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php $others = array_filter($ctSet, static fn ($k) => !in_array($k, $ctPrimaries, true), ARRAY_FILTER_USE_KEY); ?>
                        <?php if ($others !== []): ?>
                            <div class="contact-secondary">
                                <?php foreach ($others as $ch => $val): $m = \App\Services\ContactChannels::meta($ch); ?>
                                    <a class="contact-ico contact--<?= e($m['class']) ?>" rel="noopener" target="_blank"
                                       href="<?= e(\App\Services\ContactChannels::url($ch, $val)) ?>"
                                       title="<?= e($m['label']) ?>" aria-label="<?= e($m['label']) ?>"><?= $m['icon'] ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($waPhone !== ''): ?>
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>">💬 <?= e(t('listing.contact_whatsapp')) ?></a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
