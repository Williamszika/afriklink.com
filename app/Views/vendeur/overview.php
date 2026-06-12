<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_version  @var ?string $avatar_url  @var int $completion */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$emailOk     = !empty($user['email_verified_at']);
$hasLogo     = ($avatar_version ?? null) !== null;
$hasReg      = !empty($profile['reg_number']);
$hasDesc     = !empty($profile['description']);
$cur         = (string) ($user['preferred_currency'] ?? 'EUR');

$ordersPending = \App\Models\Order::pendingForUser((int) ($user['id'] ?? 0));
$viewsTotal    = \App\Models\ShopView::totalForUser((int) ($user['id'] ?? 0));
$stats = [
    ['icon' => '💰', 'label' => t('seller.stat.revenue'), 'value' => format_price(0, $cur), 'note' => t('dash.phase', ['n' => 3])],
    ['icon' => '🧾', 'label' => t('seller.stat.orders'),  'value' => (string) $ordersPending,
     'note' => t('shop.kpi.orders_cta') . ' →', 'href' => url('/vendeur/commandes?filtre=a_traiter'), 'urgent' => $ordersPending > 0],
    ['icon' => '👁️', 'label' => t('seller.stat.views'),   'value' => (string) $viewsTotal,
     'note' => t('shop.kpi.views_cta') . ' →', 'href' => url('/boutique/stats')],
    ['icon' => '💬', 'label' => t('dash.stat.messages'),  'value' => '0',                   'note' => t('dash.phase', ['n' => 5])],
];

$checklist = [
    ['done' => $emailOk, 'label' => t('pro.dash.check_email'),      'href' => $emailOk ? null : url('/verify-email/notice')],
    ['done' => $hasLogo, 'label' => t('seller.check_logo'),         'href' => $hasLogo ? null : url('/vendeur/profil')],
    ['done' => $hasDesc, 'label' => t('seller.check_description'),  'href' => $hasDesc ? null : url('/vendeur/profil')],
    ['done' => $hasReg,  'label' => t('pro.dash.check_reg'),        'href' => $hasReg ? null : url('/vendeur/profil')],
    ['done' => false,    'label' => t('pro.dash.check_storefront'), 'href' => url('/vendeur/vitrines')],
];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= e(t('pro.dash.welcome', ['name' => $companyName])) ?></h1>
            <p class="muted"><?= e(t('seller.overview_sub')) ?></p>
        </div>

        <?php if (!$verified): ?>
            <div class="notice notice-info"><p><?= e(t('pro.dash.pending_note')) ?></p></div>
        <?php endif; ?>

        <div class="stat-grid cols-4">
            <?php foreach ($stats as $s): ?>
                <?php $tag = isset($s['href']) ? 'a' : 'div'; ?>
                <<?= $tag ?> class="stat-card<?= isset($s['href']) ? ' stat-card--link' : '' ?><?= !empty($s['urgent']) ? ' stat-card--urgent' : '' ?>"<?= isset($s['href']) ? ' href="' . e($s['href']) . '"' : '' ?>>
                    <div class="num"><span aria-hidden="true"><?= $s['icon'] ?></span> <?= e($s['value']) ?></div>
                    <div class="lbl"><?= e($s['label']) ?></div>
                    <div class="<?= isset($s['href']) ? 'stat-cta' : 'phase' ?>"><?= e($s['note']) ?></div>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>

        <div class="panel dash-progress">
            <div class="progress-head">
                <strong><?= e(t('seller.completion', ['pct' => $completion])) ?></strong>
                <span class="muted"><?= (int) $completion ?>%</span>
            </div>
            <div class="progress-track"><div class="progress-fill" style="width: <?= (int) $completion ?>%"></div></div>
            <ul class="checklist" style="margin-top:14px">
                <?php foreach ($checklist as $item): ?>
                    <li class="<?= $item['done'] ? 'done' : '' ?>">
                        <span class="check-ico"><?= $item['done'] ? '✅' : '⬜' ?></span>
                        <?php if (!$item['done'] && $item['href'] !== null): ?>
                            <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                        <?php else: ?>
                            <?= e($item['label']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="panel">
            <h2 class="panel-title">💡 <?= e(t('seller.tips_title')) ?></h2>
            <ul class="tips">
                <li>📷 <?= e(t('seller.tip1')) ?></li>
                <li>⚡ <?= e(t('seller.tip2')) ?></li>
                <li>🛡️ <?= e(t('seller.tip3')) ?></li>
            </ul>
        </div>

    </div>
</div>
