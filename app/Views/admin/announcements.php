<?php
/** @var list<array> $list  @var bool $is_admin  @var int $pending */
?>
<div class="container ann-admin">
    <h1>📢 <?= e(t('ann.admin_title')) ?></h1>
    <p class="muted"><?= e($is_admin ? t('ann.admin_intro') : t('ann.mod_intro')) ?></p>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('ann.new')) ?></h2>
        <form method="post" action="<?= e(url('/admin/annonces')) ?>">
            <?= csrf_field() ?>
            <label for="ann-title"><?= e(t('ann.f_title')) ?></label>
            <input type="text" id="ann-title" name="title" maxlength="160" required value="<?= e(old('title')) ?>" placeholder="<?= e(t('ann.f_title_ph')) ?>">
            <?php if (has_error('title')): ?><p class="field-error"><?= e(error('title')) ?></p><?php endif; ?>

            <label for="ann-body"><?= e(t('ann.f_body')) ?></label>
            <textarea id="ann-body" name="body" rows="6" maxlength="5000" placeholder="<?= e(t('ann.f_body_ph')) ?>"><?= e(old('body')) ?></textarea>
            <p class="hint"><?= e(t('ann.f_body_hint')) ?></p>
            <?php if (has_error('body')): ?><p class="field-error"><?= e(error('body')) ?></p><?php endif; ?>

            <label for="ann-link"><?= e(t('ann.f_link')) ?></label>
            <input type="url" id="ann-link" name="link" maxlength="255" value="<?= e(old('link')) ?>" placeholder="https://…">
            <?php if (has_error('link')): ?><p class="field-error"><?= e(error('link')) ?></p><?php endif; ?>

            <p class="hint"><?= $is_admin ? '✅ ' . e(t('ann.publish_now_hint')) : '⏳ ' . e(t('ann.pending_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e($is_admin ? t('ann.publish') : t('ann.submit_review')) ?></button>
        </form>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('ann.list_title')) ?>
            <?php if ($is_admin && $pending > 0): ?> <span class="badge badge-warn"><?= (int) $pending ?> <?= e(t('ann.pending')) ?></span><?php endif; ?>
        </h2>
        <?php if ($list === []): ?>
            <div class="empty-state"><p><?= e(t('ann.empty')) ?></p></div>
        <?php else: ?>
            <ul class="ann-list">
                <?php foreach ($list as $a): $st = (string) $a['status']; ?>
                    <li class="ann-row ann-row--<?= e($st) ?>">
                        <div class="ann-row-main">
                            <span class="ann-status ann-status--<?= e($st) ?>"><?= e(t('ann.status.' . $st)) ?></span>
                            <a href="<?= e(url('/info/' . $a['public_id'])) ?>" target="_blank" rel="noopener"><strong><?= e((string) $a['title']) ?></strong></a>
                            <span class="muted">— <?= e((string) ($a['author_name'] ?? '')) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?></span>
                        </div>
                        <div class="ann-row-actions">
                            <?php if ($is_admin && $st === 'pending'): ?>
                                <form method="post" action="<?= e(url('/admin/annonces/' . $a['id'] . '/valider')) ?>" class="inline-form">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="approve">
                                    <button class="btn btn-primary btn-sm">✓ <?= e(t('ann.approve')) ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/admin/annonces/' . $a['id'] . '/valider')) ?>" class="inline-form">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reject">
                                    <button class="btn btn-ghost btn-sm"><?= e(t('ann.reject')) ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/annonces/' . $a['id'] . '/supprimer')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm" title="<?= e(t('ann.delete')) ?>" aria-label="<?= e(t('ann.delete')) ?>">🗑</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
