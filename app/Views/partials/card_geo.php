<?php
/**
 * Badge « localisation + portée de livraison » d'une boutique, vu par l'acheteur
 * courant (géolocalisation détectée). Utilisé sur les cartes produit.
 *
 * Variable attendue : $row — une ligne produit enrichie des champs boutique
 * (boutique_city, boutique_country, delivery_zones, delivery_methods,
 * boutique_lat, boutique_lng), telle que renvoyée par Product::recentMarketplace()
 * et Product::search().
 */
$row  = $row ?? [];
// Boutique = origine d'expédition / politique de livraison (zones + coordonnées).
$shop = [
    'city'             => $row['boutique_city']    ?? null,
    'country_code'     => $row['boutique_country'] ?? null,
    'delivery_zones'   => $row['delivery_zones']   ?? null,
    'delivery_methods' => $row['delivery_methods'] ?? null,
    'geo_lat'          => $row['boutique_lat']     ?? null,
    'geo_lng'          => $row['boutique_lng']     ?? null,
];
// Lieu AFFICHÉ : localisation PROPRE de l'annonce (p.city / p.country_code) si
// renseignée, sinon celle de la boutique (repli).
$ownCity = trim((string) ($row['product_city'] ?? $row['city'] ?? ''));
$ownCc   = strtoupper(trim((string) ($row['product_country'] ?? $row['country_code'] ?? '')));
$placeCity = $ownCity !== '' ? $ownCity : ($shop['city'] ?? null);
$placeCc   = $ownCc   !== '' ? $ownCc   : ($shop['country_code'] ?? null);
$loc = place_label($placeCity, $placeCc);
if ($loc === '') {
    return;
}
$reach = delivery_reach($shop);
$showReach = in_array($reach['status'], ['city', 'country', 'international', 'pickup'], true);
?>
<span class="card-geo">
    <span class="card-geo__place" title="<?= e($loc) ?>">📍 <?= e($loc) ?></span>
    <?php if ($showReach): ?>
        <span class="reach-chip reach-chip--<?= e($reach['local'] ? 'local' : 'intl') ?>"><?= e($reach['label']) ?></span>
    <?php endif; ?>
</span>
