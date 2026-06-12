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
                    <?php foreach (array_filter(explode(',', (string) ($resto['cuisine'] ?? ''))) as $cui): ?><span class="badge badge-neutral"><?= e(t('resto.cuisine.' . $cui)) ?></span> <?php endforeach; ?>
                    <?php if (!empty($resto['city']) || $cc !== ''): ?> 🌍 <?= e(trim(($resto['city'] ?? '') . ' ' . ($cc !== '' ? flag_emoji($cc) : ''))) ?><?php endif; ?>
                </p>
                <?= render_partial('partials/share_row', ['share_url' => $shopUrl, 'share_text' => t('resto.share_text', ['name' => (string) $resto['name']])]) ?>
            </div>
        </div>
    </div>

    <div class="shop-body">
        <div class="panel">
            <h2 class="panel-title">📋 <?= e(t('resto.menu_title')) ?></h2>
            <?php
            $hasItems = false;
            foreach ($categories as $c) { if (!empty($by_cat[(int) $c['id']])) { $hasItems = true; break; } }
            ?>
            <?php if (!$hasItems): ?>
                <div class="empty-state"><p><?= e(t('resto.menu_empty')) ?></p></div>
            <?php else: ?>
                <?php foreach ($categories as $c): $list = $by_cat[(int) $c['id']] ?? []; ?>
                    <?php if ($list === []) { continue; } ?>
                    <div class="menu-cat">
                        <h3 class="menu-cat-title"><?= e((string) $c['name']) ?></h3>
                        <?php foreach ($list as $it): ?>
                            <div class="menu-item-row">
                                <div class="menu-item-main">
                                    <span class="menu-item-name"><?= e((string) $it['name']) ?>
                                        <?php foreach (array_filter(explode(',', (string) ($it['diets'] ?? ''))) as $dt): ?><span class="diet-badge"><?= e(t('resto.diet.' . $dt)) ?></span><?php endforeach; ?>
                                    </span>
                                    <?php if (!empty($it['description'])): ?><span class="menu-item-desc"><?= e((string) $it['description']) ?></span><?php endif; ?>
                                </div>
                                <span class="menu-item-price"><?= e(format_price((int) $it['price_cents'], $cur)) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <p class="hint">🧺 <?= e(t('resto.cart_soon')) ?></p>
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
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank" href="https://wa.me/<?= e($wa) ?>">💬 <?= e(t('resto.order_whatsapp')) ?></a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
