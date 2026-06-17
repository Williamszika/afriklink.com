<?php
/** @var array{driver:string,from:string,from_name:string,api_key_set:bool,api_url:string,smtp_host:string,delivers:bool} $cfg
 *  @var string $me */
$dash = static fn (string $v): string => $v !== '' ? e($v) : '—';
?>
<section class="profile">
    <div class="seller-head">
        <h1>✉️ <?= e(t('admin.mail.title')) ?></h1>
        <p class="muted"><?= e(t('admin.mail.sub')) ?></p>
    </div>

    <?php if ($cfg['delivers']): ?>
        <div class="notice notice-info"><p><?= e(t('admin.mail.ready', ['driver' => $cfg['driver']])) ?></p></div>
    <?php else: ?>
        <div class="notice notice-warn"><p><?= e(t('admin.mail.not_ready')) ?></p></div>
    <?php endif; ?>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.mail.cfg_title')) ?></h2>
        <table class="admin-table">
            <tr><th>MAIL_DRIVER</th><td><code><?= e($cfg['driver']) ?></code></td></tr>
            <tr><th>MAIL_FROM</th><td><?= $dash($cfg['from']) ?></td></tr>
            <tr><th>MAIL_FROM_NAME</th><td><?= $dash($cfg['from_name']) ?></td></tr>
            <?php if ($cfg['driver'] === 'api'): ?>
                <tr><th>MAIL_API_KEY</th><td><?= $cfg['api_key_set'] ? '✅ ' . e(t('admin.mail.set')) : '❌ ' . e(t('admin.mail.unset')) ?></td></tr>
                <tr><th>MAIL_API_URL</th><td><?= $dash($cfg['api_url']) ?></td></tr>
            <?php elseif ($cfg['driver'] === 'smtp'): ?>
                <tr><th>MAIL_HOST</th><td><?= $dash($cfg['smtp_host']) ?></td></tr>
            <?php endif; ?>
        </table>
        <?php if ($cfg['driver'] === 'log'): ?>
            <p class="hint"><?= e(t('admin.mail.log_hint')) ?></p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.mail.test_title')) ?></h2>
        <?php if ($me === ''): ?>
            <div class="notice notice-warn"><p><?= e(t('admin.mail.no_email')) ?></p></div>
        <?php else: ?>
            <p class="muted"><?= e(t('admin.mail.test_desc', ['email' => $me])) ?></p>
            <form method="post" action="<?= e(url('/admin/email/test')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><?= e(t('admin.mail.test_btn')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
