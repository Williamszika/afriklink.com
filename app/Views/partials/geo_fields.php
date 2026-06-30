<?php
/**
 * Localisation de la boutique : bouton « Utiliser ma position » (Geolocation
 * API du navigateur, avec permission) + ville, pays, continent.
 * Quand une position a déjà été fournie (coordonnées présentes), ville et
 * pays sont VERROUILLÉS : seul le bouton 📍 peut les actualiser — et le
 * serveur recalcule de toute façon ville/pays/continent depuis les
 * coordonnées (le verrou n'est pas que cosmétique).
 * @var ?string $city  @var ?string $cc  @var ?string $continent
 * @var ?string $lat   @var ?string $lng
 */
$countries = countries_list();
$locked = ($lat ?? '') !== '' && ($lng ?? '') !== '';
?>
<div class="geo-row">
    <?php /* Bouton masqué : la géolocalisation est automatique. Il reste dans le
       DOM car il porte la configuration et déclenche la capture précise silencieuse. */ ?>
    <button type="button" class="btn btn-ghost btn-sm" data-geolocate data-geo-lock="1" data-geo-auto="1" hidden
            data-geo-url="<?= e(url('/api/geo/reverse')) ?>"
            data-geo-city="#shop-city" data-geo-country="#shop-country"
            data-geo-continent="#shop-continent" data-geo-status="#geo-status"
            data-geo-lat="#geo-lat" data-geo-lng="#geo-lng"
            data-msg-asking="<?= e(t('geo.asking')) ?>" data-msg-denied="<?= e(t('geo.denied')) ?>"
            data-msg-error="<?= e(t('geo.error')) ?>" data-msg-unsupported="<?= e(t('geo.unsupported')) ?>">
        📍 <?= e(t('geo.btn')) ?>
    </button>
    <span class="geo-auto-hint">📍 <?= e(t('geo.auto')) ?></span>
    <span class="geo-status hint" id="geo-status" role="status" aria-live="polite"></span>
</div>
<div class="grid-2">
    <div>
        <label for="shop-city"><?= e(t('shop.f.city')) ?></label>
        <input type="text" id="shop-city" name="city" maxlength="80"
               value="<?= e((string) ($city ?? '')) ?>" autocomplete="address-level2"
               <?= $locked ? 'readonly class="is-locked"' : '' ?>>
    </div>
    <div>
        <label for="shop-country"><?= e(t('shop.f.country')) ?></label>
        <select id="shop-country" <?= $locked ? 'disabled class="is-locked"' : 'name="country_code"' ?>>
            <option value=""><?= e(t('field.choose')) ?></option>
            <?php foreach ($countries as $code => $label): ?>
                <option value="<?= e((string) $code) ?>" <?= ($cc ?? '') === $code ? 'selected' : '' ?>><?= e((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($locked): ?>
            <input type="hidden" id="shop-country-locked" name="country_code" value="<?= e((string) ($cc ?? '')) ?>">
        <?php endif; ?>
    </div>
</div>
<p class="hint geo-continent" id="shop-continent" data-prefix="🌍 <?= e(t('geo.continent_label')) ?>">
    🌍 <?= e(t('geo.continent_label')) ?> <?= !empty($continent) ? e(t('geo.continent.' . $continent)) : '—' ?>
</p>
<p class="hint geo-lock-note" id="geo-lock-note" <?= $locked ? '' : 'hidden' ?>>🔒 <?= e(t('geo.locked')) ?>
    <button type="button" id="shop-geo-unlock" class="link-button">— <?= e(t('geo.unlock')) ?></button>
</p>
<input type="hidden" id="geo-lat" name="geo_lat" value="<?= e((string) ($lat ?? '')) ?>">
<input type="hidden" id="geo-lng" name="geo_lng" value="<?= e((string) ($lng ?? '')) ?>">
