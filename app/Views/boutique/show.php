<?php
/** @var array $boutique  @var array $seller  @var bool $is_owner  @var bool $seller_verified  @var list<string> $banners */
use App\Services\BusinessHours;
use App\Services\CloudinaryService;
use App\Services\ContactChannels;

// Canaux de contact / réseaux sociaux : calculés une fois, réutilisés sur la
// bannière (icônes cliquables) et dans le bloc « Contacter la boutique ».
[$ctSet, $ctPrimaries] = ContactChannels::forBoutique($boutique);

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
$onVacation = !empty($boutique['is_vacation']);
// Horaires structurés : « ouvert / fermé maintenant » à l'heure locale du pays.
$hoursStruct = BusinessHours::decode($boutique['hours_json'] ?? null);
$hoursTz     = BusinessHours::timezoneFor($boutique['country_code'] ?? ($seller['country_code'] ?? null));
$openNow     = BusinessHours::isOpenNow($hoursStruct, $hoursTz);
$hoursToday  = $hoursStruct !== [] ? BusinessHours::todayKey($hoursTz) : '';
// Commandes suspendues hors horaires (si le commerçant l'a activé).
$enforceClosed = !empty($boutique['orders_within_hours']) && $openNow === false;
$canOrder = !empty($products) && ($published || $is_owner) && !$onVacation && !$enforceClosed;
// Couleur d'accent de la boutique : on redéfinit --brand (et une nuance plus
// foncée pour le survol) sur la vitrine. Ignorée si trop claire (texte blanc
// des boutons sinon illisible).
$accentStyle = '';
$accentHex = (string) ($boutique['accent_color'] ?? '');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $accentHex)) {
    $rr = (int) hexdec(substr($accentHex, 1, 2));
    $gg = (int) hexdec(substr($accentHex, 3, 2));
    $bb = (int) hexdec(substr($accentHex, 5, 2));
    if (0.2126 * $rr + 0.7152 * $gg + 0.0722 * $bb < 200) {
        $dark = sprintf('#%02x%02x%02x', max(0, $rr - 30), max(0, $gg - 30), max(0, $bb - 30));
        $accentStyle = '--brand:' . $accentHex . ';--brand-dark:' . $dark;
    }
}
?>
<section class="shop-page"<?= $accentStyle !== '' ? ' style="' . e($accentStyle) . '"' : '' ?>>
<?php if (!empty($boutique['announcement'])): ?>
    <div class="shop-announce"><?= icon('megaphone', ['size' => 16]) ?> <?= e((string) $boutique['announcement']) ?></div>
<?php endif; ?>
<?php if ($onVacation): ?>
    <div class="notice notice-warning"><p>🏖️ <?= e(!empty($boutique['vacation_until']) ? t('shop.vacation_until', ['date' => date('d/m/Y', strtotime((string) $boutique['vacation_until']))]) : t('shop.vacation_now')) ?></p></div>
<?php elseif ($enforceClosed): ?>
    <div class="notice notice-warning"><p><?= icon('clock', ['size' => 16]) ?> <?= e(t('shop.hours.closed_now_note')) ?></p></div>
<?php endif; ?>
<?php if ($previewOrder && $canOrder): ?>
    <div class="notice notice-info"><p><?= icon('eye', ['size' => 16]) ?> <?= e(t('shop.preview_note')) ?></p></div>
<?php endif; ?>
    <?php if ($is_owner && ($boutique['status'] ?? '') !== 'published'): ?>
        <div class="notice notice-info"><p><?= e(t('shop.owner_draft')) ?> — <a href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a></p></div>
    <?php endif; ?>

    <div class="shop-hero">
        <?= render_partial('partials/shop_banner', ['images' => $banners, 'w' => 1100, 'h' => 300]) ?>
        <?php if ($ctSet !== []): ?>
            <div class="shop-social" aria-label="<?= e(t('shop.social_label')) ?>">
                <?php foreach ($ctSet as $ch => $val): $m = ContactChannels::meta($ch); ?>
                    <a class="shop-social-link" rel="noopener" target="_blank"
                       href="<?= e(ContactChannels::url($ch, $val)) ?>"
                       title="<?= e($m['label']) ?>" aria-label="<?= e($m['label']) ?>">
                        <img src="<?= e(ContactChannels::logo($ch)) ?>" alt="<?= e($m['label']) ?>" width="26" height="26">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="shop-hero-id">
            <?php if ($logo !== null): ?>
                <img class="shop-logo" src="<?= e(CloudinaryService::imageUrl($logo, 160, 160)) ?>" alt="" width="80" height="80">
            <?php else: ?>
                <div class="shop-logo shop-logo--empty" aria-hidden="true"><?= icon('store', ['size' => 34]) ?></div>
            <?php endif; ?>
            <div>
                <h1><?= e((string) $boutique['name']) ?>
                    <?php if (!empty($seller_verified)): ?>
                        <span class="badge badge-verified" title="<?= e(t('shop.verified_hint')) ?>"><?= icon('shield', ['size' => 15]) ?> <?= e(t('shop.verified_seller')) ?></span>
                    <?php endif; ?>
                </h1>
                <?php if (!empty($boutique['tagline'])): ?><p class="lead"><?= e((string) $boutique['tagline']) ?></p><?php endif; ?>
                <p class="muted">
                    <?php if (!empty($boutique['category'])): ?><span class="badge badge-neutral"><?= e(t('listing.cat.' . $boutique['category'])) ?></span><?php endif; ?>
                    <?php $heroPlace = place_label($boutique['city'] ?? null, $boutique['country_code'] ?: $cc); ?>
                    <?php if ($heroPlace !== ''): ?> <?= e($heroPlace) ?><?php endif; ?>
                    <?php if ($openNow !== null): ?> <span class="hours-badge <?= $openNow ? 'is-open' : 'is-closed' ?>"><?= icon('clock', ['size' => 12]) ?> <?= e($openNow ? t('shop.open_now') : t('shop.closed_now')) ?></span><?php endif; ?>
                </p>
                <?php if (!empty($shop_rating['count'])): ?>
                    <p class="shop-rating"><?= render_partial('partials/stars', ['avg' => $shop_rating['avg'], 'count' => $shop_rating['count']]) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="shop-body">
        <div class="panel" data-cart-root data-shop-slug="<?= e($boutique['slug']) ?>" data-added-label="<?= e(t('bcart.added')) ?>" data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>">
            <div class="shop-toolbar">
                <h2 class="panel-title"><?= icon('package', ['size' => 18]) ?> <?= e(t('shop.products_title')) ?>
                    <span class="shop-count muted"><?= e(t('shop.count', ['n' => count($products)])) ?></span>
                </h2>
                <?php if (!empty($products)): $sort = $sort ?? ''; ?>
                    <div class="shop-sort">
                        <span class="muted"><?= e(t('explore.sort_label')) ?> :</span>
                        <?php foreach (['recent' => 'explore.sort.recent', 'price_asc' => 'explore.sort.price_asc', 'price_desc' => 'explore.sort.price_desc', 'rating' => 'explore.sort.rating'] as $sk => $lk): ?>
                            <a class="chip-filter<?= $sort === $sk ? ' is-active' : '' ?>" href="<?= e(url('/boutique/' . $boutique['slug'] . '?tri=' . $sk)) ?>"><?= e(t($lk)) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php $rayons = $collections ?? []; $curRayon = $rayon ?? ''; if ($rayons !== []): $sfx = ($sort ?? '') !== '' ? '&tri=' . $sort : ''; ?>
                <div class="shop-rayons">
                    <a class="chip-filter<?= $curRayon === '' ? ' is-active' : '' ?>" href="<?= e(url('/boutique/' . $boutique['slug'] . (($sort ?? '') !== '' ? '?tri=' . $sort : ''))) ?>"><?= e(t('shop.rayon_all')) ?></a>
                    <?php foreach ($rayons as $c): ?>
                        <a class="chip-filter<?= $curRayon === $c ? ' is-active' : '' ?>" href="<?= e(url('/boutique/' . $boutique['slug'] . '?rayon=' . rawurlencode((string) $c) . $sfx)) ?>"><?= e((string) $c) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (empty($products)): ?>
                <div class="empty-state"><p><?= e($curRayon !== '' ? t('shop.rayon_empty') : t('shop.no_products_public')) ?></p></div>
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
                                    <?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span><?php endif; ?>
                                    <?php if (!empty($pr['pinned'])): ?><span class="pin-badge" title="<?= e(t('product.pinned')) ?>"><?= icon('pin', ['size' => 14]) ?></span><?php endif; ?>
                                    <?php if (\App\Models\Product::isPromoted($pr)): ?><span class="promo-badge"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                                    <?php if (!$inStock): ?><span class="card-out-badge"><?= e(t('product.out_of_stock')) ?></span><?php endif; ?>
                                </span>
                                <span class="product-card-name"><?= e((string) $pr['name']) ?></span>
                                <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $pr['price_cents'], 'cur' => $cur]) ?></span>
                                <?php if (!empty($ratings[(int) $pr['id']]['count'])): ?>
                                    <span class="product-card-rating"><?= render_partial('partials/stars', ['avg' => $ratings[(int) $pr['id']]['avg'], 'count' => $ratings[(int) $pr['id']]['count'], 'small' => true]) ?></span>
                                <?php endif; ?>
                            </a>
                            <?= render_partial('partials/wish_heart', ['pid' => (string) $pr['public_id']]) ?>
                            <?= render_partial('partials/compare_toggle', ['pid' => (string) $pr['public_id']]) ?>
                            <?php if ($canOrder && $inStock): ?>
                                <div class="product-actions">
                                    <button type="button" class="btn btn-primary btn-sm buy-now-btn" data-buy-now="<?= e((string) $pr['public_id']) ?>"><?= icon('zap', ['size' => 16]) ?> <?= e(t('bcart.buy_now')) ?></button>
                                    <?= render_partial('partials/cart_stepper', ['id' => (string) $pr['public_id'], 'size' => '', 'name' => (string) $pr['name'], 'price' => (int) $pr['price_cents'], 'add_label' => t('bcart.add_to_cart'), 'qty' => \App\Services\Cart::qty((int) $boutique['id'], (string) $pr['public_id'])]) ?>
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
            <?php if (!empty($boutique['return_policy'])): ?>
                <div class="panel">
                    <h2 class="panel-title"><?= icon('undo', ['size' => 18]) ?> <?= e(t('shop.policy_title')) ?></h2>
                    <p class="listing-description"><?= nl2br(e((string) $boutique['return_policy'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($ctPrimaries !== []): ?>
                <div class="panel shop-contact-panel">
                    <h2 class="panel-title"><?= icon('chat', ['size' => 18]) ?> <?= e(t('shop.contact_title')) ?></h2>
                    <div class="contact-buttons">
                        <?php foreach ($ctPrimaries as $ch): $pm = ContactChannels::meta($ch); ?>
                            <a class="btn btn-block contact-btn contact--<?= e($pm['class']) ?>" rel="noopener" target="_blank"
                               href="<?= e(ContactChannels::url($ch, $ctSet[$ch])) ?>">
                                <img class="social-logo" src="<?= e(ContactChannels::logo($ch)) ?>" alt="" width="24" height="24">
                                <?= e(t('contact.reach', ['channel' => $pm['label']])) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($waPhone !== ''): ?>
                <div class="panel shop-contact-panel">
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('listing.contact_whatsapp')) ?></a>
                </div>
            <?php endif; ?>

            <details class="panel info-disclosure">
                <summary class="info-summary">
                    <?= icon('info', ['size' => 18]) ?>
                    <span><?= e(t('shop.infos')) ?></span>
                    <?= icon('chevron', ['size' => 18, 'class' => 'info-caret']) ?>
                </summary>
                <div class="info-disclosure-body">
                <dl class="meta">
                    <dt><?= e(t('shop.f.type')) ?></dt>
                    <dd><?= ($boutique['shop_type'] ?? 'online') === 'physical' ? icon('store', ['size' => 16]) . ' ' . e(t('shop.type.physical')) : icon('globe', ['size' => 16]) . ' ' . e(t('shop.type.online')) ?></dd>
                    <?php if (($boutique['shop_type'] ?? '') === 'physical' && !empty($boutique['address'])): ?>
                        <dt><?= e(t('shop.f.address')) ?></dt><dd><?= icon('pin', ['size' => 15]) ?> <?= e((string) $boutique['address']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($boutique['city']) || !empty($boutique['country_code'])): ?>
                        <dt><?= e(t('shop.f.location')) ?></dt>
                        <dd><?= icon('globe', ['size' => 15]) ?> <?= e(implode(' · ', array_filter([
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
                    <?php
                    $dFee = (int) ($boutique['delivery_fee_cents'] ?? 0);
                    $dIntl = (int) ($boutique['delivery_intl_cents'] ?? 0);
                    $dDelay = (string) ($boutique['delivery_delay'] ?? '');
                    if ($dFee > 0 || $dIntl > 0 || $dDelay !== ''):
                        $rows = [];
                        if ($dFee > 0) { $rows[] = e(t('shop.method.local')) . ' : <strong>' . e(format_price($dFee, $cur)) . '</strong>'; }
                        if ($dIntl > 0) { $rows[] = e(t('shop.method.international')) . ' : <strong>' . e(format_price($dIntl, $cur)) . '</strong>'; }
                        if (!empty($boutique['free_ship_cents'])) { $rows[] = e(t('shop.f.free_ship')) . ' ' . e(format_price((int) $boutique['free_ship_cents'], $cur)); }
                        if ($dDelay !== '') { $rows[] = icon('clock', ['size' => 15]) . ' ' . e(t('shop.prep.' . $dDelay)); }
                    ?>
                        <dt><?= e(t('shop.f.delivery_fee')) ?></dt>
                        <dd><?= icon('truck', ['size' => 16]) ?> <?= implode('<br>', $rows) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($shipping_zones)): ?>
                        <dt><?= e(t('ship.ships_to')) ?></dt>
                        <dd>
                            <ul class="ship-zones-public">
                                <?php foreach ($shipping_zones as $z):
                                    $codes = array_filter(array_map('trim', explode(',', (string) ($z['countries'] ?? ''))));
                                    $zn = $codes === [] ? t('ship.zone.rest') : (string) $z['name'];
                                ?>
                                    <li><strong><?= e($zn) ?></strong> — <?= e(format_price((int) $z['fee_cents'], $cur)) ?><?php if (!empty($z['free_above_cents'])): ?> · <?= e(t('ship.zone.free_above', ['amount' => format_price((int) $z['free_above_cents'], $cur)])) ?><?php endif; ?><?php if (!empty($z['delay'])): ?> · <?= e(t('shop.prep.' . $z['delay'])) ?><?php endif; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </dd>
                    <?php endif; ?>
                    <?php if (!empty($boutique['prep_time'])): ?>
                        <dt><?= e(t('shop.f.prep')) ?></dt><dd><?= e(t('shop.prep.' . $boutique['prep_time'])) ?></dd>
                    <?php endif; ?>
                    <?php if ($hoursStruct !== []): ?>
                        <dt>
                            <?= e(t('shop.cfg.hours')) ?>
                            <?php if ($openNow !== null): ?>
                                <span class="hours-badge <?= $openNow ? 'is-open' : 'is-closed' ?>"><?= e($openNow ? t('shop.open_now') : t('shop.closed_now')) ?></span>
                            <?php endif; ?>
                        </dt>
                        <dd>
                            <ul class="hours-list">
                                <?php foreach (BusinessHours::DAYS as $day): $s = $hoursStruct[$day] ?? null; ?>
                                    <li<?= $day === $hoursToday ? ' class="is-today"' : '' ?>>
                                        <span class="hours-list-day"><?= e(t('shop.hours.day.' . $day)) ?></span>
                                        <span class="hours-list-time"><?= $s !== null ? e($s['o'] . ' – ' . $s['c']) : e(t('shop.hours.closed')) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </dd>
                    <?php elseif (!empty($boutique['open_hours'])): ?>
                        <dt><?= e(t('shop.cfg.hours')) ?></dt><dd><?= icon('clock', ['size' => 15]) ?> <?= nl2br(e((string) $boutique['open_hours'])) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($boutique['min_order_cents'])): ?>
                        <dt><?= e(t('shop.cfg.min_order')) ?></dt><dd><?= icon('cart', ['size' => 15]) ?> <?= e(format_price((int) $boutique['min_order_cents'], $cur)) ?></dd>
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
                        <dt><?= e(t('shop.f.payment')) ?></dt><dd><?= icon('banknote', ['size' => 16]) ?> <?= e(t('shop.f.cod')) ?></dd>
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
                </div>
            </details>

            <?php if ($canOrder): ?>
                <!-- Le panier (JS) est posté ici, revalidé serveur, puis on passe à la caisse. -->
                <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/caisse')) ?>" data-caisse-form hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="cart_json" data-cart-json value="[]">
                </form>
            <?php endif; ?>
        </aside>
    </div>

    <?= render_partial('partials/assistant', ['boutique' => $boutique, 'wa' => $waPhone]) ?>
</section>
