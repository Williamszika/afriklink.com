<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$cc          = strtoupper((string) ($user['country_code'] ?? ''));

$items = [
    ['key' => 'overview',  'icon' => '🏠', 'href' => url('/dashboard'),         'label' => t('seller.nav.overview'),    'chip' => null],
    ['key' => 'vitrines',  'icon' => '🏪', 'href' => url('/vendeur/vitrines'),  'label' => t('seller.nav.storefronts'), 'chip' => null],
    ['key' => 'commandes', 'icon' => '📦', 'href' => url('/vendeur/commandes'), 'label' => t('seller.nav.orders'),      'chip' => t('dash.soon')],
    ['key' => 'messages',  'icon' => '💬', 'href' => url('/vendeur/messages'),  'label' => t('seller.nav.messages'),    'chip' => t('dash.soon')],
    ['key' => 'profil',    'icon' => '🏢', 'href' => url('/vendeur/profil'),    'label' => t('seller.nav.profile'),     'chip' => null],
];
?>
<aside class="seller-sidebar" aria-label="<?= e(t('seller.nav.aria')) ?>">
    <div class="seller-ident">
        <?php if ($avatar_url !== null): ?>
            <img class="avatar avatar-img" src="<?= e($avatar_url) ?>" alt="" width="48" height="48">
        <?php else: ?>
            <div class="avatar avatar-sm" aria-hidden="true">🏪</div>
        <?php endif; ?>
        <div class="seller-ident-body">
            <p class="seller-ident-name"><?= e($companyName) ?></p>
            <p class="seller-ident-meta">
                <?php if ($verified): ?>
                    <span class="badge badge-ok">✓ <?= e(t('pro.dash.badge_verified')) ?></span>
                <?php else: ?>
                    <span class="badge badge-warn"><?= e(t('pro.dash.badge_pending')) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <nav class="seller-nav">
        <?php foreach ($items as $it): ?>
            <a class="seller-nav-item<?= $active === $it['key'] ? ' is-active' : '' ?>"
               href="<?= e($it['href']) ?>"<?= $active === $it['key'] ? ' aria-current="page"' : '' ?>>
                <span class="seller-nav-ico" aria-hidden="true"><?= $it['icon'] ?></span>
                <span class="seller-nav-label"><?= e($it['label']) ?></span>
                <?php if ($it['chip'] !== null): ?><span class="chip-soon"><?= e($it['chip']) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
