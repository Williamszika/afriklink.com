<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';

// Pastilles en direct : commandes « à traiter » + messages non lus + avis sans réponse.
$ordersPending = \App\Models\Order::pendingForUser((int) ($user['id'] ?? 0));
$msgUnread     = \App\Models\Conversation::unreadCountFor((int) ($user['id'] ?? 0));
$boutiqueSb    = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
$reviewsTodo   = $boutiqueSb !== null ? \App\Models\Review::unansweredCountFor((int) $boutiqueSb['id']) : 0;
$groups = [
    ['label' => null, 'items' => [
        ['key' => 'overview',  'icon' => 'grid',  'href' => url('/dashboard'),         'label' => t('seller.nav.overview'),    'chip' => null],
        ['key' => 'vitrines',  'icon' => 'store', 'href' => url('/vendeur/vitrines'),  'label' => t('seller.nav.storefronts'), 'chip' => null],
        ['key' => 'pos',       'icon' => 'receipt', 'href' => url('/vendeur/point-de-vente'), 'label' => t('seller.nav.pos'), 'chip' => null],
        ['key' => 'commandes', 'icon' => 'package', 'href' => url('/vendeur/commandes'), 'label' => t('seller.nav.orders'),
         'chip' => $ordersPending > 0 ? (string) $ordersPending : null, 'chip_class' => 'chip-pending'],
        ['key' => 'portefeuille', 'icon' => 'wallet', 'href' => url('/vendeur/portefeuille'), 'label' => t('seller.nav.wallet'), 'chip' => null],
        ['key' => 'messages',  'icon' => 'chat', 'href' => url('/messages'),  'label' => t('seller.nav.messages'),
         'chip' => $msgUnread > 0 ? (string) $msgUnread : null, 'chip_class' => 'chip-pending'],
        ['key' => 'avis',      'icon' => 'star', 'href' => url('/vendeur/avis'), 'label' => t('seller.nav.reviews'),
         'chip' => $reviewsTodo > 0 ? (string) $reviewsTodo : null, 'chip_class' => 'chip-pending'],
    ]],
    ['label' => t('seller.group.develop'), 'items' => [
        ['key' => 'publicite',   'icon' => 'megaphone', 'href' => url('/vendeur/publicite'),   'label' => t('seller.nav.ads'),          'chip' => null],
        ['key' => 'affiliation', 'icon' => 'users', 'href' => url('/vendeur/affiliation'), 'label' => t('seller.nav.affiliation'),  'chip' => null],
    ]],
    ['label' => t('seller.group.account'), 'items' => [
        ['key' => 'verification', 'icon' => 'shield', 'href' => url('/vendeur/verification'), 'label' => t('seller.nav.verification'), 'chip' => null],
        ['key' => 'profil',       'icon' => 'building', 'href' => url('/vendeur/profil'),       'label' => t('seller.nav.profile'),      'chip' => null],
        ['key' => 'reglages',     'icon' => 'settings', 'href' => url('/vendeur/reglages'),     'label' => t('seller.nav.settings'),     'chip' => null],
    ]],
];
?>
<aside class="seller-sidebar" aria-label="<?= e(t('seller.nav.aria')) ?>">
    <div class="seller-ident">
        <?php if ($avatar_url !== null): ?>
            <img class="avatar avatar-img" src="<?= e($avatar_url) ?>" alt="" width="48" height="48">
        <?php else: ?>
            <div class="avatar avatar-sm" aria-hidden="true"><?= icon('store', ['size' => 22]) ?></div>
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
        <?php foreach ($groups as $group): ?>
            <?php if ($group['label'] !== null): ?>
                <p class="seller-nav-group"><?= e($group['label']) ?></p>
            <?php endif; ?>
            <?php foreach ($group['items'] as $it): ?>
                <a class="seller-nav-item<?= $active === $it['key'] ? ' is-active' : '' ?>"
                   href="<?= e($it['href']) ?>"<?= $active === $it['key'] ? ' aria-current="page"' : '' ?>>
                    <span class="seller-nav-ico"><?= icon($it['icon']) ?></span>
                    <span class="seller-nav-label"><?= e($it['label']) ?></span>
                    <?php if ($it['chip'] !== null): ?><span class="<?= e($it['chip_class'] ?? 'chip-soon') ?>"><?= e($it['chip']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
