<?php
/**
 * Encart newsletter — proposé aux comptes créés par TÉLÉPHONE (sans e-mail).
 * Le client peut s'abonner (saisie e-mail) ou refuser ; en cas de refus, un
 * cookie (nl_seen) empêche le retour de l'encart. Affichage piloté par le JS.
 */
?>
<div class="nl-pop" data-newsletter-pop hidden role="dialog" aria-label="<?= e(t('newsletter.pop_title')) ?>">
    <button type="button" class="nl-pop-x" data-nl-decline aria-label="<?= e(t('newsletter.pop_decline')) ?>">✕</button>
    <span class="nl-pop-cauri" aria-hidden="true"><?= render_partial('partials/logo', ['uid' => 'nlpop']) ?></span>
    <h3 class="nl-pop-title"><?= e(t('newsletter.pop_title')) ?></h3>
    <p class="nl-pop-text"><?= e(t('newsletter.pop_text')) ?></p>
    <form class="nl-pop-form" data-nl-form action="<?= e(url('/newsletter/popup')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="email" name="email" required maxlength="160" placeholder="<?= e(t('newsletter.pop_ph')) ?>" data-nl-email aria-label="<?= e(t('newsletter.pop_ph')) ?>">
        <button type="submit" class="btn btn-primary btn-block" data-nl-submit><?= icon('banknote', ['size' => 16]) ?> <?= e(t('newsletter.pop_accept')) ?></button>
    </form>
    <button type="button" class="nl-pop-no" data-nl-decline><?= e(t('newsletter.pop_decline')) ?></button>
    <div class="nl-pop-done" data-nl-done hidden>
        <span class="nl-pop-check">✓</span>
        <p><?= e(t('newsletter.subscribed')) ?></p>
    </div>
</div>
