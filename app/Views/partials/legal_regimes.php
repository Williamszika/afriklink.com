<?php
/**
 * Sélecteur de régime juridique sur les pages légales.
 * Affiche le pays détecté et permet d'afficher les informations adaptées à un
 * autre pays (DE / UE / CI / international). Sans JavaScript : simples liens
 * `?pays=…` relus par le LegalController. Libellés localisés (8 langues).
 *
 * Variables attendues : $current (code régime), $base (chemin de la page).
 */
$current = $current ?? 'INTL';
$base    = $base ?? '/mentions-legales';
$loc     = current_locale();
$pick = static fn (array $m): string => $m[$loc] ?? $m['en'];

$euLbl   = ['fr' => 'UE / EEE', 'en' => 'EU / EEA', 'de' => 'EU / EWR', 'es' => 'UE / EEE', 'it' => 'UE / SEE', 'pt' => 'UE / EEE', 'nl' => 'EU / EER', 'ar' => 'الاتحاد الأوروبي / المنطقة الاقتصادية الأوروبية'];
$intlLbl = ['fr' => 'International', 'en' => 'International', 'de' => 'International', 'es' => 'Internacional', 'it' => 'Internazionale', 'pt' => 'Internacional', 'nl' => 'Internationaal', 'ar' => 'دولي'];
$headLbl = ['fr' => 'Informations affichées pour :', 'en' => 'Information shown for:', 'de' => 'Angezeigte Informationen für:', 'es' => 'Información mostrada para:', 'it' => 'Informazioni mostrate per:', 'pt' => 'Informações apresentadas para:', 'nl' => 'Getoonde informatie voor:', 'ar' => 'المعلومات المعروضة لـ:'];
$ariaLbl = ['fr' => 'Pays applicable', 'en' => 'Applicable country', 'de' => 'Anwendbares Land', 'es' => 'País aplicable', 'it' => 'Paese applicabile', 'pt' => 'País aplicável', 'nl' => 'Toepasselijk land', 'ar' => 'البلد المعني'];

$opts = [
    'DE'   => ['🇩🇪', country_name('DE')],
    'EU'   => ['🇪🇺', $pick($euLbl)],
    'CI'   => ['🇨🇮', country_name('CI')],
    'INTL' => ['🌍', $pick($intlLbl)],
];
?>
<div class="legal-regimes" role="group" aria-label="<?= e($pick($ariaLbl)) ?>">
    <span class="legal-regimes__label"><?= e($pick($headLbl)) ?></span>
    <span class="legal-regimes__opts">
        <?php foreach ($opts as $code => [$flag, $name]): ?>
            <?php $isCur = $code === $current; ?>
            <a class="legal-regimes__opt<?= $isCur ? ' is-active' : '' ?>"
               href="<?= e(url($base . '?pays=' . $code)) ?>"
               <?= $isCur ? 'aria-current="true"' : '' ?>><?= $flag ?> <?= e($name) ?></a>
        <?php endforeach; ?>
    </span>
</div>
