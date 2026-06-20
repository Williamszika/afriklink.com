<?php
/** @var array $stats  @var array<string,int> $pending  @var array $me */
$totalPending = (int) array_sum($pending);
$name = trim((string) ($me['full_name'] ?? ''));
// [clé stat, emoji, libellé]
$cards = [
    ['users', '👥', 'admin.dash.s_users'],
    ['sellers', '🏪', 'admin.dash.s_sellers'],
    ['boutiques', '🛍️', 'admin.dash.s_boutiques'],
    ['products', '📦', 'admin.dash.s_products'],
    ['restaurants', '🍽️', 'admin.dash.s_restaurants'],
    ['listings', '🏷️', 'admin.dash.s_listings'],
    ['orders', '🧾', 'admin.dash.s_orders'],
    ['reviews', '⭐', 'admin.dash.s_reviews'],
];
$fmt = static fn (int $n): string => number_format($n, 0, ',', ' ');
?>
<section class="profile admin-dash">
    <div class="seller-head">
        <h1><?= icon('shield', ['size' => 22]) ?> <?= e(t('admin.dash.title')) ?></h1>
        <p class="muted"><?= e($name !== '' ? t('admin.dash.welcome', ['name' => $name]) : t('admin.dash.sub')) ?></p>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= icon('bell', ['size' => 18]) ?> <?= e(t('admin.dash.pending_title')) ?></h2>
        <?php if ($totalPending === 0): ?>
            <p class="notice notice-ok">✓ <?= e(t('admin.dash.allgood')) ?></p>
        <?php else: ?>
            <div class="admin-pending">
                <?php if ($pending['kyc'] > 0): ?>
                    <a class="admin-todo" href="<?= e(url('/admin/kyc')) ?>"><span class="admin-todo-n"><?= $fmt((int) $pending['kyc']) ?></span> <?= e(t('admin.dash.p_kyc')) ?> →</a>
                <?php endif; ?>
                <?php if ($pending['withdrawals'] > 0): ?>
                    <a class="admin-todo" href="<?= e(url('/admin/retraits')) ?>"><span class="admin-todo-n"><?= $fmt((int) $pending['withdrawals']) ?></span> <?= e(t('admin.dash.p_withdrawals')) ?> →</a>
                <?php endif; ?>
                <?php if ($pending['ann'] > 0): ?>
                    <a class="admin-todo" href="<?= e(url('/admin/annonces')) ?>"><span class="admin-todo-n"><?= $fmt((int) $pending['ann']) ?></span> <?= e(t('admin.dash.p_ann')) ?> →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">📊 <?= e(t('admin.dash.stats_title')) ?></h2>
        <div class="admin-stats">
            <?php foreach ($cards as [$k, $emoji, $lbl]): ?>
                <div class="admin-stat">
                    <span class="admin-stat-ico" aria-hidden="true"><?= $emoji ?></span>
                    <span class="admin-stat-n"><?= $fmt((int) ($stats[$k] ?? 0)) ?></span>
                    <span class="admin-stat-lbl"><?= e(t($lbl)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title">🧰 <?= e(t('admin.dash.tools_title')) ?></h2>
        <div class="admin-tools">
            <a class="admin-tool" href="<?= e(url('/admin/kyc')) ?>"><?= icon('shield', ['size' => 20]) ?> <span><?= e(t('admin.dash.t_kyc')) ?></span></a>
            <a class="admin-tool" href="<?= e(url('/admin/annonces')) ?>"><?= icon('megaphone', ['size' => 20]) ?> <span><?= e(t('admin.dash.t_ann')) ?></span></a>
            <a class="admin-tool" href="<?= e(url('/admin/retraits')) ?>"><?= icon('wallet', ['size' => 20]) ?> <span><?= e(t('admin.dash.t_wallet')) ?></span></a>
            <a class="admin-tool" href="<?= e(url('/admin/publicite')) ?>"><?= icon('sparkle', ['size' => 20]) ?> <span><?= e(t('ads.admin_title')) ?></span></a>
            <a class="admin-tool" href="<?= e(url('/admin/email')) ?>"><span class="admin-tool-emoji" aria-hidden="true">✉️</span> <span><?= e(t('admin.dash.t_email')) ?></span></a>
            <a class="admin-tool" href="<?= e(url('/health')) ?>" target="_blank" rel="noopener"><span class="admin-tool-emoji" aria-hidden="true">🩺</span> <span><?= e(t('admin.dash.t_health')) ?></span></a>
        </div>
    </div>
</section>
