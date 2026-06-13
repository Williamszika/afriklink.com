<?php
/** @var array $resto  @var list<array> $categories  @var array<int,list<array>> $by_cat
 *  @var bool $is_owner  @var array $seller */
$cur = (string) $resto['currency'];
$cc = strtoupper((string) ($resto['country_code'] ?? ''));
$services = array_filter(explode(',', (string) ($resto['services'] ?? '')));
$wa = preg_replace('/\D+/', '', (string) ($resto['contact_whatsapp'] ?? '') ?: (string) ($seller['phone'] ?? ''));
$shopUrl = url('/restaurant/' . $resto['slug']);
?>
<section class="shop-page">
    <?php if ($is_owner && ($resto['status'] ?? '') !== 'published'): ?>
        <div class="notice notice-info"><p><?= e(t('resto.owner_draft')) ?> — <a href="<?= e(url('/restaurant/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a></p></div>
    <?php endif; ?>

    <div class="shop-hero">
        <div class="resto-hero-band">🍽️</div>
        <div class="shop-hero-id">
            <div class="shop-logo shop-logo--empty" aria-hidden="true">🍽️</div>
            <div>
                <h1><?= e((string) $resto['name']) ?></h1>
                <?php if (!empty($resto['tagline'])): ?><p class="lead"><?= e((string) $resto['tagline']) ?></p><?php endif; ?>
                <p class="muted">
                    <?php foreach (array_filter(explode(',', (string) ($resto['cuisine'] ?? ''))) as $cui): ?>
                        <span class="badge badge-neutral"><?= $cui === 'autre' && !empty($resto['cuisine_other']) ? e((string) $resto['cuisine_other']) : e(t('resto.cuisine.' . $cui)) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($resto['city']) || $cc !== ''): ?> 🌍 <?= e(trim(($resto['city'] ?? '') . ' ' . ($cc !== '' ? flag_emoji($cc) : ''))) ?><?php endif; ?>
                </p>
                <?= render_partial('partials/share_row', ['share_url' => $shopUrl, 'share_text' => t('resto.share_text', ['name' => (string) $resto['name']])]) ?>
            </div>
        </div>
    </div>

    <?php
    $hasItems = false;
    foreach ($categories as $c) { if (!empty($by_cat[(int) $c['id']])) { $hasItems = true; break; } }
    $offered = $services !== [] ? $services : ['takeaway'];
    ?>
    <div class="shop-body">
        <div class="panel" data-cart-root
             data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>"
             data-cur-sym="<?= e(['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur) ?>">
            <h2 class="panel-title">📋 <?= e(t('resto.menu_title')) ?></h2>
            <?php if (!$hasItems): ?>
                <div class="empty-state"><p><?= e(t('resto.menu_empty')) ?></p></div>
            <?php else: ?>
                <?php foreach ($categories as $c): $list = $by_cat[(int) $c['id']] ?? []; ?>
                    <?php if ($list === []) { continue; } ?>
                    <div class="menu-cat">
                        <h3 class="menu-cat-title"><?= e((string) $c['name']) ?></h3>
                        <?php foreach ($list as $it): ?>
                            <?php $vars = \App\Models\MenuItem::variants($it['variants'] ?? null); $iid = (string) $it['public_id']; ?>
                            <div class="menu-item-row">
                                <div class="menu-item-main">
                                    <span class="menu-item-name"><?= e((string) $it['name']) ?>
                                        <?php foreach (array_filter(explode(',', (string) ($it['diets'] ?? ''))) as $dt): ?><span class="diet-badge"><?= e(t('resto.diet.' . $dt)) ?></span><?php endforeach; ?>
                                    </span>
                                    <?php if (!empty($it['description'])): ?><span class="menu-item-desc"><?= e((string) $it['description']) ?></span><?php endif; ?>
                                    <?php if ($vars !== []): ?>
                                        <div class="order-sizes">
                                            <?php foreach ($vars as $vr): $vOut = !empty($vr['out']); $lbl = rtrim(rtrim((string) $vr['v'], '0'), '.'); ?>
                                                <div class="order-size<?= $vOut ? ' is-out' : '' ?>">
                                                    <span class="order-size-label"><?= e($lbl) ?> L · <?= e(format_price((int) $vr['p'], $cur)) ?><?= $vOut ? ' — ' . e(t('resto.size_out')) : '' ?></span>
                                                    <?php if (!$vOut): ?>
                                                        <?= render_partial('restaurant/_stepper', ['id' => $iid, 'size' => (string) $vr['v'], 'name' => (string) $it['name'] . ' — ' . $lbl . ' L', 'price' => (int) $vr['p']]) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="menu-item-side">
                                    <span class="menu-item-price"><?= $vars !== [] ? e(t('resto.from_price', ['price' => format_price((int) $it['price_cents'], $cur)])) : e(format_price((int) $it['price_cents'], $cur)) ?></span>
                                    <?php if ($vars === []): ?>
                                        <?= render_partial('restaurant/_stepper', ['id' => $iid, 'size' => '', 'name' => (string) $it['name'], 'price' => (int) $it['price_cents']]) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <aside class="shop-aside">
            <div class="panel">
                <h2 class="panel-title"><?= e(t('shop.infos')) ?></h2>
                <dl class="meta">
                    <?php if ($services): ?>
                        <dt><?= e(t('resto.f.services')) ?></dt>
                        <dd><?= e(implode(' · ', array_map(static fn ($s) => t('resto.service.' . $s), $services))) ?></dd>
                    <?php endif; ?>
                    <?php $hoursLabel = resto_hours_label($resto['open_days'] ?? null, $resto['open_time'] ?? null, $resto['close_time'] ?? null, $resto['hours'] ?? null); ?>
                    <?php if ($hoursLabel !== ''): ?><dt><?= e(t('resto.f.hours')) ?></dt><dd>🕒 <?= e($hoursLabel) ?></dd><?php endif; ?>
                    <?php if (!empty($resto['address'])): ?><dt><?= e(t('resto.f.address')) ?></dt><dd>📍 <?= e((string) $resto['address']) ?></dd><?php endif; ?>
                    <?php if (!empty($resto['prep_minutes'])): ?><dt><?= e(t('resto.f.prep')) ?></dt><dd>⏱️ <?= (int) $resto['prep_minutes'] ?> min</dd><?php endif; ?>
                    <?php if (!empty($resto['delivery_fee_cents'])): ?><dt><?= e(t('resto.f.delivery_fee')) ?></dt><dd>🛵 <?= e(format_price((int) $resto['delivery_fee_cents'], $cur)) ?></dd><?php endif; ?>
                    <?php if (!empty($resto['delivery_min_cents'])): ?><dt><?= e(t('resto.f.delivery_min')) ?></dt><dd><?= e(format_price((int) $resto['delivery_min_cents'], $cur)) ?></dd><?php endif; ?>
                </dl>
                <?php if ($wa !== ''): ?>
                    <a class="btn btn-ghost btn-block btn-wa" rel="noopener" target="_blank" href="https://wa.me/<?= e($wa) ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('resto.order_whatsapp')) ?></a>
                <?php endif; ?>
            </div>

            <!-- Le panier (JS) est posté ici, revalidé serveur, puis on passe à la caisse. -->
            <form method="post" action="<?= e(url('/restaurant/' . $resto['slug'] . '/caisse')) ?>" data-caisse-form hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="cart_json" data-cart-json value="[]">
            </form>
        </aside>
    </div>

    <!-- Barre de panier (apparaît dès qu'un article est choisi) -->
    <div class="cart-bar" data-cart-bar hidden>
        <span class="cart-bar-info">🧺 <span data-cart-count>0</span> <?= e(t('rorder.items')) ?> · <strong data-cart-total>0</strong></span>
        <button type="button" class="btn btn-primary" data-cart-checkout><?= e(t('bcart.to_checkout')) ?> →</button>
    </div>
</section>
