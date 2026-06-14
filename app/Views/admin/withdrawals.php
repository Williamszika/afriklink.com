<?php
/** @var list<array> $list */
?>
<div class="container ann-admin">
    <h1>💸 <?= e(t('wallet.admin_title')) ?></h1>
    <p class="muted"><?= e(t('wallet.admin_intro')) ?></p>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('wallet.admin_pending')) ?>
            <?php if ($list !== []): ?> <span class="badge badge-warn"><?= count($list) ?></span><?php endif; ?>
        </h2>
        <?php if ($list === []): ?>
            <div class="empty-state"><p><?= e(t('wallet.admin_empty')) ?></p></div>
        <?php else: ?>
            <ul class="ann-list">
                <?php foreach ($list as $w): ?>
                    <li class="ann-row ann-row--pending">
                        <div class="ann-row-main">
                            <strong><?= e(format_price((int) $w['amount_cents'], (string) $w['currency'])) ?></strong>
                            <span class="muted">— <?= e((string) ($w['seller_name'] ?? '')) ?> · <?= e(t('wallet.method.' . $w['method'])) ?> :
                                <code><?= e((string) $w['destination']) ?></code> · <?= e(date('d/m/Y H:i', strtotime((string) $w['created_at']))) ?></span>
                        </div>
                        <div class="ann-row-actions">
                            <form method="post" action="<?= e(url('/admin/retraits/' . $w['id'] . '/traiter')) ?>" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="paid">
                                <button class="btn btn-primary btn-sm">✓ <?= e(t('wallet.mark_paid')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/retraits/' . $w['id'] . '/traiter')) ?>" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reject">
                                <button class="btn btn-ghost btn-sm"><?= e(t('wallet.reject')) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
