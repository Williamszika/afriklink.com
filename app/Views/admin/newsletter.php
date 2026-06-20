<?php
/** @var array{subscribed:int,unsubscribed:int,total:int} $counts  @var string $me */
?>
<div class="container ann-admin">
    <h1>📨 <?= e(t('newsletter.admin_title')) ?></h1>
    <p class="muted"><?= e(t('newsletter.admin_intro')) ?></p>

    <div class="admin-stats">
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) $counts['subscribed'], 0, ',', ' ') ?></span><span class="admin-stat-lbl"><?= e(t('newsletter.admin_subscribed')) ?></span></div>
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) $counts['unsubscribed'], 0, ',', ' ') ?></span><span class="admin-stat-lbl"><?= e(t('newsletter.admin_unsubscribed')) ?></span></div>
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) $counts['total'], 0, ',', ' ') ?></span><span class="admin-stat-lbl"><?= e(t('newsletter.admin_total')) ?></span></div>
    </div>

    <form method="post" action="<?= e(url('/admin/newsletter')) ?>" class="panel ad-buy" id="nl-form">
        <?= csrf_field() ?>
        <h2 class="panel-title"><?= e(t('newsletter.admin_compose')) ?></h2>

        <label class="field">
            <span class="field-label"><?= e(t('newsletter.admin_subject')) ?></span>
            <input type="text" name="subject" maxlength="160" required placeholder="<?= e(t('newsletter.admin_subject_ph')) ?>">
        </label>

        <label class="field">
            <span class="field-label"><?= e(t('newsletter.admin_message')) ?></span>
            <textarea name="message" rows="9" required placeholder="<?= e(t('newsletter.admin_message_ph')) ?>"></textarea>
        </label>

        <div class="form-row">
            <label class="field">
                <span class="field-label"><?= e(t('newsletter.admin_cta_url')) ?></span>
                <input type="text" name="cta_url" placeholder="/explorer">
            </label>
            <label class="field">
                <span class="field-label"><?= e(t('newsletter.admin_cta_label')) ?></span>
                <input type="text" name="cta_label" maxlength="60" placeholder="<?= e(t('newsletter.admin_cta_default')) ?>">
            </label>
        </div>

        <p class="muted" style="font-size:.85rem"><?= icon('info', ['size' => 14]) ?> <?= e(t('newsletter.admin_rgpd_note')) ?></p>

        <div class="product-row-actions">
            <button class="btn btn-ghost" type="submit" name="action" value="test"><?= icon('eye', ['size' => 16]) ?> <?= e(t('newsletter.admin_test_btn', ['email' => $me])) ?></button>
            <button class="btn btn-primary" type="submit" name="action" value="all"
                    onclick="return confirm('<?= e(t('newsletter.admin_confirm', ['n' => $counts['subscribed']])) ?>')">
                <?= icon('megaphone', ['size' => 16]) ?> <?= e(t('newsletter.admin_send_btn', ['n' => $counts['subscribed']])) ?>
            </button>
        </div>
    </form>
</div>
