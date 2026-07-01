<?php
/**
 * Contrôle de géolocalisation à placer juste après le champ #city.
 * La ville (et le pays #country_code s'il est présent) est remplie + VERROUILLÉE
 * automatiquement depuis la position détectée (IP puis GPS silencieux) ; le lien
 * « Ce n'est pas ma position ? » rouvre tout pour une saisie manuelle.
 */
?>
<span id="geo-detect-status" class="hint geo-detect-status" aria-live="polite"></span>
<p class="hint geo-lock-note" id="geo-lock-note" <?= !empty($locked) ? '' : 'hidden' ?>>
    <button type="button" id="geo-unlock" class="link-button"><?= e(t('geo.unlock')) ?></button>
</p>
