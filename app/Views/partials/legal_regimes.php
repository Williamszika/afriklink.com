<?php
/**
 * Sélecteur de régime juridique sur les pages légales.
 * Affiche le pays détecté et permet d'afficher les informations adaptées à un
 * autre pays (DE / UE / CI / international). Sans JavaScript : simples liens
 * `?pays=…` relus par le LegalController.
 *
 * Variables attendues : $current (code régime), $base (chemin de la page).
 */
$current = $current ?? 'INTL';
$base    = $base ?? '/mentions-legales';
$en      = current_locale() === 'en';
$opts = [
    'DE'   => ['🇩🇪', $en ? 'Germany' : 'Allemagne'],
    'EU'   => ['🇪🇺', $en ? 'EU / EEA' : 'UE / EEE'],
    'CI'   => ['🇨🇮', "Côte d'Ivoire"],
    'INTL' => ['🌍', $en ? 'International' : 'International'],
];
?>
<div class="legal-regimes" role="group" aria-label="<?= e($en ? 'Applicable country' : 'Pays applicable') ?>">
    <span class="legal-regimes__label"><?= e($en ? 'Information shown for:' : 'Informations affichées pour :') ?></span>
    <span class="legal-regimes__opts">
        <?php foreach ($opts as $code => [$flag, $name]): ?>
            <?php $isCur = $code === $current; ?>
            <a class="legal-regimes__opt<?= $isCur ? ' is-active' : '' ?>"
               href="<?= e(url($base . '?pays=' . $code)) ?>"
               <?= $isCur ? 'aria-current="true"' : '' ?>><?= $flag ?> <?= e($name) ?></a>
        <?php endforeach; ?>
    </span>
</div>
