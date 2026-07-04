<?php
/** @var array $user */
$isPro = ($user['account_type'] ?? '') === 'professionnel';
$backUrl = $isPro ? '/vendeur/reglages' : '/profile';
?>
<section class="profile">
    <div class="profile-head">
        <h1><?= e(t('privacy.title')) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url($backUrl)) ?>">← <?= e(t('privacy.back')) ?></a>
    </div>

    <p class="hint"><?= e(t('privacy.intro')) ?></p>

    <!-- Droit d'accès & de portabilité (RGPD Art. 15 / 20) -->
    <div class="panel">
        <h2 class="panel-title"><?= e(t('privacy.export_title')) ?></h2>
        <p><?= e(t('privacy.export_desc')) ?></p>
        <div class="dsar-actions">
            <a class="btn btn-primary" href="<?= e(url('/profile/donnees/export')) ?>"><?= e(t('privacy.export_cta')) ?></a>
        </div>
        <p class="hint"><?= e(t('privacy.export_hint')) ?></p>
    </div>

    <!-- Droit à l'effacement (RGPD Art. 17) -->
    <div class="panel panel--danger">
        <h2 class="panel-title"><?= e(t('privacy.delete_title')) ?></h2>
        <p><?= e(t('privacy.delete_desc')) ?></p>
        <ul class="dsar-list">
            <li><?= e(t('privacy.delete_removed_1')) ?></li>
            <li><?= e(t('privacy.delete_removed_2')) ?></li>
            <li><?= e(t('privacy.delete_removed_3')) ?></li>
        </ul>
        <p class="dsar-kept"><?= e(t('privacy.delete_kept')) ?></p>
        <div class="dsar-actions">
            <a class="btn btn-danger" href="<?= e(url('/profile/supprimer')) ?>"><?= e(t('privacy.delete_cta')) ?></a>
        </div>
    </div>
</section>
