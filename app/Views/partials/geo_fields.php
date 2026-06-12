<?php
/**
 * Localisation de la boutique : bouton « Utiliser ma position » (Geolocation
 * API du navigateur, avec permission) + ville, pays, continent. Le continent
 * est recalculé côté serveur à partir du pays — l'affichage ici est indicatif.
 * @var ?string $city  @var ?string $cc  @var ?string $continent
 * @var ?string $lat   @var ?string $lng
 */
$countries = config('countries', []);
?>
<div class="geo-row">
    <button type="button" class="btn btn-ghost btn-sm" data-geolocate
            data-geo-url="<?= e(url('/api/geo/reverse')) ?>"
            data-geo-city="#shop-city" data-geo-country="#shop-country"
            data-geo-continent="#shop-continent" data-geo-status="#geo-status"
            data-geo-lat="#geo-lat" data-geo-lng="#geo-lng"
            data-msg-asking="<?= e(t('geo.asking')) ?>" data-msg-denied="<?= e(t('geo.denied')) ?>"
            data-msg-error="<?= e(t('geo.error')) ?>" data-msg-unsupported="<?= e(t('geo.unsupported')) ?>">
        📍 <?= e(t('geo.btn')) ?>
    </button>
    <span class="geo-status hint" id="geo-status" role="status" aria-live="polite"></span>
</div>
<div class="grid-2">
    <div>
        <label for="shop-city"><?= e(t('shop.f.city')) ?></label>
        <input type="text" id="shop-city" name="city" maxlength="80"
               value="<?= e((string) ($city ?? '')) ?>" autocomplete="address-level2">
    </div>
    <div>
        <label for="shop-country"><?= e(t('shop.f.country')) ?></label>
        <select id="shop-country" name="country_code">
            <option value=""><?= e(t('field.choose')) ?></option>
            <?php foreach ($countries as $code => $label): ?>
                <option value="<?= e((string) $code) ?>" <?= ($cc ?? '') === $code ? 'selected' : '' ?>><?= e((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<p class="hint geo-continent" id="shop-continent" data-prefix="🌍 <?= e(t('geo.continent_label')) ?>">
    🌍 <?= e(t('geo.continent_label')) ?> <?= !empty($continent) ? e(t('geo.continent.' . $continent)) : '—' ?>
</p>
<input type="hidden" id="geo-lat" name="geo_lat" value="<?= e((string) ($lat ?? '')) ?>">
<input type="hidden" id="geo-lng" name="geo_lng" value="<?= e((string) ($lng ?? '')) ?>">
