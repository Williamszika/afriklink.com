<?php /** @var array $user */ ?>
<section class="auth-card">
    <h1><?= e(t('verify.notice_title')) ?></h1>
    <p><?= e(t('verify.notice_body', ['email' => $user['email'] ?? ''])) ?></p>

    <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost"><?= e(t('verify.resend')) ?></button>
    </form>

    <p class="auth-alt">
        <a href="<?= e(url('/dashboard')) ?>"><?= e(t('verify.go_dashboard')) ?></a>
    </p>
</section>
