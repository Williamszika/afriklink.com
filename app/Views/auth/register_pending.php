<?php
/**
 * Page neutre de fin d'inscription — affichée à l'identique que l'adresse/le
 * numéro soit nouveau OU déjà utilisé (anti-énumération de comptes). Elle ne
 * révèle donc jamais si un compte existe déjà. Aucun branchement sur l'état de
 * connexion : le HTML est strictement le même dans tous les cas.
 */
?>
<section class="auth-card">
    <h1><?= e(t('register.pending_title')) ?></h1>
    <p><?= e(t('register.pending_lead')) ?></p>
    <p class="auth-alt">
        <a class="btn btn-green" href="<?= e(url('/login')) ?>"><?= e(t('register.pending_login')) ?></a>
    </p>
    <p class="auth-alt">
        <a href="<?= e(url('/')) ?>"><?= e(t('register.pending_home')) ?></a>
    </p>
</section>
