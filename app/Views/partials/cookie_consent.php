<?php
/** Bandeau de consentement aux cookies (RGPD). Rendu côté serveur, sans JS :
 *  ne s'affiche que tant qu'aucun choix n'a été enregistré. Les boutons sont de
 *  simples liens vers /consentement/{choix}, qui posent le cookie et reviennent. */
if (!empty($_COOKIE['cookie_consent'])) {
    return;
}
$to = rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'));
?>
<div class="cookie-banner" role="region" aria-label="<?= e(t('cookie.aria')) ?>">
    <p class="cookie-text">🍪 <?= e(t('cookie.text')) ?>
        <a href="<?= e(url('/confidentialite')) ?>#cookies"><?= e(t('cookie.learn')) ?></a>
    </p>
    <div class="cookie-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/consentement/refuser?to=' . $to)) ?>"><?= e(t('cookie.refuse')) ?></a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/consentement/accepter?to=' . $to)) ?>"><?= e(t('cookie.accept')) ?></a>
    </div>
</div>
