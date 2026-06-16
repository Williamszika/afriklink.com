<?php
/** @var int $step  @var array $draft  @var array $user  @var bool $media_ready  @var string $suggestSlug */
use App\Services\CloudinaryService;

$s1 = $draft['step1'] ?? [];
$s2 = $draft['step2'] ?? [];
$cats = config('listings.categories', []);
$zones = config('shop.delivery_zones', []);
$methods = config('shop.delivery_methods', []);
$preps = config('shop.prep_options', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$val = static fn (string $k, array $d): string => old($k) !== '' ? old($k) : e((string) ($d[$k] ?? ''));
$steps = [1 => t('shop.step1'), 2 => t('shop.step2'), 3 => t('shop.step3')];
$baseUrl = rtrim((string) (config('app.url') ?: 'afriklink.com'), '/');
$baseUrl = preg_replace('#^https?://#', '', $baseUrl);
?>
<section class="auth-card auth-card--wide">
    <h1>🛍️ <?= e(t('shop.title')) ?></h1>
    <p class="muted"><?= e(t('shop.subtitle')) ?></p>

    <ol class="wizard-steps">
        <?php foreach ($steps as $n => $label): ?>
            <li class="<?= $n === $step ? 'is-current' : ($n < $step ? 'is-done' : '') ?>">
                <span class="wizard-num"><?= $n < $step ? '✓' : $n ?></span>
                <span class="wizard-label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (!$media_ready): ?>
        <div class="notice notice-warning"><p><?= e(t('listing.media_unconfigured')) ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/boutique/creer')) ?>" id="shop-form" novalidate
          data-slug-url="<?= e(url('/api/boutique/slug')) ?>"
          data-slug-ok="<?= e(t('shop.slug_ok')) ?>" data-slug-taken="<?= e(t('shop.slug_taken')) ?>"
          data-slug-short="<?= e(t('shop.slug_short')) ?>" data-uploading="<?= e(t('kyc.uploading')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="etape" value="<?= (int) $step ?>">

        <?php if ($step === 1): ?>
            <h2 class="wizard-h2"><?= e(t('shop.step1_title')) ?></h2>

            <label for="shop-name"><?= e(t('shop.f.name')) ?></label>
            <input type="text" id="shop-name" name="name" value="<?= old('name') ?: e((string) ($s1['name'] ?? ($user['full_name'] ?? ''))) ?>"
                   required maxlength="<?= (int) config('shop.name_max', 80) ?>" placeholder="<?= e(t('shop.f.name_ph')) ?>">
            <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

            <label for="shop-slug"><?= e(t('shop.f.slug')) ?></label>
            <div class="slug-row">
                <span class="slug-prefix"><?= e($baseUrl) ?>/boutique/</span>
                <input type="text" id="shop-slug" name="slug" value="<?= old('slug') ?: e((string) ($s1['slug'] ?? $suggestSlug)) ?>"
                       maxlength="<?= (int) config('shop.slug_max', 40) ?>" autocomplete="off" spellcheck="false">
            </div>
            <p class="hint" id="slug-status"><?= e(t('shop.f.slug_hint')) ?></p>
            <?php if (has_error('slug')): ?><p class="field-error"><?= e(error('slug')) ?></p><?php endif; ?>

            <label><?= e(t('shop.f.logo')) ?></label>
            <div class="upload-zone" id="logo-zone">
                <label class="btn btn-ghost btn-sm" for="logo-input">🖼️ <?= e(t('shop.f.pick_logo')) ?></label>
                <input type="file" id="logo-input" class="file-hidden" accept="image/jpeg,image/png,image/webp">
                <input type="hidden" name="logo_public_id" id="logo-public-id" value="<?= e((string) ($s1['logo_public_id'] ?? '')) ?>">
                <span class="kyc-slot-state" id="logo-state"></span>
            </div>

            <label><?= e(t('shop.f.banner', ['max' => (int) config('shop.banner_max', 10)])) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="upload-zone" id="banner-zone" data-max="<?= (int) config('shop.banner_max', 10) ?>">
                <div class="upload-actions"><label class="btn btn-ghost btn-sm" for="banner-input">🏞️ <?= e(t('shop.f.pick_banner')) ?></label></div>
                <p class="hint"><?= e(t('shop.f.banner_hint')) ?></p>
                <input type="file" id="banner-input" class="file-hidden" accept="image/jpeg,image/png,image/webp" multiple>
                <input type="hidden" name="banners_json" id="banners-json" value="<?= e(json_encode($s1['banner_ids'] ?? [])) ?>">
                <span class="kyc-slot-state" id="banner-state"></span>
            </div>
            <div class="upload-previews" id="banner-previews">
                <?php foreach (($s1['banner_ids'] ?? []) as $bid): ?>
                    <div class="preview" data-public-id="<?= e((string) $bid) ?>">
                        <img src="<?= e(CloudinaryService::imageUrl((string) $bid, 200, 112)) ?>" alt="">
                        <button type="button" class="preview-remove">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <label for="shop-tagline"><?= e(t('shop.f.tagline')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="shop-tagline" name="tagline" value="<?= $val('tagline', $s1) ?>"
                   maxlength="<?= (int) config('shop.tagline_max', 120) ?>" placeholder="<?= e(t('shop.f.tagline_ph')) ?>">

            <label for="shop-desc"><?= e(t('shop.f.description')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <textarea id="shop-desc" name="description" rows="3" maxlength="<?= (int) config('shop.desc_max', 1500) ?>"><?= $val('description', $s1) ?></textarea>
            <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

            <label for="shop-cat"><?= e(t('shop.f.category')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <select id="shop-cat" name="category" data-shop-cat>
                <option value=""><?= e(t('field.choose')) ?></option>
                <?php $selCat = old('category') ?: (string) ($s1['category'] ?? ''); ?>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= e($c) ?>" data-vertical="<?= e(product_vertical($c)) ?>" <?= $selCat === $c ? 'selected' : '' ?>><?= e(t('listing.cat.' . $c)) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint cat-phone-hint" data-cat-phone-hint<?= product_vertical($selCat) === 'phone' ? '' : ' hidden' ?>>📱 <?= e(t('shop.f.category_phone_hint')) ?></p>

            <?= render_partial('partials/contact_fields', [
                'values'  => $s1['contacts'] ?? [],
                'primary' => (array) ($s1['contact_primary'] ?? []),
            ]) ?>

            <button type="submit" class="btn btn-primary btn-block"><?= e(t('pro.next')) ?> →</button>

        <?php elseif ($step === 2): ?>
            <h2 class="wizard-h2"><?= e(t('shop.step2_title')) ?></h2>

            <?php $selType = old('shop_type') ?: (string) ($s2['shop_type'] ?? ''); ?>
            <label><?= e(t('shop.f.type')) ?></label>
            <div class="shop-type-choice">
                <label class="type-card">
                    <input type="radio" name="shop_type" value="physical" <?= $selType === 'physical' ? 'checked' : '' ?> required>
                    <span class="type-card-body">
                        <strong>🏬 <?= e(t('shop.type.physical')) ?></strong>
                        <span class="muted"><?= e(t('shop.type.physical_desc')) ?></span>
                    </span>
                </label>
                <label class="type-card">
                    <input type="radio" name="shop_type" value="online" <?= $selType === 'online' ? 'checked' : '' ?> required>
                    <span class="type-card-body">
                        <strong>🌐 <?= e(t('shop.type.online')) ?></strong>
                        <span class="muted"><?= e(t('shop.type.online_desc')) ?></span>
                    </span>
                </label>
            </div>
            <?php if (has_error('shop_type')): ?><p class="field-error"><?= e(error('shop_type')) ?></p><?php endif; ?>

            <div id="shop-address-wrap" <?= $selType === 'physical' ? '' : 'hidden' ?>>
                <label for="shop-address"><?= e(t('shop.f.address')) ?></label>
                <input type="text" id="shop-address" name="address"
                       value="<?= old('address') ?: e((string) ($s2['address'] ?? '')) ?>" maxlength="220"
                       placeholder="<?= e(t('pro.field.address_ph')) ?>" autocomplete="street-address">
                <p class="hint"><?= e(t('shop.f.address_hint')) ?></p>
                <?php if (has_error('address')): ?><p class="field-error"><?= e(error('address')) ?></p><?php endif; ?>
                <?php $autoGeo = detected_geo(); ?>
                <?= render_partial('partials/geo_fields', [
                    'city'      => old('city') !== '' ? old('city') : ((string) ($s2['city'] ?? '') ?: (string) ($autoGeo['city'] ?? '')),
                    'cc'        => old('country_code') !== '' ? old('country_code') : ((string) ($s2['country_code'] ?? '') ?: (string) ($autoGeo['country_code'] ?? '')),
                    'continent' => $s2['continent'] ?? ($autoGeo['continent'] ?? null),
                    'lat'       => old('geo_lat') !== '' ? old('geo_lat') : ((string) ($s2['geo_lat'] ?? '') ?: (string) ($autoGeo['lat'] ?? '')),
                    'lng'       => old('geo_lng') !== '' ? old('geo_lng') : ((string) ($s2['geo_lng'] ?? '') ?: (string) ($autoGeo['lng'] ?? '')),
                ]) ?>
            </div>

            <label for="shop-cur"><?= e(t('shop.f.currency')) ?></label>
            <select id="shop-cur" name="currency">
                <?php $selCur = old('currency') ?: (string) ($s2['currency'] ?? ($user['preferred_currency'] ?? 'EUR')); ?>
                <?php foreach ($currencies as $cur): ?>
                    <option value="<?= e($cur) ?>" <?= $selCur === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                <?php endforeach; ?>
            </select>

            <label><?= e(t('shop.f.zones')) ?></label>
            <?php $selZones = old('zones') !== '' ? [] : explode(',', (string) ($s2['delivery_zones'] ?? '')); ?>
            <?php $zCity = old('city') !== '' ? old('city') : (string) ($s2['city'] ?? ''); $zCc = old('country_code') !== '' ? old('country_code') : (string) ($s2['country_code'] ?? ''); ?>
            <div class="lang-checks">
                <?php foreach ($zones as $z): ?>
                    <label class="check-pill"><input type="checkbox" name="zones[]" value="<?= e($z) ?>" <?= in_array($z, $selZones, true) ? 'checked' : '' ?>>
                        <span data-zone-label="<?= e($z) ?>"><?= e(shop_zone_label($z, $zCity, $zCc)) ?></span></label>
                <?php endforeach; ?>
            </div>

            <label><?= e(t('shop.f.methods')) ?></label>
            <?php $selMethods = old('methods') !== '' ? [] : explode(',', (string) ($s2['delivery_methods'] ?? '')); ?>
            <div class="lang-checks">
                <?php foreach ($methods as $m): ?>
                    <label class="check-pill" <?= $m === 'pickup' ? 'data-pickup-pill' : '' ?> <?= ($m === 'pickup' && $selType === 'online') ? 'hidden' : '' ?>>
                        <input type="checkbox" name="methods[]" value="<?= e($m) ?>" <?= in_array($m, $selMethods, true) ? 'checked' : '' ?>>
                        <span><?= e(t('shop.method.' . $m)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <p class="hint" id="online-methods-hint" <?= $selType === 'online' ? '' : 'hidden' ?>><?= e(t('shop.online_delivery_note')) ?></p>
            <?php if (has_error('methods')): ?><p class="field-error"><?= e(error('methods')) ?></p><?php endif; ?>

            <div class="grid-2">
                <div>
                    <label for="shop-free"><?= e(t('shop.f.free_ship')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="shop-free" name="free_ship" value="<?= old('free_ship') ?>" inputmode="decimal" placeholder="0">
                    <p class="hint"><?= e(t('shop.f.free_ship_hint')) ?></p>
                    <?php if (has_error('free_ship')): ?><p class="field-error"><?= e(error('free_ship')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="shop-prep"><?= e(t('shop.f.prep')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <select id="shop-prep" name="prep_time">
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php $selPrep = old('prep_time') ?: (string) ($s2['prep_time'] ?? ''); ?>
                        <?php foreach ($preps as $pp): ?>
                            <option value="<?= e($pp) ?>" <?= $selPrep === $pp ? 'selected' : '' ?>><?= e(t('shop.prep.' . $pp)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="shop-dfee"><?= e(t('shop.f.delivery_fee')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="shop-dfee" name="delivery_fee" value="<?= old('delivery_fee') ?: ($s2['delivery_fee_cents'] ?? '' ? rtrim(rtrim(number_format(((int) $s2['delivery_fee_cents']) / 100, 2, '.', ''), '0'), '.') : '') ?>" inputmode="decimal" placeholder="0">
                </div>
                <div>
                    <label for="shop-dintl"><?= e(t('shop.f.delivery_intl')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="shop-dintl" name="delivery_intl" value="<?= old('delivery_intl') ?: ($s2['delivery_intl_cents'] ?? '' ? rtrim(rtrim(number_format(((int) $s2['delivery_intl_cents']) / 100, 2, '.', ''), '0'), '.') : '') ?>" inputmode="decimal" placeholder="0">
                </div>
            </div>
            <label for="shop-ddelay"><?= e(t('shop.f.delivery_delay')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <select id="shop-ddelay" name="delivery_delay">
                <option value=""><?= e(t('field.choose')) ?></option>
                <?php $selDelay = old('delivery_delay') ?: (string) ($s2['delivery_delay'] ?? ''); ?>
                <?php foreach ($preps as $pp): ?>
                    <option value="<?= e($pp) ?>" <?= $selDelay === $pp ? 'selected' : '' ?>><?= e(t('shop.prep.' . $pp)) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="wizard-nav">
                <a class="btn btn-ghost" href="<?= e(url('/boutique/creer?etape=1')) ?>">← <?= e(t('pro.back')) ?></a>
                <button type="submit" class="btn btn-primary"><?= e(t('pro.next')) ?> →</button>
            </div>

        <?php else: ?>
            <h2 class="wizard-h2"><?= e(t('shop.step3_title')) ?></h2>

            <div class="recap">
                <p class="recap-head"><strong><?= e(t('pro.recap_title')) ?></strong></p>
                <dl class="recap-list">
                    <dt><?= e(t('shop.f.name')) ?></dt><dd><?= e((string) ($s1['name'] ?? '')) ?></dd>
                    <dt><?= e(t('shop.f.slug')) ?></dt><dd><?= e($baseUrl) ?>/boutique/<?= e((string) ($s1['slug'] ?? '')) ?></dd>
                    <dt><?= e(t('shop.f.type')) ?></dt>
                    <dd><?= ($s2['shop_type'] ?? 'online') === 'physical' ? '🏬 ' . e(t('shop.type.physical')) : '🌐 ' . e(t('shop.type.online')) ?></dd>
                    <?php if (!empty($s2['address'])): ?>
                        <dt><?= e(t('shop.f.address')) ?></dt><dd><?= e((string) $s2['address']) ?></dd>
                    <?php endif; ?>
                    <dt><?= e(t('shop.f.currency')) ?></dt><dd><?= e((string) ($s2['currency'] ?? '')) ?></dd>
                </dl>
                <p class="hint"><a href="<?= e(url('/boutique/creer?etape=1')) ?>"><?= e(t('shop.edit1')) ?></a> · <a href="<?= e(url('/boutique/creer?etape=2')) ?>"><?= e(t('shop.edit2')) ?></a></p>
            </div>

            <h3 class="wizard-h2"><?= e(t('shop.f.payment')) ?></h3>
            <?= render_partial('partials/payment_fields', ['terms_sel' => ['on_delivery'], 'methods_sel' => ['cash'], 'provider' => (string) config('payment.default', 'simulation')]) ?>

            <p class="hint"><?= e(t('shop.draft_note')) ?></p>
            <div class="wizard-nav">
                <a class="btn btn-ghost" href="<?= e(url('/boutique/creer?etape=2')) ?>">← <?= e(t('pro.back')) ?></a>
                <button type="submit" class="btn btn-primary"><?= e(t('shop.submit')) ?></button>
            </div>
        <?php endif; ?>
    </form>

    <p class="auth-alt"><a href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a></p>
</section>
