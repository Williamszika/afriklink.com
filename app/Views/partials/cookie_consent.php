<?php
/** Bandeau de consentement aux cookies (RGPD / DSGVO). Rendu côté serveur, sans
 *  JS : ne s'affiche que tant qu'aucun choix n'est enregistré. Refuser est aussi
 *  simple qu'accepter ; aucun cookie non essentiel n'est déposé avant accord.
 *  « Personnaliser » utilise une <détails> + formulaire GET (fonctionne sans JS). */
if (!empty($_COOKIE['cookie_consent'])) {
    return;
}
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$to  = rawurlencode($uri);
?>
<div class="cookie-banner" role="region" aria-label="<?= e(t('cookie.aria')) ?>">
    <div class="cookie-main">
        <p class="cookie-text">🍪 <?= e(t('cookie.text')) ?>
            <a href="<?= e(url('/confidentialite')) ?>#cookies"><?= e(t('cookie.learn')) ?></a>
        </p>
        <div class="cookie-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/consentement/refuser?to=' . $to)) ?>"><?= e(t('cookie.refuse')) ?></a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('/consentement/accepter?to=' . $to)) ?>"><?= e(t('cookie.accept')) ?></a>
        </div>
    </div>
    <details class="cookie-custom">
        <summary><?= e(t('cookie.customize')) ?></summary>
        <form class="cookie-cats" method="get" action="<?= e(url('/consentement/personnaliser')) ?>">
            <input type="hidden" name="to" value="<?= e($uri) ?>">
            <label class="cookie-cat is-locked">
                <input type="checkbox" checked disabled> <span><?= e(t('cookie.cat.essential')) ?></span>
            </label>
            <label class="cookie-cat">
                <input type="checkbox" name="functional" value="1"> <span><?= e(t('cookie.cat.functional')) ?></span>
            </label>
            <label class="cookie-cat">
                <input type="checkbox" name="analytics" value="1"> <span><?= e(t('cookie.cat.analytics')) ?></span>
            </label>
            <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('cookie.save')) ?></button>
        </form>
    </details>
</div>
