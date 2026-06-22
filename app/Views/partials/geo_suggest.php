<?php
/**
 * Bandeau « passer dans la langue/devise de votre pays » : s'affiche quand la
 * langue OU la devise active diffère de celle du pays détecté, tant que le
 * visiteur n'a pas répondu (cookie region_hint). Sans JavaScript : deux liens.
 * Récupère les visiteurs qui ont un ancien choix mémorisé (cookie) ≠ leur pays.
 */
if (isset($_COOKIE['region_hint'])) {
    return;
}
$geo = detected_geo();
$cc  = strtoupper((string) ($geo['country_code'] ?? ''));
if ($cc === '') {
    return;
}
$sugLang = language_for_country($cc);
$sugCur  = currency_for_country($cc);
$curLang = current_locale();
$curCur  = current_currency();
$langMismatch = $sugLang !== null && $sugLang !== $curLang;
$curMismatch  = $sugCur !== null && $sugCur !== $curCur;
if (!$langMismatch && !$curMismatch) {
    return; // déjà aligné sur le pays : rien à proposer
}

$place    = country_name($cc);
$langName = $sugLang !== null && extension_loaded('intl')
    ? \Locale::getDisplayLanguage($sugLang, $curLang)
    : (string) ($sugLang ?? $curLang);
$targetLang = $sugLang ?? $curLang;
$targetCur  = $sugCur ?? $curCur;
$to = (string) ($_SERVER['REQUEST_URI'] ?? '/');

$applyUrl = url('/region/appliquer?lang=' . rawurlencode($targetLang) . '&cur=' . rawurlencode($targetCur) . '&to=' . rawurlencode($to));
$keepUrl  = url('/region/ignorer?to=' . rawurlencode($to));

$question = match (true) {
    $langMismatch && $curMismatch => t('geo.suggest.both', ['lang' => $langName, 'cur' => $targetCur]),
    $langMismatch                 => t('geo.suggest.lang', ['lang' => $langName]),
    default                       => t('geo.suggest.cur', ['cur' => $targetCur]),
};
?>
<div class="region-suggest" role="region" aria-label="<?= e(t('geo.suggest.aria')) ?>">
    <span class="region-suggest__text">
        <span class="region-suggest__flag" aria-hidden="true"><?= flag_emoji($cc) ?></span>
        <?= e(t('geo.suggest.intro', ['place' => $place])) ?> <?= e($question) ?>
    </span>
    <span class="region-suggest__actions">
        <a class="btn btn-primary btn-sm" href="<?= e($applyUrl) ?>"><?= e(t('geo.suggest.yes')) ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e($keepUrl) ?>"><?= e(t('geo.suggest.keep')) ?></a>
    </span>
</div>
