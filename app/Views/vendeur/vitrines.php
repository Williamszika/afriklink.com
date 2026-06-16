<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var ?array $boutique  @var ?array $restaurant */
$verticals = [
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('store', ['size' => 24]) ?> <?= e(t('pro.dash.create_title')) ?></h1>
            <p class="muted"><?= e(t('pro.dash.create_desc')) ?></p>
        </div>

        <h2 class="vitrines-section">🛍️ <?= e(t('vitrines.my_shops')) ?></h2>
        <div class="action-grid">
            <?php foreach (($boutiques ?? []) as $b): $isActive = (int) $b['id'] === (int) ($boutique['id'] ?? 0); ?>
                <form method="post" action="<?= e(url('/vendeur/boutique-active')) ?>" class="action-card action-card--live shop-mini">
                    <?= csrf_field() ?>
                    <input type="hidden" name="boutique_id" value="<?= (int) $b['id'] ?>">
                    <input type="hidden" name="to" value="/boutique/gerer">
                    <span class="action-head">🛍️ <strong><?= e((string) $b['name']) ?></strong>
                        <?php if ($isActive): ?><span class="chip-live"><?= e(t('vitrines.active')) ?></span><?php endif; ?></span>
                    <span class="muted">
                        <?php if (!empty($b['category'])): ?><?= e(t('listing.cat.' . $b['category'])) ?> · <?php endif; ?>
                        <?= e(($b['status'] ?? '') === 'published' ? t('vitrines.published') : t('vitrines.draft')) ?>
                    </span>
                    <button type="submit" class="btn btn-primary btn-sm shop-mini-cta"><?= e(t('shop.cta_manage')) ?> →</button>
                </form>
            <?php endforeach; ?>
            <a class="action-card shop-mini shop-mini--new" href="<?= e(url('/boutique/creer')) ?>">
                <span class="action-head">＋ <strong><?= e(t('seller.shop_switch.new')) ?></strong></span>
                <span class="muted"><?= e(t('vitrines.new_desc')) ?></span>
            </a>
        </div>

        <h2 class="vitrines-section"><?= e(t('vitrines.other')) ?></h2>
        <div class="action-grid">
            <!-- Restaurant : RÉEL -->
            <a class="action-card action-card--live" href="<?= e(url($restaurant ? '/restaurant/gerer' : '/restaurant/creer')) ?>">
                <span class="action-head">🍽️ <strong><?= e(t('pro.vertical.restaurant')) ?></strong>
                    <span class="chip-live"><?= e($restaurant ? t('shop.cta_manage') : t('shop.cta_create')) ?></span></span>
                <span class="muted"><?= e(t('pro.vertical.restaurant_desc')) ?></span>
            </a>

            <!-- Les autres verticales : bientôt -->
            <?php foreach ($verticals as $v): ?>
                <a class="action-card" href="<?= e(url('/bientot/' . $v['key'])) ?>">
                    <span class="action-head"><?= $v['icon'] ?> <strong><?= e(t('pro.vertical.' . $v['key'])) ?></strong>
                        <span class="chip-soon"><?= e(t('dash.soon')) ?></span></span>
                    <span class="muted"><?= e(t('pro.vertical.' . $v['key'] . '_desc')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
</div>
