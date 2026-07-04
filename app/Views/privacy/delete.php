<?php
/** @var array $user */
$keyword = t('privacy.delete_keyword');
?>
<section class="profile">
    <div class="profile-head">
        <h1><?= e(t('privacy.delete_title')) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('/profile/donnees')) ?>">← <?= e(t('privacy.back')) ?></a>
    </div>

    <div class="panel panel--danger">
        <h2 class="panel-title"><?= e(t('privacy.delete_confirm_title')) ?></h2>
        <p><?= e(t('privacy.delete_confirm_lead')) ?></p>
        <ul class="dsar-list">
            <li><?= e(t('privacy.delete_removed_1')) ?></li>
            <li><?= e(t('privacy.delete_removed_2')) ?></li>
            <li><?= e(t('privacy.delete_removed_3')) ?></li>
        </ul>
        <p class="dsar-kept"><?= e(t('privacy.delete_kept')) ?></p>

        <form method="post" action="<?= e(url('/profile/supprimer')) ?>" novalidate data-submit-once>
            <?= csrf_field() ?>

            <label for="password"><?= e(t('privacy.confirm_password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>

            <label for="confirm"><?= e(t('privacy.confirm_keyword', ['word' => $keyword])) ?></label>
            <input type="text" id="confirm" name="confirm" required autocomplete="off"
                   spellcheck="false" placeholder="<?= e($keyword) ?>" aria-describedby="confirm-hint">
            <p class="hint" id="confirm-hint"><?= e(t('privacy.confirm_keyword_hint', ['word' => $keyword])) ?></p>
            <?php if (has_error('confirm')): ?><p class="field-error"><?= e(error('confirm')) ?></p><?php endif; ?>

            <div class="dsar-actions">
                <a class="btn btn-ghost" href="<?= e(url('/profile/donnees')) ?>"><?= e(t('privacy.cancel')) ?></a>
                <button type="submit" class="btn btn-danger"><?= e(t('privacy.delete_submit')) ?></button>
            </div>
        </form>
    </div>
</section>
