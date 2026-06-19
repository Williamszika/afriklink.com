<?php
/** @var array $boutique  @var array $product  @var list<array> $photos  @var array $seller  @var bool $is_owner  @var bool $seller_verified
 *  @var list<array> $reviews  @var array{avg:float,count:int} $rating  @var list<array> $related  @var array<int,string> $related_mains */
use App\Services\CloudinaryService;

$cur     = (string) $boutique['currency'];
$main    = $photos[0]['cloud_public_id'] ?? null;
$hasVideo = !empty($product['video_public_id']);
// Bouton « Commander » : WhatsApp de la boutique en priorité, sinon le téléphone du vendeur.
$waPhone = preg_replace('/\D+/', '', (string) ($boutique['contact_whatsapp'] ?? '') ?: (string) ($seller['phone'] ?? ''));
$inStock = $product['stock'] === null || (int) $product['stock'] > 0;
$productUrl = url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']);
$waText  = rawurlencode(t('product.wa_text', ['name' => (string) $product['name']]) . ' ' . $productUrl);
$curSym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur;
$methods = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
$payTerms = array_values(array_filter(explode(',', (string) ($boutique['payment_terms'] ?? ''))));
$payMethods = array_values(array_filter(explode(',', (string) ($boutique['payment_methods'] ?? ''))));
// Commande en ligne si en stock, et vitrine publiée (ou aperçu propriétaire).
$published = ($boutique['status'] ?? '') === 'published';
$canOrder = $inStock && ($published || $is_owner);
// Déclinaisons « réelles » (hors variante par défaut implicite).
$variants = $variants ?? [];
$realVariants = array_values(array_filter($variants, static fn (array $v): bool =>
    trim((string) ($v['label'] ?? '')) !== '' || count($variants) > 1));
// Cible d'achat : 1ʳᵉ déclinaison en stock si variantes, sinon le produit lui-même.
$buyId = (string) $product['public_id'];
$buyPrice = (int) $product['price_cents'];
foreach ($realVariants as $rv) {
    if ($rv['stock'] === null || (int) $rv['stock'] > 0) {
        $buyId = (string) $rv['public_id'];
        $buyPrice = $rv['price_cents'] !== null ? (int) $rv['price_cents'] : (int) $product['price_cents'];
        break;
    }
}
// Déclinaisons structurées : tailles + couleurs distinctes (2 sélecteurs) + carte pour le JS.
$vSizes = [];
$vColors = [];
$vMap = [];
$vSizeHex = [];   // beauté : pastille couleur par teinte (attributes.hex)
$vColorHex = [];  // perruque : pastille couleur par couleur de l'axe « color »
$vSizeNuance = []; // beauté : carnation par teinte
foreach ($realVariants as $rv) {
    $a  = is_array($rv['attributes'] ?? null) ? $rv['attributes'] : (json_decode((string) ($rv['attributes'] ?? ''), true) ?: []);
    $sz = (string) ($a['size'] ?? '');
    $co = (string) ($a['color'] ?? '');
    if ($sz === '' && $co === '' && trim((string) ($rv['label'] ?? '')) !== '') { $sz = (string) $rv['label']; }
    if ($sz !== '' && !in_array($sz, $vSizes, true)) { $vSizes[] = $sz; }
    if ($co !== '' && !in_array($co, $vColors, true)) { $vColors[] = $co; }
    if ($sz !== '' && !empty($a['hex']))    { $vSizeHex[$sz] = (string) $a['hex']; }
    if ($co !== '' && !empty($a['hex']))    { $vColorHex[$co] = (string) $a['hex']; }
    if ($sz !== '' && !empty($a['nuance'])) { $vSizeNuance[$sz] = (string) $a['nuance']; }
    $vBase = $rv['price_cents'] !== null ? (int) $rv['price_cents'] : (int) $product['price_cents'];
    $vMap[] = [
        'id'    => (string) $rv['public_id'],
        'size'  => $sz,
        'color' => $co,
        'stock' => $rv['stock'] === null ? null : (int) $rv['stock'],
        'price' => product_effective_unit_cents($product, $vBase),
        'base'  => $vBase,
    ];
}
// Rendu d'une pastille couleur : une teinte, ou deux côte à côte pour une valeur
// bicolore (« Rouge/Noir »). Rien n'est affiché pour une capacité (« 256 Go »),
// ce qui sépare visuellement un axe couleur d'un axe capacité.
$dotHtml = static function (array $hexes): string {
    if ($hexes === []) { return ''; }
    if (count($hexes) >= 2) {
        return '<span class="chip-dot chip-dot--split" style="background:linear-gradient(135deg,' . e($hexes[0]) . ' 0 50%,' . e($hexes[1]) . ' 50% 100%)"></span>';
    }
    return '<span class="chip-dot" style="background:' . e($hexes[0]) . '"></span>';
};
?>
<section class="listing-page">
    <p class="muted"><a href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e((string) $boutique['name']) ?></a></p>

    <div class="listing-layout">
        <?php $fulls = array_map(static fn ($ph) => CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 1400, 1050), $photos); ?>
        <div class="listing-media" data-gallery data-photos="<?= e((string) json_encode(array_values($fulls), JSON_UNESCAPED_SLASHES)) ?>">
            <?php if ($main !== null): ?>
                <button type="button" class="listing-main-zoom" data-zoom-open data-zoom-hover data-index="0" aria-label="<?= e(t('product.zoom')) ?>">
                    <img id="listing-main-photo" src="<?= e(CloudinaryService::imageUrl($main, 1100, 825)) ?>" alt="<?= e((string) $product['name']) ?>" width="880" height="660">
                    <span class="zoom-hint" aria-hidden="true"><?= icon('search', ['size' => 16]) ?></span>
                </button>
            <?php endif; ?>
            <?php if (count($photos) > 1): ?>
                <div class="listing-thumbs">
                    <?php foreach ($photos as $i => $ph): ?>
                        <button type="button" class="thumb" data-index="<?= (int) $i ?>" data-gallery-full="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 1100, 825)) ?>">
                            <img src="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 120, 90)) ?>" alt="" loading="lazy" width="120" height="90">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($hasVideo): ?>
                <video controls preload="none" playsinline class="listing-video"
                       poster="<?= e(CloudinaryService::videoPosterUrl((string) $product['video_public_id'], 880)) ?>"
                       src="<?= e(CloudinaryService::videoUrl((string) $product['video_public_id'])) ?>"></video>
            <?php endif; ?>
        </div>

        <div class="listing-side">
            <?php $isMeter = (string) ($product['sale_unit'] ?? 'piece') === 'meter'; ?>
            <div class="panel" data-cart-root data-shop-slug="<?= e($boutique['slug']) ?>" data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>" data-sale-unit="<?= $isMeter ? 'meter' : 'piece' ?>">
                <h1 class="listing-title"><?= e((string) $product['name']) ?></h1>
                <?php
                $pVertical = product_vertical((string) ($boutique['category'] ?? ''));
                // Libellé de l'axe de déclinaison piloté par le rayon (Taille / Stockage / Contenance / Teinte…).
                $pAxis = rayon_axis_meta((string) ($boutique['category'] ?? ''), (string) ($product['collection'] ?? ''));
                $pSizeLabel = $pAxis['key'] !== 'none'
                    ? $pAxis['label']
                    : ($pVertical === 'phone' ? t('phone.f.storage') : t('variant.size'));
                $apTags = [];
                // Libellé du 2ᵉ axe : « Couleur » par défaut, ou le nom choisi par le
                // vendeur (variant_axis2) — ex. « Capacité » pour une double capacité.
                $pColorLabel = t('variant.color');
                $pAxis2Attr = json_decode((string) ($product['attributes'] ?? ''), true);
                if (is_array($pAxis2Attr) && !empty($pAxis2Attr['variant_axis2'])) { $pColorLabel = (string) $pAxis2Attr['variant_axis2']; }
                $pOngColors = [];
                if ($pVertical === 'phone' && elec_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Électronique adaptatif : type-driven (specs dans attributes) + axe de déclinaison libre.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['compatibilite'])) { $apTags[] = (string) $aAttr['compatibilite']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['compatibilite', 'condition', 'garantie', 'variant_axis', 'capteurs'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['capteurs']) && is_array($aAttr['capteurs'])) {
                        $apTags[] = t('elec.f.sensors') . ' : ' . implode(', ', array_map('strval', $aAttr['capteurs']));
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('elec.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                } elseif ($pVertical === 'generic' && cuisine_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Cuisine adaptatif (Maison & meubles) : type-driven (specs dans attributes).
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'garantie', 'variant_axis'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('cuisine.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                } elseif ($pVertical === 'generic' && cuisine_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '') {
                    // Nouveau rayon Maison (hors des 6 répertoriés) : caractéristiques libres (specs) dans attributes.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('cuisine.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                } elseif ($pVertical === 'generic' && alim_capable((string) ($boutique['category'] ?? '')) && alim_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Alimentation adaptatif (Bio & naturel…) : specs alimentaires dans attributes.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['conservation', 'dlc_type', 'date_limite', 'allergenes', 'variant_axis', 'alcoolise'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['alcoolise'])) { $apTags[] = '🔞 18+'; }
                    if (!empty($aAttr['conservation']) && $aAttr['conservation'] !== 'Ambiante / sèche') { $apTags[] = (string) $aAttr['conservation']; }
                    if (!empty($aAttr['allergenes']) && is_array($aAttr['allergenes'])) {
                        $apTags[] = t('alim.f.allergenes') . ' : ' . implode(', ', array_map('strval', $aAttr['allergenes']));
                    }
                } elseif ($pVertical === 'generic' && alim_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '') {
                    // Nouveau rayon Alimentation (hors des rayons répertoriés) : caractéristiques libres (specs) + conservation / allergènes.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($aAttr['conservation']) && $aAttr['conservation'] !== 'Ambiante / sèche') { $apTags[] = (string) $aAttr['conservation']; }
                    if (!empty($aAttr['allergenes']) && is_array($aAttr['allergenes'])) {
                        $apTags[] = t('alim.f.allergenes') . ' : ' . implode(', ', array_map('strval', $aAttr['allergenes']));
                    }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && bebe_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Bébé & Enfant · Alimentation : âge, type, conservation, allergènes, régime.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['age_min'])) { $apTags[] = '👶 ' . (string) $aAttr['age_min']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['conservation', 'dlc_type', 'date_limite', 'allergenes', 'regime', 'variant_axis', 'age_min', 'formula', 'formula_1er_age', 'complement'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['conservation']) && $aAttr['conservation'] !== 'Ambiante') { $apTags[] = (string) $aAttr['conservation']; }
                    if (!empty($aAttr['regime']) && is_array($aAttr['regime'])) {
                        foreach ($aAttr['regime'] as $rg) { if (is_scalar($rg) && trim((string) $rg) !== '') { $apTags[] = (string) $rg; } }
                    }
                    if (!empty($aAttr['allergenes']) && is_array($aAttr['allergenes'])) {
                        $apTags[] = t('bebe.f.allergenes') . ' : ' . implode(', ', array_map('strval', $aAttr['allergenes']));
                    }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && bebe_toy_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Bébé & Enfant · Jouets : type, âge, matière, CE/EN71, mention petites pièces.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['age_min'])) { $apTags[] = '👶 ' . (string) $aAttr['age_min']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'age_min', 'ce', 'en71', 'small_parts', 'avertissement_3ans', 'variant_axis'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['ce'])) { $apTags[] = 'CE / EN71'; }
                    if (!empty($aAttr['avertissement_3ans'])) { $apTags[] = t('bebe.toy.tag_3ans'); }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && bebe_puer_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Bébé & Enfant · Puériculture : type, norme, groupe/âge, matière, CE, état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['groupe'])) { $apTags[] = '👶 ' . (string) $aAttr['groupe']; }
                    elseif (!empty($aAttr['age'])) { $apTags[] = '👶 ' . (string) $aAttr['age']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'ce', 'elec', 'variant_axis', 'groupe', 'age'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['ce'])) { $apTags[] = 'CE'; }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && bebe_soin_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Bébé & Enfant · Soins : type, taille couche / SPF / âge, contenance, labels.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['taille_couche'])) { $apTags[] = (string) $aAttr['taille_couche']; }
                    elseif (!empty($aAttr['spf'])) { $apTags[] = (string) $aAttr['spf']; }
                    elseif (!empty($aAttr['age'])) { $apTags[] = '👶 ' . (string) $aAttr['age']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'labels', 'peremption', 'cosmetique', 'medical', 'complement', 'variant_axis', 'taille_couche', 'spf', 'age'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['labels']) && is_array($aAttr['labels'])) {
                        foreach ($aAttr['labels'] as $lb) { if (is_scalar($lb) && trim((string) $lb) !== '') { $apTags[] = (string) $lb; } }
                    }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && bebe_vet_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Bébé & Enfant · Vêtements bébé : type, taille (âge), TOG, matière, état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['taille'])) { $apTags[] = '👶 ' . (string) $aAttr['taille']; }
                    if (!empty($aAttr['tog'])) { $apTags[] = (string) $aAttr['tog']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'securite_enfant', 'variant_axis', 'taille', 'tog'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && !str_starts_with((string) $aAttr['condition'], 'Neuf')) { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'generic' && bebe_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '' && !bebe_any_rayon((string) ($product['collection'] ?? ''))) {
                    // Nouveau rayon Bébé & Enfant (personnalisé) : caractéristiques libres + âge + CE + état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['age_min'])) { $apTags[] = '👶 ' . (string) $aAttr['age_min']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($aAttr['ce'])) { $apTags[] = 'CE'; }
                    if (!empty($aAttr['condition']) && !str_starts_with((string) $aAttr['condition'], 'Neuf')) { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'generic' && sport_capable((string) ($boutique['category'] ?? '')) && sport_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Sport & loisirs adaptatif (Chaussures…) : type, public, terrain, amorti, état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'variant_axis', 'elec', 'par_paire', 'personnalisation', 'ce'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['par_paire'])) { $apTags[] = t('sport.f.par_paire'); }
                    if (!empty($aAttr['personnalisation'])) { $apTags[] = t('sport.f.perso'); }
                    if (!empty($aAttr['ce'])) { $apTags[] = 'CE'; }
                    if (!empty($aAttr['condition']) && !str_starts_with((string) $aAttr['condition'], 'Neuf')) { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'generic' && sport_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '' && !sport_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Nouveau rayon Sport (personnalisé) : caractéristiques libres + CE + état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($aAttr['ce'])) { $apTags[] = 'CE'; }
                    if (!empty($aAttr['condition']) && !str_starts_with((string) $aAttr['condition'], 'Neuf')) { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'generic' && auto_capable((string) ($boutique['category'] ?? '')) && auto_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Auto & pièces adaptatif (Accessoires…) : specs (type) + état + compatibilité véhicule.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    // Pneus : la dimension composée résume largeur/série/diamètre/charge/vitesse.
                    if (!empty($aAttr['dimension'])) { $apTags[] = (string) $aAttr['dimension']; }
                    $autoExcl = ['condition', 'universel', 'compatibilite', 'ref_oem', 'variant_axis', 'dimension', 'largeur', 'serie', 'diametre', 'charge', 'vitesse', 'dot', 'profondeur_mm', 'monte'];
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, $autoExcl, true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['ref_oem'])) { $apTags[] = 'OEM ' . (string) $aAttr['ref_oem']; }
                    if (!empty($aAttr['universel'])) { $apTags[] = t('auto.universel_tag'); }
                    elseif (!empty($aAttr['compatibilite'])) { $apTags[] = t('auto.compat_label') . ' : ' . (string) $aAttr['compatibilite']; }
                } elseif ($pVertical === 'generic' && auto_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '') {
                    // Nouveau rayon Auto (hors des rayons répertoriés) : caractéristiques libres (specs) + état + compatibilité.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('cuisine.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                    if (!empty($aAttr['ref_oem'])) { $apTags[] = 'OEM ' . (string) $aAttr['ref_oem']; }
                    if (!empty($aAttr['universel'])) { $apTags[] = t('auto.universel_tag'); }
                    elseif (!empty($aAttr['compatibilite'])) { $apTags[] = t('auto.compat_label') . ' : ' . (string) $aAttr['compatibilite']; }
                } elseif ($pVertical === 'generic' && arti_capable((string) ($boutique['category'] ?? '')) && arti_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Artisanat adaptatif (Bijoux…) : specs (matière, origine…) + fait main / pièce unique + état.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['condition', 'fait_main', 'piece_unique', 'histoire', 'elec', 'garantie', 'contact_alimentaire', 'sale_mode', 'unit', 'variant_axis'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (($aAttr['sale_mode'] ?? '') === 'metre') { $apTags[] = t('arti.mode_tag_metre'); }
                    if (!empty($aAttr['fait_main'])) { $apTags[] = t('arti.f.faitmain'); }
                    if (!empty($aAttr['piece_unique'])) { $apTags[] = t('arti.f.unique'); }
                    if (!empty($aAttr['contact_alimentaire'])) { $apTags[] = t('arti.food_tag'); }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('cuisine.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'generic' && arti_capable((string) ($boutique['category'] ?? '')) && (string) ($product['collection'] ?? '') !== '') {
                    // Nouveau rayon Artisanat (hors des rayons répertoriés) : specs libres + fait main / pièce unique + au mètre.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (($aAttr['sale_mode'] ?? '') === 'metre') { $apTags[] = t('arti.mode_tag_metre'); }
                    if (!empty($aAttr['fait_main'])) { $apTags[] = t('arti.f.faitmain'); }
                    if (!empty($aAttr['piece_unique'])) { $apTags[] = t('arti.f.unique'); }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                } elseif ($pVertical === 'phone') {
                    // Fiche téléphone (legacy) OU rayon électronique « autre » : specs libres dans attributes.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['model'])) { $apTags[] = (string) $product['model']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['compatibilite'])) { $apTags[] = (string) $aAttr['compatibilite']; }
                    if (!empty($aAttr['specs']) && is_array($aAttr['specs'])) {
                        foreach ($aAttr['specs'] as $sv) { if (is_scalar($sv) && trim((string) $sv) !== '') { $apTags[] = (string) $sv; } }
                    }
                    if (!empty($product['item_condition'])) { $apTags[] = t('phone.cond.' . (string) $product['item_condition']); }
                    if (!empty($aAttr['condition']) && $aAttr['condition'] !== 'Neuf') { $apTags[] = (string) $aAttr['condition']; }
                    if (!empty($aAttr['garantie'])) { $apTags[] = t('elec.f.warranty') . ' ' . (string) $aAttr['garantie']; }
                } elseif ($pVertical === 'beauty') {
                    $col0  = (string) ($product['collection'] ?? '');
                    $isOng = $col0 === 'Ongles';
                    $isPar = $col0 === 'Parfums';
                    $isPer = $col0 === 'Perruque';
                    $isAutreP = !in_array($col0, ['Ongles', 'Parfums', 'Perruque', 'Soins corps', 'Soins visage', 'Maquillage', ''], true);
                    $bAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if ($isPer) {
                        $pSizeLabel = t('perruque.f.length');
                        $pColorLabel = t('perruque.f.color');
                        foreach (['hair_type', 'texture', 'densite', 'qualite', 'origine', 'lace_color', 'cap_size'] as $pk) {
                            if (!empty($bAttr[$pk])) { $apTags[] = (string) $bAttr[$pk]; }
                        }
                        if (!empty($bAttr['longueur'])) { $apTags[] = (int) $bAttr['longueur'] . '"'; }
                    } elseif ($isPar) {
                        $pSizeLabel = t('parfum.f.volume');
                        if (!empty($bAttr['genre']))   { $apTags[] = (string) $bAttr['genre']; }
                        if (!empty($bAttr['famille']))  { $apTags[] = (string) $bAttr['famille']; }
                        if (((float) ($product['volume'] ?? 0)) > 0) { $apTags[] = (int) $product['volume'] . ' ml'; }
                        if (!empty($bAttr['sillage'])) { $apTags[] = t('parfum.f.sillage') . ' ' . (string) $bAttr['sillage']; }
                        if (!empty($bAttr['tenue']))   { $apTags[] = t('parfum.f.tenue') . ' ' . (string) $bAttr['tenue']; }
                        if (!empty($bAttr['alcool']))  { $apTags[] = (string) $bAttr['alcool']; }
                        if (!empty($bAttr['format']))  { $apTags[] = (string) $bAttr['format']; }
                        foreach ((array) ($bAttr['occasions'] ?? []) as $o) { $apTags[] = (string) $o; }
                    } elseif ($isOng) {
                        $pSizeLabel  = t('ongles.f.forme');
                        $pColorLabel = t('ongles.f.length');
                        if (!empty($bAttr['forme']))    { $apTags[] = (string) $bAttr['forme']; }
                        if (!empty($bAttr['longueur'])) { $apTags[] = (string) $bAttr['longueur']; }
                        if (!empty($bAttr['material']))  { $apTags[] = (string) $bAttr['material']; }
                        if (!empty($bAttr['tips_count'])) { $apTags[] = (int) $bAttr['tips_count'] . ' ' . mb_strtolower(t('ongles.f.tips')); }
                        if (!empty($bAttr['wear_days']))  { $apTags[] = t('ongles.f.wear') . ' ' . (int) $bAttr['wear_days'] . ' ' . t('ongles.f.days'); }
                        foreach ((array) ($bAttr['designs'] ?? []) as $d) { $apTags[] = (string) $d; }
                        $hexMap = ongles_couleur_hex();
                        foreach ((array) ($bAttr['couleurs'] ?? []) as $cn) { $pOngColors[] = ['n' => (string) $cn, 'c' => $hexMap[(string) $cn] ?? '#ccc']; }
                    } elseif ($isAutreP) {
                        // Rayon libre : axe de déclinaison + caractéristiques (libellé→valeur).
                        if (!empty($bAttr['variant_axis'])) { $pSizeLabel = (string) $bAttr['variant_axis']; }
                        if (((float) ($product['volume'] ?? 0)) > 0) {
                            $apTags[] = rtrim(rtrim(number_format((float) $product['volume'], 2, '.', ''), '0'), '.') . ' ' . (string) ($product['volume_unit'] ?: 'ml');
                        }
                        foreach ((array) ($bAttr['specs'] ?? []) as $sl => $sv) {
                            $sv = trim((string) $sv);
                            if ($sv !== '') { $apTags[] = $sv; }
                        }
                        if (!empty($product['pao'])) { $apTags[] = 'PAO ' . (string) $product['pao']; }
                    } else {
                        if (!empty($product['line'])) { $apTags[] = (string) $product['line']; }
                        if (((float) ($product['volume'] ?? 0)) > 0) {
                            $apTags[] = rtrim(rtrim(number_format((float) $product['volume'], 2, '.', ''), '0'), '.') . ' ' . (string) ($product['volume_unit'] ?: 'ml');
                        }
                        foreach ($bAttr as $av) { if (is_scalar($av)) { $av = trim((string) $av); if ($av !== '') { $apTags[] = $av; } } }
                        // Soins : actifs clés en tags.
                        foreach ((array) ($bAttr['actifs'] ?? []) as $ac) { if (trim((string) $ac) !== '') { $apTags[] = (string) $ac; } }
                        if (!empty($product['pao'])) { $apTags[] = 'PAO ' . (string) $product['pao']; }
                    }
                } elseif (apparel_is_rayon((string) ($product['collection'] ?? ''))) {
                    // Mode adaptatif (Chaussures…) : genre / type / couleur / specs / état dans attributes.
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['genre'])) { $apTags[] = (string) $aAttr['genre']; }
                    if (!empty($aAttr['couleur'])) { $apTags[] = (string) $aAttr['couleur']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['genre', 'couleur', 'condition', 'variant_axis', 'public'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if (!empty($aAttr['condition']) && !in_array($aAttr['condition'], ['Neuf', 'Neuf avec étiquette'], true)) { $apTags[] = (string) $aAttr['condition']; }
                } else {
                    // Nouveau rayon mode (libre) : genre / type / couleur / specs dans attributes ;
                    // sinon fiche basique (audience / catégorie de vêtement).
                    $aAttr = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
                    if (!empty($aAttr['variant_axis'])) { $pSizeLabel = (string) $aAttr['variant_axis']; }
                    if (!empty($product['brand'])) { $apTags[] = (string) $product['brand']; }
                    if (!empty($product['product_type'])) { $apTags[] = (string) $product['product_type']; }
                    if (!empty($aAttr['genre']) && $aAttr['genre'] !== 'Non applicable') { $apTags[] = (string) $aAttr['genre']; }
                    elseif (!empty($product['audience'])) { $apTags[] = t('apparel.aud.' . (string) $product['audience']); }
                    if (!empty($aAttr['couleur'])) { $apTags[] = (string) $aAttr['couleur']; }
                    foreach ($aAttr as $ak => $av) {
                        if (in_array($ak, ['genre', 'couleur', 'condition', 'variant_axis'], true)) { continue; }
                        if (is_scalar($av) && trim((string) $av) !== '') { $apTags[] = (string) $av; }
                    }
                    if ($aAttr === [] && !empty($product['garment_category'])) { $apTags[] = t('apparel.cat.' . (string) $product['garment_category']); }
                    if (!empty($aAttr['condition']) && !in_array($aAttr['condition'], ['Neuf', 'Neuf avec étiquette'], true)) { $apTags[] = (string) $aAttr['condition']; }
                }
                // Atouts (Vegan, Bio…) en petits badges sous les caractéristiques.
                $pAtouts = array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
                ?>
                <?php if ($apTags !== []): ?><p class="listing-apparel"><?= icon('tag', ['size' => 14]) ?> <?= e(implode(' · ', $apTags)) ?></p><?php endif; ?>
                <?php if ($pAtouts !== []): ?><p class="listing-atouts"><?php foreach ($pAtouts as $a): ?><span class="atout-badge"><?= e($a) ?></span><?php endforeach; ?></p><?php endif; ?>
                <?php if ($pOngColors !== []): ?><p class="listing-tones"><?php foreach ($pOngColors as $oc): ?><span class="listing-tone" style="background:<?= e($oc['c']) ?>" title="<?= e($oc['n']) ?>"></span><?php endforeach; ?></p><?php endif; ?>
                <?php if (\App\Models\Product::isPromoted($product)): ?><p class="promo-line"><?= icon('sparkle', ['size' => 16]) ?> <?= e(t('ads.badge')) ?></p><?php endif; ?>
                <?php if (($rating['count'] ?? 0) > 0): ?>
                    <p class="listing-rating"><a href="#avis"><?= render_partial('partials/stars', ['avg' => $rating['avg'], 'count' => $rating['count']]) ?></a></p>
                <?php endif; ?>
                <?php $pPct = product_promo_pct($product); $pEff = product_effective_unit_cents($product, (int) $product['price_cents']); ?>
                <p class="listing-price"><?= render_partial('partials/price_dual', ['cents' => $pEff, 'cur' => $cur, 'compare' => $pPct > 0 ? (int) $product['price_cents'] : 0]) ?><?php if ($isMeter): ?> <span class="price-unit"><?= e(t('product.per_meter')) ?></span><?php endif; ?><?php if ($pPct > 0): ?> <span class="discount-badge discount-badge--inline">−<?= $pPct ?>%</span><?php endif; ?></p>
                <?php if ($pPct > 0 && !empty($product['promo_until'])): ?><p class="promo-until-line"><?= icon('clock', ['size' => 14]) ?> <?= e(t('product.promo_until', ['date' => date('d/m/Y', (int) strtotime((string) $product['promo_until']))])) ?></p><?php endif; ?>
                <p class="listing-tags">
                    <?php if ($inStock): ?>
                        <span class="badge badge-ok"><?= $product['stock'] === null ? e(t('product.in_stock')) : e(t('product.stock_n', ['n' => (int) $product['stock']])) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warn"><?= e(t('product.out_of_stock')) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!$inStock && $published): ?>
                    <div class="stock-alert" id="stock-alert">
                        <p class="stock-alert-cta"><?= icon('bell', ['size' => 16]) ?> <?= e(t('stock.cta')) ?></p>
                        <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'] . '/alerte-stock')) ?>" class="stock-alert-form">
                            <?= csrf_field() ?>
                            <input type="email" name="email" maxlength="120" value="<?= old('email') ?>" placeholder="<?= e(t('order.f.email_ph')) ?>">
                            <input type="tel" name="phone" maxlength="22" value="<?= old('phone') ?>" placeholder="+221 …">
                            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('stock.subscribe_btn')) ?></button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($realVariants !== []): ?>
                    <div class="variant-pick" data-variant-pick data-variants="<?= e((string) json_encode($vMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
                        <?php if ($vSizes !== []): ?>
                            <div class="variant-axis">
                                <p class="variant-pick-label"><?= e($pSizeLabel) ?> <span class="variant-pick-val" data-axis-val="size"></span></p>
                                <div class="variant-chips">
                                    <?php foreach ($vSizes as $sz):
                                        // Teinte stockée → teinte beauté (beauté seulement) → couleur déduite du nom.
                                        $hxs = (isset($vSizeHex[$sz]) && $vSizeHex[$sz] !== '') ? [$vSizeHex[$sz]]
                                             : ($pVertical === 'beauty' && ($bh = beauty_hex_for($sz)) !== null ? [$bh] : variant_color_hex($sz));
                                        $nz = $vSizeNuance[$sz] ?? ''; ?>
                                        <label class="variant-chip<?= $hxs !== [] ? ' variant-chip--tone' : '' ?>"><input type="radio" name="pick_size" value="<?= e($sz) ?>"><span><?= $dotHtml($hxs) ?><?= e($sz) ?><?php if ($nz !== ''): ?> <small class="chip-nuance"><?= e($nz) ?></small><?php endif; ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($vColors !== []): ?>
                            <div class="variant-axis">
                                <p class="variant-pick-label"><?= e($pColorLabel) ?> <span class="variant-pick-val" data-axis-val="color"></span></p>
                                <div class="variant-chips">
                                    <?php foreach ($vColors as $co):
                                        $hxs = (isset($vColorHex[$co]) && $vColorHex[$co] !== '') ? [$vColorHex[$co]]
                                             : ($pVertical === 'beauty' && ($ph = perruque_couleur_hex()[$co] ?? '') !== '' ? [$ph] : variant_color_hex($co)); ?>
                                        <label class="variant-chip<?= $hxs !== [] ? ' variant-chip--tone' : '' ?>"><input type="radio" name="pick_color" value="<?= e($co) ?>"><span><?= $dotHtml($hxs) ?><?= e($co) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <p class="variant-unavailable" data-variant-unavailable hidden><?= e(t('variant.unavailable')) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($isMeter): ?>
                    <div class="meter-buy" data-meter-buy data-price-m="<?= $pEff ?>">
                        <label for="meter-len"><?= e(t('product.meter_length')) ?></label>
                        <div class="meter-len-row">
                            <input type="number" id="meter-len" data-meter-length min="0.5" max="100" step="0.5" value="1" inputmode="decimal">
                            <span class="meter-unit">m</span>
                        </div>
                        <p class="meter-total"><?= e(t('product.meter_total')) ?> <strong data-meter-total>—</strong></p>
                    </div>
                <?php endif; ?>
                <?php if ($canOrder): ?>
                    <div class="product-buy">
                        <button type="button" class="btn btn-primary btn-block buy-now-btn" data-buy-now="<?= e($buyId) ?>"><?= icon('zap', ['size' => 18]) ?> <?= e(t($isMeter ? 'product.meter_buy' : 'bcart.buy_now')) ?></button>
                        <?php if ($realVariants === [] && !$isMeter): ?>
                            <?= render_partial('partials/cart_stepper', ['id' => (string) $product['public_id'], 'size' => '', 'name' => (string) $product['name'], 'price' => $pEff, 'add_label' => t('bcart.add_to_cart'), 'qty' => \App\Services\Cart::qty((int) $boutique['id'], (string) $product['public_id'])]) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="product-wish-line">
                    <?= render_partial('partials/wish_heart', ['pid' => (string) $product['public_id']]) ?>
                    <span class="muted"><?= e(t('wish.add')) ?></span>
                    <?= render_partial('partials/compare_toggle', ['pid' => (string) $product['public_id']]) ?>
                    <span class="muted"><?= e(t('compare.add')) ?></span>
                </div>
                <?php if ($waPhone !== '' && $boutique['status'] === 'published'): ?>
                    <a class="btn btn-ghost btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waText ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('product.order_whatsapp')) ?></a>
                <?php endif; ?>
                <?php if (!empty($seller_verified)): ?>
                    <p class="verified-line" title="<?= e(t('shop.verified_hint')) ?>"><?= icon('shield', ['size' => 16]) ?> <?= e(t('shop.verified_seller')) ?></p>
                <?php endif; ?>
                <?= render_partial('partials/share_row', [
                    'share_url'  => $productUrl,
                    'share_text' => t('share.product_text', ['name' => (string) $product['name']]),
                ]) ?>
                <?php if (!empty($aff_link)): ?>
                    <div class="aff-share">
                        <p class="aff-share-cta"><?= icon('wallet', ['size' => 16]) ?> <?= e(t('aff.share_cta', ['rate' => (string) ($aff_rate ?? '')])) ?></p>
                        <div class="aff-link-row">
                            <input type="text" class="aff-link-input" value="<?= e($aff_link) ?>" readonly aria-label="<?= e(t('aff.your_link')) ?>">
                            <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($aff_link) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= e(t('aff.copy')) ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($published && !$is_owner): ?>
                    <?php if ((int) (current_user_id() ?? 0) > 0): ?>
                        <details class="msg-ask">
                            <summary><?= icon('chat', ['size' => 16]) ?> <?= e(t('msg.ask_seller')) ?></summary>
                            <form method="post" action="<?= e(url('/messages/demarrer')) ?>" class="msg-ask-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= e($boutique['slug']) ?>">
                                <input type="hidden" name="product" value="<?= e($product['public_id']) ?>">
                                <textarea name="body" rows="3" maxlength="2000" required placeholder="<?= e(t('msg.ask_ph')) ?>"></textarea>
                                <button type="submit" class="btn btn-ghost btn-block"><?= e(t('msg.send_seller')) ?></button>
                            </form>
                        </details>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-block" href="<?= e(url('/login')) ?>"><?= icon('chat', ['size' => 16]) ?> <?= e(t('msg.login_to_ask')) ?></a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($is_owner): ?>
                    <div class="listing-owner-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/produits/' . $product['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($product['description'])): ?>
        <div class="panel">
            <h2 class="panel-title"><?= e(t('product.f.description')) ?></h2>
            <p class="listing-description"><?= nl2br(e((string) $product['description'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($pVertical === 'beauty' && (!empty($product['ingredients']) || !empty($product['expiry_date']) || !empty($product['ean']))): ?>
        <div class="panel">
            <details class="variants-box"<?= !empty($product['ingredients']) ? ' open' : '' ?>>
                <summary>🧴 <?= e(t('beauty.sec.specs')) ?></summary>
                <?php if (((float) ($product['volume'] ?? 0)) > 0): ?><p class="muted"><?= e(t('beauty.f.volume')) ?> : <strong><?= e(rtrim(rtrim(number_format((float) $product['volume'], 2, '.', ''), '0'), '.')) ?> <?= e((string) ($product['volume_unit'] ?: 'ml')) ?></strong></p><?php endif; ?>
                <?php if (!empty($product['pao'])): ?><p class="muted"><?= e(t('beauty.f.pao')) ?> : <strong><?= e((string) $product['pao']) ?></strong></p><?php endif; ?>
                <?php if (!empty($product['expiry_date'])): ?><p class="muted"><?= e(t('beauty.f.expiry')) ?> : <strong><?= e(date('d/m/Y', (int) strtotime((string) $product['expiry_date']))) ?></strong></p><?php endif; ?>
                <?php if (!empty($product['ean'])): ?><p class="muted mono"><?= e(t('beauty.f.ean')) ?> : <?= e((string) $product['ean']) ?></p><?php endif; ?>
                <?php if (!empty($product['ingredients'])): ?>
                    <p class="muted" style="margin-top:8px"><strong><?= e(t('beauty.f.ingredients')) ?></strong></p>
                    <p class="listing-description"><?= nl2br(e((string) $product['ingredients'])) ?></p>
                <?php endif; ?>
            </details>
        </div>
    <?php endif; ?>

    <?php
    $parNotesD = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
    $parNotesD = is_array($parNotesD['notes'] ?? null) ? $parNotesD['notes'] : [];
    ?>
    <?php if ($pVertical === 'beauty' && $parNotesD !== []): ?>
        <div class="panel">
            <h2 class="panel-title">🔺 <?= e(t('parfum.sec.pyramid')) ?></h2>
            <div class="pyr-read">
                <?php foreach (['tete' => 'parfum.f.top', 'coeur' => 'parfum.f.heart', 'fond' => 'parfum.f.base'] as $nk => $lbl): ?>
                    <?php if (empty($parNotesD[$nk])) { continue; } ?>
                    <p class="pyr-read-row"><span class="pyr-read-tag pyr-<?= e($nk) ?>"><?= e(t($lbl)) ?></span> <?= e((string) $parNotesD[$nk]) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Avis & notes -->
    <div class="panel" id="avis">
        <h2 class="panel-title"><?= icon('star', ['size' => 18]) ?> <?= e(t('review.title')) ?>
            <?php if (($rating['count'] ?? 0) > 0): ?> <?= render_partial('partials/stars', ['avg' => $rating['avg'], 'count' => $rating['count']]) ?><?php endif; ?>
        </h2>
        <?php if (empty($reviews)): ?>
            <p class="muted"><?= e(t('review.empty')) ?></p>
        <?php else: ?>
            <ul class="review-list">
                <?php foreach ($reviews as $rv): ?>
                    <li class="review-item">
                        <div class="review-head">
                            <?= render_partial('partials/stars', ['avg' => (int) $rv['rating'], 'count' => 0, 'small' => true]) ?>
                            <strong class="review-author"><?= e((string) $rv['author_name']) ?></strong>
                            <?php if (!empty($rv['verified'])): ?><span class="review-verified" title="<?= e(t('review.verified_hint')) ?>">✓ <?= e(t('review.verified')) ?></span><?php endif; ?>
                            <span class="review-date muted"><?= e(date('d/m/Y', strtotime((string) $rv['created_at']))) ?></span>
                            <?php if ($is_owner): ?>
                                <form method="post" action="<?= e(url('/boutique/avis/' . $rv['public_id'] . '/masquer')) ?>" class="inline-form review-hide">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="/boutique/<?= e($boutique['slug']) ?>/p/<?= e($product['public_id']) ?>#avis">
                                    <button class="link-button btn-danger" data-confirm="<?= e(t('review.hide_confirm')) ?>"><?= e(t('review.hide')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($rv['comment'])): ?><p class="review-comment"><?= nl2br(e((string) $rv['comment'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($published && (int) (current_user_id() ?? 0) === 0): ?>
            <p class="review-login-cta"><?= icon('lock', ['size' => 16]) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('review.login_to_post')) ?></a></p>
        <?php endif; ?>
        <?php if ($published && (int) (current_user_id() ?? 0) > 0): ?>
            <details class="review-form-box" <?= has_error('review') ? 'open' : '' ?>>
                <summary><?= icon('pencil', ['size' => 16]) ?> <?= e(t('review.cta')) ?></summary>
                <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'] . '/avis')) ?>" class="review-form">
                    <?= csrf_field() ?>
                    <div class="star-input" role="radiogroup" aria-label="<?= e(t('review.title')) ?>">
                        <?php for ($s = 5; $s >= 1; $s--): ?>
                            <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" <?= $s === 5 ? 'checked' : '' ?>>
                            <label for="star<?= $s ?>" title="<?= $s ?>/5">★</label>
                        <?php endfor; ?>
                    </div>
                    <label for="rv-name"><?= e(t('order.f.client')) ?></label>
                    <input type="text" id="rv-name" name="author_name" maxlength="80" required value="<?= old('author_name') ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
                    <label for="rv-contact"><?= e(t('review.verify_label')) ?></label>
                    <input type="text" id="rv-contact" name="purchase_contact" maxlength="120" value="<?= old('purchase_contact') ?>" placeholder="<?= e(t('review.verify_ph')) ?>">
                    <p class="form-hint muted">✓ <?= e(t('review.verify_hint')) ?></p>
                    <label for="rv-comment"><?= e(t('review.comment')) ?></label>
                    <textarea id="rv-comment" name="comment" maxlength="1000" rows="3" placeholder="<?= e(t('review.comment_ph')) ?>"><?= old('comment') ?></textarea>
                    <button type="submit" class="btn btn-primary"><?= e(t('review.submit')) ?></button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <!-- Produits recommandés -->
    <?php if (!empty($related)): ?>
        <div class="panel">
            <h2 class="panel-title"><?= icon('bag', ['size' => 18]) ?> <?= e(t('product.related')) ?></h2>
            <div class="product-grid">
                <?php foreach ($related as $rp): $rm = $related_mains[(int) $rp['id']] ?? null; ?>
                    <a class="product-card" href="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $rp['public_id'])) ?>">
                        <span class="product-card-img">
                            <?php if ($rm !== null): ?><img src="<?= e(CloudinaryService::imageUrl($rm, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span><?php endif; ?>
                        </span>
                        <span class="product-card-name"><?= e((string) $rp['name']) ?></span>
                        <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $rp['price_cents'], 'cur' => $cur]) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommandations : souvent achetés ensemble (co-achats) + vu récemment (historique). -->
    <?php if (!empty($fbt)): ?>
        <?= render_partial('partials/product_rail', ['icon' => 'bag', 'title' => t('reco.fbt'), 'products' => $fbt, 'mains' => $reco_mains ?? []]) ?>
    <?php endif; ?>
    <?php if (!empty($recently_viewed)): ?>
        <?= render_partial('partials/product_rail', ['icon' => 'clock', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains ?? []]) ?>
    <?php endif; ?>

    <?php if ($canOrder): ?>
        <!-- Le panier (JS) est posté ici, revalidé serveur, puis on passe à la caisse. -->
        <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/caisse')) ?>" data-caisse-form hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="cart_json" data-cart-json value="[]">
        </form>

        <!-- Barre de panier (apparaît dès qu'un article est choisi) -->
        <div class="cart-bar" data-cart-bar hidden>
            <span class="cart-bar-info"><?= icon('cart', ['size' => 16]) ?> <span data-cart-count>0</span> <?= e(t('rorder.items')) ?> · <strong data-cart-total>0</strong></span>
            <button type="button" class="btn btn-primary" data-cart-checkout><?= e(t('bcart.to_checkout')) ?> →</button>
        </div>
    <?php endif; ?>

    <?= render_partial('partials/assistant', ['boutique' => $boutique, 'wa' => $waPhone]) ?>
</section>
