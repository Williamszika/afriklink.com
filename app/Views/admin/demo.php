<?php
/** @var int $count  Boutiques de démo actuellement publiées. */
?>
<section class="profile">
    <div class="seller-head">
        <h1>🧪 <?= e(t('admin.demo.title')) ?></h1>
        <p class="muted"><?= e(t('admin.demo.sub')) ?></p>
    </div>

    <div class="notice notice-warn"><p>⚠️ <?= e(t('admin.demo.warn')) ?></p></div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.demo.state_title')) ?></h2>
        <?php if ($count > 0): ?>
            <p>✅ <strong><?= (int) $count ?></strong> <?= e(t('admin.demo.state_on')) ?></p>
        <?php else: ?>
            <p class="muted"><?= e(t('admin.demo.state_off')) ?></p>
        <?php endif; ?>
        <p class="hint"><?= e(t('admin.demo.login_hint')) ?> <code>seed1@afriklink.demo</code> / <code>demo1234</code></p>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.demo.seed_title')) ?></h2>
        <p class="muted"><?= e(t('admin.demo.seed_desc')) ?></p>
        <form method="post" action="<?= e(url('/admin/demo/seed')) ?>" data-confirm="<?= e(t('admin.demo.seed_confirm')) ?>">
            <?= csrf_field() ?>
            <label for="demo-confirm"><?= e(t('admin.demo.confirm_label')) ?></label>
            <input type="text" id="demo-confirm" name="confirm" autocomplete="off" placeholder="OUI" style="max-width:160px">
            <p><button type="submit" class="btn btn-primary"><?= e(t('admin.demo.seed_btn')) ?></button></p>
        </form>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('admin.demo.purge_title')) ?></h2>
        <p class="muted"><?= e(t('admin.demo.purge_desc')) ?></p>
        <form method="post" action="<?= e(url('/admin/demo/purge')) ?>" data-confirm="<?= e(t('admin.demo.purge_confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-danger"><?= e(t('admin.demo.purge_btn')) ?></button>
        </form>
    </div>
</section>
