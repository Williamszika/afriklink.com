<?php
/**
 * Bouton « Partager ma position » pour la caisse : le navigateur fournit le
 * GPS (comme « envoyer ma position » sur WhatsApp), l'adresse se remplit toute
 * seule et les coordonnées précises sont jointes à la commande pour la livraison.
 * Cible : le champ adresse #cl-addr + les champs cachés geo_lat / geo_lng.
 */
?>
<div class="geo-share">
    <button type="button" class="btn btn-ghost btn-sm geo-share-btn" data-geolocate
            data-geo-url="<?= e(url('/api/geo/reverse')) ?>"
            data-geo-address="#cl-addr" data-geo-lat="#geo-lat" data-geo-lng="#geo-lng" data-geo-status="#geo-status"
            data-msg-asking="<?= e(t('geo.asking')) ?>" data-msg-denied="<?= e(t('geo.denied')) ?>"
            data-msg-error="<?= e(t('geo.error')) ?>" data-msg-unsupported="<?= e(t('geo.unsupported')) ?>">
        📍 <?= e(t('order.f.share_location')) ?>
    </button>
    <span id="geo-status" class="geo-status" aria-live="polite"></span>
    <input type="hidden" id="geo-lat" name="geo_lat" value="<?= old('geo_lat') ?>">
    <input type="hidden" id="geo-lng" name="geo_lng" value="<?= old('geo_lng') ?>">
</div>
