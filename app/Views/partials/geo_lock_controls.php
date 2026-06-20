<?php
/**
 * Contrôles de géolocalisation à placer juste après le champ #city.
 * app.js révèle le bouton, remplit + VERROUILLE la ville (et le pays #country_code
 * s'il est présent) sur une position GPS précise, et le lien « Ce n'est pas ma
 * position ? » rouvre tout pour saisie manuelle. Le bouton est requis pour les
 * navigateurs mobiles qui ignorent la géoloc sans geste utilisateur.
 */
?>
<button type="button" id="geo-detect" class="link-button geo-detect-btn" hidden
        data-asking="<?= e(t('geo.asking')) ?>" data-denied="<?= e(t('geo.denied')) ?>"
        data-unavailable="<?= e(t('geo.error')) ?>">📍 <?= e(t('geo.btn')) ?></button>
<span id="geo-detect-status" class="hint geo-detect-status" aria-live="polite"></span>
<p class="hint geo-lock-note" id="geo-lock-note" <?= !empty($locked) ? '' : 'hidden' ?>>🔒 <?= e(t('geo.locked')) ?>
    <button type="button" id="geo-unlock" class="link-button">— <?= e(t('geo.unlock')) ?></button>
</p>
