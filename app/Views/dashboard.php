<?php
/** @var array $user */
$verified = !empty($user['email_verified_at']);
?>
<section class="panel">
    <h1><?= e(t('dashboard.title')) ?></h1>
    <p class="lead"><?= e(t('dashboard.welcome', ['email' => $user['email'] ?? ''])) ?></p>

    <?php if (!$verified): ?>
        <div class="notice notice-warning">
            <p><?= e(t('dashboard.email_unverified')) ?></p>
            <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><?= e(t('verify.resend')) ?></button>
            </form>
        </div>
    <?php else: ?>
        <div class="notice notice-success">
            <p><?= e(t('dashboard.email_verified')) ?></p>
        </div>
    <?php endif; ?>

    <dl class="meta">
        <dt><?= e(t('field.email')) ?></dt>
        <dd><?= e($user['email'] ?? '') ?></dd>
        <dt><?= e(t('dashboard.role')) ?></dt>
        <dd><?= e($user['role'] ?? 'user') ?></dd>
        <dt><?= e(t('dashboard.member_since')) ?></dt>
        <dd><?= e((string) ($user['created_at'] ?? '')) ?></dd>
    </dl>

    <p class="muted"><?= e(t('dashboard.next_steps')) ?></p>
</section>
