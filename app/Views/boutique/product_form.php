<?php
/** @var string $mode  @var array $boutique  @var ?array $product  @var list<array> $photos  @var bool $media_ready */
use App\Services\CloudinaryService;

$isEdit = $mode === 'edit';
$cur    = (string) $boutique['currency'];
$boutiqueCat = (string) ($boutique['category'] ?? '');
$vertical  = product_vertical($boutiqueCat); // 'phone' | 'apparel' | 'beauty' | 'generic'
$isPhone   = $vertical === 'phone';
$isApparel = $vertical === 'apparel';
$isBeauty  = $vertical === 'beauty';

// Rayon courant : sur re-render d'erreur on relit les champs réellement postés
// (collection_select / collection_other), sinon la valeur enregistrée du produit.
// NB : valeurs BRUTES (non échappées) — $curCol pilote la logique adaptative
// (*_is_rayon / *_autre_cfg / beauty_slug). L'échappement se fait à l'affichage
// (e($curCol), e(t(...))). Lire old() ici casserait les rayons contenant « & ».
$rawOldSel   = $_SESSION['_old'] ?? [];
$curRayonSel = is_string($rawOldSel['collection_select'] ?? null) ? (string) $rawOldSel['collection_select'] : '';
$curCol = $curRayonSel === '__other__'
    ? (is_string($rawOldSel['collection_other'] ?? null) ? (string) $rawOldSel['collection_other'] : '')
    : ($curRayonSel !== '' ? $curRayonSel : (string) ($product['collection'] ?? ''));

// Électronique : certains rayons (Accessoires, Audio & écouteurs…) basculent vers un
// formulaire adaptatif au type ; les autres gardent la fiche téléphone (marque/modèle/état).
$isElecForm = $isPhone && elec_is_rayon($curCol);
// Rayon électronique non répertorié (ou vide) => formulaire « autre / nouveau rayon » adaptatif.
$isElecAutre = $isPhone && !$isElecForm;
$elecSection = $isElecForm ? 'elec' : 'autre';

// Mode : certains rayons (Chaussures…) basculent vers un formulaire adaptatif au type
// (genre + couleur + caractéristiques du type + pointures) ; les autres gardent la fiche basique.
$isApparelRayon = $isApparel && apparel_is_rayon($curCol);
// Rayon mode non répertorié (ou vide) => formulaire « nouveau rayon » adaptatif au slug.
$isApparelAutre = $isApparel && !$isApparelRayon;
$appaSection = $isApparelRayon ? 'adaptive' : 'autre';

// Repli « vertical » de l'axe taille (utilisé tant qu'aucun rayon n'impose d'axe).
if ($isPhone) {
    $baseLabel = t('phone.f.storage'); $basePh = t('phone.f.storage_ph'); $baseOpts = phone_storage();
    $varSection = t('phone.f.variants'); $varHint = t('phone.f.variants_hint');
} elseif ($isApparel) {
    $baseLabel = t('variant.size'); $basePh = t('variant.size_ph');
    $baseOpts = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
    $varSection = t('variant.section'); $varHint = t('variant.hint');
} else {
    $baseLabel = t('variant.option'); $basePh = t('variant.option_ph'); $baseOpts = [];
    $varSection = t('variant.section_generic'); $varHint = t('variant.hint_generic');
}

// Le RAYON choisi pilote l'axe de déclinaison : taille → Stockage / Contenance / Teinte / Pointure…
$axisMeta = rayon_axis_meta($boutiqueCat, $curCol);
if ($axisMeta['key'] !== 'none') {
    $sizeLabel = $axisMeta['label']; $sizePh = $axisMeta['label']; $sizeOpts = $axisMeta['opts'];
} else {
    $sizeLabel = $baseLabel; $sizePh = $basePh; $sizeOpts = $baseOpts;
}
$action = $isEdit ? '/boutique/produits/' . $product['public_id'] . '/modifier' : '/boutique/produits';
$maxPhotos = (int) config('shop.product_max_photos', 6);
$existingIds = array_map(static fn (array $p): string => (string) $p['cloud_public_id'], $photos);
$priceVal = $isEdit ? rtrim(rtrim(number_format(((int) $product['price_cents']) / 100, 2, '.', ''), '0'), '.') : '';
if (currency_is_integer($cur) && $isEdit) { $priceVal = (string) intdiv((int) $product['price_cents'], 100); }
$variants = $variants ?? [];
// Variantes « réelles » = hors variante par défaut implicite (1 seule, sans libellé/sku).
$realVariants = array_values(array_filter($variants, static fn (array $v): bool =>
    trim((string) ($v['label'] ?? '')) !== '' || trim((string) ($v['sku'] ?? '')) !== '' || count($variants) > 1));
$fmtP = static function ($cents) use ($cur): string {
    if ($cents === null || $cents === '') { return ''; }
    return currency_is_integer($cur)
        ? (string) intdiv((int) $cents, 100)
        : rtrim(rtrim(number_format(((int) $cents) / 100, 2, '.', ''), '0'), '.');
};
?>
<section class="auth-card auth-card--pf"><?php /* formulaire produit : large (2 colonnes form + aperçu) sur toutes les verticales */ ?>
    <h1>📦 <?= e($isEdit ? t('product.edit_title') : t('product.add_title')) ?></h1>
    <p class="muted"><?= e($boutique['name']) ?> · <?= e($cur) ?></p>

    <?php if (!$media_ready): ?>
        <div class="notice notice-warning"><p><?= e(t('listing.media_unconfigured')) ?></p></div>
    <?php else: ?>
    <div class="pf-grid"><?php /* Aperçu fiche à droite pour TOUTES les verticales */ ?>
    <form method="post" action="<?= e(url($action)) ?>" id="product-form" novalidate
          data-uploading="<?= e(t('kyc.uploading')) ?>" data-max="<?= $maxPhotos ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="photos_json" id="product-photos-json" value="<?= e(json_encode($existingIds)) ?>">
        <input type="hidden" name="photos_touched" id="product-photos-touched" value="0">

        <label for="p-name"><?= e(t('product.f.name')) ?></label>
        <input type="text" id="p-name" name="name" value="<?= old('name') ?: e((string) ($product['name'] ?? '')) ?>"
               required maxlength="<?= (int) config('shop.product_name_max', 150) ?>" placeholder="<?= e(t('product.f.name_ph')) ?>" data-pv="name">
        <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

        <?php
        $cols   = $collections ?? [];
        $catOpts = [];
        foreach ($cols as $c) { if (trim((string) $c) !== '') { $catOpts[(string) $c] = (string) $c; } }
        // Rayons suggérés selon la catégorie VERROUILLÉE de la boutique (pas les autres catégories).
        foreach (shop_rayons_for($boutiqueCat) as $r) { $catOpts[(string) $r] = (string) $r; }
        ksort($catOpts, SORT_NATURAL | SORT_FLAG_CASE);
        $isOther = $curCol !== '' && !isset($catOpts[$curCol]);
        ?>
        <label for="p-collection"><?= e(t('product.f.collection')) ?> <span class="req">*</span></label>
        <select id="p-collection" name="collection_select" data-collection-select required>
            <option value=""><?= e(t('product.f.collection_none')) ?></option>
            <?php foreach ($catOpts as $val => $lbl): ?>
                <option value="<?= e((string) $val) ?>" data-axis="<?= e(rayon_axis($boutiqueCat, (string) $val)) ?>" <?= $curCol === (string) $val ? 'selected' : '' ?>><?= e((string) $lbl) ?></option>
            <?php endforeach; ?>
            <option value="__other__" data-axis="none" <?= $isOther ? 'selected' : '' ?>><?= e(t('product.f.collection_other')) ?></option>
        </select>
        <input type="text" id="p-collection-other" name="collection_other" maxlength="60" data-collection-other
               value="<?= $isOther ? e($curCol) : '' ?>" placeholder="<?= e(t('product.f.collection_ph')) ?>"<?= $isOther ? '' : ' hidden' ?>>
        <?php if (has_error('collection')): ?><p class="field-error"><?= e(error('collection')) ?></p><?php endif; ?>
        <p class="hint"><?= e(t('product.f.collection_hint')) ?></p>

        <?php if (cuisine_capable($boutiqueCat)):
            $rawOldC  = $_SESSION['_old'] ?? [];
            $cuiAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $cuiActive = cuisine_is_rayon($curCol);                       // rayon adaptatif Maison sélectionné ?
            $cuiRayon  = $cuiActive ? $curCol : (cuisine_rayons()[0] ?? 'Cuisine'); // rayon rendu côté serveur
            $cuiType  = (string) ($rawOldC['product_type'] ?? ($product['product_type'] ?? ''));
            $cuiMeta  = cuisine_type_meta($cuiRayon, $cuiType);
            $cuiAttr  = static fn (string $k): string => (string) ($rawOldC[$k] ?? ($cuiAttrs[$k] ?? ''));
            $cuiElec  = $cuiMeta !== null && !empty($cuiMeta['elec']);
            $cuiAtoutsSel = isset($rawOldC['atouts']) && is_array($rawOldC['atouts'])
                ? array_map('strval', $rawOldC['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $cuiDis = $cuiActive ? '' : ' disabled';
            // « Nouveau rayon » : rayon Maison hors des 6 répertoriés (collection « autre »
            // ou rayon personnalisé déjà enregistré).
            $cuiAutreActive = !$cuiActive && $curCol !== '';
            $cuiAutreCfg  = cuisine_autre_cfg($curCol);
            $cuiAutreType = (string) ($rawOldC['product_type'] ?? ($product['product_type'] ?? ''));
            $cuiAutreElec = isset($rawOldC['elec_on'])
                ? ((string) $rawOldC['elec_on'] === '1')
                : (!empty($cuiAttrs['elec']) || ($cuiAutreCfg !== null && !empty($cuiAutreCfg['elec'])));
            $cuiAutreSpecs = [];
            if (isset($rawOldC['spec_label']) && is_array($rawOldC['spec_label'])) {
                foreach ($rawOldC['spec_label'] as $i => $lb) { $cuiAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldC['spec_value'][$i] ?? '')]; }
            } elseif (is_array($cuiAttrs['specs'] ?? null)) {
                foreach ($cuiAttrs['specs'] as $lb => $val) { $cuiAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
            }
            $cuiAutreDis = $cuiAutreActive ? '' : ' disabled';
        ?>
        <div data-cuisine
             data-rayons="<?= e((string) json_encode((array) config('cuisine.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-any="<?= e(t('cuisine.f.type_any')) ?>"
             data-autre="<?= e((string) json_encode(cuisine_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('cuisine.autre_generic')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Cuisine adaptatif (Maison & meubles) ===== -->
        <div data-cuisine-root<?= $cuiActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="cui-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="cui-brand" name="brand" maxlength="60" value="<?= e((string) ($rawOldC['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('cuisine.brand_ph')) ?>" data-pv="brand"<?= $cuiDis ?>>
                </div>
                <div>
                    <label for="cui-type"><?= e(t('cuisine.f.type')) ?> <span class="req">*</span></label>
                    <select id="cui-type" name="product_type" data-pv="type" data-cuisine-type<?= $cuiDis ?>>
                        <option value=""><?= e(t('cuisine.f.type_any')) ?></option>
                        <?php foreach (cuisine_groups($cuiRayon) as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (cuisine_types($cuiRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $cuiType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-cuisine-hint><?= e($cuiMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-cuisine-attrs>
                    <?php if ($cuiMeta): foreach ((array) ($cuiMeta['fields'] ?? []) as $fk): $fd = cuisine_fields($cuiRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($cuiAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $cuiDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="cui-condition"><?= e(t('cuisine.f.condition')) ?></label>
                        <select id="cui-condition" name="acc_condition"<?= $cuiDis ?>>
                            <?php $cCur = $cuiAttr('condition') ?: 'Neuf'; foreach (cuisine_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $cCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div data-cuisine-elec-box<?= $cuiElec ? '' : ' hidden' ?>>
                        <label for="cui-garantie"><?= e(t('cuisine.f.warranty')) ?></label>
                        <select id="cui-garantie" name="acc_garantie"<?= ($cuiActive && $cuiElec) ? '' : ' disabled' ?>>
                            <option value=""><?= e(t('cuisine.f.warranty_none')) ?></option>
                            <?php foreach (cuisine_garanties() as $g): ?><option value="<?= e($g) ?>" <?= $cuiAttr('garantie') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="notice notice-warning" data-cuisine-elec-warn<?= $cuiElec ? '' : ' hidden' ?>><p>⚡ <?= e(t('cuisine.elec_warn')) ?></p></div>
                <div class="grid-2">
                    <div>
                        <label for="cui-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="cui-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldC['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="CUIS-001"<?= $cuiDis ?>>
                    </div>
                    <div>
                        <label for="cui-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="cui-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldC['variant_axis'] ?? ($cuiAttrs['variant_axis'] ?? ($cuiMeta['axis'] ?? '')))) ?>" placeholder="<?= e(t('cuisine.axis_ph')) ?>" data-cuisine-axis<?= $cuiDis ?>>
                    </div>
                </div>
                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-cuisine-atouts>
                    <?php foreach (cuisine_atouts($cuiRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $cuiAtoutsSel, true) ? 'checked' : '' ?><?= $cuiDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div><!-- /cuisine adaptatif -->

        <!-- ===== Maison : NOUVEAU RAYON (adaptatif au slug) ===== -->
        <div data-cuisine-autre-root<?= $cuiAutreActive ? '' : ' hidden' ?>>
            <p class="hint" data-cuisine-autre-hint><?= $cuiAutreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('cuisine.autre_generic')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="cua-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="cua-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldC['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('cuisine.brand_ph')) ?>"<?= $cuiAutreDis ?>>
                </div>
                <div>
                    <label for="cua-type"><?= e(t('cuisine.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="cua-type" name="product_type" data-pv="type" maxlength="60" value="<?= e($cuiAutreType) ?>" placeholder="<?= e(t('cuisine.autre_type_ph')) ?>"<?= $cuiAutreDis ?>>
                </div>
            </div>
            <label style="margin-top:10px"><?= e(t('autre.rayon_suggest')) ?></label>
            <div class="chips-row" data-cuisine-autre-rayon-chips>
                <?php foreach (cuisine_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-cuisine-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
            </div>

            <label class="check-row" style="margin-top:14px"><input type="checkbox" name="elec_on" value="1" data-cuisine-autre-elec-toggle <?= $cuiAutreElec ? 'checked' : '' ?><?= $cuiAutreDis ?>><span><?= e(t('cuisine.autre_elec_q')) ?></span></label>
            <div class="grid-2" data-cuisine-autre-elec-box<?= $cuiAutreElec ? '' : ' hidden' ?>>
                <div>
                    <label for="cua-condition"><?= e(t('cuisine.f.condition')) ?></label>
                    <select id="cua-condition" name="acc_condition"<?= $cuiAutreDis ?>>
                        <?php $cCur2 = (string) ($rawOldC['acc_condition'] ?? ($cuiAttrs['condition'] ?? 'Neuf')); foreach (cuisine_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $cCur2 === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="cua-garantie"><?= e(t('cuisine.f.warranty')) ?></label>
                    <select id="cua-garantie" name="acc_garantie"<?= ($cuiAutreActive && $cuiAutreElec) ? '' : ' disabled' ?>>
                        <option value=""><?= e(t('cuisine.f.warranty_none')) ?></option>
                        <?php $gCur2 = (string) ($rawOldC['acc_garantie'] ?? ($cuiAttrs['garantie'] ?? '')); foreach (cuisine_garanties() as $g): ?><option value="<?= e($g) ?>" <?= $gCur2 === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="notice notice-warning" data-cuisine-autre-elec-warn<?= $cuiAutreElec ? '' : ' hidden' ?>><p>⚡ <?= e(t('cuisine.elec_warn')) ?></p></div>

            <details class="variants-box" open>
                <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
                <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
                <div class="axis-suggest" data-cuisine-autre-spec-box>
                    <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                    <div class="axis-suggest-chips" data-cuisine-autre-spec-chips>
                        <?php foreach (($cuiAutreCfg['specs'] ?? cuisine_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-cuisine-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="spec-rows" data-cuisine-autre-specs>
                    <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                    <?php foreach ($cuiAutreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                        <div class="spec-row">
                            <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>"<?= $cuiAutreDis ?>>
                            <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>"<?= $cuiAutreDis ?>>
                            <button type="button" class="variant-del" data-cuisine-autre-spec-del aria-label="✕">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-cuisine-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
                <template id="cuisine-autre-spec-template">
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-cuisine-autre-spec-del aria-label="✕">✕</button>
                    </div>
                </template>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="cua-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="cua-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldC['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="MAISON-001"<?= $cuiAutreDis ?>>
                    </div>
                    <div>
                        <label for="cua-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="cua-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldC['variant_axis'] ?? ($cuiAttrs['variant_axis'] ?? ($cuiAutreCfg['axis'] ?? '')))) ?>" placeholder="<?= e(t('cuisine.axis_ph')) ?>" data-cuisine-autre-axis<?= $cuiAutreDis ?>>
                    </div>
                </div>
                <?php $cuaSizes = ($cuiAutreCfg && !empty($cuiAutreCfg['sizes'])) ? (array) (cuisine_autre('size_systems')[$cuiAutreCfg['sizes']] ?? []) : []; ?>
                <label data-cuisine-autre-size-label<?= $cuaSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-cuisine-autre-size-chips<?= $cuaSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($cuaSizes as $sb): ?><button type="button" class="axis-chip" data-cuisine-autre-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
                <div class="warn-box">ℹ️ <?= e((string) config('cuisine.autre.warn_text', '')) ?></div>
                <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-cuisine-autre-atouts>
                    <?php $cuaAll = array_values(array_unique(array_merge(cuisine_autre('atout_suggest'), $cuiAtoutsSel)));
                    foreach ($cuaAll as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $cuiAtoutsSel, true) ? 'checked' : '' ?><?= $cuiAutreDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="autre-atout-add">
                    <input type="text" id="cua-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-cuisine-autre-atout-input>
                    <button type="button" class="btn btn-ghost btn-sm" data-cuisine-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
                </div>
            </details>
        </div><!-- /cuisine nouveau rayon -->
        <?php endif; ?>

        <?php if (alim_capable($boutiqueCat)):
            $rawOldF   = $_SESSION['_old'] ?? [];
            $alimAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $alimActive = alim_is_rayon($curCol);
            $alimRayon  = $alimActive ? $curCol : (alim_rayons()[0] ?? 'Bio & naturel');
            $alimType   = (string) ($rawOldF['product_type'] ?? ($product['product_type'] ?? ''));
            $alimMeta   = alim_type_meta($alimRayon, $alimType);
            $alimConserv = (string) ($rawOldF['conservation'] ?? ($alimAttrs['conservation'] ?? ($alimMeta['conserv'] ?? 'Ambiante / sèche')));
            $alimDlc     = (string) ($rawOldF['dlc_type'] ?? ($alimAttrs['dlc_type'] ?? ''));
            $alimDate    = (string) ($rawOldF['date_limite'] ?? ($alimAttrs['date_limite'] ?? ''));
            $alimAtoutsSel = isset($rawOldF['atouts']) && is_array($rawOldF['atouts'])
                ? array_map('strval', $rawOldF['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $alimAllergSel = isset($rawOldF['allergenes']) && is_array($rawOldF['allergenes'])
                ? array_map('strval', $rawOldF['allergenes'])
                : array_map('strval', (array) ($alimAttrs['allergenes'] ?? []));
            $alimCold = $alimConserv !== '' && $alimConserv !== 'Ambiante / sèche';
            $alimAlc  = $alimMeta !== null && !empty($alimMeta['alcool']);
            $alimDis = $alimActive ? '' : ' disabled';
            // « Nouveau rayon » Alimentation : rayon hors des rayons répertoriés.
            $alimAutreActive = !$alimActive && $curCol !== '';
            $alimAutreCfg    = alim_autre_cfg($curCol);
            $alimAutreType   = (string) ($rawOldF['product_type'] ?? ($product['product_type'] ?? ''));
            $alimAutreConserv = (string) ($rawOldF['conservation'] ?? ($alimAttrs['conservation'] ?? ($alimAutreCfg['conserv'] ?? 'Ambiante / sèche')));
            $alimAutreDlc     = (string) ($rawOldF['dlc_type'] ?? ($alimAttrs['dlc_type'] ?? ''));
            $alimAutreDate    = (string) ($rawOldF['date_limite'] ?? ($alimAttrs['date_limite'] ?? ''));
            $alimAutreCold = $alimAutreConserv !== '' && $alimAutreConserv !== 'Ambiante / sèche';
            $alimAutreBaby = ($alimAutreCfg !== null && !empty($alimAutreCfg['baby'])) || preg_match('/b[ée]b[ée]|nourrisson/iu', $curCol) === 1;
            $alimAutreSpecs = [];
            if (isset($rawOldF['spec_label']) && is_array($rawOldF['spec_label'])) {
                foreach ($rawOldF['spec_label'] as $i => $lb) { $alimAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldF['spec_value'][$i] ?? '')]; }
            } elseif (is_array($alimAttrs['specs'] ?? null)) {
                foreach ($alimAttrs['specs'] as $lb => $val) { $alimAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
            }
            $alimAutreDis = $alimAutreActive ? '' : ' disabled';
        ?>
        <div data-alim
             data-rayons="<?= e((string) json_encode((array) config('alimentation.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('alimentation.size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(alim_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('alim.autre_generic')) ?>"
             data-any="<?= e(t('alim.f.type_any')) ?>" data-ambient="Ambiante / sèche"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Alimentation adaptatif (Bio & naturel…) ===== -->
        <div data-alim-root<?= $alimActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="alim-brand"><?= e(t('alim.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="alim-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldF['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('alim.brand_ph')) ?>"<?= $alimDis ?>>
                </div>
                <div>
                    <label for="alim-type"><?= e(t('alim.f.type')) ?> <span class="req">*</span></label>
                    <select id="alim-type" name="product_type" data-pv="type" data-alim-type<?= $alimDis ?>>
                        <option value=""><?= e(t('alim.f.type_any')) ?></option>
                        <?php $alimGroups = alim_groups($alimRayon); ?>
                        <?php if ($alimGroups !== []): foreach ($alimGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (alim_types($alimRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $alimType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (alim_types($alimRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $alimType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-alim-hint><?= e($alimMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-alim-attrs>
                    <?php if ($alimMeta): foreach ((array) ($alimMeta['fields'] ?? []) as $fk): $fd = alim_fields($alimRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($alimAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $alimDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="notice notice-warn" data-alim-alc-note<?= $alimAlc ? '' : ' hidden' ?>><p>🔞 <?= e(t('alim.alc_note')) ?></p></div>
                <div class="grid-2">
                    <div>
                        <label for="alim-conserv"><?= e(t('alim.f.conservation')) ?></label>
                        <select id="alim-conserv" name="conservation" data-alim-conserv<?= $alimDis ?>>
                            <?php foreach (alim_conservations() as $cs): ?><option value="<?= e($cs) ?>" <?= $alimConserv === $cs ? 'selected' : '' ?>><?= e($cs) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="alim-dlc"><?= e(t('alim.f.dlc_type')) ?></label>
                        <select id="alim-dlc" name="dlc_type" data-alim-dlc<?= $alimDis ?>>
                            <option value=""><?= e(t('alim.f.dlc_none')) ?></option>
                            <?php foreach (alim_dlc_types() as $d): ?><option value="<?= e($d) ?>" <?= $alimDlc === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="alim-date"><?= e(t('alim.f.date_limite')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="date" id="alim-date" name="date_limite" value="<?= e($alimDate) ?>"<?= $alimDis ?>>
                    </div>
                    <div>
                        <label for="alim-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="alim-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldF['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="BIO-001"<?= $alimDis ?>>
                    </div>
                </div>
                <div class="notice notice-info" data-alim-cold-note<?= $alimCold ? '' : ' hidden' ?>><p>❄️ <?= e(t('alim.cold_note')) ?></p></div>

                <label style="margin-top:12px"><?= e(t('alim.f.allergenes')) ?> <span class="muted">(<?= e(t('alim.f.allergenes_opt')) ?>)</span></label>
                <div class="chip-checks" data-alim-allergenes>
                    <?php foreach (alim_allergenes() as $al): ?>
                        <label class="chip-check chip-check--health"><input type="checkbox" name="allergenes[]" value="<?= e($al) ?>" <?= in_array($al, $alimAllergSel, true) ? 'checked' : '' ?><?= $alimDis ?>><span><?= e($al) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="notice notice-warning"><p>🌿 <?= e(t('alim.bio_note')) ?></p></div>

                <div class="grid-2" style="margin-top:12px">
                    <div>
                        <label for="alim-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="alim-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldF['variant_axis'] ?? ($alimAttrs['variant_axis'] ?? ($alimMeta['axis'] ?? 'Poids')))) ?>" placeholder="<?= e(t('alim.axis_ph')) ?>" data-alim-axis<?= $alimDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $alimSizes = ($alimMeta && !empty($alimMeta['axis'])) ? (array) (config('alimentation.size_systems')[$alimMeta['axis']] ?? []) : []; ?>
                <label data-alim-size-label<?= $alimSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-alim-size-chips<?= $alimSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($alimSizes as $sb): ?><button type="button" class="axis-chip" data-alim-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-alim-atouts>
                    <?php foreach (alim_atouts($alimRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $alimAtoutsSel, true) ? 'checked' : '' ?><?= $alimDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div><!-- /alim adaptatif -->

        <!-- ===== Alimentation : NOUVEAU RAYON (adaptatif au slug) ===== -->
        <div data-alim-autre-root<?= $alimAutreActive ? '' : ' hidden' ?>>
            <p class="hint" data-alim-autre-hint><?= $alimAutreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('alim.autre_generic')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="aua-brand"><?= e(t('alim.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldF['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('alim.brand_ph')) ?>"<?= $alimAutreDis ?>>
                </div>
                <div>
                    <label for="aua-type"><?= e(t('alim.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua-type" name="product_type" data-pv="type" maxlength="60" value="<?= e($alimAutreType) ?>" placeholder="<?= e(t('alim.autre_type_ph')) ?>"<?= $alimAutreDis ?>>
                </div>
            </div>
            <label style="margin-top:10px"><?= e(t('autre.rayon_suggest')) ?></label>
            <div class="chips-row" data-alim-autre-rayon-chips>
                <?php foreach (alim_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-alim-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
            </div>

            <details class="variants-box" open>
                <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
                <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
                <div class="axis-suggest" data-alim-autre-spec-box>
                    <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                    <div class="axis-suggest-chips" data-alim-autre-spec-chips>
                        <?php foreach (($alimAutreCfg['specs'] ?? alim_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-alim-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="spec-rows" data-alim-autre-specs>
                    <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                    <?php foreach ($alimAutreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                        <div class="spec-row">
                            <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>"<?= $alimAutreDis ?>>
                            <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>"<?= $alimAutreDis ?>>
                            <button type="button" class="variant-del" data-alim-autre-spec-del aria-label="✕">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-alim-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
                <template id="alim-autre-spec-template">
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-alim-autre-spec-del aria-label="✕">✕</button>
                    </div>
                </template>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="aua-conserv"><?= e(t('alim.f.conservation')) ?></label>
                        <select id="aua-conserv" name="conservation" data-alim-autre-conserv<?= $alimAutreDis ?>>
                            <?php foreach (alim_conservations() as $cs): ?><option value="<?= e($cs) ?>" <?= $alimAutreConserv === $cs ? 'selected' : '' ?>><?= e($cs) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="aua-dlc"><?= e(t('alim.f.dlc_type')) ?></label>
                        <select id="aua-dlc" name="dlc_type" data-alim-autre-dlc<?= $alimAutreDis ?>>
                            <option value=""><?= e(t('alim.f.dlc_none')) ?></option>
                            <?php foreach (alim_dlc_types() as $d): ?><option value="<?= e($d) ?>" <?= $alimAutreDlc === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="aua-date"><?= e(t('alim.f.date_limite')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="date" id="aua-date" name="date_limite" value="<?= e($alimAutreDate) ?>"<?= $alimAutreDis ?>>
                    </div>
                    <div>
                        <label for="aua-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="aua-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldF['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="ALIM-001"<?= $alimAutreDis ?>>
                    </div>
                </div>
                <div class="notice notice-info" data-alim-autre-cold-note<?= $alimAutreCold ? '' : ' hidden' ?>><p>❄️ <?= e(t('alim.cold_note')) ?></p></div>
                <div class="notice notice-warning" data-alim-autre-baby-note<?= $alimAutreBaby ? '' : ' hidden' ?>><p>👶 <?= e(t('alim.baby_note')) ?></p></div>

                <label style="margin-top:12px"><?= e(t('alim.f.allergenes')) ?> <span class="muted">(<?= e(t('alim.f.allergenes_opt')) ?>)</span></label>
                <div class="chip-checks" data-alim-autre-allergenes>
                    <?php foreach (alim_allergenes() as $al): ?>
                        <label class="chip-check chip-check--health"><input type="checkbox" name="allergenes[]" value="<?= e($al) ?>" <?= in_array($al, $alimAllergSel, true) ? 'checked' : '' ?><?= $alimAutreDis ?>><span><?= e($al) ?></span></label>
                    <?php endforeach; ?>
                </div>

                <div class="grid-2" style="margin-top:12px">
                    <div>
                        <label for="aua-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="aua-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldF['variant_axis'] ?? ($alimAttrs['variant_axis'] ?? ($alimAutreCfg['axis'] ?? 'Poids')))) ?>" placeholder="<?= e(t('alim.axis_ph')) ?>" data-alim-autre-axis<?= $alimAutreDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $auaSizes = ($alimAutreCfg && !empty($alimAutreCfg['axis'])) ? (array) (config('alimentation.size_systems')[$alimAutreCfg['axis']] ?? []) : []; ?>
                <label data-alim-autre-size-label<?= $auaSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-alim-autre-size-chips<?= $auaSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($auaSizes as $sb): ?><button type="button" class="axis-chip" data-alim-autre-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
                <div class="warn-box">ℹ️ <?= e((string) config('alimentation.autre.warn_text', '')) ?></div>

                <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-alim-autre-atouts>
                    <?php $auaAll = array_values(array_unique(array_merge(alim_autre('atout_suggest'), $alimAtoutsSel)));
                    foreach ($auaAll as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $alimAtoutsSel, true) ? 'checked' : '' ?><?= $alimAutreDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="autre-atout-add">
                    <input type="text" id="aua-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-alim-autre-atout-input>
                    <button type="button" class="btn btn-ghost btn-sm" data-alim-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
                </div>
            </details>
        </div><!-- /alim nouveau rayon -->
        <?php endif; ?>

        <?php if (auto_capable($boutiqueCat)):
            $rawOldA   = $_SESSION['_old'] ?? [];
            $autoAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $autoActive = auto_is_rayon($curCol);
            $autoRayon  = $autoActive ? $curCol : (auto_rayons()[0] ?? 'Accessoires');
            $autoType   = (string) ($rawOldA['product_type'] ?? ($product['product_type'] ?? ''));
            $autoMeta   = auto_type_meta($autoRayon, $autoType);
            $autoCond   = (string) ($rawOldA['acc_condition'] ?? ($autoAttrs['condition'] ?? 'Neuf'));
            $autoElec   = $autoMeta !== null && !empty($autoMeta['elec']);
            $autoOil    = $autoMeta !== null && !empty($autoMeta['oil']);
            $autoUniversel = isset($rawOldA['universel'])
                ? ((string) $rawOldA['universel'] === '1')
                : (array_key_exists('universel', $autoAttrs) ? !empty($autoAttrs['universel']) : (($autoAttrs['compatibilite'] ?? '') === '' || ($autoAttrs['compatibilite'] ?? '') === 'Universel'));
            $autoCompat = (string) ($rawOldA['compatibilite'] ?? ($autoAttrs['compatibilite'] ?? ''));
            $autoOemRef = (string) ($rawOldA['ref_oem'] ?? ($autoAttrs['ref_oem'] ?? ''));
            $autoAtoutsSel = isset($rawOldA['atouts']) && is_array($rawOldA['atouts'])
                ? array_map('strval', $rawOldA['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $autoDis = $autoActive ? '' : ' disabled';
            // Mode PNEU : la compatibilité est la dimension composée (pas d'interrupteur universel).
            $autoIsPneus = auto_rayon_is_dimension($autoRayon);
            $autoDimVal  = auto_tyre_dimension(is_array($autoAttrs) ? $autoAttrs : []);
            $autoDot     = (string) ($rawOldA['dot'] ?? ($autoAttrs['dot'] ?? ''));
            $autoProf    = (string) ($rawOldA['profondeur_mm'] ?? ($autoAttrs['profondeur_mm'] ?? ''));
            $autoMonte   = (string) ($rawOldA['monte'] ?? ($autoAttrs['monte'] ?? ''));
            $autoPneusEn = ($autoActive && $autoIsPneus) ? '' : ' disabled';
            // « Nouveau rayon » Auto : rayon hors des rayons répertoriés.
            $autoAutreActive = !$autoActive && $curCol !== '';
            $autoAutreCfg    = auto_autre_cfg($curCol);
            $autoAutreType   = (string) ($rawOldA['product_type'] ?? ($product['product_type'] ?? ''));
            $autoAutreUniversel = isset($rawOldA['universel'])
                ? ((string) $rawOldA['universel'] === '1')
                : (array_key_exists('universel', $autoAttrs) ? !empty($autoAttrs['universel'])
                    : ($autoAutreCfg !== null ? !empty($autoAutreCfg['uni']) : true));
            $autoAutreCompat = (string) ($rawOldA['compatibilite'] ?? ($autoAttrs['compatibilite'] ?? ''));
            $autoAutreOem    = (string) ($rawOldA['ref_oem'] ?? ($autoAttrs['ref_oem'] ?? ''));
            $autoAutreElec   = isset($rawOldA['elec_on'])
                ? ((string) $rawOldA['elec_on'] === '1')
                : (!empty($autoAttrs['elec']) || ($autoAutreCfg !== null && !empty($autoAutreCfg['elec'])));
            $autoAutreGar    = (string) ($rawOldA['acc_garantie'] ?? ($autoAttrs['garantie'] ?? ''));
            $autoAutreSpecs = [];
            if (isset($rawOldA['spec_label']) && is_array($rawOldA['spec_label'])) {
                foreach ($rawOldA['spec_label'] as $i => $lb) { $autoAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldA['spec_value'][$i] ?? '')]; }
            } elseif (is_array($autoAttrs['specs'] ?? null)) {
                foreach ($autoAttrs['specs'] as $lb => $val) { $autoAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
            }
            $autoAutreDis = $autoAutreActive ? '' : ' disabled';
        ?>
        <div data-auto
             data-rayons="<?= e((string) json_encode((array) config('auto.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('auto.size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(auto_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('auto.autre_generic')) ?>"
             data-any="<?= e(t('auto.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Auto & pièces adaptatif (Accessoires…) ===== -->
        <div data-auto-root<?= $autoActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="auto-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="auto-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldA['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('auto.brand_ph')) ?>"<?= $autoDis ?>>
                </div>
                <div>
                    <label for="auto-type"><?= e(t('cuisine.f.type')) ?> <span class="req">*</span></label>
                    <select id="auto-type" name="product_type" data-pv="type" data-auto-type<?= $autoDis ?>>
                        <option value=""><?= e(t('auto.f.type_any')) ?></option>
                        <?php $autoGroups = auto_groups($autoRayon); ?>
                        <?php if ($autoGroups !== []): foreach ($autoGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (auto_types($autoRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $autoType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (auto_types($autoRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $autoType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="auto-condition"><?= e(t('cuisine.f.condition')) ?></label>
                    <select id="auto-condition" name="acc_condition" data-auto-condition<?= $autoDis ?>>
                        <?php foreach (auto_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $autoCond === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>

            <!-- Compatibilité véhicule (signature auto) — masquée en mode pneu (la dimension fait foi) -->
            <div data-auto-compat-wrap<?= $autoIsPneus ? ' hidden' : '' ?>>
                <label class="check-row" style="margin-top:14px"><input type="checkbox" name="universel" value="1" data-auto-universel <?= $autoUniversel ? 'checked' : '' ?><?= ($autoActive && !$autoIsPneus) ? '' : ' disabled' ?>><span><strong><?= e(t('auto.f.universel')) ?></strong> — <?= e(t('auto.universel_hint')) ?></span></label>
                <div data-auto-compat-box<?= $autoUniversel ? ' hidden' : '' ?> style="margin-top:10px">
                    <label for="auto-compat"><?= e(t('auto.f.compat')) ?></label>
                    <textarea id="auto-compat" name="compatibilite" rows="2" maxlength="300" placeholder="<?= e(t('auto.compat_ph')) ?>"<?= ($autoActive && !$autoIsPneus && !$autoUniversel) ? '' : ' disabled' ?>><?= e($autoCompat === 'Universel' ? '' : $autoCompat) ?></textarea>
                    <p class="hint"><?= e(t('auto.compat_hint')) ?></p>
                    <label for="auto-oem" style="margin-top:10px"><?= e(t('auto.f.oem')) ?> <span class="muted">(<?= e(t('auto.oem_opt')) ?>)</span></label>
                    <input type="text" id="auto-oem" name="ref_oem" class="mono" maxlength="60" value="<?= e($autoOemRef) ?>" placeholder="<?= e(t('auto.oem_ph')) ?>"<?= ($autoActive && !$autoIsPneus && !$autoUniversel) ? '' : ' disabled' ?>>
                    <p class="hint">🔎 <?= e(t('auto.oem_note')) ?></p>
                </div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-auto-hint><?= e($autoMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-auto-attrs>
                    <?php if ($autoMeta): foreach ((array) ($autoMeta['fields'] ?? []) as $fk): $fd = auto_fields($autoRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($autoAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $autoDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="notice notice-warning" data-auto-elec-note<?= $autoElec ? '' : ' hidden' ?>><p>⚡ <?= e(t('auto.elec_note')) ?></p></div>
                <div class="notice notice-warning" data-auto-oil-note<?= $autoOil ? '' : ' hidden' ?>><p>🛢️ <?= e(t('auto.oil_note')) ?></p></div>

                <!-- Mode PNEU : dimension composée + DOT / gomme / monte (toujours rendu, affiché selon le rayon) -->
                <div data-auto-pneus-wrap<?= $autoIsPneus ? '' : ' hidden' ?>>
                    <div class="notice notice-info" data-auto-dim><p><strong><?= e(t('auto.dim_label')) ?> :</strong> <span class="mono" data-auto-dim-val data-empty="<?= e(t('auto.dim_empty')) ?>"><?= e($autoDimVal !== '' ? $autoDimVal : t('auto.dim_empty')) ?></span></p></div>
                    <div class="grid-2" style="margin-top:12px">
                        <div>
                            <label for="auto-dot"><?= e(t('auto.f.dot')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="text" id="auto-dot" name="dot" class="mono" maxlength="20" value="<?= e($autoDot) ?>" placeholder="<?= e(t('auto.dot_ph')) ?>"<?= $autoPneusEn ?>>
                        </div>
                        <div>
                            <label for="auto-prof"><?= e(t('auto.f.profondeur')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="number" id="auto-prof" name="profondeur_mm" min="0" step="0.5" max="30" value="<?= e($autoProf) ?>" placeholder="6.5"<?= $autoPneusEn ?>>
                        </div>
                    </div>
                    <div class="notice notice-warning" data-auto-occasion-note<?= ($autoIsPneus && $autoCond === 'Occasion') ? '' : ' hidden' ?>><p>⚠️ <?= e(t('auto.tyre_used_note')) ?></p></div>
                    <div style="margin-top:12px">
                        <label for="auto-monte"><?= e(t('auto.f.monte')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="auto-monte" name="monte" maxlength="120" value="<?= e($autoMonte) ?>" placeholder="<?= e(t('auto.monte_ph')) ?>"<?= $autoPneusEn ?>>
                        <p class="hint"><?= e(t('auto.monte_hint')) ?></p>
                    </div>
                </div>

                <div class="grid-2" style="margin-top:12px">
                    <div>
                        <label for="auto-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="auto-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldA['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="ACC-001"<?= $autoDis ?>>
                    </div>
                    <div>
                        <label for="auto-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="auto-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldA['variant_axis'] ?? ($autoAttrs['variant_axis'] ?? ($autoMeta['axis'] ?? '')))) ?>" placeholder="<?= e(t('auto.axis_ph')) ?>" data-auto-axis<?= $autoDis ?>>
                    </div>
                </div>
                <?php $autoSizes = ($autoMeta && !empty($autoMeta['axis'])) ? (array) (config('auto.size_systems')[$autoMeta['axis']] ?? []) : []; ?>
                <label data-auto-size-label<?= $autoSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-auto-size-chips<?= $autoSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($autoSizes as $sb): ?><button type="button" class="axis-chip" data-auto-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-auto-atouts>
                    <?php foreach (auto_atouts($autoRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $autoAtoutsSel, true) ? 'checked' : '' ?><?= $autoDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div><!-- /auto adaptatif -->

        <!-- ===== Auto : NOUVEAU RAYON (adaptatif au slug) ===== -->
        <div data-auto-autre-root<?= $autoAutreActive ? '' : ' hidden' ?>>
            <p class="hint" data-auto-autre-hint><?= $autoAutreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('auto.autre_generic')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="aua2-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua2-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldA['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('auto.brand_ph')) ?>"<?= $autoAutreDis ?>>
                </div>
                <div>
                    <label for="aua2-type"><?= e(t('cuisine.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua2-type" name="product_type" data-pv="type" maxlength="60" value="<?= e($autoAutreType) ?>" placeholder="<?= e(t('auto.autre_type_ph')) ?>"<?= $autoAutreDis ?>>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="aua2-condition"><?= e(t('cuisine.f.condition')) ?></label>
                    <select id="aua2-condition" name="acc_condition"<?= $autoAutreDis ?>>
                        <?php $aua2Cond = (string) ($rawOldA['acc_condition'] ?? ($autoAttrs['condition'] ?? 'Neuf')); foreach (auto_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $aua2Cond === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>
            <label style="margin-top:10px"><?= e(t('autre.rayon_suggest')) ?></label>
            <div class="chips-row" data-auto-autre-rayon-chips>
                <?php foreach (auto_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-auto-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
            </div>

            <!-- compatibilité véhicule -->
            <label class="check-row" style="margin-top:14px"><input type="checkbox" name="universel" value="1" data-auto-autre-universel <?= $autoAutreUniversel ? 'checked' : '' ?><?= $autoAutreDis ?>><span><strong><?= e(t('auto.f.universel')) ?></strong> — <?= e(t('auto.universel_hint')) ?></span></label>
            <div data-auto-autre-compat-box<?= $autoAutreUniversel ? ' hidden' : '' ?> style="margin-top:10px">
                <label for="aua2-compat"><?= e(t('auto.f.compat')) ?></label>
                <textarea id="aua2-compat" name="compatibilite" rows="2" maxlength="300" placeholder="<?= e(t('auto.compat_ph')) ?>"<?= ($autoAutreActive && !$autoAutreUniversel) ? '' : ' disabled' ?>><?= e($autoAutreCompat === 'Universel' ? '' : $autoAutreCompat) ?></textarea>
                <label for="aua2-oem" style="margin-top:10px"><?= e(t('auto.f.oem')) ?> <span class="muted">(<?= e(t('auto.oem_opt')) ?>)</span></label>
                <input type="text" id="aua2-oem" name="ref_oem" class="mono" maxlength="60" value="<?= e($autoAutreOem) ?>" placeholder="<?= e(t('auto.oem_ph')) ?>"<?= ($autoAutreActive && !$autoAutreUniversel) ? '' : ' disabled' ?>>
                <p class="hint">🔎 <?= e(t('auto.oem_note')) ?></p>
            </div>

            <details class="variants-box" open>
                <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
                <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
                <div class="axis-suggest" data-auto-autre-spec-box>
                    <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                    <div class="axis-suggest-chips" data-auto-autre-spec-chips>
                        <?php foreach (($autoAutreCfg['specs'] ?? auto_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-auto-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="spec-rows" data-auto-autre-specs>
                    <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                    <?php foreach ($autoAutreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                        <div class="spec-row">
                            <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>"<?= $autoAutreDis ?>>
                            <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>"<?= $autoAutreDis ?>>
                            <button type="button" class="variant-del" data-auto-autre-spec-del aria-label="✕">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-auto-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
                <template id="auto-autre-spec-template">
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-auto-autre-spec-del aria-label="✕">✕</button>
                    </div>
                </template>

                <label class="check-row" style="margin-top:14px"><input type="checkbox" name="elec_on" value="1" data-auto-autre-elec-toggle <?= $autoAutreElec ? 'checked' : '' ?><?= $autoAutreDis ?>><span><?= e(t('cuisine.autre_elec_q')) ?></span></label>
                <div data-auto-autre-elec-box<?= $autoAutreElec ? '' : ' hidden' ?> style="margin-top:10px">
                    <label for="aua2-garantie"><?= e(t('cuisine.f.warranty')) ?></label>
                    <select id="aua2-garantie" name="acc_garantie"<?= ($autoAutreActive && $autoAutreElec) ? '' : ' disabled' ?>>
                        <option value=""><?= e(t('cuisine.f.warranty_none')) ?></option>
                        <?php foreach (['3 mois', '6 mois', '1 an', '2 ans'] as $g): ?><option value="<?= e($g) ?>" <?= $autoAutreGar === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="notice notice-warning" data-auto-autre-elec-warn<?= $autoAutreElec ? '' : ' hidden' ?>><p>⚡ <?= e(t('cuisine.elec_warn')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="aua2-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="aua2-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldA['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="AUTO-001"<?= $autoAutreDis ?>>
                    </div>
                    <div>
                        <label for="aua2-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="aua2-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldA['variant_axis'] ?? ($autoAttrs['variant_axis'] ?? ($autoAutreCfg['axis'] ?? '')))) ?>" placeholder="<?= e(t('auto.axis_ph')) ?>" data-auto-autre-axis<?= $autoAutreDis ?>>
                    </div>
                </div>
                <div class="warn-box">ℹ️ <?= e((string) config('auto.autre.warn_text', '')) ?></div>

                <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-auto-autre-atouts>
                    <?php $aua2All = array_values(array_unique(array_merge(auto_autre('atout_suggest'), $autoAtoutsSel)));
                    foreach ($aua2All as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $autoAtoutsSel, true) ? 'checked' : '' ?><?= $autoAutreDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="autre-atout-add">
                    <input type="text" id="aua2-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-auto-autre-atout-input>
                    <button type="button" class="btn btn-ghost btn-sm" data-auto-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
                </div>
            </details>
        </div><!-- /auto nouveau rayon -->
        <?php endif; ?>

        <?php if (arti_capable($boutiqueCat)):
            $rawOldR   = $_SESSION['_old'] ?? [];
            $artiAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $artiActive = arti_is_rayon($curCol);
            $artiRayon  = $artiActive ? $curCol : (arti_rayons()[0] ?? 'Bijoux');
            $artiType   = (string) ($rawOldR['product_type'] ?? ($product['product_type'] ?? ''));
            $artiMeta   = arti_type_meta($artiRayon, $artiType);
            $artiCond   = (string) ($rawOldR['acc_condition'] ?? ($artiAttrs['condition'] ?? 'Neuf'));
            $artiFaitMain = isset($rawOldR['fait_main']) ? ((string) $rawOldR['fait_main'] === '1')
                : (array_key_exists('fait_main', $artiAttrs) ? !empty($artiAttrs['fait_main']) : true);
            $artiUnique = isset($rawOldR['piece_unique']) ? ((string) $rawOldR['piece_unique'] === '1') : !empty($artiAttrs['piece_unique']);
            $artiHistoire = (string) ($rawOldR['histoire'] ?? ($artiAttrs['histoire'] ?? ''));
            $artiElec = isset($rawOldR['elec_on']) ? ((string) $rawOldR['elec_on'] === '1')
                : (!empty($artiAttrs['elec']) || ($artiMeta !== null && !empty($artiMeta['elec'])));
            $artiGar = (string) ($rawOldR['acc_garantie'] ?? ($artiAttrs['garantie'] ?? ''));
            // Le bloc « électrique » n'apparaît que pour les rayons ayant des types électriques (ex. luminaires).
            $artiRayonElec = false;
            foreach (arti_types($artiRayon) as $artiTm) { if (!empty($artiTm['elec'])) { $artiRayonElec = true; break; } }
            $artiElecEn = ($artiActive && $artiRayonElec) ? '' : ' disabled';
            // Contact alimentaire (poterie) : alerte si le type est alimentaire OU l'usage l'est.
            $artiFoodUsages = (array) config('artisanat.food_usages', []);
            $artiFoodLikely = ($artiMeta !== null && !empty($artiMeta['food'])) || in_array((string) ($artiAttrs['usage'] ?? ''), $artiFoodUsages, true);
            $artiFoodSafe = isset($rawOldR['contact_alimentaire']) ? ((string) $rawOldR['contact_alimentaire'] === '1') : !empty($artiAttrs['contact_alimentaire']);
            // Mode de vente (textile) : 'metre' (au mètre / coupon) ou 'confection' (pièce finie).
            $artiMode = (string) ($artiMeta['mode'] ?? '');
            $artiAtoutsSel = isset($rawOldR['atouts']) && is_array($rawOldR['atouts'])
                ? array_map('strval', $rawOldR['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $artiDis = $artiActive ? '' : ' disabled';
            // « Nouveau rayon » Artisanat : rayon hors des rayons répertoriés.
            $artiAutreActive = !$artiActive && $curCol !== '';
            $artiAutreCfg    = arti_autre_cfg($curCol);
            $artiAutreType   = (string) ($rawOldR['product_type'] ?? ($product['product_type'] ?? ''));
            $artiAutreFaitMain = isset($rawOldR['fait_main']) ? ((string) $rawOldR['fait_main'] === '1')
                : (array_key_exists('fait_main', $artiAttrs) ? !empty($artiAttrs['fait_main']) : true);
            $artiAutreUnique = isset($rawOldR['piece_unique']) ? ((string) $rawOldR['piece_unique'] === '1')
                : (array_key_exists('piece_unique', $artiAttrs) ? !empty($artiAttrs['piece_unique']) : ($artiAutreCfg !== null && !empty($artiAutreCfg['unique'])));
            $artiAutreMetre = isset($rawOldR['metre_on']) ? ((string) $rawOldR['metre_on'] === '1')
                : (($artiAttrs['sale_mode'] ?? '') === 'metre' || ($artiAutreCfg !== null && ($artiAutreCfg['mode'] ?? '') === 'metre'));
            $artiAutreHistoire = (string) ($rawOldR['histoire'] ?? ($artiAttrs['histoire'] ?? ''));
            $artiAutreSpecs = [];
            if (isset($rawOldR['spec_label']) && is_array($rawOldR['spec_label'])) {
                foreach ($rawOldR['spec_label'] as $i => $lb) { $artiAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldR['spec_value'][$i] ?? '')]; }
            } elseif (is_array($artiAttrs['specs'] ?? null)) {
                foreach ($artiAttrs['specs'] as $lb => $val) { $artiAutreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
            }
            $artiAutreDis = $artiAutreActive ? '' : ' disabled';
        ?>
        <div data-arti
             data-rayons="<?= e((string) json_encode((array) config('artisanat.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('artisanat.size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(arti_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('arti.autre_generic')) ?>"
             data-food-usages="<?= e((string) json_encode((array) config('artisanat.food_usages', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-mode-metre="<?= e(t('arti.mode_metre')) ?>" data-mode-confection="<?= e(t('arti.mode_confection')) ?>"
             data-any="<?= e(t('arti.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Artisanat & Art adaptatif (Bijoux…) ===== -->
        <div data-arti-root<?= $artiActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="arti-brand"><?= e(t('arti.f.artisan')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="arti-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldR['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('arti.artisan_ph')) ?>"<?= $artiDis ?>>
                </div>
                <div>
                    <label for="arti-type"><?= e(t('cuisine.f.type')) ?> <span class="req">*</span></label>
                    <select id="arti-type" name="product_type" data-pv="type" data-arti-type<?= $artiDis ?>>
                        <option value=""><?= e(t('arti.f.type_any')) ?></option>
                        <?php $artiGroups = arti_groups($artiRayon); ?>
                        <?php if ($artiGroups !== []): foreach ($artiGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (arti_types($artiRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $artiType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (arti_types($artiRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $artiType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="arti-condition"><?= e(t('cuisine.f.condition')) ?></label>
                    <select id="arti-condition" name="acc_condition"<?= $artiDis ?>>
                        <?php foreach (arti_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $artiCond === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>
            <p data-arti-mode-pill-wrap<?= $artiMeta ? '' : ' hidden' ?> style="margin-top:12px"><span class="chip" data-arti-mode-pill><?= e($artiMode === 'metre' ? t('arti.mode_metre') : ($artiMode === 'confection' ? t('arti.mode_confection') : '')) ?></span></p>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-arti-hint><?= e($artiMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-arti-attrs>
                    <?php if ($artiMeta): foreach ((array) ($artiMeta['fields'] ?? []) as $fk): $fd = arti_fields($artiRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($artiAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $artiDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="notice notice-info" data-arti-metre-note<?= $artiMode === 'metre' ? '' : ' hidden' ?>><p>✂️ <?= e(t('arti.metre_note')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <label class="check-row"><input type="checkbox" name="fait_main" value="1" data-arti-faitmain <?= $artiFaitMain ? 'checked' : '' ?><?= $artiDis ?>><span><strong><?= e(t('arti.f.faitmain')) ?></strong> — <?= e(t('arti.faitmain_hint')) ?></span></label>
                    <label class="check-row"><input type="checkbox" name="piece_unique" value="1" data-arti-unique <?= $artiUnique ? 'checked' : '' ?><?= $artiDis ?>><span><strong><?= e(t('arti.f.unique')) ?></strong> — <?= e(t('arti.unique_hint')) ?></span></label>
                </div>
                <div class="notice notice-info" data-arti-unique-note<?= $artiUnique ? '' : ' hidden' ?>><p>✨ <?= e(t('arti.unique_note')) ?></p></div>

                <!-- Objet électrique (luminaires) → garantie + rappel CE. Visible seulement si le rayon a des types électriques. -->
                <div data-arti-elec-wrap<?= ($artiActive && $artiRayonElec) ? '' : ' hidden' ?>>
                    <label class="check-row" style="margin-top:14px"><input type="checkbox" name="elec_on" value="1" data-arti-elec-toggle <?= $artiElec ? 'checked' : '' ?><?= $artiElecEn ?>><span><?= e(t('arti.elec_q')) ?></span></label>
                    <div data-arti-elec-box<?= ($artiElec && $artiRayonElec) ? '' : ' hidden' ?> style="margin-top:10px">
                        <label for="arti-garantie"><?= e(t('cuisine.f.warranty')) ?></label>
                        <select id="arti-garantie" name="acc_garantie"<?= ($artiActive && $artiRayonElec && $artiElec) ? '' : ' disabled' ?>>
                            <option value=""><?= e(t('cuisine.f.warranty_none')) ?></option>
                            <?php foreach (['3 mois', '6 mois', '1 an', '2 ans'] as $g): ?><option value="<?= e($g) ?>" <?= $artiGar === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="notice notice-warning" data-arti-elec-warn<?= ($artiElec && $artiRayonElec) ? '' : ' hidden' ?>><p>⚡ <?= e(t('cuisine.elec_warn')) ?></p></div>
                </div>

                <!-- Contact alimentaire (poterie) : visible si le type ou l'usage est alimentaire -->
                <div data-arti-food-wrap<?= ($artiActive && $artiFoodLikely) ? '' : ' hidden' ?>>
                    <label class="check-row" style="margin-top:14px"><input type="checkbox" name="contact_alimentaire" value="1" data-arti-food-toggle <?= $artiFoodSafe ? 'checked' : '' ?><?= ($artiActive && $artiFoodLikely) ? '' : ' disabled' ?>><span><?= e(t('arti.food_q')) ?></span></label>
                    <div class="notice notice-warning"><p>🍽️ <?= e(t('arti.food_note')) ?></p></div>
                </div>

                <div class="notice notice-warning"><p>🛡️ <?= e(t('arti.cites_note')) ?></p></div>

                <label for="arti-histoire" style="margin-top:14px"><?= e(t('arti.f.histoire')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <textarea id="arti-histoire" name="histoire" maxlength="2000" rows="3" placeholder="<?= e(t('arti.histoire_ph')) ?>"<?= $artiDis ?>><?= e($artiHistoire) ?></textarea>
                <p class="hint"><?= e(t('arti.histoire_hint')) ?></p>

                <div class="grid-2" style="margin-top:12px">
                    <div>
                        <label for="arti-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="arti-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldR['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="BIJ-001"<?= $artiDis ?>>
                    </div>
                    <div>
                        <label for="arti-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="arti-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldR['variant_axis'] ?? ($artiAttrs['variant_axis'] ?? ($artiMeta['axis'] ?? '')))) ?>" placeholder="<?= e(t('arti.axis_ph')) ?>" data-arti-axis<?= $artiDis ?>>
                    </div>
                </div>
                <?php $artiSizes = ($artiMeta && !empty($artiMeta['axis'])) ? (array) (config('artisanat.size_systems')[$artiMeta['axis']] ?? []) : []; ?>
                <label data-arti-size-label<?= $artiSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-arti-size-chips<?= $artiSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($artiSizes as $sb): ?><button type="button" class="axis-chip" data-arti-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-arti-atouts>
                    <?php foreach (arti_atouts($artiRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $artiAtoutsSel, true) ? 'checked' : '' ?><?= $artiDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div><!-- /arti adaptatif -->

        <!-- ===== Artisanat : NOUVEAU RAYON (adaptatif au slug) ===== -->
        <div data-arti-autre-root<?= $artiAutreActive ? '' : ' hidden' ?>>
            <p class="hint" data-arti-autre-hint><?= $artiAutreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('arti.autre_generic')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="aua3-brand"><?= e(t('arti.f.artisan')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua3-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldR['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('arti.artisan_ph')) ?>"<?= $artiAutreDis ?>>
                </div>
                <div>
                    <label for="aua3-type"><?= e(t('cuisine.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="aua3-type" name="product_type" data-pv="type" maxlength="60" value="<?= e($artiAutreType) ?>" placeholder="<?= e(t('arti.autre_type_ph')) ?>"<?= $artiAutreDis ?>>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="aua3-condition"><?= e(t('cuisine.f.condition')) ?></label>
                    <select id="aua3-condition" name="acc_condition"<?= $artiAutreDis ?>>
                        <?php $aua3Cond = (string) ($rawOldR['acc_condition'] ?? ($artiAttrs['condition'] ?? 'Neuf')); foreach (arti_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $aua3Cond === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>
            <label style="margin-top:10px"><?= e(t('autre.rayon_suggest')) ?></label>
            <div class="chips-row" data-arti-autre-rayon-chips>
                <?php foreach (arti_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-arti-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
            </div>

            <details class="variants-box" open>
                <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
                <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
                <div class="axis-suggest" data-arti-autre-spec-box>
                    <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                    <div class="axis-suggest-chips" data-arti-autre-spec-chips>
                        <?php foreach (($artiAutreCfg['specs'] ?? arti_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-arti-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="spec-rows" data-arti-autre-specs>
                    <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                    <?php foreach ($artiAutreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                        <div class="spec-row">
                            <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>"<?= $artiAutreDis ?>>
                            <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>"<?= $artiAutreDis ?>>
                            <button type="button" class="variant-del" data-arti-autre-spec-del aria-label="✕">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-arti-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
                <template id="arti-autre-spec-template">
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-arti-autre-spec-del aria-label="✕">✕</button>
                    </div>
                </template>

                <div class="grid-2" style="margin-top:14px">
                    <label class="check-row"><input type="checkbox" name="fait_main" value="1" data-arti-autre-faitmain <?= $artiAutreFaitMain ? 'checked' : '' ?><?= $artiAutreDis ?>><span><strong><?= e(t('arti.f.faitmain')) ?></strong> — <?= e(t('arti.faitmain_hint')) ?></span></label>
                    <label class="check-row"><input type="checkbox" name="piece_unique" value="1" data-arti-autre-unique <?= $artiAutreUnique ? 'checked' : '' ?><?= $artiAutreDis ?>><span><strong><?= e(t('arti.f.unique')) ?></strong> — <?= e(t('arti.unique_hint')) ?></span></label>
                </div>
                <div class="notice notice-info" data-arti-autre-unique-note<?= $artiAutreUnique ? '' : ' hidden' ?>><p>✨ <?= e(t('arti.unique_note')) ?></p></div>

                <label class="check-row" style="margin-top:14px"><input type="checkbox" name="metre_on" value="1" data-arti-autre-metre <?= $artiAutreMetre ? 'checked' : '' ?><?= $artiAutreDis ?>><span><?= e(t('arti.metre_q')) ?></span></label>
                <p data-arti-autre-mode-pill-wrap<?= $artiAutreMetre ? '' : ' hidden' ?> style="margin-top:8px"><span class="chip" data-arti-autre-mode-pill><?= e(t('arti.mode_metre')) ?></span></p>

                <div class="notice notice-warning"><p>🛡️ <?= e(t('arti.cites_note')) ?></p></div>

                <label for="aua3-histoire" style="margin-top:14px"><?= e(t('arti.f.histoire')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <textarea id="aua3-histoire" name="histoire" maxlength="2000" rows="3" placeholder="<?= e(t('arti.histoire_ph')) ?>"<?= $artiAutreDis ?>><?= e($artiAutreHistoire) ?></textarea>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="aua3-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="aua3-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldR['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="ART-001"<?= $artiAutreDis ?>>
                    </div>
                    <div>
                        <label for="aua3-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="aua3-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldR['variant_axis'] ?? ($artiAttrs['variant_axis'] ?? ($artiAutreCfg['axis'] ?? '')))) ?>" placeholder="<?= e(t('arti.axis_ph')) ?>" data-arti-autre-axis<?= $artiAutreDis ?>>
                    </div>
                </div>
                <?php $aua3Sizes = ($artiAutreCfg && !empty($artiAutreCfg['axis'])) ? (array) (config('artisanat.size_systems')[$artiAutreCfg['axis']] ?? []) : []; ?>
                <label data-arti-autre-size-label<?= $aua3Sizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-arti-autre-size-chips<?= $aua3Sizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($aua3Sizes as $sb): ?><button type="button" class="axis-chip" data-arti-autre-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-arti-autre-atouts>
                    <?php $aua3All = array_values(array_unique(array_merge(arti_autre('atout_suggest'), $artiAtoutsSel)));
                    foreach ($aua3All as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $artiAtoutsSel, true) ? 'checked' : '' ?><?= $artiAutreDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="autre-atout-add">
                    <input type="text" id="aua3-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-arti-autre-atout-input>
                    <button type="button" class="btn btn-ghost btn-sm" data-arti-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
                </div>
            </details>
        </div><!-- /arti nouveau rayon -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldB   = $_SESSION['_old'] ?? [];
            $bebeAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $bebeActive = bebe_is_rayon($curCol);
            $bebeRayon  = $bebeActive ? $curCol : (bebe_rayons()[0] ?? 'Alimentation');
            $bebeType   = (string) ($rawOldB['product_type'] ?? ($product['product_type'] ?? ''));
            $bebeMeta   = bebe_type_meta($bebeRayon, $bebeType);
            $bebeAgeFix = (string) ($bebeMeta['age_fix'] ?? '');
            $bebeAge    = $bebeAgeFix !== '' ? $bebeAgeFix : (string) ($rawOldB['age_min'] ?? ($bebeAttrs['age_min'] ?? ''));
            $bebeConserv = (string) ($rawOldB['conservation'] ?? ($bebeAttrs['conservation'] ?? ($bebeMeta['conserv'] ?? 'Ambiante')));
            $bebeDlc     = (string) ($rawOldB['dlc_type'] ?? ($bebeAttrs['dlc_type'] ?? ''));
            $bebeDate    = (string) ($rawOldB['date_limite'] ?? ($bebeAttrs['date_limite'] ?? ''));
            $bebeAtoutsSel = isset($rawOldB['atouts']) && is_array($rawOldB['atouts'])
                ? array_map('strval', $rawOldB['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $bebeAllergSel = isset($rawOldB['allergenes']) && is_array($rawOldB['allergenes'])
                ? array_map('strval', $rawOldB['allergenes'])
                : array_map('strval', (array) ($bebeAttrs['allergenes'] ?? []));
            $bebeRegimeSel = isset($rawOldB['regime']) && is_array($rawOldB['regime'])
                ? array_map('strval', $rawOldB['regime'])
                : array_map('strval', (array) ($bebeAttrs['regime'] ?? []));
            $bebeFields    = (array) ($bebeMeta['fields'] ?? []);
            $bebeShowAllerg = in_array('allerg', $bebeFields, true);
            $bebeShowRegime = in_array('regime', $bebeFields, true);
            $bebeCold = $bebeConserv !== '' && $bebeConserv !== 'Ambiante';
            $bebeF1   = $bebeMeta !== null && !empty($bebeMeta['formula1']);
            $bebeForm = $bebeMeta !== null && !empty($bebeMeta['formula']) && !$bebeF1;
            $bebeCompl = $bebeMeta !== null && !empty($bebeMeta['complement']);
            $bebeDis  = $bebeActive ? '' : ' disabled';
        ?>
        <div data-bebe
             data-rayons="<?= e((string) json_encode((array) config('bebe.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-any="<?= e(t('bebe.f.type_any')) ?>" data-ambient="Ambiante"
             data-promo-lock="<?= e(t('bebe.promo_lock')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Bébé & Enfant · Alimentation (réglementé) ===== -->
        <div data-bebe-root<?= $bebeActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="bebe-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="bebe-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldB['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('bebe.brand_ph')) ?>"<?= $bebeDis ?>>
                </div>
                <div>
                    <label for="bebe-type"><?= e(t('bebe.f.type')) ?> <span class="req">*</span></label>
                    <select id="bebe-type" name="product_type" data-pv="type" data-bebe-type<?= $bebeDis ?>>
                        <option value=""><?= e(t('bebe.f.type_any')) ?></option>
                        <?php $bebeGroups = bebe_groups($bebeRayon); ?>
                        <?php if ($bebeGroups !== []): foreach ($bebeGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (bebe_types($bebeRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $bebeType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (bebe_types($bebeRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $bebeType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-bebe-hint><?= e($bebeMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>

                <div class="grid-2">
                    <div>
                        <label for="bebe-age"><?= e(t('bebe.f.age')) ?> <span class="req">*</span></label>
                        <div class="static-val" data-bebe-age-fix<?= $bebeAgeFix === '' ? ' hidden' : '' ?>><?= e($bebeAgeFix) ?></div>
                        <select id="bebe-age" name="age_min" data-bebe-age<?= $bebeAgeFix !== '' ? ' hidden' : '' ?><?= $bebeDis ?>>
                            <option value="">—</option>
                            <?php foreach (bebe_ages() as $ag): ?><option value="<?= e($ag) ?>" <?= $bebeAge === $ag ? 'selected' : '' ?>><?= e($ag) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div></div>
                </div>

                <div class="attrs grid-2" data-bebe-attrs>
                    <?php if ($bebeMeta): foreach ($bebeFields as $fk): $fd = bebe_fields($bebeRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($bebeAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $bebeDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="grid-2">
                    <div>
                        <label for="bebe-conserv"><?= e(t('bebe.f.conservation')) ?></label>
                        <select id="bebe-conserv" name="conservation" data-bebe-conserv<?= $bebeDis ?>>
                            <?php foreach (bebe_conservations() as $cs): ?><option value="<?= e($cs) ?>" <?= $bebeConserv === $cs ? 'selected' : '' ?>><?= e($cs) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="bebe-dlc"><?= e(t('bebe.f.dlc_type')) ?></label>
                        <select id="bebe-dlc" name="dlc_type" data-bebe-dlc<?= $bebeDis ?>>
                            <?php foreach (bebe_dlc_types() as $d): ?><option value="<?= e($d) ?>" <?= $bebeDlc === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="bebe-date"><?= e(t('bebe.f.date_limite')) ?> <span class="req">*</span></label>
                        <input type="date" id="bebe-date" name="date_limite" value="<?= e($bebeDate) ?>"<?= $bebeDis ?>>
                        <?php if (has_error('date_limite')): ?><p class="field-error"><?= e(error('date_limite')) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label for="bebe-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="bebe-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldB['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="BB-001"<?= $bebeDis ?>>
                    </div>
                </div>
                <div class="notice notice-info" data-bebe-cold-note<?= $bebeCold ? '' : ' hidden' ?>><p>❄️ <?= e(t('bebe.cold_note')) ?></p></div>

                <div class="notice notice-warning" data-bebe-note-formula1<?= $bebeF1 ? '' : ' hidden' ?>><p>🔒 <?= e(t('bebe.formula1_note')) ?></p></div>
                <div class="notice notice-warning" data-bebe-note-formula<?= $bebeForm ? '' : ' hidden' ?>><p>⚖️ <?= e(t('bebe.formula_note')) ?></p></div>
                <div class="notice notice-warning" data-bebe-note-complement<?= $bebeCompl ? '' : ' hidden' ?>><p>⚖️ <?= e(t('bebe.complement_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-note-baby<?= $bebeMeta ? '' : ' hidden' ?>><p>🍼 <?= e(t('bebe.baby_note')) ?></p></div>

                <div data-bebe-allerg-wrap<?= $bebeShowAllerg ? '' : ' hidden' ?>>
                    <label style="margin-top:12px"><?= e(t('bebe.f.allergenes')) ?> <span class="muted">(<?= e(t('bebe.f.allergenes_opt')) ?>)</span></label>
                    <div class="chip-checks" data-bebe-allergenes>
                        <?php foreach (bebe_allergenes() as $al): ?>
                            <label class="chip-check chip-check--health"><input type="checkbox" name="allergenes[]" value="<?= e($al) ?>" <?= in_array($al, $bebeAllergSel, true) ? 'checked' : '' ?><?= $bebeShowAllerg ? $bebeDis : ' disabled' ?>><span><?= e($al) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <span class="hint"><?= e(t('bebe.allergenes_hint')) ?></span>
                </div>

                <div data-bebe-regime-wrap<?= $bebeShowRegime ? '' : ' hidden' ?>>
                    <label style="margin-top:12px"><?= e(t('bebe.f.regime')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <div class="chip-checks" data-bebe-regime>
                        <?php foreach (bebe_regimes() as $rg): ?>
                            <label class="chip-check"><input type="checkbox" name="regime[]" value="<?= e($rg) ?>" <?= in_array($rg, $bebeRegimeSel, true) ? 'checked' : '' ?><?= $bebeShowRegime ? $bebeDis : ' disabled' ?>><span><?= e($rg) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid-2" style="margin-top:12px">
                    <div>
                        <label for="bebe-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="bebe-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldB['variant_axis'] ?? ($bebeAttrs['variant_axis'] ?? ($bebeMeta['axis'] ?? 'Lot')))) ?>" placeholder="<?= e(t('bebe.axis_ph')) ?>" data-bebe-axis<?= $bebeDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $bebeSizes = ($bebeMeta && !empty($bebeMeta['axis'])) ? (array) (config('bebe.size_systems')[$bebeMeta['axis']] ?? []) : []; ?>
                <label data-bebe-size-label<?= $bebeSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-size-chips<?= $bebeSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($bebeSizes as $sb): ?><button type="button" class="axis-chip" data-bebe-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-atouts>
                    <?php foreach (bebe_atouts($bebeRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $bebeAtoutsSel, true) ? 'checked' : '' ?><?= $bebeDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div><!-- /bébé alimentation -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldT   = $_SESSION['_old'] ?? [];
            $toyAttrs  = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $toyActive = bebe_toy_is_rayon($curCol);
            $toyRayon  = $toyActive ? $curCol : (bebe_toy_rayons()[0] ?? 'Jouets');
            $toyType   = (string) ($rawOldT['product_type'] ?? ($product['product_type'] ?? ''));
            $toyMeta   = bebe_toy_type_meta($toyRayon, $toyType);
            $toyCond   = (string) ($rawOldT['acc_condition'] ?? ($toyAttrs['condition'] ?? 'Neuf'));
            $toyAge    = (string) ($rawOldT['age_min'] ?? ($toyAttrs['age_min'] ?? ''));
            $toyAgeFix = $toyMeta !== null && !empty($toyMeta['age_fix']);
            $toyAgeOpts = $toyAgeFix ? bebe_toy_ages_under3() : bebe_toy_ages();
            $toyUnder3 = bebe_toy_is_under3($toyAge);
            $toyCE     = isset($rawOldT['ce']) ? ((string) $rawOldT['ce'] === '1') : (array_key_exists('ce', $toyAttrs) ? !empty($toyAttrs['ce']) : false);
            $toySmall  = isset($rawOldT['small_parts']) ? ((string) $rawOldT['small_parts'] === '1') : !empty($toyAttrs['small_parts']);
            $toyPiles  = (string) ($toyAttrs['piles'] ?? '');
            $toyAtoutsSel = isset($rawOldT['atouts']) && is_array($rawOldT['atouts'])
                ? array_map('strval', $rawOldT['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $toyDis = $toyActive ? '' : ' disabled';
            $toyShowCE    = !$toyCE;                  // note « CE obligatoire » si non coché
            $toyConflict  = $toyUnder3 && $toySmall;  // incohérence âge / petites pièces
            $toyShow3     = !$toyUnder3 && $toySmall;  // mention obligatoire 3 ans+
            $toyShowPiles = $toyPiles !== '' && $toyPiles !== 'Sans pile';
        ?>
        <div data-bebe-toy
             data-rayons="<?= e((string) json_encode((array) config('bebe.toys', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.toy_size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-ages="<?= e((string) json_encode(bebe_toy_ages(), JSON_UNESCAPED_UNICODE)) ?>"
             data-ages-under3="<?= e((string) json_encode(bebe_toy_ages_under3(), JSON_UNESCAPED_UNICODE)) ?>"
             data-age-default="Dès 6 mois"
             data-any="<?= e(t('bebe.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Bébé & Enfant · Jouets (sécurité enfant) ===== -->
        <div data-bebe-toy-root<?= $toyActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="toy-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="toy-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldT['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="ex. Lego, Vtech, sans marque…"<?= $toyDis ?>>
                </div>
                <div>
                    <label for="toy-type"><?= e(t('bebe.f.type')) ?> <span class="req">*</span></label>
                    <select id="toy-type" name="product_type" data-pv="type" data-bebe-toy-type<?= $toyDis ?>>
                        <option value=""><?= e(t('bebe.f.type_any')) ?></option>
                        <?php $toyGroups = bebe_toy_groups($toyRayon); ?>
                        <?php if ($toyGroups !== []): foreach ($toyGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (bebe_toy_types($toyRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $toyType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (bebe_toy_types($toyRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $toyType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="toy-cond"><?= e(t('bebe.toy.f.condition')) ?></label>
                    <select id="toy-cond" name="acc_condition"<?= $toyDis ?>>
                        <?php foreach (bebe_conditions() as $cc): ?><option value="<?= e($cc) ?>" <?= $toyCond === $cc ? 'selected' : '' ?>><?= e($cc) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-bebe-toy-hint><?= e($toyMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>

                <div class="grid-2">
                    <div>
                        <label for="toy-age"><?= e(t('bebe.toy.f.age')) ?> <span class="req">*</span></label>
                        <select id="toy-age" name="age_min" data-bebe-toy-age<?= $toyDis ?>>
                            <option value="">—</option>
                            <?php foreach ($toyAgeOpts as $ag): ?><option value="<?= e($ag) ?>" <?= $toyAge === $ag ? 'selected' : '' ?>><?= e($ag) ?></option><?php endforeach; ?>
                        </select>
                        <span class="hint" data-bebe-toy-age-hint<?= $toyAgeFix ? '' : ' hidden' ?>><?= e(t('bebe.toy.age_under3_hint')) ?></span>
                        <?php if (has_error('age_min')): ?><p class="field-error"><?= e(error('age_min')) ?></p><?php endif; ?>
                    </div>
                    <div></div>
                </div>

                <div class="attrs grid-2" data-bebe-toy-attrs>
                    <?php if ($toyMeta): foreach ((array) ($toyMeta['fields'] ?? []) as $fk): $fd = bebe_toy_fields($toyRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($toyAttrs[$fk] ?? ''); ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]" data-bebe-toy-field="<?= e($fk) ?>"<?= $toyDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-toy-atouts>
                    <?php foreach (bebe_toy_atouts($toyRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $toyAtoutsSel, true) ? 'checked' : '' ?><?= $toyDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="variants-box" open>
                <summary>🛡️ <?= e(t('bebe.toy.sec_safety')) ?></summary>
                <div>
                    <label class="check-row"><input type="checkbox" name="ce" value="1" data-bebe-toy-ce <?= $toyCE ? 'checked' : '' ?><?= $toyDis ?>><span><strong><?= e(t('bebe.toy.f.ce')) ?></strong> — <?= e(t('bebe.toy.ce_hint')) ?></span></label>
                    <label class="check-row"><input type="checkbox" name="small_parts" value="1" data-bebe-toy-small <?= $toySmall ? 'checked' : '' ?><?= $toyDis ?>><span><strong><?= e(t('bebe.toy.f.small_parts')) ?></strong> — <?= e(t('bebe.toy.small_parts_hint')) ?></span></label>
                </div>
                <div class="notice notice-warning" data-bebe-toy-ce-note<?= $toyShowCE ? '' : ' hidden' ?>><p>⚖️ <?= e(t('bebe.toy.ce_note')) ?></p></div>
                <div class="notice notice-warning" data-bebe-toy-u3-note<?= $toyUnder3 ? '' : ' hidden' ?>><p>🚸 <?= e(t('bebe.toy.under3_note')) ?> <span data-bebe-toy-conflict<?= $toyConflict ? '' : ' hidden' ?>><br><strong><?= e(t('bebe.toy.under3_conflict')) ?></strong></span></p></div>
                <div class="notice notice-warning" data-bebe-toy-3-note<?= $toyShow3 ? '' : ' hidden' ?>><p>⚠️ <?= e(t('bebe.toy.parts3_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-toy-piles-note<?= $toyShowPiles ? '' : ' hidden' ?>><p>🔋 <?= e(t('bebe.toy.piles_note')) ?></p></div>
                <?php if (has_error('small_parts')): ?><p class="field-error"><?= e(error('small_parts')) ?></p><?php endif; ?>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="toy-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="toy-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldT['variant_axis'] ?? ($toyAttrs['variant_axis'] ?? ($toyMeta['axis'] ?? 'Modèle')))) ?>" placeholder="Couleur / Modèle / Taille" data-bebe-toy-axis<?= $toyDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $toySizes = ($toyMeta && !empty($toyMeta['axis'])) ? (array) (config('bebe.toy_size_systems')[$toyMeta['axis']] ?? []) : []; ?>
                <label data-bebe-toy-size-label<?= $toySizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-toy-size-chips<?= $toySizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($toySizes as $sb): ?><button type="button" class="axis-chip" data-bebe-toy-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
            </details>
        </div><!-- /bébé jouets -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldP   = $_SESSION['_old'] ?? [];
            $puerAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $puerActive = bebe_puer_is_rayon($curCol);
            $puerRayon  = $puerActive ? $curCol : (bebe_puer_rayons()[0] ?? 'Puériculture');
            $puerType   = (string) ($rawOldP['product_type'] ?? ($product['product_type'] ?? ''));
            $puerMeta   = bebe_puer_type_meta($puerRayon, $puerType);
            $puerCond   = (string) ($rawOldP['acc_condition'] ?? ($puerAttrs['condition'] ?? 'Neuf'));
            $puerCE     = isset($rawOldP['ce']) ? ((string) $rawOldP['ce'] === '1') : (array_key_exists('ce', $puerAttrs) ? !empty($puerAttrs['ce']) : false);
            $puerAtoutsSel = isset($rawOldP['atouts']) && is_array($rawOldP['atouts'])
                ? array_map('strval', $rawOldP['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $puerDis = $puerActive ? '' : ' disabled';
            $puerOccasion = in_array($puerCond, ['Occasion', 'Reconditionné'], true);
            $puerCarseat = $puerMeta !== null && !empty($puerMeta['carseat']);
            $puerBed     = $puerMeta !== null && !empty($puerMeta['bed']);
            $puerChair   = $puerMeta !== null && !empty($puerMeta['chair']);
            $puerElec    = $puerMeta !== null && !empty($puerMeta['elec']);
            $puerBottle  = $puerMeta !== null && !empty($puerMeta['bottle']);
            $puerCsDef   = (array) config('bebe.puer_carseat_defaults', []);
        ?>
        <div data-bebe-puer
             data-rayons="<?= e((string) json_encode((array) config('bebe.puer', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.puer_size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-carseat-defaults="<?= e((string) json_encode($puerCsDef, JSON_UNESCAPED_UNICODE)) ?>"
             data-any="<?= e(t('bebe.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Bébé & Enfant · Puériculture (sécurité) ===== -->
        <div data-bebe-puer-root<?= $puerActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="puer-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="puer-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldP['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="ex. Bébé Confort, Chicco, Philips Avent…"<?= $puerDis ?>>
                </div>
                <div>
                    <label for="puer-type"><?= e(t('bebe.f.type')) ?> <span class="req">*</span></label>
                    <select id="puer-type" name="product_type" data-pv="type" data-bebe-puer-type<?= $puerDis ?>>
                        <option value=""><?= e(t('bebe.f.type_any')) ?></option>
                        <?php $puerGroups = bebe_puer_groups($puerRayon); ?>
                        <?php if ($puerGroups !== []): foreach ($puerGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (bebe_puer_types($puerRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $puerType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (bebe_puer_types($puerRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $puerType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="puer-cond"><?= e(t('bebe.puer.f.condition')) ?></label>
                    <select id="puer-cond" name="acc_condition" data-bebe-puer-cond<?= $puerDis ?>>
                        <?php foreach (bebe_puer_conditions() as $cc): ?><option value="<?= e($cc) ?>" <?= $puerCond === $cc ? 'selected' : '' ?>><?= e($cc) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-bebe-puer-hint><?= e($puerMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-bebe-puer-attrs>
                    <?php if ($puerMeta): foreach ((array) ($puerMeta['fields'] ?? []) as $fk): $fd = bebe_puer_fields($puerRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($puerAttrs[$fk] ?? ''); if ($fv === '' && $puerCarseat && isset($puerCsDef[$fk])) { $fv = (string) $puerCsDef[$fk]; } ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $puerDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-puer-atouts>
                    <?php foreach (bebe_puer_atouts($puerRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $puerAtoutsSel, true) ? 'checked' : '' ?><?= $puerDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="variants-box" open>
                <summary>🛡️ <?= e(t('bebe.puer.sec_safety')) ?></summary>
                <label class="check-row"><input type="checkbox" name="ce" value="1" data-bebe-puer-ce <?= $puerCE ? 'checked' : '' ?><?= $puerDis ?>><span><strong><?= e(t('bebe.puer.f.ce')) ?></strong> — <?= e(t('bebe.puer.ce_hint')) ?></span></label>
                <div class="notice notice-warning" data-bebe-puer-ce-note<?= $puerCE ? ' hidden' : '' ?>><p>⚖️ <?= e(t('bebe.puer.ce_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-puer-carseat-note<?= $puerCarseat ? '' : ' hidden' ?>><p>🚗 <?= e(t('bebe.puer.carseat_note')) ?></p></div>
                <div class="notice notice-warning" data-bebe-puer-carseat-occ-note<?= ($puerCarseat && $puerOccasion) ? '' : ' hidden' ?>><p>🚨 <?= e(t('bebe.puer.carseat_occasion_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-puer-bed-note<?= $puerBed ? '' : ' hidden' ?>><p>🛏️ <?= e(t('bebe.puer.bed_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-puer-chair-note<?= $puerChair ? '' : ' hidden' ?>><p>🪑 <?= e(t('bebe.puer.chair_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-puer-elec-note<?= $puerElec ? '' : ' hidden' ?>><p>⚡ <?= e(t('bebe.puer.elec_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-puer-bottle-note<?= $puerBottle ? '' : ' hidden' ?>><p>🍼 <?= e(t('bebe.puer.bottle_note')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="puer-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="puer-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldP['variant_axis'] ?? ($puerAttrs['variant_axis'] ?? ($puerMeta['axis'] ?? 'Couleur')))) ?>" placeholder="Couleur / Modèle / Taille" data-bebe-puer-axis<?= $puerDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $puerSizes = ($puerMeta && !empty($puerMeta['axis'])) ? (array) (config('bebe.puer_size_systems')[$puerMeta['axis']] ?? []) : []; ?>
                <label data-bebe-puer-size-label<?= $puerSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-puer-size-chips<?= $puerSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($puerSizes as $sb): ?><button type="button" class="axis-chip" data-bebe-puer-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
            </details>
        </div><!-- /bébé puériculture -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldS   = $_SESSION['_old'] ?? [];
            $soinAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $soinActive = bebe_soin_is_rayon($curCol);
            $soinRayon  = $soinActive ? $curCol : (bebe_soin_rayons()[0] ?? 'Soins');
            $soinType   = (string) ($rawOldS['product_type'] ?? ($product['product_type'] ?? ''));
            $soinMeta   = bebe_soin_type_meta($soinRayon, $soinType);
            $soinPerem  = (string) ($rawOldS['peremption'] ?? ($soinAttrs['peremption'] ?? ''));
            $soinLabelsSel = isset($rawOldS['labels']) && is_array($rawOldS['labels'])
                ? array_map('strval', $rawOldS['labels'])
                : array_map('strval', (array) ($soinAttrs['labels'] ?? []));
            $soinAtoutsSel = isset($rawOldS['atouts']) && is_array($rawOldS['atouts'])
                ? array_map('strval', $rawOldS['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $soinFields = (array) ($soinMeta['fields'] ?? []);
            $soinShowLabels = in_array('labels', $soinFields, true);
            $soinCosm = $soinMeta !== null && !empty($soinMeta['cosmetic']);
            $soinSun  = $soinMeta !== null && !empty($soinMeta['sun']);
            $soinMed  = $soinMeta !== null && !empty($soinMeta['medical']);
            $soinSup  = $soinMeta !== null && !empty($soinMeta['supplement']);
            $soinDia  = $soinMeta !== null && !empty($soinMeta['diaper']);
            $soinShowPerem = $soinCosm || $soinMed || $soinSup;
            $soinSunDef = (string) config('bebe.soin_sun_default', 'SPF 50+');
            $soinDis  = $soinActive ? '' : ' disabled';
        ?>
        <div data-bebe-soin
             data-rayons="<?= e((string) json_encode((array) config('bebe.soin', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.soin_size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-sun-default="<?= e($soinSunDef) ?>"
             data-any="<?= e(t('bebe.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Bébé & Enfant · Soins (hygiène / santé) ===== -->
        <div data-bebe-soin-root<?= $soinActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="soin-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="soin-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldS['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="ex. Mustela, Pampers, marque locale…"<?= $soinDis ?>>
                </div>
                <div>
                    <label for="soin-type"><?= e(t('bebe.f.type')) ?> <span class="req">*</span></label>
                    <select id="soin-type" name="product_type" data-pv="type" data-bebe-soin-type<?= $soinDis ?>>
                        <option value=""><?= e(t('bebe.f.type_any')) ?></option>
                        <?php $soinGroups = bebe_soin_groups($soinRayon); ?>
                        <?php if ($soinGroups !== []): foreach ($soinGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (bebe_soin_types($soinRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $soinType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (bebe_soin_types($soinRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $soinType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label><?= e(t('bebe.soin.f.condition')) ?></label>
                    <div class="static-val"><?= e(t('bebe.soin.condition_fixed')) ?></div>
                    <span class="hint"><?= e(t('bebe.soin.condition_hint')) ?></span>
                </div>
                <div></div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-bebe-soin-hint><?= e($soinMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-bebe-soin-attrs>
                    <?php if ($soinMeta): foreach ($soinFields as $fk): $fd = bebe_soin_fields($soinRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($soinAttrs[$fk] ?? ''); if ($fv === '' && $soinSun && $fk === 'spf') { $fv = $soinSunDef; } ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $soinDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div data-bebe-soin-labels-wrap<?= $soinShowLabels ? '' : ' hidden' ?>>
                    <label style="margin-top:12px"><?= e(t('bebe.soin.f.labels')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <div class="chip-checks" data-bebe-soin-labels>
                        <?php foreach (bebe_soin_labels() as $lb): ?>
                            <label class="chip-check"><input type="checkbox" name="labels[]" value="<?= e($lb) ?>" <?= in_array($lb, $soinLabelsSel, true) ? 'checked' : '' ?><?= $soinShowLabels ? $soinDis : ' disabled' ?>><span><?= e($lb) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-soin-atouts>
                    <?php foreach (bebe_soin_atouts($soinRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $soinAtoutsSel, true) ? 'checked' : '' ?><?= $soinDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="variants-box" open>
                <summary>🛡️ <?= e(t('bebe.soin.sec_safety')) ?></summary>
                <div data-bebe-soin-perem-wrap<?= $soinShowPerem ? '' : ' hidden' ?>>
                    <label for="soin-perem"><?= e(t('bebe.soin.f.peremption')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="date" id="soin-perem" name="peremption" value="<?= e($soinPerem) ?>"<?= $soinShowPerem ? $soinDis : ' disabled' ?>>
                    <span class="hint"><?= e(t('bebe.soin.peremption_hint')) ?></span>
                </div>
                <div class="notice notice-info" data-bebe-soin-cosmetic-note<?= $soinCosm ? '' : ' hidden' ?>><p>🧴 <?= e(t('bebe.soin.cosmetic_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-soin-sun-note<?= $soinSun ? '' : ' hidden' ?>><p>☀️ <?= e(t('bebe.soin.sun_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-soin-medical-note<?= $soinMed ? '' : ' hidden' ?>><p>⚕️ <?= e(t('bebe.soin.medical_note')) ?></p></div>
                <div class="notice notice-warning" data-bebe-soin-supplement-note<?= $soinSup ? '' : ' hidden' ?>><p>⚖️ <?= e(t('bebe.soin.supplement_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-soin-diaper-note<?= $soinDia ? '' : ' hidden' ?>><p>👶 <?= e(t('bebe.soin.diaper_note')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="soin-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="soin-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldS['variant_axis'] ?? ($soinAttrs['variant_axis'] ?? ($soinMeta['axis'] ?? 'Contenance')))) ?>" placeholder="Taille / Contenance / Lot" data-bebe-soin-axis<?= $soinDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $soinSizes = ($soinMeta && !empty($soinMeta['axis'])) ? (array) (config('bebe.soin_size_systems')[$soinMeta['axis']] ?? []) : []; ?>
                <label data-bebe-soin-size-label<?= $soinSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-soin-size-chips<?= $soinSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($soinSizes as $sb): ?><button type="button" class="axis-chip" data-bebe-soin-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
            </details>
        </div><!-- /bébé soins -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldV  = $_SESSION['_old'] ?? [];
            $vetAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $vetActive = bebe_vet_is_rayon($curCol);
            $vetRayon  = $vetActive ? $curCol : (bebe_vet_rayons()[0] ?? 'Vêtements bébé');
            $vetType   = (string) ($rawOldV['product_type'] ?? ($product['product_type'] ?? ''));
            $vetMeta   = bebe_vet_type_meta($vetRayon, $vetType);
            $vetCond   = (string) ($rawOldV['acc_condition'] ?? ($vetAttrs['condition'] ?? 'Neuf avec étiquette'));
            $vetSafe   = isset($rawOldV['securite_enfant']) ? ((string) $rawOldV['securite_enfant'] === '1')
                : (array_key_exists('securite_enfant', $vetAttrs) ? !empty($vetAttrs['securite_enfant']) : true);
            $vetSleep  = $vetMeta !== null && !empty($vetMeta['sleep']);
            $vetDefaults = (array) ($vetMeta['defaults'] ?? []);
            $vetAtoutsSel = isset($rawOldV['atouts']) && is_array($rawOldV['atouts'])
                ? array_map('strval', $rawOldV['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $vetDis = $vetActive ? '' : ' disabled';
        ?>
        <div data-bebe-vet
             data-rayons="<?= e((string) json_encode((array) config('bebe.vet', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.vet_size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-any="<?= e(t('bebe.f.type_any')) ?>"
             data-hint-specs="<?= e(t('cuisine.specs_hint')) ?>" data-hint-pick="<?= e(t('cuisine.specs_pick')) ?>" hidden></div>

        <!-- ===== Bébé & Enfant · Vêtements bébé (sécurité textile) ===== -->
        <div data-bebe-vet-root<?= $vetActive ? '' : ' hidden' ?>>
            <div class="grid-2">
                <div>
                    <label for="vet-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="vet-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldV['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="ex. Petit Bateau, sans marque…"<?= $vetDis ?>>
                </div>
                <div>
                    <label for="vet-type"><?= e(t('bebe.f.type')) ?> <span class="req">*</span></label>
                    <select id="vet-type" name="product_type" data-pv="type" data-bebe-vet-type<?= $vetDis ?>>
                        <option value=""><?= e(t('bebe.f.type_any')) ?></option>
                        <?php $vetGroups = bebe_vet_groups($vetRayon); ?>
                        <?php if ($vetGroups !== []): foreach ($vetGroups as $gk => $glabel): ?>
                            <optgroup label="<?= e($glabel) ?>">
                                <?php foreach (bebe_vet_types($vetRayon) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                    <option value="<?= e($tname) ?>" <?= $vetType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; else: foreach (bebe_vet_types($vetRayon) as $tname => $tm): ?>
                            <option value="<?= e($tname) ?>" <?= $vetType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="vet-cond"><?= e(t('bebe.vet.f.condition')) ?></label>
                    <select id="vet-cond" name="acc_condition"<?= $vetDis ?>>
                        <?php foreach (bebe_vet_conditions() as $cc): ?><option value="<?= e($cc) ?>" <?= $vetCond === $cc ? 'selected' : '' ?>><?= e($cc) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div></div>
            </div>

            <details class="variants-box" open>
                <summary>⚙️ <?= e(t('cuisine.sec.specs')) ?></summary>
                <p class="hint" data-bebe-vet-hint><?= e($vetMeta ? t('cuisine.specs_hint') : t('cuisine.specs_pick')) ?></p>
                <div class="attrs grid-2" data-bebe-vet-attrs>
                    <?php if ($vetMeta): foreach ((array) ($vetMeta['fields'] ?? []) as $fk): $fd = bebe_vet_fields($vetRayon)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($vetAttrs[$fk] ?? ''); if ($fv === '' && isset($vetDefaults[$fk])) { $fv = (string) $vetDefaults[$fk]; } ?>
                        <div>
                            <label><?= e((string) $fd['label']) ?></label>
                            <select name="attr[<?= e($fk) ?>]"<?= $vetDis ?>>
                                <option value="">—</option>
                                <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-vet-atouts>
                    <?php foreach (bebe_vet_atouts($vetRayon) as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $vetAtoutsSel, true) ? 'checked' : '' ?><?= $vetDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="variants-box" open>
                <summary>🛡️ <?= e(t('bebe.vet.sec_safety')) ?></summary>
                <label class="check-row"><input type="checkbox" name="securite_enfant" value="1" data-bebe-vet-safe <?= $vetSafe ? 'checked' : '' ?><?= $vetDis ?>><span><strong><?= e(t('bebe.vet.f.safe')) ?></strong> — <?= e(t('bebe.vet.safe_hint')) ?></span></label>
                <div class="notice notice-warning" data-bebe-vet-en-note<?= $vetMeta ? '' : ' hidden' ?>><p>🧷 <?= e(t('bebe.vet.en14682_note')) ?></p></div>
                <div class="notice notice-info" data-bebe-vet-sleep-note<?= $vetSleep ? '' : ' hidden' ?>><p>🌙 <?= e(t('bebe.vet.sleep_note')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="vet-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="vet-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldV['variant_axis'] ?? ($vetAttrs['variant_axis'] ?? ($vetMeta['axis'] ?? 'Taille')))) ?>" placeholder="Taille / Couleur / Lot" data-bebe-vet-axis<?= $vetDis ?>>
                    </div>
                    <div></div>
                </div>
                <?php $vetSizes = ($vetMeta && !empty($vetMeta['axis'])) ? (array) (config('bebe.vet_size_systems')[$vetMeta['axis']] ?? []) : []; ?>
                <label data-bebe-vet-size-label<?= $vetSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-vet-size-chips<?= $vetSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($vetSizes as $sb): ?><button type="button" class="axis-chip" data-bebe-vet-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>
            </details>
        </div><!-- /bébé vêtements -->
        <?php endif; ?>

        <?php if (bebe_capable($boutiqueCat)):
            $rawOldBA   = $_SESSION['_old'] ?? [];
            $baAttrs    = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
            $baActive   = $curCol !== '' && !bebe_any_rayon($curCol);
            $baCfg      = bebe_autre_cfg($curCol);
            $baType     = (string) ($rawOldBA['product_type'] ?? ($product['product_type'] ?? ''));
            $baCond     = (string) ($rawOldBA['acc_condition'] ?? ($baAttrs['condition'] ?? 'Neuf avec étiquette'));
            $baAge      = (string) ($rawOldBA['age_min'] ?? ($baAttrs['age_min'] ?? ''));
            $baCE       = isset($rawOldBA['ce']) ? ((string) $rawOldBA['ce'] === '1')
                : (array_key_exists('ce', $baAttrs) ? !empty($baAttrs['ce']) : ($baCfg !== null && !empty($baCfg['ce'])));
            $baSafe     = isset($rawOldBA['securite_enfant']) ? ((string) $rawOldBA['securite_enfant'] === '1')
                : (array_key_exists('securite_enfant', $baAttrs) ? !empty($baAttrs['securite_enfant']) : true);
            $baKnown    = array_values(array_merge(bebe_rayons(), bebe_toy_rayons(), bebe_puer_rayons(), bebe_soin_rayons(), bebe_vet_rayons()));
            $baSpecs = [];
            if (isset($rawOldBA['spec_label']) && is_array($rawOldBA['spec_label'])) {
                foreach ($rawOldBA['spec_label'] as $i => $lb) { $baSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldBA['spec_value'][$i] ?? '')]; }
            } elseif (is_array($baAttrs['specs'] ?? null)) {
                foreach ($baAttrs['specs'] as $lb => $val) { $baSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
            }
            $baAtoutsSel = isset($rawOldBA['atouts']) && is_array($rawOldBA['atouts'])
                ? array_map('strval', $rawOldBA['atouts'])
                : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
            $baDis = $baActive ? '' : ' disabled';
        ?>
        <div data-bebe-autre
             data-known="<?= e((string) json_encode($baKnown, JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(bebe_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-size-systems="<?= e((string) json_encode((array) config('bebe.autre_size_systems', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-generic="<?= e(t('bebe.autre.generic')) ?>"
             data-ce-note="1" hidden></div>

        <!-- ===== Bébé & Enfant · Nouveau rayon (générique adaptatif) ===== -->
        <div data-bebe-autre-root<?= $baActive ? '' : ' hidden' ?>>
            <p class="hint" data-bebe-autre-hint><?= $baCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('bebe.autre.generic')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="bau-brand"><?= e(t('bebe.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="bau-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldBA['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="marque, sans marque…"<?= $baDis ?>>
                </div>
                <div>
                    <label for="bau-type"><?= e(t('cuisine.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="bau-type" name="product_type" data-pv="type" maxlength="60" value="<?= e($baType) ?>" placeholder="<?= e(t('bebe.autre.type_ph')) ?>"<?= $baDis ?>>
                </div>
            </div>
            <div class="grid-2" style="margin-top:14px">
                <div>
                    <label for="bau-cond"><?= e(t('bebe.vet.f.condition')) ?></label>
                    <select id="bau-cond" name="acc_condition"<?= $baDis ?>>
                        <?php foreach (bebe_autre('conditions') as $c): ?><option value="<?= e($c) ?>" <?= $baCond === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="bau-age"><?= e(t('bebe.autre.f.age')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <select id="bau-age" name="age_min"<?= $baDis ?>>
                        <option value="">—</option>
                        <?php foreach (bebe_autre('age_opts') as $ag): ?><option value="<?= e($ag) ?>" <?= $baAge === $ag ? 'selected' : '' ?>><?= e($ag) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label style="margin-top:10px"><?= e(t('autre.rayon_suggest')) ?></label>
            <div class="chips-row" data-bebe-autre-rayon-chips>
                <?php foreach (bebe_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-bebe-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
            </div>

            <details class="variants-box" open>
                <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
                <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
                <div class="axis-suggest" data-bebe-autre-spec-box>
                    <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                    <div class="axis-suggest-chips" data-bebe-autre-spec-chips>
                        <?php foreach (($baCfg['specs'] ?? bebe_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-bebe-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="spec-rows" data-bebe-autre-specs>
                    <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                    <?php foreach ($baSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                        <div class="spec-row">
                            <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>"<?= $baDis ?>>
                            <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>"<?= $baDis ?>>
                            <button type="button" class="variant-del" data-bebe-autre-spec-del aria-label="✕">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-bebe-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
                <template id="bebe-autre-spec-template">
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-bebe-autre-spec-del aria-label="✕">✕</button>
                    </div>
                </template>

                <div class="notice notice-warning" style="margin-top:14px"><p>🚸 <?= e(t('bebe.autre.en_note')) ?></p></div>
                <div class="grid-2" style="margin-top:12px">
                    <label class="check-row"><input type="checkbox" name="securite_enfant" value="1" data-bebe-autre-safe <?= $baSafe ? 'checked' : '' ?><?= $baDis ?>><span><strong><?= e(t('bebe.autre.f.safe')) ?></strong> — <?= e(t('bebe.autre.safe_hint')) ?></span></label>
                    <label class="check-row"><input type="checkbox" name="ce" value="1" data-bebe-autre-ce <?= $baCE ? 'checked' : '' ?><?= $baDis ?>><span><strong><?= e(t('bebe.autre.f.ce')) ?></strong> — <?= e(t('bebe.autre.ce_hint')) ?></span></label>
                </div>
                <div class="notice notice-info" data-bebe-autre-ce-note<?= ($baCfg !== null && !empty($baCfg['ce'])) ? '' : ' hidden' ?>><p>⚖️ <?= e(t('bebe.autre.ce_note')) ?></p></div>

                <div class="grid-2" style="margin-top:14px">
                    <div>
                        <label for="bau-sku"><?= e(t('beauty.f.sku')) ?></label>
                        <input type="text" id="bau-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldBA['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="BB-001"<?= $baDis ?>>
                    </div>
                    <div>
                        <label for="bau-axis"><?= e(t('autre.axis')) ?></label>
                        <input type="text" id="bau-axis" name="variant_axis" maxlength="24" value="<?= e((string) ($rawOldBA['variant_axis'] ?? ($baAttrs['variant_axis'] ?? ($baCfg['axis'] ?? '')))) ?>" placeholder="Taille / Pointure / Couleur" data-bebe-autre-axis<?= $baDis ?>>
                    </div>
                </div>
                <?php $baSizes = ($baCfg && !empty($baCfg['axis'])) ? (array) (config('bebe.autre_size_systems')[$baCfg['axis']] ?? []) : []; ?>
                <label data-bebe-autre-size-label<?= $baSizes === [] ? ' hidden' : '' ?>><?= e(t('cuisine.autre_sizes')) ?></label>
                <div class="chips-row" data-bebe-autre-size-chips<?= $baSizes === [] ? ' hidden' : '' ?>>
                    <?php foreach ($baSizes as $sb): ?><button type="button" class="axis-chip" data-bebe-autre-fill="<?= e((string) json_encode($sb['list'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) ($sb['label'] ?? '')) ?></button><?php endforeach; ?>
                </div>

                <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-bebe-autre-atouts>
                    <?php $baAll = array_values(array_unique(array_merge(bebe_autre('atout_suggest'), $baAtoutsSel)));
                    foreach ($baAll as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $baAtoutsSel, true) ? 'checked' : '' ?><?= $baDis ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="autre-atout-add">
                    <input type="text" id="bau-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-bebe-autre-atout-input>
                    <button type="button" class="btn btn-ghost btn-sm" data-bebe-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
                </div>
            </details>
        </div><!-- /bébé nouveau rayon -->
        <?php endif; ?>

        <?php if ($vertical === 'phone'): ?>
        <?php
        $rawOldE  = $_SESSION['_old'] ?? [];
        $accAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
        $elecRayonSSR = elec_is_rayon($curCol) ? $curCol : (elec_rayons()[0] ?? 'Accessoires');
        $accType  = (string) ($rawOldE['product_type'] ?? ($product['product_type'] ?? ''));
        $accMeta  = elec_type_meta($elecRayonSSR, $accType);
        $eautreCfg = elec_autre_cfg($curCol); // null si rayon générique/inconnu
        $eAttr    = static fn (string $k): string => (string) ($rawOldE[$k] ?? ($accAttrs[$k] ?? ''));
        $accAtouts = isset($rawOldE['atouts']) && is_array($rawOldE['atouts'])
            ? array_map('strval', $rawOldE['atouts'])
            : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
        // Caractéristiques libres (rayon « autre ») : ré-affichage erreur (POST) ou édition (attributes).
        $eautreSpecs = [];
        if (isset($rawOldE['spec_label']) && is_array($rawOldE['spec_label'])) {
            foreach ($rawOldE['spec_label'] as $i => $lb) { $eautreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldE['spec_value'][$i] ?? '')]; }
        } elseif (is_array($accAttrs['specs'] ?? null)) {
            foreach ($accAttrs['specs'] as $lb => $val) { $eautreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
        }
        // Axe & pastille couleur partagés : depuis le type (rayon connu) sinon depuis la config « autre ».
        $accAxis = (string) ($rawOldE['variant_axis'] ?? ($accAttrs['variant_axis'] ?? ($accMeta['axis'] ?? ($eautreCfg['axis'] ?? ''))));
        $accHasColor = (bool) ($accMeta['color'] ?? ($eautreCfg['color'] ?? false));
        $accCapteurs = isset($rawOldE['capteur']) && is_array($rawOldE['capteur'])
            ? array_map('strval', $rawOldE['capteur'])
            : array_map('strval', (array) ($accAttrs['capteurs'] ?? []));
        foreach ($realVariants as $vv) { $aa = is_array($vv['attributes'] ?? null) ? $vv['attributes'] : (json_decode((string) ($vv['attributes'] ?? ''), true) ?: []); if (!empty($aa['hex'])) { $accHasColor = true; break; } }
        if (isset($rawOldE['var_has_color'])) { $accHasColor = (string) $rawOldE['var_has_color'] === '1'; }
        $eSec = static fn (string $s): string => ' data-elec-section="' . $s . '"' . ($s === $elecSection ? '' : ' hidden');
        ?>
        <div data-elec
             data-rayons="<?= e((string) json_encode((array) config('electronics.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(elec_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('elec.autre_generic')) ?>"
             data-opt="<?= e(t('variant.option')) ?>"
             data-hint-specs="<?= e(t('elec.specs_hint')) ?>" data-hint-pick="<?= e(t('elec.specs_pick')) ?>" hidden></div>

        <!-- ===== Fiche téléphone (rayons hors Accessoires) ===== -->
        <div<?= $eSec('phone') ?>>
        <div class="grid-2">
            <div>
                <label for="p-brand"><?= e(t('phone.f.brand')) ?></label>
                <input type="text" id="p-brand" name="brand" data-pv="brand" list="brand-list" maxlength="60" value="<?= old('brand') ?: e((string) ($product['brand'] ?? '')) ?>" placeholder="<?= e(t('phone.f.brand_ph')) ?>">
                <datalist id="brand-list"><?php foreach (phone_brands() as $br): ?><option value="<?= e($br) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <div>
                <label for="p-model"><?= e(t('phone.f.model')) ?></label>
                <input type="text" id="p-model" name="model" data-pv="type" maxlength="80" value="<?= old('model') ?: e((string) ($product['model'] ?? '')) ?>" placeholder="<?= e(t('phone.f.model_ph')) ?>">
            </div>
        </div>
        <label for="p-condition"><?= e(t('phone.f.condition')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <select id="p-condition" name="item_condition">
            <option value=""><?= e(t('phone.f.condition_any')) ?></option>
            <?php foreach (phone_conditions() as $co): ?>
                <option value="<?= e($co) ?>" <?= ($isEdit && (string) ($product['item_condition'] ?? '') === $co) ? 'selected' : '' ?>><?= e(t('phone.cond.' . $co)) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="hint"><?= e(t('phone.f.hint')) ?></p>
        </div><!-- /phone -->

        <!-- ===== Électronique adaptatif (Accessoires / Audio…) ===== -->
        <div<?= $eSec('elec') ?> data-elec-root>
        <div class="grid-2">
            <div>
                <label for="acc-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="acc-brand" name="brand" maxlength="60" value="<?= e((string) ($rawOldE['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('elec.brand_ph')) ?>" data-pv="brand">
            </div>
            <div>
                <label for="acc-type"><?= e(t('elec.f.type')) ?> <span class="req">*</span></label>
                <select id="acc-type" name="product_type" data-pv="type" data-elec-type data-any="<?= e(t('beauty.f.type_any')) ?>">
                    <option value=""><?= e(t('beauty.f.type_any')) ?></option>
                    <?php $eGroups = elec_groups($elecRayonSSR); ?>
                    <?php if ($eGroups !== []): foreach ($eGroups as $gk => $glabel): ?>
                        <optgroup label="<?= e($glabel) ?>">
                            <?php foreach (elec_types($elecRayonSSR) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                <option value="<?= e($tname) ?>" <?= $accType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; else: foreach (elec_types($elecRayonSSR) as $tname => $tm): ?>
                        <option value="<?= e($tname) ?>" <?= $accType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>⚙️ <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint" data-elec-hint><?= e($accMeta ? t('elec.specs_hint') : t('elec.specs_pick')) ?></p>
            <div class="field" data-elec-compat-box<?= ($accMeta && !empty($accMeta['compat'])) ? '' : ' hidden' ?>>
                <label for="acc-compat"><?= e(t('elec.f.compat')) ?></label>
                <input type="text" id="acc-compat" name="compatibilite" maxlength="120" value="<?= e($eAttr('compatibilite')) ?>" placeholder="<?= e(t('elec.f.compat_ph')) ?>">
            </div>
            <div class="attrs grid-2" data-elec-attrs>
                <?php if ($accMeta): foreach ((array) ($accMeta['fields'] ?? []) as $fk): $fd = elec_fields($elecRayonSSR)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($accAttrs[$fk] ?? ''); ?>
                    <div>
                        <label><?= e((string) $fd['label']) ?></label>
                        <select name="attr[<?= e($fk) ?>]">
                            <option value="">—</option>
                            <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="field" data-elec-sensors-box<?= ($accMeta && !empty($accMeta['sensors'])) ? '' : ' hidden' ?>>
                <label><?= e(t('elec.f.sensors')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-elec-sensors-chips>
                    <?php foreach (elec_sensors($elecRayonSSR) as $s): ?>
                        <label class="chip-check chip-check--health"><input type="checkbox" name="capteur[]" value="<?= e($s) ?>" <?= in_array($s, $accCapteurs, true) ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="hint"><?= e(t('elec.f.sensors_hint')) ?></p>
            </div>
            <div class="grid-2">
                <div>
                    <label for="acc-condition"><?= e(t('elec.f.condition')) ?></label>
                    <select id="acc-condition" name="acc_condition">
                        <?php $cCur = $eAttr('condition') ?: 'Neuf'; foreach (elec_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $cCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="acc-garantie"><?= e(t('elec.f.warranty')) ?></label>
                    <select id="acc-garantie" name="acc_garantie">
                        <option value=""><?= e(t('elec.f.warranty_none')) ?></option>
                        <?php foreach (elec_garanties() as $g): ?><option value="<?= e($g) ?>" <?= $eAttr('garantie') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label for="acc-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="acc-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e((string) ($rawOldE['ean'] ?? ($product['ean'] ?? ''))) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="acc-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="acc-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldE['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="ACC-CHARG-20W">
                </div>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-elec-atouts-chips>
                <?php foreach (elec_atouts($elecRayonSSR) as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $accAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /elec adaptatif -->

        <!-- ===== Électronique : AUTRE / NOUVEAU RAYON (adaptatif au slug) ===== -->
        <div<?= $eSec('autre') ?> data-eautre-root>
        <p class="hint" data-eautre-rayon-hint><?= $eautreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('elec.autre_generic')) ?></p>
        <div class="grid-2">
            <div>
                <label for="eautre-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="eautre-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldE['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('elec.brand_ph')) ?>">
            </div>
            <div>
                <label for="eautre-ptype"><?= e(t('elec.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="eautre-ptype" name="product_type" data-pv="type" maxlength="60" value="<?= e($accType) ?>" placeholder="<?= e(t('elec.autre_type_ph')) ?>">
            </div>
        </div>
        <label><?= e(t('autre.rayon_suggest')) ?></label>
        <div class="chips-row" data-eautre-rayon-chips>
            <?php foreach (elec_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-eautre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
        </div>

        <details class="variants-box" open>
            <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
            <div class="axis-suggest" data-eautre-spec-box>
                <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                <div class="axis-suggest-chips" data-eautre-spec-chips>
                    <?php foreach (($eautreCfg['specs'] ?? elec_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-eautre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="spec-rows" data-eautre-specs>
                <div class="spec-head">
                    <span><?= e(t('autre.spec_label')) ?></span>
                    <span><?= e(t('autre.spec_value')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($eautreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-eautre-spec-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-eautre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
            <template id="eautre-spec-template">
                <div class="spec-row">
                    <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                    <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                    <button type="button" class="variant-del" data-eautre-spec-del aria-label="✕">✕</button>
                </div>
            </template>

            <div class="field" style="margin-top:14px">
                <label for="eautre-compat"><?= e(t('elec.f.compat')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="eautre-compat" name="compatibilite" maxlength="120" value="<?= e($eAttr('compatibilite')) ?>" placeholder="<?= e(t('elec.f.compat_ph')) ?>">
            </div>
            <div class="grid-2">
                <div>
                    <label for="eautre-condition"><?= e(t('elec.f.condition')) ?></label>
                    <select id="eautre-condition" name="acc_condition">
                        <?php $cCur = $eAttr('condition') ?: 'Neuf'; foreach (elec_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $cCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="eautre-garantie"><?= e(t('elec.f.warranty')) ?></label>
                    <select id="eautre-garantie" name="acc_garantie">
                        <option value=""><?= e(t('elec.f.warranty_none')) ?></option>
                        <?php foreach (elec_garanties() as $g): ?><option value="<?= e($g) ?>" <?= $eAttr('garantie') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label for="eautre-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="eautre-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e((string) ($rawOldE['ean'] ?? ($product['ean'] ?? ''))) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="eautre-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="eautre-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldE['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="REF-001">
                </div>
            </div>
            <div class="warn-box">ℹ️ <?= e((string) config('electronics.autre.warn_text', '')) ?></div>
            <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-eautre-atouts>
                <?php
                $eautreAtoutSugg = elec_autre('atout_suggest');
                $eautreAllAtouts = array_values(array_unique(array_merge($eautreAtoutSugg, $accAtouts)));
                foreach ($eautreAllAtouts as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $accAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="autre-atout-add">
                <input type="text" id="eautre-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-eautre-atout-input>
                <button type="button" class="btn btn-ghost btn-sm" data-eautre-atout-add><?= e(t('autre.atout_add')) ?></button>
            </div>
        </details>
        </div><!-- /elec autre -->
        <?php elseif ($isApparel): ?>
        <?php
        $rawOldA   = $_SESSION['_old'] ?? [];
        $appaAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
        $appaRayonSSR = apparel_is_rayon($curCol) ? $curCol : (apparel_rayons()[0] ?? 'Chaussures');
        $appaType  = (string) ($rawOldA['product_type'] ?? ($product['product_type'] ?? ''));
        $appaMeta  = apparel_type_meta($appaRayonSSR, $appaType);
        $aAttr     = static fn (string $k): string => (string) ($rawOldA[$k] ?? ($appaAttrs[$k] ?? ''));
        $appaConds = apparel_rayon_conditions($appaRayonSSR);
        $appaCondition = $aAttr('condition') ?: ($appaConds[0] ?? 'Neuf avec étiquette');
        $appaCondNote = (string) (((array) config('apparel.rayons', []))[$appaRayonSSR]['condition_note'] ?? '');
        $appaLocked = apparel_rayon_public($appaRayonSSR); // '' ou public imposé (ex. 'feminin')
        $appaTypePublic = (bool) (((array) config('apparel.rayons', []))[$appaRayonSSR]['type_public'] ?? false);
        $appaNoEmpty = $appaLocked !== '' || $appaTypePublic; // pas d'option « — » sur le genre
        $appaGenres = $appaTypePublic ? apparel_type_public($appaRayonSSR, $appaType) : apparel_rayon_genres($appaRayonSSR);
        $appaGenreSel = $aAttr('genre') ?: ($appaNoEmpty ? ($appaGenres[0] ?? '') : ''); // 1er genre par défaut si pas d'option vide
        $appaAtouts = isset($rawOldA['atouts']) && is_array($rawOldA['atouts'])
            ? array_map('strval', $rawOldA['atouts'])
            : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
        $appaAxisDefault = (string) ($appaMeta['axis'] ?? (((array) config('apparel.rayons', []))[$appaRayonSSR]['axis'] ?? 'Pointure'));
        $appaAxis = (string) ($rawOldA['variant_axis'] ?? ($appaAttrs['variant_axis'] ?? $appaAxisDefault));
        $appaHasColor = (bool) ($appaMeta['color'] ?? false);
        foreach ($realVariants as $vv) { $aa = is_array($vv['attributes'] ?? null) ? $vv['attributes'] : (json_decode((string) ($vv['attributes'] ?? ''), true) ?: []); if (!empty($aa['hex'])) { $appaHasColor = true; break; } }
        if (isset($rawOldA['var_has_color'])) { $appaHasColor = (string) $rawOldA['var_has_color'] === '1'; }
        $aSec = static fn (string $s): string => ' data-appa-section="' . $s . '"' . ($s === $appaSection ? '' : ' hidden');
        ?>
        <div data-appa
             data-rayons="<?= e((string) json_encode((array) config('apparel.rayons', []), JSON_UNESCAPED_UNICODE)) ?>"
             data-genres="<?= e((string) json_encode(apparel_genres(), JSON_UNESCAPED_UNICODE)) ?>"
             data-couleurs="<?= e((string) json_encode(apparel_couleurs(), JSON_UNESCAPED_UNICODE)) ?>"
             data-conditions="<?= e((string) json_encode(apparel_conditions(), JSON_UNESCAPED_UNICODE)) ?>"
             data-opt="<?= e(t('variant.option')) ?>" data-any="<?= e(t('appa.f.genre_any')) ?>"
             data-sizes-hint="<?= e(t('appa.sizes_hint')) ?>" data-sizes-pick="<?= e(t('appa.sizes_pick')) ?>" data-sizes-genre="<?= e(t('appa.sizes_genre', ['genre' => '%G%'])) ?>"
             data-decl-size="<?= e(t('appa.decl_size')) ?>" data-decl-color="<?= e(t('appa.decl_color')) ?>"
             data-autre="<?= e((string) json_encode(apparel_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('appa.autre_generic')) ?>"
             data-hint-specs="<?= e(t('appa.specs_hint')) ?>" data-hint-pick="<?= e(t('appa.specs_pick')) ?>" hidden></div>

        <!-- ===== Mode : fiche basique (rayons non adaptatifs) ===== -->
        <div<?= $aSec('basic') ?>>
        <div class="grid-2">
            <div>
                <label for="p-audience"><?= e(t('product.f.audience')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <select id="p-audience" name="audience">
                    <option value=""><?= e(t('product.f.audience_any')) ?></option>
                    <?php foreach (apparel_audiences() as $aud): ?>
                        <option value="<?= e($aud) ?>" <?= ($isEdit && (string) ($product['audience'] ?? '') === $aud) ? 'selected' : '' ?>><?= e(t('apparel.aud.' . $aud)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="p-garment"><?= e(t('product.f.garment')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <select id="p-garment" name="garment_category" data-garment-select>
                    <option value=""><?= e(t('product.f.garment_any')) ?></option>
                    <?php
                    $byGroup = [];
                    foreach (apparel_categories() as $gkey => $gc) { $byGroup[(string) $gc[0]][$gkey] = $gc; }
                    foreach ($byGroup as $grp => $cats): ?>
                        <optgroup label="<?= e(t('apparel.grp.' . $grp)) ?>">
                            <?php foreach ($cats as $gkey => $gc): ?>
                                <option value="<?= e((string) $gkey) ?>" data-size-system="<?= e((string) $gc[1]) ?>" data-unit="<?= e((string) $gc[2]) ?>" data-audiences="<?= e(implode(',', (array) ($gc[3] ?? []))) ?>" <?= ($isEdit && (string) ($product['garment_category'] ?? '') === (string) $gkey) ? 'selected' : '' ?>><?= e(t('apparel.cat.' . $gkey)) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?= e(t('product.f.garment_hint')) ?></p>
            </div>
        </div>
        </div><!-- /mode basique -->

        <!-- ===== Mode adaptatif (Chaussures…) : genre + couleur + type-driven ===== -->
        <div<?= $aSec('adaptive') ?> data-appa-root>
        <div class="warn-box" data-appa-lockban<?= $appaLocked !== '' ? '' : ' hidden' ?>>🔒 <span data-appa-lockban-text><?= e((string) (((array) config('apparel.rayons', []))[$appaRayonSSR]['lock_label'] ?? '')) ?></span></div>
        <div class="grid-2">
            <div>
                <label for="appa-brand"><?= e(t('phone.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="appa-brand" name="brand" data-pv="brand" maxlength="60" value="<?= e((string) ($rawOldA['brand'] ?? ($product['brand'] ?? ''))) ?>" placeholder="<?= e(t('appa.brand_ph')) ?>">
            </div>
            <div>
                <label for="appa-type"><?= e(t('appa.f.type')) ?> <span class="req">*</span></label>
                <select id="appa-type" name="product_type" data-pv="type" data-appa-type data-any="<?= e(t('beauty.f.type_any')) ?>">
                    <option value=""><?= e(t('beauty.f.type_any')) ?></option>
                    <?php foreach (apparel_groups($appaRayonSSR) as $gk => $glabel): ?>
                        <optgroup label="<?= e($glabel) ?>">
                            <?php foreach (apparel_types($appaRayonSSR) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                <option value="<?= e($tname) ?>" <?= $appaType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <?php foreach (apparel_types($appaRayonSSR) as $tname => $tm): if (($tm['group'] ?? '') !== '') { continue; } ?>
                        <option value="<?= e($tname) ?>" <?= $appaType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="appa-genre"><?= e(t('appa.f.genre')) ?> <span class="req">*</span> <span class="lockhint" data-appa-lockhint<?= $appaLocked !== '' ? '' : ' hidden' ?>>🔒 <?= e(t('appa.locked_only')) ?></span></label>
                <select id="appa-genre" name="genre" data-appa-genre>
                    <?php if (!$appaNoEmpty): ?><option value=""><?= e(t('appa.f.genre_any')) ?></option><?php endif; ?>
                    <?php foreach ($appaGenres as $g): ?><option value="<?= e($g) ?>" <?= $appaGenreSel === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appa-couleur"><?= e(t('appa.f.color')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <select id="appa-couleur" name="couleur" data-appa-couleur>
                    <option value="">—</option>
                    <?php foreach (apparel_rayon_couleurs($appaRayonSSR) as $c): ?><option value="<?= e($c) ?>" <?= $aAttr('couleur') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>⚙️ <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint" data-appa-hint><?= e($appaMeta ? t('appa.specs_hint') : t('appa.specs_pick')) ?></p>
            <div class="attrs grid-2" data-appa-attrs>
                <?php if ($appaMeta): foreach ((array) ($appaMeta['fields'] ?? []) as $fk): $fd = apparel_fields($appaRayonSSR)[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($appaAttrs[$fk] ?? ''); $fDrop = (array) (($fd['exclude'] ?? [])[$aAttr('genre')] ?? []); ?>
                    <div>
                        <label><?= e((string) $fd['label']) ?></label>
                        <select name="attr[<?= e($fk) ?>]">
                            <option value="">—</option>
                            <?php foreach ((array) $fd['opts'] as $o): if (in_array($o, $fDrop, true)) { continue; } ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="grid-2">
                <div>
                    <label for="appa-condition"><?= e(t('appa.f.condition')) ?> <span class="hint" data-appa-cond-note<?= $appaCondNote !== '' ? '' : ' hidden' ?>>· <?= e($appaCondNote) ?></span></label>
                    <select id="appa-condition" name="appa_condition" data-appa-condition>
                        <?php foreach ($appaConds as $c): ?><option value="<?= e($c) ?>" <?= $appaCondition === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="appa-sku"><?= e(t('beauty.f.sku')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="appa-sku" name="sku" class="mono" maxlength="40" value="<?= e((string) ($rawOldA['sku'] ?? ($product['sku'] ?? ''))) ?>" placeholder="REF-001">
                </div>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-appa-atouts-chips>
                <?php foreach (apparel_rayon_atouts($appaRayonSSR) as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $appaAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /mode adaptatif -->

        <!-- ===== Mode : NOUVEAU RAYON (générique, adaptatif au slug) ===== -->
        <?php
        $autreCfg   = apparel_autre_cfg($curCol);
        $autreGenres = apparel_autre_genres($curCol);
        $autreGenreSel = $aAttr('genre') ?: ($autreGenres[0] ?? 'Mixte / unisexe');
        $autreAxis  = (string) ($rawOldA['variant_axis'] ?? ($appaAttrs['variant_axis'] ?? ($autreCfg['axis'] ?? 'Taille')));
        $autreColor = (bool) ($autreCfg['color'] ?? false);
        foreach ($realVariants as $vv) { $aa = is_array($vv['attributes'] ?? null) ? $vv['attributes'] : (json_decode((string) ($vv['attributes'] ?? ''), true) ?: []); if (!empty($aa['hex'])) { $autreColor = true; break; } }
        if (isset($rawOldA['var_has_color'])) { $autreColor = (string) $rawOldA['var_has_color'] === '1'; }
        // Caractéristiques libres : ré-affichage (POST) ou édition (attributes hors genre/couleur/condition).
        $autreSpecs = [];
        if (isset($rawOldA['spec_label']) && is_array($rawOldA['spec_label'])) {
            foreach ($rawOldA['spec_label'] as $i => $lb) { $autreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOldA['spec_value'][$i] ?? '')]; }
        } else {
            foreach ($appaAttrs as $lb => $val) { if (!in_array($lb, ['genre', 'couleur', 'condition', 'variant_axis'], true) && is_scalar($val)) { $autreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; } }
        }
        $autreRows = [];
        foreach ($realVariants as $v) { $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []); $autreRows[] = ['name' => (string) ($attr['size'] ?? ($v['label'] ?? '')), 'hex' => (string) ($attr['hex'] ?? '#222222'), 'stock' => $v['stock'], 'price' => $v['price_cents'] ?? null]; }
        $autreAtoutSugg = apparel_autre('atout_suggest');
        $autreAllAtouts = array_values(array_unique(array_merge($autreAtoutSugg, $appaAtouts)));
        ?>
        <div<?= $aSec('autre') ?> data-appa-autre-root>
        <p class="hint" data-aautre-hint><?= $autreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('appa.autre_generic')) ?></p>
        <label><?= e(t('autre.rayon_suggest')) ?></label>
        <div class="chips-row" data-aautre-rayon-chips>
            <?php foreach (apparel_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-aautre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
        </div>
        <div class="grid-2" style="margin-top:14px">
            <div>
                <label for="aautre-ptype"><?= e(t('appa.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="aautre-ptype" name="product_type" data-pv="type" maxlength="60" value="<?= e($appaType) ?>" placeholder="<?= e(t('appa.autre_type_ph')) ?>">
            </div>
            <div>
                <label for="aautre-genre"><?= e(t('appa.f.genre')) ?></label>
                <select id="aautre-genre" name="genre" data-aautre-genre>
                    <?php foreach ($autreGenres as $g): ?><option value="<?= e($g) ?>" <?= $autreGenreSel === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="aautre-couleur"><?= e(t('appa.f.color')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <select id="aautre-couleur" name="couleur" data-aautre-couleur>
                    <option value="">—</option>
                    <?php foreach (apparel_autre('couleurs') as $c): ?><option value="<?= e($c) ?>" <?= $aAttr('couleur') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="aautre-condition"><?= e(t('appa.f.condition')) ?></label>
                <select id="aautre-condition" name="appa_condition">
                    <?php foreach (apparel_conditions() as $c): ?><option value="<?= e($c) ?>" <?= $appaCondition === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
            <div class="axis-suggest" data-aautre-spec-box>
                <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                <div class="axis-suggest-chips" data-aautre-spec-chips>
                    <?php foreach (($autreCfg['specs'] ?? apparel_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-aautre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="spec-rows" data-aautre-specs>
                <div class="spec-head"><span><?= e(t('autre.spec_label')) ?></span><span><?= e(t('autre.spec_value')) ?></span><span></span></div>
                <?php foreach ($autreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-aautre-spec-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-aautre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
            <template id="aautre-spec-template">
                <div class="spec-row">
                    <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                    <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                    <button type="button" class="variant-del" data-aautre-spec-del aria-label="✕">✕</button>
                </div>
            </template>
            <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-aautre-atouts>
                <?php foreach ($autreAllAtouts as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $appaAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="autre-atout-add">
                <input type="text" id="aautre-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-aautre-atout-input>
                <button type="button" class="btn btn-ghost btn-sm" data-aautre-atout-add><?= e(t('autre.atout_add')) ?></button>
            </div>
        </details>

        <details class="variants-box" data-aautre-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>📏 <?= e(t('appa.sizes')) ?></summary>
            <p class="hint" data-aautre-sizes-hint><?= e(t('appa.sizes_hint')) ?></p>
            <input type="hidden" name="var_has_color" value="0">
            <div class="chips-row" data-aautre-quickfill>
                <?php foreach (apparel_autre_quickfill($curCol, $autreGenreSel) as $qf): ?>
                    <button type="button" class="axis-chip" data-aautre-fill data-fill="<?= e((string) json_encode($qf, JSON_UNESCAPED_UNICODE)) ?>">+ <?= e((string) $qf['label']) ?></button>
                <?php endforeach; ?>
                <button type="button" class="axis-chip" data-aautre-clear><?= e(t('appa.clear')) ?></button>
            </div>
            <div class="grid-2">
                <div>
                    <label for="aautre-axis"><?= e(t('autre.axis')) ?></label>
                    <input type="text" id="aautre-axis" name="variant_axis" maxlength="24" value="<?= e($autreAxis) ?>" placeholder="<?= e(t('appa.axis_ph')) ?>" list="aautre-axis-list" data-aautre-axis>
                    <datalist id="aautre-axis-list"><?php foreach (['Taille', 'Longueur', 'Couleur', 'Modèle'] as $ax): ?><option value="<?= e($ax) ?>"></option><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label class="check-row" style="margin-top:24px"><input type="checkbox" name="var_has_color" value="1" data-aautre-color-toggle <?= $autreColor ? 'checked' : '' ?>><span><?= e(t('autre.has_color')) ?></span></label>
                </div>
            </div>
            <div class="bvariant-rows autre-rows<?= $autreColor ? ' has-color' : '' ?>" data-aautre-rows>
                <div class="bvariant-head autre-head">
                    <span data-aautre-axis-label><?= e($autreAxis !== '' ? $autreAxis : t('variant.option')) ?></span>
                    <span class="autre-col-color"><?= e(t('perruque.f.color')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span><span><?= e(t('variant.price_opt')) ?></span><span></span>
                </div>
                <?php foreach ($autreRows as $ar): ?>
                    <div class="bvariant-row autre-row">
                        <input type="text" name="var_size[]" value="<?= e($ar['name']) ?>" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                        <input type="color" name="var_hex[]" class="autre-col-color" value="<?= e($ar['hex'] !== '' ? $ar['hex'] : '#222222') ?>" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $ar['stock'] !== null ? (int) $ar['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($ar['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-aautre-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-aautre-add>+ <?= e(t('appa.size_add')) ?></button>
            <template id="aautre-variant-template">
                <div class="bvariant-row autre-row">
                    <input type="text" name="var_size[]" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                    <input type="color" name="var_hex[]" class="autre-col-color" value="#222222" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-aautre-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /mode nouveau rayon -->
        <?php elseif ($isBeauty): ?>
        <?php
        $rawOld = $_SESSION['_old'] ?? [];
        $bcur = static fn (string $k): string => (string) ($rawOld[$k] ?? ($product[$k] ?? ''));
        $selAtouts = isset($rawOld['atouts']) && is_array($rawOld['atouts'])
            ? array_map('strval', $rawOld['atouts'])
            : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
        $volCur = $bcur('volume');
        $volCur = ($volCur !== '' && (float) str_replace(',', '.', $volCur) > 0)
            ? rtrim(rtrim(number_format((float) str_replace(',', '.', $volCur), 2, '.', ''), '0'), '.') : '';
        $expCur = $bcur('expiry_date');
        $expCur = ($expCur !== '' && strtotime($expCur) !== false) ? date('Y-m-d', (int) strtotime($expCur)) : '';
        $curType   = $bcur('product_type');
        $typeMeta  = beauty_type_meta($curType);
        $savedAttrs = isset($rawOld['attr']) && is_array($rawOld['attr'])
            ? $rawOld['attr']
            : (json_decode((string) ($product['attributes'] ?? ''), true) ?: []);
        // Sous-formulaire beauté piloté par le rayon : Ongles / Parfums / Perruque / Soins / (sinon) Maquillage.
        $isOngles = ($curCol === 'Ongles');
        $isParfum = ($curCol === 'Parfums');
        $isPerruque = ($curCol === 'Perruque');
        $isSoins = in_array($curCol, ['Soins corps', 'Soins visage'], true);
        $soinsKind = beauty_soins_kind($curCol); // 'corps' | 'visage'
        // Rayon beauté spécialisé → sa section ; vide → maquillage ; sinon (libre/custom) → autre.
        $beautySpecialized = ['Maquillage' => 'maquillage', 'Ongles' => 'ongles', 'Parfums' => 'parfum', 'Perruque' => 'perruque', 'Soins corps' => 'soins', 'Soins visage' => 'soins'];
        $beautySec = $beautySpecialized[$curCol] ?? ($curCol === '' ? 'maquillage' : 'autre');
        $isAutre = ($beautySec === 'autre');
        $ongAttrs = json_decode((string) ($product['attributes'] ?? ''), true) ?: [];
        $ongScalar = static fn (string $k): string => (string) ($rawOld['ong_' . $k] ?? ($ongAttrs[$k] ?? ''));
        $ongList = static function (string $k) use ($rawOld, $ongAttrs): array {
            return isset($rawOld['ong_' . $k]) && is_array($rawOld['ong_' . $k])
                ? array_map('strval', $rawOld['ong_' . $k])
                : array_map('strval', (array) ($ongAttrs[$k] ?? []));
        };
        $ongBool = static fn (string $k): bool => isset($rawOld['ong_' . $k])
            ? ((string) $rawOld['ong_' . $k] !== '' && (string) $rawOld['ong_' . $k] !== '0')
            : (bool) ($ongAttrs[$k] ?? false);
        // Parfum : specs (genre, famille…), pyramide (notes), occasions.
        $parScalar = static fn (string $k): string => (string) ($rawOld['par_' . $k] ?? ($ongAttrs[$k] ?? ''));
        $parList = static function (string $k) use ($rawOld, $ongAttrs): array {
            return isset($rawOld['par_' . $k]) && is_array($rawOld['par_' . $k])
                ? array_map('strval', $rawOld['par_' . $k])
                : array_map('strval', (array) ($ongAttrs[$k] ?? []));
        };
        $parNotes = (array) ($ongAttrs['notes'] ?? []);
        $parNote = static fn (string $k): string => (string) ($rawOld['par_note_' . $k] ?? ($parNotes[$k] ?? ''));
        // Perruque : specs (cheveux, texture, densité…), champs adaptatifs (humain / lace).
        $perScalar = static fn (string $k): string => (string) ($rawOld['per_' . $k] ?? ($ongAttrs[$k] ?? ''));
        $humanType = (string) config('beauty.perruque.human_type', 'Cheveux naturels (humains)');
        $perHuman = $perScalar('hair_type') === $humanType;
        $perLace  = in_array($curType, (array) beauty_perruque('lace_types'), true);
        // Soins (visage / corps) : le type pilote les champs ; + actifs (multi) + conformité.
        $soinsTypeMeta = $isSoins ? beauty_soins_type_meta($curCol, $curType) : null;
        $soinsActifs = isset($rawOld['soins_actif']) && is_array($rawOld['soins_actif'])
            ? array_map('strval', $rawOld['soins_actif'])
            : array_map('strval', (array) ($ongAttrs['actifs'] ?? []));
        // Rappel conformité : type-based (corps) OU valeur d'un champ (visage : concern).
        $soinsWarnField = (string) config('beauty.soins.' . $soinsKind . '.warn_field', '');
        $soinsWarnValue = (string) config('beauty.soins.' . $soinsKind . '.warn_value', '');
        $soinsWarn = $soinsTypeMeta !== null && (!empty($soinsTypeMeta['warn'])
            || ($soinsWarnField !== '' && (string) ($ongAttrs[$soinsWarnField] ?? '') === $soinsWarnValue));
        // Autre / nouveau rayon : tout libre (specs label→valeur, axe libre, atouts perso).
        $autreCfg  = beauty_autre_cfg($curCol);
        $autreWarn = beauty_autre_warn($curCol);
        $autreAxis = (string) ($rawOld['variant_axis'] ?? ($ongAttrs['variant_axis'] ?? ($autreCfg['axis'] ?? '')));
        $autreSpecsSaved = is_array($ongAttrs['specs'] ?? null) ? $ongAttrs['specs'] : [];
        // Specs en lignes (libellé→valeur). Sur re-render : champs postés ; sinon : sauvegardés.
        if (isset($rawOld['spec_label']) && is_array($rawOld['spec_label'])) {
            $autreSpecs = [];
            foreach ($rawOld['spec_label'] as $i => $lb) {
                $autreSpecs[] = ['label' => (string) $lb, 'value' => (string) ($rawOld['spec_value'][$i] ?? '')];
            }
        } else {
            $autreSpecs = [];
            foreach ($autreSpecsSaved as $lb => $val) { $autreSpecs[] = ['label' => (string) $lb, 'value' => (string) $val]; }
        }
        $autreAtouts = isset($rawOld['atouts']) && is_array($rawOld['atouts'])
            ? array_map('strval', $rawOld['atouts'])
            : array_values(array_filter(array_map('trim', explode(',', (string) ($product['atouts'] ?? '')))));
        // Pastille couleur sur les options ? (déclinaisons avec hex enregistré, ou config rayon)
        $autreHasColor = (bool) ($autreCfg['color'] ?? false);
        foreach ($realVariants as $vv) {
            $aa = is_array($vv['attributes'] ?? null) ? $vv['attributes'] : (json_decode((string) ($vv['attributes'] ?? ''), true) ?: []);
            if (!empty($aa['hex'])) { $autreHasColor = true; break; }
        }
        if (isset($rawOld['var_has_color'])) { $autreHasColor = (string) $rawOld['var_has_color'] === '1'; }
        $secAttr = static fn (string $sec): string => ' data-beauty-section="' . $sec . '"' . ($sec === $beautySec ? '' : ' hidden');
        ?>
        <div data-beauty
             data-types="<?= e((string) json_encode(beauty_types(), JSON_UNESCAPED_UNICODE)) ?>"
             data-fields="<?= e((string) json_encode(beauty_fields(), JSON_UNESCAPED_UNICODE)) ?>"
             data-palettes="<?= e((string) json_encode(beauty_palettes(), JSON_UNESCAPED_UNICODE)) ?>"
             data-axes="<?= e((string) json_encode(rayon_axes(), JSON_UNESCAPED_UNICODE)) ?>"
             data-nuances="<?= e((string) json_encode(beauty_nuances(), JSON_UNESCAPED_UNICODE)) ?>"
             data-ongles-hex="<?= e((string) json_encode(ongles_couleur_hex(), JSON_UNESCAPED_UNICODE)) ?>"
             data-soins="<?= e((string) json_encode(['corps' => beauty_soins('corps'), 'visage' => beauty_soins('visage'), 'pao' => beauty_soins_pao()], JSON_UNESCAPED_UNICODE)) ?>"
             data-autre="<?= e((string) json_encode(beauty_autre(), JSON_UNESCAPED_UNICODE)) ?>"
             data-autre-adapted="<?= e(t('autre.adapted', ['rayon' => '%R%'])) ?>" data-autre-generic="<?= e(t('autre.generic')) ?>" data-opt="<?= e(t('variant.option')) ?>"
             data-hint-specs="<?= e(t('beauty.sec.specs_hint')) ?>" data-hint-pick="<?= e(t('beauty.sec.specs_pick')) ?>" hidden></div>
        <label for="p-brand"><?= e(t('beauty.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="p-brand" name="brand" maxlength="60" value="<?= e($bcur('brand')) ?>" placeholder="<?= e(t('beauty.f.brand_ph')) ?>" data-pv="brand">

        <!-- ================= MAQUILLAGE ================= -->
        <div<?= $secAttr('maquillage') ?>>
        <div class="grid-2">
            <div>
                <label for="p-ptype"><?= e(t('beauty.f.type')) ?> <span class="req">*</span></label>
                <select id="p-ptype" name="product_type" data-pv="type" data-beauty-type>
                    <option value=""><?= e(t('beauty.f.type_any')) ?></option>
                    <?php foreach (beauty_groups() as $gk => $glabel): ?>
                        <optgroup label="<?= e($glabel) ?>">
                            <?php foreach (beauty_types() as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                <option value="<?= e($tname) ?>" <?= $curType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="p-line"><?= e(t('beauty.f.line')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="p-line" name="line" maxlength="80" value="<?= e($bcur('line')) ?>" placeholder="<?= e(t('beauty.f.line_ph')) ?>">
            </div>
        </div>

        <details class="variants-box" open>
            <summary>🧴 <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint" data-beauty-specs-hint><?= e($typeMeta ? t('beauty.sec.specs_hint') : t('beauty.sec.specs_pick')) ?></p>
            <div class="grid-3">
                <div>
                    <label for="p-volume"><?= e(t('beauty.f.volume')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="p-volume" name="volume" inputmode="decimal" value="<?= e($volCur) ?>" placeholder="30" data-pv="volume">
                        <span class="input-suffix-tag" data-pv-unit><?= e($bcur('volume_unit') ?: 'ml') ?></span>
                    </div>
                </div>
                <div>
                    <label for="p-unit"><?= e(t('beauty.f.unit')) ?></label>
                    <select id="p-unit" name="volume_unit" data-pv="unit">
                        <?php foreach (beauty_volume_units() as $u): ?>
                            <option value="<?= e($u) ?>" <?= ($bcur('volume_unit') ?: 'ml') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="p-pao"><?= e(t('beauty.f.pao')) ?></label>
                    <select id="p-pao" name="pao">
                        <option value="">—</option>
                        <?php foreach (beauty_pao() as $pp): ?><option value="<?= e($pp) ?>" <?= $bcur('pao') === $pp ? 'selected' : '' ?>><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Caractéristiques propres au TYPE (générées selon le type choisi). -->
            <div class="grid-3" data-beauty-attrs>
                <?php if ($typeMeta): foreach ((array) ($typeMeta['fields'] ?? []) as $fk): $fd = beauty_fields()[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($savedAttrs[$fk] ?? ''); ?>
                    <div>
                        <label><?= e((string) $fd['label']) ?></label>
                        <select name="attr[<?= e($fk) ?>]">
                            <option value="">—</option>
                            <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="grid-3">
                <div>
                    <label for="p-expiry"><?= e(t('beauty.f.expiry')) ?></label>
                    <input type="date" id="p-expiry" name="expiry_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($expCur) ?>">
                </div>
                <div>
                    <label for="p-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="p-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="p-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="p-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="FT-IVOIRE-30">
                </div>
            </div>
            <label><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks">
                <?php foreach (beauty_atouts() as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $selAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /maquillage -->

        <!-- ================= ONGLES (faux ongles) ================= -->
        <div<?= $secAttr('ongles') ?>>
        <div class="grid-2">
            <div>
                <label for="o-type"><?= e(t('beauty.f.type')) ?> <span class="req">*</span></label>
                <select id="o-type" name="product_type">
                    <?php foreach (beauty_ongles('product_types') as $ot): ?>
                        <option value="<?= e($ot) ?>" <?= $curType === $ot ? 'selected' : '' ?>><?= e($ot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="o-material"><?= e(t('ongles.f.material')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <select id="o-material" name="ong_material">
                    <option value="">—</option>
                    <?php foreach (beauty_ongles('materials') as $m): ?><option value="<?= e($m) ?>" <?= $ongScalar('material') === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>📐 <?= e(t('ongles.sec.shape')) ?></summary>
            <p class="hint"><?= e(t('ongles.sec.shape_hint')) ?></p>
            <div class="grid-2">
                <div>
                    <label for="o-forme"><?= e(t('ongles.f.forme')) ?> <span class="req">*</span></label>
                    <select id="o-forme" name="ong_forme" data-pv="forme">
                        <option value="">— <?= e(t('beauty.f.type_any')) ?> —</option>
                        <?php foreach (beauty_ongles('formes') as $f): ?><option value="<?= e($f) ?>" <?= $ongScalar('forme') === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="o-long"><?= e(t('ongles.f.length')) ?> <span class="req">*</span></label>
                    <select id="o-long" name="ong_longueur" data-pv="longueur">
                        <option value="">— <?= e(t('beauty.f.type_any')) ?> —</option>
                        <?php foreach (beauty_ongles('longueurs') as $l): ?><option value="<?= e($l) ?>" <?= $ongScalar('longueur') === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label for="o-tips"><?= e(t('ongles.f.tips')) ?></label>
                    <input type="text" id="o-tips" name="ong_tips" inputmode="numeric" maxlength="3" value="<?= e($ongScalar('tips_count')) ?>" placeholder="24" data-pv="tips">
                </div>
                <div>
                    <label for="o-sizes"><?= e(t('ongles.f.sizes')) ?></label>
                    <input type="text" id="o-sizes" name="ong_sizes" inputmode="numeric" maxlength="3" value="<?= e($ongScalar('sizes_count')) ?>" placeholder="10">
                </div>
                <div>
                    <label for="o-wear"><?= e(t('ongles.f.wear')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="o-wear" name="ong_wear" inputmode="numeric" maxlength="3" value="<?= e($ongScalar('wear_days')) ?>" placeholder="7">
                        <span class="input-suffix-tag"><?= e(t('ongles.f.days')) ?></span>
                    </div>
                </div>
            </div>
        </details>

        <details class="variants-box" open>
            <summary>🎨 <?= e(t('ongles.sec.design')) ?></summary>
            <label><?= e(t('ongles.f.designs')) ?></label>
            <div class="chip-checks">
                <?php $selD = $ongList('designs'); foreach (beauty_ongles('designs') as $d): ?>
                    <label class="chip-check"><input type="checkbox" name="ong_design[]" value="<?= e($d) ?>" <?= in_array($d, $selD, true) ? 'checked' : '' ?>><span><?= e($d) ?></span></label>
                <?php endforeach; ?>
            </div>
            <label style="margin-top:12px"><?= e(t('ongles.f.colors')) ?></label>
            <div class="chip-checks">
                <?php $selC = $ongList('couleurs'); foreach (beauty_ongles('couleurs') as $cr): $cn = (string) $cr[0]; $ch = (string) $cr[1]; ?>
                    <label class="chip-check chip-check--tone"><input type="checkbox" name="ong_couleur[]" value="<?= e($cn) ?>" <?= in_array($cn, $selC, true) ? 'checked' : '' ?>><span><span class="chip-dot" style="background:<?= e($ch) ?>"></span><?= e($cn) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="variants-box" open>
            <summary>🧰 <?= e(t('ongles.sec.kit')) ?></summary>
            <label><?= e(t('ongles.f.kit')) ?></label>
            <div class="chip-checks">
                <?php $selK = $ongList('kit'); foreach (beauty_ongles('kit') as $k): ?>
                    <label class="chip-check"><input type="checkbox" name="ong_kit[]" value="<?= e($k) ?>" <?= in_array($k, $selK, true) ? 'checked' : '' ?>><span><?= e($k) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="toggle-rows">
                <?php foreach (beauty_ongles('toggles') as $tk => $tl): ?>
                    <label class="check-row"><input type="checkbox" name="ong_<?= e($tk) ?>" value="1" <?= $ongBool($tk) ? 'checked' : '' ?>><span><?= e($tl) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="grid-2">
                <div>
                    <label for="o-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="o-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="o-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="o-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="FO-FRENCH-M">
                </div>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks">
                <?php foreach (beauty_ongles('atouts') as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $selAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /ongles -->

        <!-- ================= PARFUMS ================= -->
        <div<?= $secAttr('parfum') ?>>
        <div class="grid-2">
            <div>
                <label for="par-type"><?= e(t('parfum.f.concentration')) ?> <span class="req">*</span></label>
                <select id="par-type" name="product_type" data-pv="type">
                    <option value="">— <?= e(t('beauty.f.type_any')) ?> —</option>
                    <?php foreach (beauty_parfum('concentrations') as $pc): ?><option value="<?= e($pc) ?>" <?= $curType === $pc ? 'selected' : '' ?>><?= e($pc) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="par-genre"><?= e(t('parfum.f.genre')) ?> <span class="req">*</span></label>
                <select id="par-genre" name="par_genre" data-pv="genre">
                    <option value="">—</option>
                    <?php foreach (beauty_parfum('genres') as $g): ?><option value="<?= e($g) ?>" <?= $parScalar('genre') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>🌸 <?= e(t('beauty.sec.specs')) ?></summary>
            <div class="grid-3">
                <div>
                    <label for="par-famille"><?= e(t('parfum.f.family')) ?></label>
                    <select id="par-famille" name="par_famille" data-pv="famille">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('familles') as $f): ?><option value="<?= e($f) ?>" <?= $parScalar('famille') === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="par-format"><?= e(t('parfum.f.format')) ?></label>
                    <select id="par-format" name="par_format">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('formats') as $f): ?><option value="<?= e($f) ?>" <?= $parScalar('format') === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="par-alcool"><?= e(t('parfum.f.alcool')) ?></label>
                    <select id="par-alcool" name="par_alcool">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('alcool') as $a): ?><option value="<?= e($a) ?>" <?= $parScalar('alcool') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label for="par-sillage"><?= e(t('parfum.f.sillage')) ?></label>
                    <select id="par-sillage" name="par_sillage">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('sillages') as $s): ?><option value="<?= e($s) ?>" <?= $parScalar('sillage') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="par-tenue"><?= e(t('parfum.f.tenue')) ?></label>
                    <select id="par-tenue" name="par_tenue">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('tenues') as $tn): ?><option value="<?= e($tn) ?>" <?= $parScalar('tenue') === $tn ? 'selected' : '' ?>><?= e($tn) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="par-pao"><?= e(t('beauty.f.pao')) ?></label>
                    <select id="par-pao" name="pao">
                        <option value="">—</option>
                        <?php foreach (beauty_parfum('pao') as $pp): ?><option value="<?= e($pp) ?>" <?= $bcur('pao') === $pp ? 'selected' : '' ?>><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label for="par-volume"><?= e(t('parfum.f.volume')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="par-volume" name="volume" inputmode="numeric" value="<?= e($volCur) ?>" placeholder="100" data-pv="volume">
                        <span class="input-suffix-tag">ml</span>
                    </div>
                </div>
                <div>
                    <label for="par-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="par-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="par-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="par-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="PARF-YARA-100">
                </div>
            </div>
            <label><?= e(t('parfum.f.occasions')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks">
                <?php $selO = $parList('occasions'); foreach (beauty_parfum('occasions') as $o): ?>
                    <label class="chip-check"><input type="checkbox" name="par_occasions[]" value="<?= e($o) ?>" <?= in_array($o, $selO, true) ? 'checked' : '' ?>><span><?= e($o) ?></span></label>
                <?php endforeach; ?>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks">
                <?php foreach (beauty_parfum('atouts') as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $selAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="variants-box" open>
            <summary>🔺 <?= e(t('parfum.sec.pyramid')) ?></summary>
            <p class="hint"><?= e(t('parfum.sec.pyramid_hint')) ?></p>
            <div class="pyr">
                <div class="pyr-lvl t"><span class="pyr-tag"><span class="pyr-b"></span><?= e(t('parfum.f.top')) ?></span>
                    <input type="text" name="par_note_tete" maxlength="160" value="<?= e($parNote('tete')) ?>" placeholder="<?= e(t('parfum.f.top_ph')) ?>"></div>
                <div class="pyr-lvl c"><span class="pyr-tag"><span class="pyr-b"></span><?= e(t('parfum.f.heart')) ?></span>
                    <input type="text" name="par_note_coeur" maxlength="160" value="<?= e($parNote('coeur')) ?>" placeholder="<?= e(t('parfum.f.heart_ph')) ?>"></div>
                <div class="pyr-lvl f"><span class="pyr-tag"><span class="pyr-b"></span><?= e(t('parfum.f.base')) ?></span>
                    <input type="text" name="par_note_fond" maxlength="160" value="<?= e($parNote('fond')) ?>" placeholder="<?= e(t('parfum.f.base_ph')) ?>"></div>
            </div>
        </details>
        </div><!-- /parfum -->

        <!-- ================= PERRUQUE ================= -->
        <div<?= $secAttr('perruque') ?> data-perr data-human-type="<?= e($humanType) ?>"
             data-lace-types="<?= e((string) json_encode(array_values((array) beauty_perruque('lace_types')), JSON_UNESCAPED_UNICODE)) ?>">
        <div class="grid-2">
            <div>
                <label for="per-type"><?= e(t('perruque.f.construction')) ?> <span class="req">*</span></label>
                <select id="per-type" name="product_type" data-pv="type" data-perr-type>
                    <option value="">— <?= e(t('beauty.f.type_any')) ?> —</option>
                    <?php foreach (beauty_perruque('constructions') as $c): ?><option value="<?= e($c) ?>" <?= $curType === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="per-hair"><?= e(t('perruque.f.hair_type')) ?> <span class="req">*</span></label>
                <select id="per-hair" name="per_hair_type" data-pv="hair" data-perr-hair>
                    <option value="">—</option>
                    <?php foreach (beauty_perruque('hair_types') as $h): ?><option value="<?= e($h) ?>" <?= $perScalar('hair_type') === $h ? 'selected' : '' ?>><?= e($h) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <details class="variants-box" open>
            <summary>✨ <?= e(t('beauty.sec.specs')) ?></summary>
            <div class="grid-3">
                <div>
                    <label for="per-texture"><?= e(t('perruque.f.texture')) ?></label>
                    <select id="per-texture" name="per_texture" data-pv="texture">
                        <option value="">—</option>
                        <?php foreach (beauty_perruque('textures') as $tx): ?><option value="<?= e($tx) ?>" <?= $perScalar('texture') === $tx ? 'selected' : '' ?>><?= e($tx) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="per-densite"><?= e(t('perruque.f.density')) ?></label>
                    <select id="per-densite" name="per_densite">
                        <option value="">—</option>
                        <?php foreach (beauty_perruque('densites') as $d): ?><option value="<?= e($d) ?>" <?= $perScalar('densite') === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="per-cap"><?= e(t('perruque.f.cap')) ?></label>
                    <select id="per-cap" name="per_cap_size">
                        <option value="">—</option>
                        <?php foreach (beauty_perruque('cap_sizes') as $cs): ?><option value="<?= e($cs) ?>" <?= $perScalar('cap_size') === $cs ? 'selected' : '' ?>><?= e($cs) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Cheveux naturels : qualité + origine (affiché seulement si cheveux humains). -->
            <div class="adaptive" data-perr-when="human"<?= $perHuman ? '' : ' hidden' ?>>
                <p class="adaptive-label"><?= e(t('perruque.f.human_label')) ?></p>
                <div class="grid-2">
                    <div>
                        <label for="per-qualite"><?= e(t('perruque.f.quality')) ?></label>
                        <select id="per-qualite" name="per_qualite">
                            <option value="">—</option>
                            <?php foreach (beauty_perruque('qualites') as $q): ?><option value="<?= e($q) ?>" <?= $perScalar('qualite') === $q ? 'selected' : '' ?>><?= e($q) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="per-origine"><?= e(t('perruque.f.origin')) ?></label>
                        <select id="per-origine" name="per_origine">
                            <option value="">—</option>
                            <?php foreach (beauty_perruque('origines') as $o): ?><option value="<?= e($o) ?>" <?= $perScalar('origine') === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="grid-3">
                <!-- Couleur de lace : seulement pour les lace wigs. -->
                <div data-perr-when="lace"<?= $perLace ? '' : ' hidden' ?>>
                    <label for="per-lace"><?= e(t('perruque.f.lace_color')) ?></label>
                    <select id="per-lace" name="per_lace_color">
                        <option value="">—</option>
                        <?php foreach (beauty_perruque('lace_colors') as $lc): ?><option value="<?= e($lc) ?>" <?= $perScalar('lace_color') === $lc ? 'selected' : '' ?>><?= e($lc) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="per-long"><?= e(t('perruque.f.length')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="per-long" name="per_longueur" inputmode="numeric" maxlength="3" value="<?= e($perScalar('longueur')) ?>" placeholder="18" data-pv="longueur">
                        <span class="input-suffix-tag">in</span>
                    </div>
                </div>
                <div>
                    <label for="per-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="per-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
            </div>
            <label><?= e(t('perruque.f.color')) ?></label>
            <div class="chip-checks chip-checks--radio">
                <?php $curCoul = $perScalar('couleur'); foreach (beauty_perruque('couleurs') as $cr): $cn = (string) $cr[0]; $chx = (string) $cr[1]; ?>
                    <label class="chip-check chip-check--tone"><input type="radio" name="per_couleur" value="<?= e($cn) ?>" <?= $curCoul === $cn ? 'checked' : '' ?>><span><span class="chip-dot" style="background:<?= e($chx) ?>"></span><?= e($cn) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="grid-2" style="margin-top:12px">
                <div>
                    <label for="per-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="per-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="PERR-BW-18">
                </div>
                <div></div>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks">
                <?php foreach (beauty_perruque('atouts') as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $selAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /perruque -->

        <!-- ================= SOINS (visage / corps) ================= -->
        <div<?= $secAttr('soins') ?> data-soins-root>
        <div class="grid-2">
            <div>
                <label for="soins-type"><?= e(t('beauty.f.type')) ?> <span class="req">*</span></label>
                <select id="soins-type" name="product_type" data-pv="type" data-soins-type data-any="<?= e(t('beauty.f.type_any')) ?>">
                    <option value=""><?= e(t('beauty.f.type_any')) ?></option>
                    <?php foreach (beauty_soins_groups($curCol) as $gk => $glabel): ?>
                        <optgroup label="<?= e($glabel) ?>">
                            <?php foreach (beauty_soins_types($curCol) as $tname => $tm): if (($tm['group'] ?? '') !== $gk) { continue; } ?>
                                <option value="<?= e($tname) ?>" <?= $curType === $tname ? 'selected' : '' ?>><?= e($tname) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div></div>
        </div>

        <details class="variants-box" open>
            <summary>🌿 <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint" data-soins-hint><?= e($soinsTypeMeta ? t('beauty.sec.specs_hint') : t('beauty.sec.specs_pick')) ?></p>
            <!-- Caractéristiques propres au type (générées selon le type). -->
            <div class="attrs grid-2" data-soins-attrs>
                <?php if ($soinsTypeMeta): foreach ((array) ($soinsTypeMeta['fields'] ?? []) as $fk): $fd = beauty_soins($soinsKind, 'fields')[$fk] ?? null; if (!$fd) { continue; } $fv = (string) ($ongAttrs[$fk] ?? ''); ?>
                    <div>
                        <label><?= e((string) $fd['label']) ?></label>
                        <select name="attr[<?= e($fk) ?>]">
                            <option value="">—</option>
                            <?php foreach ((array) $fd['opts'] as $o): ?><option value="<?= e((string) $o) ?>" <?= $fv === (string) $o ? 'selected' : '' ?>><?= e((string) $o) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="warn-box" data-soins-warn<?= $soinsWarn ? '' : ' hidden' ?>>⚠️ <?= e(t('soins.warn')) ?></div>
            <div data-soins-actifs-box<?= ($soinsTypeMeta && !empty($soinsTypeMeta['actifs'])) ? '' : ' hidden' ?>>
                <label style="margin-top:14px"><?= e(t('soins.f.actifs')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <div class="chip-checks" data-soins-actifs-chips>
                    <?php foreach (beauty_soins($soinsKind, 'actifs') as $a): ?>
                        <label class="chip-check"><input type="checkbox" name="soins_actif[]" value="<?= e($a) ?>" <?= in_array($a, $soinsActifs, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grid-3" style="margin-top:14px">
                <div>
                    <label for="soins-volume"><?= e(t('beauty.f.volume')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="soins-volume" name="volume" inputmode="decimal" value="<?= e($volCur) ?>" placeholder="200" data-pv="volume">
                        <span class="input-suffix-tag" data-soins-unit-tag><?= e($bcur('volume_unit') ?: 'ml') ?></span>
                    </div>
                </div>
                <div>
                    <label for="soins-unit"><?= e(t('beauty.f.unit')) ?></label>
                    <select id="soins-unit" name="volume_unit" data-soins-unit data-pv="unit">
                        <option value="ml" <?= ($bcur('volume_unit') ?: 'ml') === 'ml' ? 'selected' : '' ?>>ml</option>
                        <option value="g" <?= $bcur('volume_unit') === 'g' ? 'selected' : '' ?>>g</option>
                    </select>
                </div>
                <div>
                    <label for="soins-pao"><?= e(t('beauty.f.pao')) ?></label>
                    <select id="soins-pao" name="pao">
                        <option value="">—</option>
                        <?php foreach (beauty_soins_pao() as $pp): ?><option value="<?= e($pp) ?>" <?= $bcur('pao') === $pp ? 'selected' : '' ?>><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label for="soins-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="soins-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="soins-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="soins-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="SC-KARITE-200">
                </div>
            </div>
            <label style="margin-top:12px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-soins-atouts-chips>
                <?php foreach (beauty_soins($soinsKind, 'atouts') as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $selAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
        </details>
        </div><!-- /soins -->

        <!-- ================= AUTRE / NOUVEAU RAYON ================= -->
        <div<?= $secAttr('autre') ?> data-autre-root>
        <p class="hint" data-autre-rayon-hint><?= $autreCfg ? e(t('autre.adapted', ['rayon' => $curCol])) : e(t('autre.generic')) ?></p>
        <div class="grid-2">
            <div>
                <label for="autre-ptype"><?= e(t('beauty.f.type')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="autre-ptype" name="product_type" maxlength="60" value="<?= e($bcur('product_type')) ?>" placeholder="<?= e(t('autre.type_ph')) ?>" data-pv="type">
            </div>
            <div></div>
        </div>
        <label><?= e(t('autre.rayon_suggest')) ?></label>
        <div class="chips-row" data-autre-rayon-chips>
            <?php foreach (beauty_autre('rayon_suggest') as $rs): ?><button type="button" class="axis-chip" data-autre-rayon="<?= e($rs) ?>"><?= e($rs) ?></button><?php endforeach; ?>
        </div>

        <details class="variants-box" open>
            <summary>🧩 <?= e(t('beauty.sec.specs')) ?></summary>
            <p class="hint"><?= e(t('autre.specs_hint')) ?></p>
            <div class="axis-suggest" data-autre-spec-box>
                <span class="axis-suggest-label"><?= e(t('autre.spec_suggest')) ?></span>
                <div class="axis-suggest-chips" data-autre-spec-chips>
                    <?php foreach (($autreCfg['specs'] ?? beauty_autre('generic_specs')) as $sp): ?><button type="button" class="axis-chip" data-autre-spec data-val="<?= e($sp) ?>"><?= e($sp) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="spec-rows" data-autre-specs>
                <div class="spec-head">
                    <span><?= e(t('autre.spec_label')) ?></span>
                    <span><?= e(t('autre.spec_value')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($autreSpecs as $sp): if (trim($sp['label']) === '' && trim($sp['value']) === '') { continue; } ?>
                    <div class="spec-row">
                        <input type="text" name="spec_label[]" value="<?= e($sp['label']) ?>" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                        <input type="text" name="spec_value[]" value="<?= e($sp['value']) ?>" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                        <button type="button" class="variant-del" data-autre-spec-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-autre-spec-add>+ <?= e(t('autre.spec_add')) ?></button>
            <template id="autre-spec-template">
                <div class="spec-row">
                    <input type="text" name="spec_label[]" maxlength="40" placeholder="<?= e(t('autre.spec_label_ph')) ?>">
                    <input type="text" name="spec_value[]" maxlength="80" placeholder="<?= e(t('autre.spec_value_ph')) ?>">
                    <button type="button" class="variant-del" data-autre-spec-del aria-label="✕">✕</button>
                </div>
            </template>

            <div class="grid-3" style="margin-top:14px">
                <div>
                    <label for="autre-volume"><?= e(t('beauty.f.volume')) ?></label>
                    <div class="input-suffix">
                        <input type="text" id="autre-volume" name="volume" inputmode="decimal" value="<?= e($volCur) ?>" placeholder="200" data-pv="volume">
                        <span class="input-suffix-tag" data-autre-unit-tag><?= e($bcur('volume_unit') ?: ($autreCfg['unit'] ?? 'ml')) ?></span>
                    </div>
                </div>
                <div>
                    <label for="autre-unit"><?= e(t('beauty.f.unit')) ?></label>
                    <select id="autre-unit" name="volume_unit" data-pv="unit" data-autre-unit>
                        <?php $uCur = $bcur('volume_unit') ?: ($autreCfg['unit'] ?? 'ml'); foreach (['ml' => 'ml', 'g' => 'g', 'pcs' => 'pièce(s)'] as $uv => $ul): ?><option value="<?= e($uv) ?>" <?= $uCur === $uv ? 'selected' : '' ?>><?= e($ul) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="autre-pao"><?= e(t('beauty.f.pao')) ?></label>
                    <select id="autre-pao" name="pao">
                        <option value="">—</option>
                        <?php foreach (['6M', '12M', '18M', '24M', '36M'] as $pp): ?><option value="<?= e($pp) ?>" <?= $bcur('pao') === $pp ? 'selected' : '' ?>><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label for="autre-ean"><?= e(t('beauty.f.ean')) ?></label>
                    <input type="text" id="autre-ean" name="ean" class="mono" inputmode="numeric" maxlength="20" value="<?= e($bcur('ean')) ?>" placeholder="3600000000000">
                </div>
                <div>
                    <label for="autre-sku"><?= e(t('beauty.f.sku')) ?></label>
                    <input type="text" id="autre-sku" name="sku" class="mono" maxlength="40" value="<?= e($bcur('sku')) ?>" placeholder="REF-001">
                </div>
            </div>
            <div class="warn-box" data-autre-warn<?= ($autreWarn !== 'none') ? '' : ' hidden' ?>>⚠️ <span data-autre-warn-text><?= e((string) (beauty_autre('warn_texts')[$autreWarn] ?? '')) ?></span></div>
            <label style="margin-top:14px"><?= e(t('beauty.f.atouts')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="chip-checks" data-autre-atouts>
                <?php
                $autreAtoutSugg = beauty_autre('atout_suggest');
                $autreAllAtouts = array_values(array_unique(array_merge($autreAtoutSugg, $autreAtouts)));
                foreach ($autreAllAtouts as $a): ?>
                    <label class="chip-check"><input type="checkbox" name="atouts[]" value="<?= e($a) ?>" <?= in_array($a, $autreAtouts, true) ? 'checked' : '' ?>><span><?= e($a) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="autre-atout-add">
                <input type="text" id="autre-atout-new" maxlength="40" placeholder="<?= e(t('autre.atout_add_ph')) ?>" data-autre-atout-input>
                <button type="button" class="btn btn-ghost btn-sm" data-autre-atout-add><?= e(t('autre.atout_add')) ?></button>
            </div>
        </details>
        </div><!-- /autre -->
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="p-price"><?= e(t('product.f.price', ['cur' => $cur])) ?></label>
                <input type="text" id="p-price" name="price" value="<?= old('price') ?: e($priceVal) ?>" inputmode="decimal" required placeholder="0" data-pv="price">
                <?php if (has_error('price')): ?><p class="field-error"><?= e(error('price')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="p-stock"><?= e(t('product.f.stock')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="p-stock" name="stock" inputmode="numeric"
                       value="<?= old('stock') ?: ($isEdit && $product['stock'] !== null ? (int) $product['stock'] : '') ?>" placeholder="<?= e(t('product.f.stock_ph')) ?>">
                <p class="hint"><?= e(t('product.f.stock_hint')) ?></p>
                <?php if (has_error('stock')): ?><p class="field-error"><?= e(error('stock')) ?></p><?php endif; ?>
            </div>
        </div>

        <details class="variants-box promo-box" data-promo-box <?= ($isEdit && (int) ($product['promo_price_cents'] ?? 0) > 0) || has_error('promo_price') ? 'open' : '' ?>>
            <summary>🏷️ <?= e(t('product.f.promo_section')) ?></summary>
            <p class="hint"><?= e(t('product.f.promo_hint')) ?></p>
            <div class="notice notice-warning" data-promo-lock-note hidden><p>🔒 <span data-promo-lock-text></span></p></div>
            <div class="grid-2" data-promo-fields>
                <div>
                    <label for="p-promo"><?= e(t('product.f.promo_price', ['cur' => $cur])) ?></label>
                    <input type="text" id="p-promo" name="promo_price" inputmode="decimal" value="<?= old('promo_price') ?: ($isEdit ? e($fmtP($product['promo_price_cents'] ?? null)) : '') ?>" placeholder="<?= e(t('product.f.promo_price_ph')) ?>" data-pv="promo">
                    <?php if (has_error('promo_price')): ?><p class="field-error"><?= e(error('promo_price')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="p-promo-until"><?= e(t('product.f.promo_until')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="date" id="p-promo-until" name="promo_until" min="<?= e(date('Y-m-d')) ?>"
                           value="<?= old('promo_until') ?: ($isEdit && !empty($product['promo_until']) ? e(date('Y-m-d', (int) strtotime((string) $product['promo_until']))) : '') ?>">
                    <p class="hint"><?= e(t('product.f.promo_until_hint')) ?></p>
                    <?php if (has_error('promo_until')): ?><p class="field-error"><?= e(error('promo_until')) ?></p><?php endif; ?>
                </div>
            </div>
        </details>

        <?php if ($isBeauty): ?>
        <?php
        $declLabel = (string) ($typeMeta['decl_label'] ?? t('beauty.decl.colors'));
        $hasNuance = ($typeMeta['decl'] ?? '') === 'teinte';
        $hasDecl   = $typeMeta !== null && !empty($typeMeta['decl']);
        $bRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $nm   = (string) ($attr['size'] ?? ($v['label'] ?? ''));
            $bRows[] = [
                'name'   => $nm,
                'hex'    => (string) ($attr['hex'] ?? (beauty_hex_for($nm) ?? '#C9A06A')),
                'nuance' => (string) ($attr['nuance'] ?? ''),
                'stock'  => $v['stock'],
                'price'  => $v['price_cents'] ?? null,
            ];
        }
        // Faux-ongles : déclinaison = forme (size) × longueur (color).
        $oRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $oRows[] = ['forme' => (string) ($attr['size'] ?? ''), 'long' => (string) ($attr['color'] ?? ''), 'stock' => $v['stock'], 'price' => $v['price_cents'] ?? null];
        }
        ?>
        <div<?= $secAttr('maquillage') ?>>
        <details class="variants-box" data-beauty-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <span data-beauty-decl-title><?= e($declLabel) ?></span></summary>
            <p class="hint"><?= e(t('beauty.decl.hint')) ?></p>
            <p class="hint nuance-hint" data-beauty-nuance-hint<?= $hasNuance ? '' : ' hidden' ?>>💡 <?= e(t('beauty.decl.nuance_hint')) ?></p>
            <div class="axis-suggest" data-beauty-chips-box<?= $hasDecl ? '' : ' hidden' ?>>
                <span class="axis-suggest-label"><strong data-beauty-decl-label><?= e($declLabel) ?></strong> · <?= e(t('variant.suggest_hint')) ?></span>
                <div class="axis-suggest-chips" data-beauty-chips></div>
            </div>
            <div class="bvariant-rows<?= $hasNuance ? ' has-nuance' : '' ?>" data-beauty-rows>
                <div class="bvariant-head">
                    <span><?= e(t('beauty.decl.name')) ?></span>
                    <span><?= e(t('beauty.decl.color')) ?></span>
                    <span class="bcol-nuance"><?= e(t('beauty.decl.nuance')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($bRows as $bv): ?>
                    <div class="bvariant-row">
                        <input type="text" name="var_name[]" value="<?= e($bv['name']) ?>" maxlength="60" placeholder="<?= e(t('beauty.decl.name_ph')) ?>" aria-label="<?= e(t('beauty.decl.name')) ?>">
                        <input type="color" name="var_hex[]" value="<?= e($bv['hex'] !== '' ? $bv['hex'] : '#C9A06A') ?>" aria-label="<?= e(t('beauty.decl.color')) ?>">
                        <select name="var_nuance[]" class="bcol-nuance" aria-label="<?= e(t('beauty.decl.nuance')) ?>">
                            <option value=""><?= e(t('beauty.decl.nuance')) ?>…</option>
                            <?php foreach (beauty_nuances() as $nz): ?><option value="<?= e($nz) ?>" <?= $bv['nuance'] === $nz ? 'selected' : '' ?>><?= e($nz) ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $bv['stock'] !== null ? (int) $bv['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($bv['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-beauty-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-beauty-add>+ <?= e(t('beauty.decl.add')) ?></button>
            <template id="bvariant-template">
                <div class="bvariant-row">
                    <input type="text" name="var_name[]" maxlength="60" placeholder="<?= e(t('beauty.decl.name_ph')) ?>" aria-label="<?= e(t('beauty.decl.name')) ?>">
                    <input type="color" name="var_hex[]" value="#C9A06A" aria-label="<?= e(t('beauty.decl.color')) ?>">
                    <select name="var_nuance[]" class="bcol-nuance" aria-label="<?= e(t('beauty.decl.nuance')) ?>">
                        <option value=""><?= e(t('beauty.decl.nuance')) ?>…</option>
                        <?php foreach (beauty_nuances() as $nz): ?><option value="<?= e($nz) ?>"><?= e($nz) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-beauty-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /maquillage decl -->

        <div<?= $secAttr('ongles') ?>>
        <details class="variants-box" data-ong-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('ongles.sec.variants')) ?></summary>
            <p class="hint"><?= e(t('ongles.sec.variants_hint')) ?></p>
            <div class="bvariant-rows ong-rows" data-ong-rows>
                <div class="bvariant-head ong-head">
                    <span><?= e(t('ongles.f.forme')) ?></span>
                    <span><?= e(t('ongles.f.length')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($oRows as $orow): ?>
                    <div class="bvariant-row ong-row">
                        <select name="var_size[]" aria-label="<?= e(t('ongles.f.forme')) ?>">
                            <option value="">—</option>
                            <?php foreach (beauty_ongles('formes') as $f): ?><option value="<?= e($f) ?>" <?= $orow['forme'] === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
                        </select>
                        <select name="var_color[]" aria-label="<?= e(t('ongles.f.length')) ?>">
                            <option value="">—</option>
                            <?php foreach (beauty_ongles('longueurs') as $l): ?><option value="<?= e($l) ?>" <?= $orow['long'] === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $orow['stock'] !== null ? (int) $orow['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($orow['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-ong-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-ong-add>+ <?= e(t('ongles.f.add')) ?></button>
            <template id="ong-variant-template">
                <div class="bvariant-row ong-row">
                    <select name="var_size[]" aria-label="<?= e(t('ongles.f.forme')) ?>">
                        <option value="">—</option>
                        <?php foreach (beauty_ongles('formes') as $f): ?><option value="<?= e($f) ?>"><?= e($f) ?></option><?php endforeach; ?>
                    </select>
                    <select name="var_color[]" aria-label="<?= e(t('ongles.f.length')) ?>">
                        <option value="">—</option>
                        <?php foreach (beauty_ongles('longueurs') as $l): ?><option value="<?= e($l) ?>"><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-ong-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /ongles decl -->

        <div<?= $secAttr('parfum') ?>>
        <details class="variants-box" data-par-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('parfum.sec.sizes')) ?></summary>
            <p class="hint"><?= e(t('parfum.sec.sizes_hint')) ?></p>
            <div class="axis-suggest" data-par-chips-box>
                <span class="axis-suggest-label"><strong><?= e(t('parfum.f.volume')) ?></strong> · <?= e(t('variant.suggest_hint')) ?></span>
                <div class="axis-suggest-chips" data-par-chips>
                    <?php foreach (beauty_parfum('tailles') as $tl): ?><button type="button" class="axis-chip" data-par-chip data-val="<?= e($tl) ?>"><?= e($tl) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="bvariant-rows par-rows" data-par-rows>
                <div class="bvariant-head par-head">
                    <span><?= e(t('parfum.f.volume')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($oRows as $orow): ?>
                    <div class="bvariant-row par-row">
                        <input type="text" name="var_size[]" value="<?= e($orow['forme']) ?>" maxlength="20" placeholder="50 ml" aria-label="<?= e(t('parfum.f.volume')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $orow['stock'] !== null ? (int) $orow['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($orow['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-par-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-par-add>+ <?= e(t('parfum.f.add')) ?></button>
            <template id="par-variant-template">
                <div class="bvariant-row par-row">
                    <input type="text" name="var_size[]" maxlength="20" placeholder="50 ml" aria-label="<?= e(t('parfum.f.volume')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-par-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /parfum decl -->

        <div<?= $secAttr('perruque') ?>>
        <?php
        $perRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $perRows[] = [
                'long'  => (string) ($attr['size'] ?? ''),
                'coul'  => (string) ($attr['color'] ?? ''),
                'hex'   => (string) ($attr['hex'] ?? (perruque_couleur_hex()[(string) ($attr['color'] ?? '')] ?? '#1A1410')),
                'stock' => $v['stock'],
                'price' => $v['price_cents'] ?? null,
            ];
        }
        ?>
        <details class="variants-box" data-perr-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('perruque.sec.variants')) ?></summary>
            <p class="hint"><?= e(t('perruque.sec.variants_hint')) ?></p>
            <div class="axis-suggest" data-perr-chips-box>
                <span class="axis-suggest-label"><strong><?= e(t('perruque.f.length')) ?></strong> · <?= e(t('variant.suggest_hint')) ?></span>
                <div class="axis-suggest-chips" data-perr-chips>
                    <?php foreach (beauty_perruque('longueurs') as $ln): ?><button type="button" class="axis-chip" data-perr-chip data-val="<?= e($ln) ?>"><?= e($ln) ?>"</button><?php endforeach; ?>
                </div>
            </div>
            <div class="bvariant-rows perr-rows" data-perr-rows>
                <div class="bvariant-head perr-head">
                    <span><?= e(t('perruque.f.length')) ?></span>
                    <span><?= e(t('perruque.f.color')) ?></span>
                    <span></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($perRows as $pr): ?>
                    <div class="bvariant-row perr-row">
                        <div class="input-suffix"><input type="text" name="var_size[]" value="<?= e($pr['long']) ?>" maxlength="5" inputmode="numeric" placeholder="18" aria-label="<?= e(t('perruque.f.length')) ?>"><span class="input-suffix-tag">in</span></div>
                        <input type="text" name="var_color[]" value="<?= e($pr['coul']) ?>" maxlength="40" placeholder="<?= e(t('perruque.f.color_ph')) ?>" list="perr-color-list" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="color" name="var_hex[]" value="<?= e($pr['hex'] !== '' ? $pr['hex'] : '#1A1410') ?>" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $pr['stock'] !== null ? (int) $pr['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($pr['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-perr-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-perr-add>+ <?= e(t('perruque.f.add')) ?></button>
            <template id="perr-variant-template">
                <div class="bvariant-row perr-row">
                    <div class="input-suffix"><input type="text" name="var_size[]" maxlength="5" inputmode="numeric" placeholder="18" aria-label="<?= e(t('perruque.f.length')) ?>"><span class="input-suffix-tag">in</span></div>
                    <input type="text" name="var_color[]" maxlength="40" placeholder="<?= e(t('perruque.f.color_ph')) ?>" list="perr-color-list" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="color" name="var_hex[]" value="#1A1410" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-perr-del aria-label="✕">✕</button>
                </div>
            </template>
            <datalist id="perr-color-list"><?php foreach (beauty_perruque('couleurs') as $cr): ?><option value="<?= e((string) $cr[0]) ?>"></option><?php endforeach; ?></datalist>
        </details>
        </div><!-- /perruque decl -->

        <div<?= $secAttr('soins') ?>>
        <details class="variants-box" data-soins-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('parfum.sec.sizes')) ?></summary>
            <p class="hint"><?= e(t('parfum.sec.sizes_hint')) ?></p>
            <div class="axis-suggest" data-soins-chips-box>
                <span class="axis-suggest-label"><strong><?= e(t('beauty.f.volume')) ?></strong> · <?= e(t('variant.suggest_hint')) ?></span>
                <div class="axis-suggest-chips" data-soins-chips>
                    <?php foreach (beauty_soins($soinsKind, 'tailles') as $tl): ?><button type="button" class="axis-chip" data-soins-chip data-val="<?= e($tl) ?>"><?= e($tl) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="bvariant-rows par-rows" data-soins-rows>
                <div class="bvariant-head par-head">
                    <span><?= e(t('beauty.f.volume')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($oRows as $orow): ?>
                    <div class="bvariant-row par-row">
                        <input type="text" name="var_size[]" value="<?= e($orow['forme']) ?>" maxlength="20" placeholder="200 ml" aria-label="<?= e(t('beauty.f.volume')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $orow['stock'] !== null ? (int) $orow['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($orow['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-soins-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-soins-add>+ <?= e(t('parfum.f.add')) ?></button>
            <template id="soins-variant-template">
                <div class="bvariant-row par-row">
                    <input type="text" name="var_size[]" maxlength="20" placeholder="200 ml" aria-label="<?= e(t('beauty.f.volume')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-soins-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /soins decl -->

        <div<?= $secAttr('autre') ?>>
        <?php
        $aRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $aRows[] = ['name' => (string) ($attr['size'] ?? ($v['label'] ?? '')), 'hex' => (string) ($attr['hex'] ?? '#C9A06A'), 'stock' => $v['stock'], 'price' => $v['price_cents'] ?? null];
        }
        ?>
        <details class="variants-box" data-autre-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('variant.section_generic')) ?></summary>
            <p class="hint"><?= e(t('autre.decl_hint')) ?></p>
            <input type="hidden" name="var_has_color" value="0">
            <div class="grid-2">
                <div>
                    <label for="autre-axis"><?= e(t('autre.axis')) ?></label>
                    <input type="text" id="autre-axis" name="variant_axis" maxlength="24" value="<?= e($autreAxis) ?>" placeholder="<?= e(t('autre.axis_ph')) ?>" list="autre-axis-list" data-autre-axis>
                    <datalist id="autre-axis-list"><?php foreach (beauty_autre('axes') as $ax): ?><option value="<?= e($ax) ?>"></option><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label class="check-row" style="margin-top:24px"><input type="checkbox" name="var_has_color" value="1" data-autre-color-toggle <?= $autreHasColor ? 'checked' : '' ?>><span><?= e(t('autre.has_color')) ?></span></label>
                </div>
            </div>
            <div class="bvariant-rows autre-rows<?= $autreHasColor ? ' has-color' : '' ?>" data-autre-rows>
                <div class="bvariant-head autre-head">
                    <span data-autre-axis-label><?= e($autreAxis !== '' ? $autreAxis : t('variant.option')) ?></span>
                    <span class="autre-col-color"><?= e(t('perruque.f.color')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($aRows as $ar): ?>
                    <div class="bvariant-row autre-row">
                        <input type="text" name="var_size[]" value="<?= e($ar['name']) ?>" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                        <input type="color" name="var_hex[]" class="autre-col-color" value="<?= e($ar['hex'] !== '' ? $ar['hex'] : '#C9A06A') ?>" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $ar['stock'] !== null ? (int) $ar['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($ar['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-autre-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-autre-add>+ <?= e(t('autre.option_add')) ?></button>
            <template id="autre-variant-template">
                <div class="bvariant-row autre-row">
                    <input type="text" name="var_size[]" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                    <input type="color" name="var_hex[]" class="autre-col-color" value="#C9A06A" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-autre-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /autre decl -->
        <?php else: ?>
        <?php if ($isPhone): ?>
        <?php
        $accRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $accRows[] = ['name' => (string) ($attr['size'] ?? ($v['label'] ?? '')), 'hex' => (string) ($attr['hex'] ?? '#222222'), 'stock' => $v['stock'], 'price' => $v['price_cents'] ?? null];
        }
        ?>
        <div><!-- éditeur de déclinaisons à axe libre : partagé entre rayon connu (elec) et « autre » -->
        <details class="variants-box" data-elec-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e(t('variant.section_generic')) ?></summary>
            <p class="hint"><?= e(t('elec.decl_hint')) ?></p>
            <input type="hidden" name="var_has_color" value="0">
            <div class="grid-2">
                <div>
                    <label for="acc-axis"><?= e(t('autre.axis')) ?></label>
                    <input type="text" id="acc-axis" name="variant_axis" maxlength="24" value="<?= e($accAxis) ?>" placeholder="<?= e(t('elec.axis_ph')) ?>" list="acc-axis-list" data-elec-axis>
                    <datalist id="acc-axis-list"><?php foreach (elec_axes() as $ax): ?><option value="<?= e($ax) ?>"></option><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label class="check-row" style="margin-top:24px"><input type="checkbox" name="var_has_color" value="1" data-elec-color-toggle <?= $accHasColor ? 'checked' : '' ?>><span><?= e(t('autre.has_color')) ?></span></label>
                </div>
            </div>
            <div class="bvariant-rows autre-rows<?= $accHasColor ? ' has-color' : '' ?>" data-elec-rows>
                <div class="bvariant-head autre-head">
                    <span data-elec-axis-label><?= e($accAxis !== '' ? $accAxis : t('variant.option')) ?></span>
                    <span class="autre-col-color"><?= e(t('perruque.f.color')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($accRows as $ar): ?>
                    <div class="bvariant-row autre-row">
                        <input type="text" name="var_size[]" value="<?= e($ar['name']) ?>" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                        <input type="color" name="var_hex[]" class="autre-col-color" value="<?= e($ar['hex'] !== '' ? $ar['hex'] : '#222222') ?>" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $ar['stock'] !== null ? (int) $ar['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($ar['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-elec-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-elec-add>+ <?= e(t('autre.option_add')) ?></button>
            <template id="elec-variant-template">
                <div class="bvariant-row autre-row">
                    <input type="text" name="var_size[]" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                    <input type="color" name="var_hex[]" class="autre-col-color" value="#222222" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-elec-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /accessoires decl -->
        <?php endif; ?>
        <div<?= $isPhone ? $eSec('phone') : ($isApparel ? $aSec('basic') : '') ?>>
        <details class="variants-box" <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e($varSection) ?></summary>
            <p class="hint"><?= e($varHint) ?></p>
            <div class="axis-suggest" data-axis-suggest<?= $sizeOpts === [] ? ' hidden' : '' ?>>
                <span class="axis-suggest-label"><strong data-axis-suggest-label><?= e($sizeLabel) ?></strong> · <?= e(t('variant.suggest_hint')) ?></span>
                <div class="axis-suggest-chips" data-axis-suggest-chips>
                    <?php foreach ($sizeOpts as $o): ?><button type="button" class="axis-chip" data-axis-chip><?= e((string) $o) ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="variant-rows variant-rows--sc" id="variant-rows" data-variant-rows
                 data-size-map="<?= e((string) json_encode(apparel_size_map(), JSON_UNESCAPED_UNICODE)) ?>"
                 data-axes="<?= e((string) json_encode(rayon_axes(), JSON_UNESCAPED_UNICODE)) ?>"
                 data-base-label="<?= e($baseLabel) ?>" data-base-ph="<?= e($basePh) ?>"
                 data-base-opts="<?= e((string) json_encode(array_values($baseOpts), JSON_UNESCAPED_UNICODE)) ?>">
                <div class="variant-head">
                    <span data-axis-label><?= e($sizeLabel) ?></span>
                    <span><?= e(t('variant.color')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($realVariants as $v):
                    $attr  = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
                    $vSize = (string) ($attr['size'] ?? '');
                    $vColor = (string) ($attr['color'] ?? '');
                    if ($vSize === '' && $vColor === '' && trim((string) ($v['label'] ?? '')) !== '') { $vSize = (string) $v['label']; }
                ?>
                    <div class="variant-row">
                        <input type="text" list="size-suggest" name="var_size[]" value="<?= e($vSize) ?>" maxlength="60" placeholder="<?= e($sizePh) ?>" aria-label="<?= e($sizeLabel) ?>">
                        <input type="text" list="color-suggest" name="var_color[]" value="<?= e($vColor) ?>" maxlength="60" placeholder="<?= e(t('variant.color_ph')) ?>" aria-label="<?= e(t('variant.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $v['stock'] !== null ? (int) $v['stock'] : '' ?>" placeholder="<?= e(t('variant.stock_ph')) ?>" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($v['price_cents'] ?? null)) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-variant-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-variant-add>+ <?= e(t('variant.add')) ?></button>
            <template id="variant-template">
                <div class="variant-row">
                    <input type="text" list="size-suggest" name="var_size[]" maxlength="60" placeholder="<?= e($sizePh) ?>" aria-label="<?= e($sizeLabel) ?>">
                    <input type="text" list="color-suggest" name="var_color[]" maxlength="60" placeholder="<?= e(t('variant.color_ph')) ?>" aria-label="<?= e(t('variant.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="<?= e(t('variant.stock_ph')) ?>" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-variant-del aria-label="✕">✕</button>
                </div>
            </template>
            <datalist id="size-suggest"><?php foreach ($sizeOpts as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?></datalist>
            <datalist id="color-suggest"><?php foreach (['Noir','Blanc','Gris','Rouge','Bleu','Vert','Jaune','Orange','Rose','Violet','Marron','Beige'] as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?></datalist>
        </details>
        </div><!-- /phone decl (ou générique) -->
        <?php if ($isApparel): ?>
        <?php
        $appaRows = [];
        foreach ($realVariants as $v) {
            $attr = is_array($v['attributes'] ?? null) ? $v['attributes'] : (json_decode((string) ($v['attributes'] ?? ''), true) ?: []);
            $appaRows[] = ['name' => (string) ($attr['size'] ?? ($v['label'] ?? '')), 'hex' => (string) ($attr['hex'] ?? '#222222'), 'stock' => $v['stock'], 'price' => $v['price_cents'] ?? null];
        }
        ?>
        <div<?= $aSec('adaptive') ?>>
        <details class="variants-box" data-appa-decl <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>📏 <?= e(t('appa.sizes')) ?></summary>
            <p class="hint" data-appa-sizes-hint><?= e(t('appa.sizes_hint')) ?></p>
            <input type="hidden" name="var_has_color" value="0">
            <div class="chips-row" data-appa-quickfill>
                <?php foreach (apparel_quickfill_resolved($appaRayonSSR, $appaType, $appaGenreSel) as $qf): ?>
                    <?php $isCol = ($qf['kind'] ?? '') === 'color'; ?>
                    <button type="button" class="axis-chip<?= $isCol ? ' axis-chip--color' : '' ?>" data-appa-fill data-fill="<?= e((string) json_encode($qf, JSON_UNESCAPED_UNICODE)) ?>"><?php if ($isCol): ?><span class="axis-dot" style="background:<?= e((string) ($qf['hex'] ?? '#222')) ?>"></span><?php endif; ?><?= $isCol ? '' : '+ ' ?><?= e((string) $qf['label']) ?></button>
                <?php endforeach; ?>
                <button type="button" class="axis-chip" data-appa-clear><?= e(t('appa.clear')) ?></button>
            </div>
            <div class="grid-2">
                <div>
                    <label for="appa-axis"><?= e(t('autre.axis')) ?></label>
                    <input type="text" id="appa-axis" name="variant_axis" maxlength="24" value="<?= e($appaAxis) ?>" placeholder="<?= e(t('appa.axis_ph')) ?>" list="appa-axis-list" data-appa-axis>
                    <datalist id="appa-axis-list"><?php foreach (apparel_axes() as $ax): ?><option value="<?= e($ax) ?>"></option><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label class="check-row" style="margin-top:24px"><input type="checkbox" name="var_has_color" value="1" data-appa-color-toggle <?= $appaHasColor ? 'checked' : '' ?>><span><?= e(t('autre.has_color')) ?></span></label>
                </div>
            </div>
            <div class="bvariant-rows autre-rows<?= $appaHasColor ? ' has-color' : '' ?>" data-appa-rows>
                <div class="bvariant-head autre-head">
                    <span data-appa-axis-label><?= e($appaAxis !== '' ? $appaAxis : t('variant.option')) ?></span>
                    <span class="autre-col-color"><?= e(t('perruque.f.color')) ?></span>
                    <span><?= e(t('variant.stock')) ?></span>
                    <span><?= e(t('variant.price_opt')) ?></span>
                    <span></span>
                </div>
                <?php foreach ($appaRows as $ar): ?>
                    <div class="bvariant-row autre-row">
                        <input type="text" name="var_size[]" value="<?= e($ar['name']) ?>" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                        <input type="color" name="var_hex[]" class="autre-col-color" value="<?= e($ar['hex'] !== '' ? $ar['hex'] : '#222222') ?>" aria-label="<?= e(t('perruque.f.color')) ?>">
                        <input type="text" name="var_stock[]" inputmode="numeric" value="<?= $ar['stock'] !== null ? (int) $ar['stock'] : '' ?>" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                        <input type="text" name="var_price[]" inputmode="decimal" value="<?= e($fmtP($ar['price'])) ?>" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                        <button type="button" class="variant-del" data-appa-del aria-label="✕">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-appa-add>+ <?= e(t('appa.size_add')) ?></button>
            <template id="appa-variant-template">
                <div class="bvariant-row autre-row">
                    <input type="text" name="var_size[]" maxlength="60" placeholder="<?= e(t('variant.option')) ?>" aria-label="<?= e(t('variant.option')) ?>">
                    <input type="color" name="var_hex[]" class="autre-col-color" value="#222222" aria-label="<?= e(t('perruque.f.color')) ?>">
                    <input type="text" name="var_stock[]" inputmode="numeric" placeholder="∞" aria-label="<?= e(t('variant.stock')) ?>">
                    <input type="text" name="var_price[]" inputmode="decimal" placeholder="<?= e(t('variant.price_ph')) ?>" aria-label="<?= e(t('variant.price_opt')) ?>">
                    <button type="button" class="variant-del" data-appa-del aria-label="✕">✕</button>
                </div>
            </template>
        </details>
        </div><!-- /mode adaptatif decl -->
        <?php endif; ?>
        <?php endif; ?>

        <label for="p-desc"><?= e(t('product.f.description')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <textarea id="p-desc" name="description" rows="4" maxlength="<?= (int) config('shop.product_desc_max', 3000) ?>"><?= old('description') ?: e((string) ($product['description'] ?? '')) ?></textarea>
        <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

        <?php if ($isBeauty): ?>
        <label for="p-inci"><?= e(t('beauty.f.ingredients')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <textarea id="p-inci" name="ingredients" rows="3" maxlength="2000" placeholder="Aqua, Glycerin, Titanium Dioxide…"><?= e($bcur('ingredients')) ?></textarea>
        <p class="hint"><?= e(t('beauty.f.ingredients_hint')) ?></p>
        <?php endif; ?>

        <label><?= e(t('product.f.photos', ['max' => $maxPhotos])) ?></label>
        <div class="upload-zone" id="product-photo-zone">
            <div class="upload-actions">
                <label class="btn btn-ghost btn-sm" for="product-photo-input">📁 <?= e(t('listing.btn.choose_files')) ?></label>
            </div>
            <p class="hint"><?= e(t('product.f.photos_hint')) ?></p>
            <input type="file" id="product-photo-input" class="file-hidden" accept="image/jpeg,image/png,image/webp" multiple>
        </div>
        <div class="upload-previews" id="product-previews">
            <?php foreach ($photos as $ph): ?>
                <div class="preview" data-public-id="<?= e((string) $ph['cloud_public_id']) ?>">
                    <img src="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 120, 120)) ?>" alt="">
                    <button type="button" class="preview-remove">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (has_error('photos')): ?><p class="field-error"><?= e(error('photos')) ?></p><?php endif; ?>
        <p class="field-error" id="product-photo-error" hidden></p>

        <?php $maxV = (int) config('shop.product_max_video_seconds', 120); ?>
        <label><?= e(t('product.f.video', ['max' => (int) ($maxV / 60)])) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <div class="upload-zone" id="product-video-zone" data-max-seconds="<?= $maxV ?>"
             data-long="<?= e(t('validation.video_too_long', ['max' => $maxV])) ?>" data-big="<?= e(t('product.video_too_big')) ?>" data-fail="<?= e(t('product.video_fail')) ?>">
            <div class="upload-actions"><label class="btn btn-ghost btn-sm" for="product-video-input">🎬 <?= e(t('product.f.pick_video')) ?></label></div>
            <p class="hint"><?= e(t('product.f.video_hint', ['max' => (int) ($maxV / 60)])) ?></p>
            <input type="file" id="product-video-input" class="file-hidden" accept="video/mp4,video/quicktime,video/webm">
            <input type="hidden" name="video_public_id" id="product-video-id" value="<?= e((string) ($product['video_public_id'] ?? '')) ?>">
        </div>
        <div class="upload-previews" id="product-video-preview">
            <?php if ($isEdit && !empty($product['video_public_id'])): ?>
                <div class="preview preview-video">
                    <video controls preload="none" src="<?= e(CloudinaryService::videoUrl((string) $product['video_public_id'])) ?>"></video>
                    <button type="button" class="preview-remove" id="product-video-remove">✕</button>
                </div>
            <?php endif; ?>
        </div>
        <p class="field-error" id="product-video-error" hidden></p>

        <label class="check-row">
            <input type="hidden" name="status" value="hidden">
            <input type="checkbox" name="status" value="active" <?= (!$isEdit || ($product['status'] ?? '') === 'active') ? 'checked' : '' ?>>
            <span><?= e(t('product.f.visible')) ?></span>
        </label>

        <button type="submit" class="btn btn-primary btn-block" id="product-submit"><?= e($isEdit ? t('profile.save') : t('product.add')) ?></button>
    </form>
    <aside class="pf-preview" data-pv-root data-cur="<?= e($cur) ?>" data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>">
        <p class="pv-eyebrow"><?= e(t('beauty.preview.title')) ?></p>
        <div class="pv-card">
            <div class="pv-img" data-pv-img><span class="pv-img-empty"><?= e(t('beauty.preview.add_photo')) ?></span></div>
            <div class="pv-body">
                <div class="pv-brand" data-pv-out="brand"></div>
                <div class="pv-name" data-pv-out="name"><?= e(t('beauty.preview.name_ph')) ?></div>
                <div class="pv-meta"><span data-pv-out="type"></span><span data-pv-out="vol"></span></div>
                <div class="pv-price">
                    <span class="pv-now" data-pv-out="price"></span>
                    <del class="pv-old" data-pv-out="old" hidden></del>
                    <span class="pv-badge" data-pv-out="disc" hidden></span>
                </div>
                <div class="pv-tones" data-pv-tones></div>
                <div class="pv-note" data-pv-out="stock"></div>
            </div>
        </div>
    </aside>
    </div>
    <?php endif; ?>

    <p class="auth-alt"><a href="<?= e(url('/boutique/gerer')) ?>">← <?= e(t('shop.back_manage')) ?></a></p>
</section>
