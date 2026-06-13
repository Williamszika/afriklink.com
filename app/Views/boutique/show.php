<?php
/** @var array $boutique  @var array $seller  @var bool $is_owner  @var bool $seller_verified  @var list<string> $banners */
use App\Services\CloudinaryService;

$logo   = $boutique['logo_public_id'] ?? null;
$banners = $banners ?? array_filter([$boutique['banner_public_id'] ?? null]);
$cc     = strtoupper((string) ($seller['country_code'] ?? ''));
$waPhone = preg_replace('/\D+/', '', (string) ($seller['phone'] ?? ''));
$zones = array_filter(explode(',', (string) ($boutique['delivery_zones'] ?? '')));
$methods = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
$shopUrl = url('/boutique/' . $boutique['slug']);
$cur = (string) $boutique['currency'];
$curSym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur;
// Commande en ligne : sur une vitrine publiée pour tout le monde ; sur un
// brouillon, seulement le propriétaire (aperçu — la vraie commande est bloquée).
$published = ($boutique['status'] ?? '') === 'published';
$previewOrder = !$published && $is_owner;
$canOrder = !empty($products) && ($published || $is_owner);
?>
<section class="shop-page">
<?php if ($previewOrder && $canOrder): ?>
    <div class="notice notice-info"><p>👁️ <?= e(t('shop.preview_note')) ?></p></div>
<?php endif; ?>
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
                <?php if (!empty($shop_rating['count'])): ?>
                    <p class="shop-rating"><?= render_partial('partials/stars', ['avg' => $shop_rating['avg'], 'count' => $shop_rating['count']]) ?></p>
                <?php endif; ?>
                <?= render_partial('partials/share_row', [
                    'share_url'  => $shopUrl,
                    'share_text' => t('share.shop_text', ['name' => (string) $boutique['name']]),
                ]) ?>
                <?php if ($canOrder): ?>
                    <button type="button" class="btn btn-primary cart-hero-btn" data-cart-open>
                        🛒 <?= e(t('bcart.view_cart')) ?> <span class="cart-hero-count" data-cart-count>0</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="shop-body">
        <div class="panel" data-cart-root data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>">
            <h2 class="panel-title">📦 <?= e(t('shop.products_title')) ?></h2>
            <?php if (empty($products)): ?>
                <div class="empty-state"><p><?= e(t('shop.no_products_public')) ?></p></div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $pr): ?>
                        <?php
                        $m = $mains[(int) $pr['id']] ?? null;
                        $inStock = $pr['stock'] === null || (int) $pr['stock'] > 0;
                        ?>
                        <div class="product-cell">
                            <a class="product-card" href="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $pr['public_id'])) ?>">
                                <span class="product-card-img">
                                    <?php if ($m !== null): ?>
                                        <img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy">
                                    <?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                                    <?php if (!$inStock): ?><span class="card-out-badge"><?= e(t('product.out_of_stock')) ?></span><?php endif; ?>
                                </span>
                                <span class="product-card-name"><?= e((string) $pr['name']) ?></span>
                                <span class="product-card-price"><?= e(format_price((int) $pr['price_cents'], $cur)) ?></span>
                                <?php if (!empty($ratings[(int) $pr['id']]['count'])): ?>
                                    <span class="product-card-rating"><?= render_partial('partials/stars', ['avg' => $ratings[(int) $pr['id']]['avg'], 'count' => $ratings[(int) $pr['id']]['count'], 'small' => true]) ?></span>
                                <?php endif; ?>
                            </a>
                            <?php if ($canOrder && $inStock): ?>
                                <div class="product-actions">
                                    <button type="button" class="btn btn-primary btn-sm buy-now-btn" data-buy-now="<?= e((string) $pr['public_id']) ?>">⚡ <?= e(t('bcart.buy_now')) ?></button>
                                    <?= render_partial('partials/cart_stepper', ['id' => (string) $pr['public_id'], 'size' => '', 'name' => (string) $pr['name'], 'price' => (int) $pr['price_cents'], 'add_label' => t('bcart.add_to_cart')]) ?>
                                </div>
                            <?php endif; ?>
                        </div>
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
                    <?php $payTerms = array_filter(explode(',', (string) ($boutique['payment_terms'] ?? ''))); ?>
                    <?php if ($payTerms): ?>
                        <dt><?= e(t('shop.f.payment_terms')) ?></dt>
                        <dd class="pay-terms-list">
                            <?php foreach ($payTerms as $x): ?>
                                <span class="pay-term-item"><img src="<?= e(asset('img/pay/' . $x . '.svg')) ?>" alt="" width="34" height="22"> <?= e(t('shop.payterm.' . $x)) ?></span>
                            <?php endforeach; ?>
                        </dd>
                    <?php elseif (!empty($boutique['cod_enabled'])): ?>
                        <dt><?= e(t('shop.f.payment')) ?></dt><dd>💵 <?= e(t('shop.f.cod')) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php $payMethods = array_filter(explode(',', (string) ($boutique['payment_methods'] ?? ''))); ?>
                <?php if ($payMethods): ?>
                    <div class="pay-accepted">
                        <p class="pay-accepted-label"><?= e(t('shop.f.payment_methods')) ?></p>
                        <div class="pay-logos">
                            <?php foreach ($payMethods as $mk): ?>
                                <img class="pay-logo" src="<?= e(asset('img/pay/' . $mk . '.svg')) ?>"
                                     alt="<?= e(t('shop.paymethod.' . $mk)) ?>" title="<?= e(t('shop.paymethod.' . $mk)) ?>" width="40" height="26">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                [$ctSet, $ctPrimaries] = \App\Services\ContactChannels::forBoutique($boutique);
                ?>
                <?php if ($ctSet !== []): ?>
                    <div class="contact-buttons">
                        <?php foreach ($ctPrimaries as $ch): $pm = \App\Services\ContactChannels::meta($ch); ?>
                            <a class="btn btn-block contact-btn contact--<?= e($pm['class']) ?>" rel="noopener" target="_blank"
                               href="<?= e(\App\Services\ContactChannels::url($ch, $ctSet[$ch])) ?>">
                                <img class="social-logo" src="<?= e(\App\Services\ContactChannels::logo($ch)) ?>" alt="" width="24" height="24">
                                <?= e(t('contact.reach', ['channel' => $pm['label']])) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php $others = array_filter($ctSet, static fn ($k) => !in_array($k, $ctPrimaries, true), ARRAY_FILTER_USE_KEY); ?>
                        <?php if ($others !== []): ?>
                            <div class="contact-secondary">
                                <?php foreach ($others as $ch => $val): $m = \App\Services\ContactChannels::meta($ch); ?>
                                    <a class="contact-logo" rel="noopener" target="_blank"
                                       href="<?= e(\App\Services\ContactChannels::url($ch, $val)) ?>"
                                       title="<?= e($m['label']) ?>" aria-label="<?= e($m['label']) ?>"><img src="<?= e(\App\Services\ContactChannels::logo($ch)) ?>" alt="<?= e($m['label']) ?>" width="46" height="46"></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($waPhone !== ''): ?>
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('listing.contact_whatsapp')) ?></a>
                <?php endif; ?>
            </div>

            <?php if ($canOrder): ?>
                <!-- Le panier (JS) est posté ici, revalidé serveur, puis on passe à la caisse. -->
                <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/caisse')) ?>" data-caisse-form hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="cart_json" data-cart-json value="[]">
                </form>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($canOrder): ?>
        <!-- Barre de panier (apparaît dès qu'un article est choisi) -->
        <div class="cart-bar" data-cart-bar hidden>
            <span class="cart-bar-info">🧺 <span data-cart-count>0</span> <?= e(t('rorder.items')) ?> · <strong data-cart-total>0</strong></span>
            <button type="button" class="btn btn-primary" data-cart-checkout><?= e(t('bcart.to_checkout')) ?> →</button>
        </div>
    <?php endif; ?>
</section>
