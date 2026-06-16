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
$curRayonSel = old('collection_select');
$curCol = $curRayonSel === '__other__'
    ? (string) old('collection_other')
    : ($curRayonSel !== '' ? $curRayonSel : (string) ($product['collection'] ?? ''));

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
<section class="auth-card <?= $isBeauty ? 'auth-card--beauty' : 'auth-card--wide' ?>">
    <h1>📦 <?= e($isEdit ? t('product.edit_title') : t('product.add_title')) ?></h1>
    <p class="muted"><?= e($boutique['name']) ?> · <?= e($cur) ?></p>

    <?php if (!$media_ready): ?>
        <div class="notice notice-warning"><p><?= e(t('listing.media_unconfigured')) ?></p></div>
    <?php else: ?>
    <div class="<?= $isBeauty ? 'pf-grid' : 'pf-plain' ?>">
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

        <?php if ($vertical === 'phone'): ?>
        <div class="grid-2">
            <div>
                <label for="p-brand"><?= e(t('phone.f.brand')) ?></label>
                <input type="text" id="p-brand" name="brand" list="brand-list" maxlength="60" value="<?= old('brand') ?: e((string) ($product['brand'] ?? '')) ?>" placeholder="<?= e(t('phone.f.brand_ph')) ?>">
                <datalist id="brand-list"><?php foreach (phone_brands() as $br): ?><option value="<?= e($br) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <div>
                <label for="p-model"><?= e(t('phone.f.model')) ?></label>
                <input type="text" id="p-model" name="model" maxlength="80" value="<?= old('model') ?: e((string) ($product['model'] ?? '')) ?>" placeholder="<?= e(t('phone.f.model_ph')) ?>">
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
        <?php elseif ($isApparel): ?>
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
        // Rayon « Ongles » (faux ongles) : sous-formulaire dédié.
        $isOngles = ($curCol === 'Ongles');
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
        $secAttr = static fn (string $sec, bool $on): string => ' data-beauty-section="' . $sec . '"' . ($on ? '' : ' hidden');
        ?>
        <div data-beauty
             data-types="<?= e((string) json_encode(beauty_types(), JSON_UNESCAPED_UNICODE)) ?>"
             data-fields="<?= e((string) json_encode(beauty_fields(), JSON_UNESCAPED_UNICODE)) ?>"
             data-palettes="<?= e((string) json_encode(beauty_palettes(), JSON_UNESCAPED_UNICODE)) ?>"
             data-axes="<?= e((string) json_encode(rayon_axes(), JSON_UNESCAPED_UNICODE)) ?>"
             data-nuances="<?= e((string) json_encode(beauty_nuances(), JSON_UNESCAPED_UNICODE)) ?>"
             data-ongles-hex="<?= e((string) json_encode(ongles_couleur_hex(), JSON_UNESCAPED_UNICODE)) ?>"
             data-hint-specs="<?= e(t('beauty.sec.specs_hint')) ?>" data-hint-pick="<?= e(t('beauty.sec.specs_pick')) ?>" hidden></div>
        <label for="p-brand"><?= e(t('beauty.f.brand')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="p-brand" name="brand" maxlength="60" value="<?= e($bcur('brand')) ?>" placeholder="<?= e(t('beauty.f.brand_ph')) ?>" data-pv="brand">

        <!-- ================= MAQUILLAGE ================= -->
        <div<?= $secAttr('maquillage', !$isOngles) ?>>
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
        <div<?= $secAttr('ongles', $isOngles) ?>>
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

        <details class="variants-box promo-box" <?= ($isEdit && (int) ($product['promo_price_cents'] ?? 0) > 0) || has_error('promo_price') ? 'open' : '' ?>>
            <summary>🏷️ <?= e(t('product.f.promo_section')) ?></summary>
            <p class="hint"><?= e(t('product.f.promo_hint')) ?></p>
            <div class="grid-2">
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
        <div<?= $secAttr('maquillage', !$isOngles) ?>>
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

        <div<?= $secAttr('ongles', $isOngles) ?>>
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
        <?php else: ?>
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
    <?php if ($isBeauty): ?>
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
    <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="auth-alt"><a href="<?= e(url('/boutique/gerer')) ?>">← <?= e(t('shop.back_manage')) ?></a></p>
</section>
