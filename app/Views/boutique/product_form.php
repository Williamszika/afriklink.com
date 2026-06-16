<?php
/** @var string $mode  @var array $boutique  @var ?array $product  @var list<array> $photos  @var bool $media_ready */
use App\Services\CloudinaryService;

$isEdit = $mode === 'edit';
$cur    = (string) $boutique['currency'];
$vertical = product_vertical((string) ($boutique['category'] ?? '')); // 'phone' ou 'apparel'
$isPhone   = $vertical === 'phone';
$sizeLabel = $isPhone ? t('phone.f.storage') : t('variant.size');
$sizePh    = $isPhone ? t('phone.f.storage_ph') : t('variant.size_ph');
$sizeOpts  = $isPhone ? phone_storage() : ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
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
<section class="auth-card auth-card--wide">
    <h1>📦 <?= e($isEdit ? t('product.edit_title') : t('product.add_title')) ?></h1>
    <p class="muted"><?= e($boutique['name']) ?> · <?= e($cur) ?></p>

    <?php if (!$media_ready): ?>
        <div class="notice notice-warning"><p><?= e(t('listing.media_unconfigured')) ?></p></div>
    <?php else: ?>
    <form method="post" action="<?= e(url($action)) ?>" id="product-form" novalidate
          data-uploading="<?= e(t('kyc.uploading')) ?>" data-max="<?= $maxPhotos ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="photos_json" id="product-photos-json" value="<?= e(json_encode($existingIds)) ?>">
        <input type="hidden" name="photos_touched" id="product-photos-touched" value="0">

        <label for="p-name"><?= e(t('product.f.name')) ?></label>
        <input type="text" id="p-name" name="name" value="<?= old('name') ?: e((string) ($product['name'] ?? '')) ?>"
               required maxlength="<?= (int) config('shop.product_name_max', 150) ?>" placeholder="<?= e(t('product.f.name_ph')) ?>">
        <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

        <?php
        $cols   = $collections ?? [];
        $curCol = (string) (old('collection') ?: (string) ($product['collection'] ?? ''));
        $catOpts = [];
        foreach ($cols as $c) { if (trim((string) $c) !== '') { $catOpts[(string) $c] = (string) $c; } }
        foreach ((array) config('listings.categories', []) as $gc) { $lbl = t('listing.cat.' . $gc); $catOpts[$lbl] = $lbl; }
        ksort($catOpts, SORT_NATURAL | SORT_FLAG_CASE);
        $isOther = $curCol !== '' && !isset($catOpts[$curCol]);
        ?>
        <label for="p-collection"><?= e(t('product.f.collection')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <select id="p-collection" name="collection_select" data-collection-select>
            <option value=""><?= e(t('product.f.collection_none')) ?></option>
            <?php foreach ($catOpts as $val => $lbl): ?>
                <option value="<?= e((string) $val) ?>" <?= $curCol === (string) $val ? 'selected' : '' ?>><?= e((string) $lbl) ?></option>
            <?php endforeach; ?>
            <option value="__other__" <?= $isOther ? 'selected' : '' ?>><?= e(t('product.f.collection_other')) ?></option>
        </select>
        <input type="text" id="p-collection-other" name="collection_other" maxlength="60" data-collection-other
               value="<?= $isOther ? e($curCol) : '' ?>" placeholder="<?= e(t('product.f.collection_ph')) ?>"<?= $isOther ? '' : ' hidden' ?>>
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
        <?php else: ?>
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
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="p-price"><?= e(t('product.f.price', ['cur' => $cur])) ?></label>
                <input type="text" id="p-price" name="price" value="<?= old('price') ?: e($priceVal) ?>" inputmode="decimal" required placeholder="0">
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
                    <input type="text" id="p-promo" name="promo_price" inputmode="decimal" value="<?= old('promo_price') ?: ($isEdit ? e($fmtP($product['promo_price_cents'] ?? null)) : '') ?>" placeholder="<?= e(t('product.f.promo_price_ph')) ?>">
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

        <details class="variants-box" <?= $realVariants !== [] ? 'open' : '' ?>>
            <summary>🎚️ <?= e($isPhone ? t('phone.f.variants') : t('variant.section')) ?></summary>
            <p class="hint"><?= e($isPhone ? t('phone.f.variants_hint') : t('variant.hint')) ?></p>
            <div class="variant-rows variant-rows--sc" id="variant-rows" data-variant-rows data-size-map="<?= e((string) json_encode(apparel_size_map(), JSON_UNESCAPED_UNICODE)) ?>">
                <div class="variant-head">
                    <span><?= e($sizeLabel) ?></span>
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

        <label for="p-desc"><?= e(t('product.f.description')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <textarea id="p-desc" name="description" rows="4" maxlength="<?= (int) config('shop.product_desc_max', 3000) ?>"><?= old('description') ?: e((string) ($product['description'] ?? '')) ?></textarea>
        <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

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
    <?php endif; ?>

    <p class="auth-alt"><a href="<?= e(url('/boutique/gerer')) ?>">← <?= e(t('shop.back_manage')) ?></a></p>
</section>
