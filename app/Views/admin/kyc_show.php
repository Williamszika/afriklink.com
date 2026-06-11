<?php
/** @var array $sub  @var list<array> $docs */
$status = (string) $sub['status'];
?>
<section class="profile">
    <div class="seller-head">
        <h1>🛡️ <?= e(t('admin.review')) ?> — <?= e(t('kyc.level' . $sub['level'] . '_title')) ?></h1>
        <p class="muted"><a href="<?= e(url('/admin/kyc')) ?>">← <?= e(t('admin.back_queue')) ?></a></p>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.person')) ?></h2>
        <dl class="meta">
            <dt><?= e(t('field.full_name')) ?></dt><dd><?= e((string) ($sub['full_name'] ?? '—')) ?></dd>
            <dt><?= e(t('field.email')) ?></dt><dd><?= e((string) $sub['email']) ?></dd>
            <?php if (!empty($sub['country_code'])): ?>
                <dt><?= e(t('field.country')) ?></dt><dd><?= flag_emoji((string) $sub['country_code']) ?> <?= e(country_name((string) $sub['country_code'])) ?><?php if (!empty($sub['city'])): ?> · <?= e((string) $sub['city']) ?><?php endif; ?></dd>
            <?php endif; ?>
            <?php if (!empty($sub['id_first_name']) || !empty($sub['id_last_name'])): ?>
                <dt><?= e(t('admin.declared_name')) ?></dt>
                <dd><strong><?= e(trim((string) ($sub['id_first_name'] ?? '') . ' ' . (string) ($sub['id_last_name'] ?? ''))) ?></strong>
                    <span class="hint"><?= e(t('admin.declared_name_hint')) ?></span></dd>
            <?php endif; ?>
            <?php if (!empty($sub['doc_type'])): ?>
                <dt><?= e(t('kyc.doc_type')) ?></dt><dd><?= e(t('kyc.idtype.' . $sub['doc_type'])) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('admin.col_submitted')) ?></dt><dd><?= e(substr((string) $sub['submitted_at'], 0, 16)) ?></dd>
        </dl>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.documents')) ?></h2>
        <div class="kyc-doc-grid">
            <?php foreach ($docs as $d): ?>
                <figure class="kyc-doc">
                    <a href="<?= e(url('/admin/kyc/doc/' . $d['id'])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url('/admin/kyc/doc/' . $d['id'])) ?>" alt="<?= e(t('kyc.slot.' . $d['slot'])) ?>" loading="lazy">
                    </a>
                    <figcaption><?= e(t('kyc.slot.' . $d['slot'])) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($status === 'pending'): ?>
        <form method="post" action="<?= e(url('/admin/kyc/' . $sub['id'] . '/review')) ?>" class="panel" novalidate>
            <?= csrf_field() ?>
            <h2 class="panel-title"><?= e(t('admin.decision')) ?></h2>
            <label for="note"><?= e(t('admin.note_label')) ?></label>
            <textarea id="note" name="note" rows="2" maxlength="500" placeholder="<?= e(t('admin.note_ph')) ?>"></textarea>
            <?php if (has_error('note')): ?><p class="field-error"><?= e(error('note')) ?></p><?php endif; ?>
            <div class="wizard-nav">
                <button type="submit" name="action" value="reject" class="btn btn-ghost btn-danger"><?= e(t('admin.reject')) ?></button>
                <button type="submit" name="action" value="approve" class="btn btn-primary"><?= e(t('admin.approve')) ?></button>
            </div>
        </form>
    <?php else: ?>
        <div class="notice notice-info">
            <p><strong><?= e(t('kyc.state.' . $status)) ?></strong><?php if (!empty($sub['review_note'])): ?> — <?= e((string) $sub['review_note']) ?><?php endif; ?></p>
        </div>
    <?php endif; ?>
</section>
