<?php
/** @var bool $ok — signalement transmis, ou lien invalide/déjà utilisé */
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon" aria-hidden="true"><?= $ok ? '🛡️' : '⚠️' ?></div>
    <h1><?= e($ok ? t('report.title_ok') : t('report.title_invalid')) ?></h1>
    <p class="muted"><?= e($ok ? t('report.body_ok') : t('report.body_invalid')) ?></p>
    <?php if ($ok): ?>
        <p class="notice notice-info"><?= e(t('report.advice')) ?></p>
        <p><a class="btn btn-primary" href="<?= e(url('/forgot-password')) ?>">🔑 <?= e(t('report.cta_password')) ?></a></p>
    <?php else: ?>
        <p><a class="btn btn-primary" href="<?= e(url('/')) ?>">← <?= e(t('nav.home')) ?></a></p>
    <?php endif; ?>
</section>
