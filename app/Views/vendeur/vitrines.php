<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var ?array $boutique  @var ?array $restaurant  @var list<array> $boutiques */
$catEmoji = [
    'mode' => '👗', 'electronique' => '📱', 'maison' => '🏠', 'beaute' => '💄',
    'alimentation' => '🍲', 'auto' => '🚗', 'artisanat' => '🎨', 'bebe' => '👶',
    'sport' => '⚽', 'autres' => '🛍️',
];
$myBoutiques = $boutiques ?? [];
usort($myBoutiques, static function (array $a, array $b): int {
    return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''))
        ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
});
$staff = is_staff($user);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sdash svit">

        <div class="sdash-topbar">
            <div class="sdash-hello">
                <h1><?= e(t('pro.dash.create_title')) ?></h1>
                <p><?= e(t('pro.dash.create_desc')) ?></p>
            </div>
            <div class="sdash-actions">
                <?php if ($staff): ?>
                    <div class="svit-admin" aria-label="<?= e(t('vitrines.admin_area')) ?>">
                        <a class="svit-icbtn" href="<?= e(url('/admin')) ?>" title="<?= e(t('vitrines.admin')) ?>" aria-label="<?= e(t('vitrines.admin')) ?>"><?= icon('shield', ['size' => 18]) ?></a>
                        <a class="svit-icbtn" href="<?= e(url('/admin/kyc')) ?>" title="<?= e(t('vitrines.moderation')) ?>" aria-label="<?= e(t('vitrines.moderation')) ?>"><?= icon('check', ['size' => 18]) ?></a>
                        <a class="svit-icbtn" href="<?= e(url('/admin/email')) ?>" title="<?= e(t('vitrines.emails')) ?>" aria-label="<?= e(t('vitrines.emails')) ?>"><?= icon('mail', ['size' => 18]) ?></a>
                    </div>
                <?php endif; ?>
                <a class="btn btn-gold" href="<?= e(url('/boutique/creer')) ?>"><?= icon('plus', ['size' => 16]) ?> <?= e(t('seller.shop_switch.new')) ?></a>
            </div>
        </div>

        <!-- Mes boutiques -->
        <section class="svit-section">
            <div class="svit-section-head">
                <h2><span aria-hidden="true">🛍️</span> <?= e(t('vitrines.my_shops')) ?></h2>
                <?php if ($myBoutiques !== []): ?><span class="svit-count"><?= count($myBoutiques) ?></span><?php endif; ?>
            </div>
            <div class="svit-shops">
                <?php foreach ($myBoutiques as $b):
                    $isActive = (int) $b['id'] === (int) ($boutique['id'] ?? 0);
                    $cat = (string) ($b['category'] ?? '');
                    $em  = $catEmoji[$cat] ?? '🛍️';
                    $pub = ($b['status'] ?? '') === 'published';
                ?>
                    <form method="post" action="<?= e(url('/vendeur/boutique-active')) ?>" class="svit-shop-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="boutique_id" value="<?= (int) $b['id'] ?>">
                        <input type="hidden" name="to" value="/boutique/gerer">
                        <button type="submit" class="svit-shop">
                            <span class="svit-shop-top">
                                <span class="svit-shop-emoji" aria-hidden="true"><?= $em ?></span>
                                <span class="svit-tag svit-tag--<?= $pub ? 'active' : 'draft' ?>"><?= e($pub ? t('vitrines.published') : t('vitrines.draft')) ?></span>
                            </span>
                            <span class="svit-shop-body">
                                <span class="svit-shop-cat"><?= $cat !== '' ? e(t('listing.cat.' . $cat)) : e((string) $b['name']) ?></span>
                                <span class="svit-shop-name"><?= e((string) $b['name']) ?></span>
                            </span>
                            <span class="svit-shop-foot">
                                <?php if ($isActive): ?><span class="svit-tag svit-tag--active"><?= e(t('vitrines.active')) ?></span><?php else: ?><span></span><?php endif; ?>
                                <span class="svit-manage"><?= e(t('shop.cta_manage')) ?> →</span>
                            </span>
                        </button>
                    </form>
                <?php endforeach; ?>
                <a class="svit-shop svit-add" href="<?= e(url('/boutique/creer')) ?>">
                    <span class="svit-add-plus" aria-hidden="true"><?= icon('plus', ['size' => 22]) ?></span>
                    <b><?= e(t('seller.shop_switch.new')) ?></b>
                    <span><?= e(t('vitrines.new_desc')) ?></span>
                </a>
            </div>
        </section>

        <!-- Autres activités -->
        <section class="svit-section">
            <div class="svit-section-head"><h2><span aria-hidden="true">✨</span> <?= e(t('vitrines.other')) ?></h2></div>
            <div class="svit-acts">
                <a class="svit-act is-live" href="<?= e(url($restaurant ? '/restaurant/gerer' : '/restaurant/creer')) ?>">
                    <span class="svit-act-top"><span class="svit-act-emoji" aria-hidden="true">🍽️</span><span class="svit-tag svit-tag--active"><?= e($restaurant ? t('shop.cta_manage') : t('shop.cta_create')) ?></span></span>
                    <h3><?= e(t('pro.vertical.restaurant')) ?></h3>
                    <p><?= e(t('pro.vertical.restaurant_desc')) ?></p>
                    <span class="btn btn-ghost btn-sm svit-act-cta"><?= e($restaurant ? t('shop.cta_manage') : t('shop.cta_create')) ?> →</span>
                </a>
                <?php foreach ([['salon', '💈'], ['service', '🛠️']] as [$k, $em]): ?>
                    <div class="svit-act is-soon">
                        <span class="svit-act-top"><span class="svit-act-emoji" aria-hidden="true"><?= $em ?></span><span class="svit-tag svit-tag--soon"><?= e(t('dash.soon')) ?></span></span>
                        <h3><?= e(t('pro.vertical.' . $k)) ?></h3>
                        <p><?= e(t('pro.vertical.' . $k . '_desc')) ?></p>
                        <span class="svit-act-cta is-disabled"><?= e(t('dash.soon')) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</div>
