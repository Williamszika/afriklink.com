<?php
/** @var list<array> $campaigns  @var int $active_count  @var int $revenue_cents  @var string $revenue_cur */
$statusLabel = static function (string $s): string {
    return t('ads.status.' . (in_array($s, ['active', 'expired', 'stopped', 'rejected'], true) ? $s : 'active'));
};
?>
<div class="container ann-admin">
    <h1>📣 <?= e(t('ads.admin_title')) ?></h1>
    <p class="muted"><?= e(t('ads.admin_intro')) ?></p>

    <div class="admin-stats">
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) $active_count, 0, ',', ' ') ?></span><span class="admin-stat-lbl"><?= e(t('ads.admin_active')) ?></span></div>
        <div class="admin-stat"><span class="admin-stat-n"><?= e(format_price($revenue_cents, $revenue_cur)) ?></span><span class="admin-stat-lbl"><?= e(t('ads.admin_revenue')) ?></span></div>
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) count($campaigns), 0, ',', ' ') ?></span><span class="admin-stat-lbl"><?= e(t('ads.admin_total')) ?></span></div>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('ads.admin_campaigns')) ?></h2>
        <?php if ($campaigns === []): ?>
            <div class="empty-state"><p><?= e(t('ads.admin_empty')) ?></p></div>
        <?php else: ?>
            <ul class="ann-list">
                <?php foreach ($campaigns as $c): ?>
                    <?php
                    $imp = (int) ($c['impressions'] ?? 0);
                    $clk = (int) ($c['clicks'] ?? 0);
                    $ctr = $imp > 0 ? round($clk / $imp * 100, 1) : 0.0;
                    $live = ($c['status'] ?? '') === 'active' && strtotime((string) $c['ends_at']) > time();
                    ?>
                    <li class="ann-row<?= $live ? ' ann-row--pending' : '' ?>">
                        <div class="ann-row-main">
                            <strong><?= e((string) ($c['object_name'] ?? '#' . $c['object_id'])) ?></strong>
                            <span class="badge"><?= e($statusLabel((string) ($c['status'] ?? ''))) ?></span>
                            <span class="muted">— <?= e((string) ($c['seller_name'] ?: $c['seller_email'] ?? '')) ?>
                                · <?= e(t('ads.days', ['days' => (int) $c['days']])) ?>
                                · <?= e(format_price((int) $c['amount_cents'], (string) $c['currency'])) ?>
                                · <?= number_format((float) $imp, 0, ',', ' ') ?> <?= e(t('ads.impressions')) ?>
                                / <?= number_format((float) $clk, 0, ',', ' ') ?> <?= e(t('ads.clicks')) ?>
                                (CTR <?= e(number_format($ctr, 1, ',', ' ')) ?> %)
                            </span>
                        </div>
                        <?php if ($live): ?>
                            <div class="ann-row-actions">
                                <form method="post" action="<?= e(url('/admin/publicite/' . $c['public_id'] . '/action')) ?>" class="inline-form">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="stop">
                                    <button class="btn btn-ghost btn-sm"><?= e(t('ads.admin_stop')) ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/admin/publicite/' . $c['public_id'] . '/action')) ?>" class="inline-form">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reject">
                                    <button class="btn btn-ghost btn-sm"><?= e(t('ads.admin_reject')) ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
