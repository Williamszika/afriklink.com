<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var array $boutique  @var bool $media_ready  @var list<string> $banners */
use App\Services\BusinessHours;
use App\Services\CloudinaryService;

$cats = config('listings.categories', []);
$zones = config('shop.delivery_zones', []);
$methods = config('shop.delivery_methods', []);
$preps = config('shop.prep_options', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$v = static fn (string $k): string => old($k) !== '' ? old($k) : e((string) ($boutique[$k] ?? ''));
$selType = old('shop_type') ?: (string) ($boutique['shop_type'] ?? 'online');
$selZones = old('zones') !== '' ? [] : array_filter(explode(',', (string) ($boutique['delivery_zones'] ?? '')));
$selMethods = old('methods') !== '' ? [] : array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? '')));
$selCat = old('category') ?: (string) ($boutique['category'] ?? '');
$selCur = old('currency') ?: (string) ($boutique['currency'] ?? 'EUR');
$selPrep = old('prep_time') ?: (string) ($boutique['prep_time'] ?? '');
$fmtCents = static fn ($c) => !empty($c) ? rtrim(rtrim(number_format(((int) $c) / 100, 2, '.', ''), '0'), '.') : '';
$freeVal = old('free_ship') ?: $fmtCents($boutique['free_ship_cents'] ?? null);
$dfeeVal = old('delivery_fee') ?: $fmtCents($boutique['delivery_fee_cents'] ?? null);
$dintlVal = old('delivery_intl') ?: $fmtCents($boutique['delivery_intl_cents'] ?? null);
$selDelay = old('delivery_delay') ?: (string) ($boutique['delivery_delay'] ?? '');
$baseUrl = preg_replace('#^https?://#', '', rtrim((string) (config('app.url') ?: 'afriklink.com'), '/'));
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1>✏️ <?= e(t('shop.edit_title')) ?></h1>
            <p class="muted shop-url-row">
                <span><?= e($baseUrl) ?>/boutique/<?= e((string) $boutique['slug']) ?></span>
                <button type="button" class="btn-copy" data-copy="<?= e(url('/boutique/' . $boutique['slug'])) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>" aria-label="<?= e(t('shop.copy_url')) ?>" title="<?= e(t('shop.copy_url')) ?>"><span class="ico-copy" aria-hidden="true">⧉</span> <?= e(t('shop.copy_url')) ?></button>
            </p>
        </div>

        <form method="post" action="<?= e(url('/boutique/modifier')) ?>" id="shop-form" novalidate
              data-slug-url="<?= e(url('/api/boutique/slug')) ?>" data-slug-ok="<?= e(t('shop.slug_ok')) ?>"
              data-slug-taken="<?= e(t('shop.slug_taken')) ?>" data-slug-short="<?= e(t('shop.slug_short')) ?>" data-uploading="<?= e(t('kyc.uploading')) ?>">
            <?= csrf_field() ?>

            <div class="panel">
                <h2 class="panel-title"><?= e(t('shop.step1_title')) ?></h2>
                <label for="shop-name"><?= e(t('shop.f.name')) ?></label>
                <input type="text" id="shop-name" name="name" value="<?= $v('name') ?>" required maxlength="<?= (int) config('shop.name_max', 80) ?>">
                <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

                <label for="shop-slug"><?= e(t('shop.f.slug')) ?></label>
                <div class="slug-row"><span class="slug-prefix"><?= e($baseUrl) ?>/boutique/</span>
                    <input type="text" id="shop-slug" name="slug" value="<?= $v('slug') ?>" maxlength="<?= (int) config('shop.slug_max', 40) ?>" autocomplete="off"></div>
                <p class="hint" id="slug-status"></p>
                <?php if (has_error('slug')): ?><p class="field-error"><?= e(error('slug')) ?></p><?php endif; ?>

                <label><?= e(t('shop.f.logo')) ?></label>
                <div class="upload-zone" id="logo-zone">
                    <div class="upload-actions"><label class="btn btn-ghost btn-sm" for="logo-input">🖼️ <?= e(t('shop.f.pick_logo')) ?></label></div>
                    <input type="file" id="logo-input" class="file-hidden" accept="image/jpeg,image/png,image/webp">
                    <input type="hidden" name="logo_public_id" id="logo-public-id" value="<?= e((string) ($boutique['logo_public_id'] ?? '')) ?>">
                    <span class="kyc-slot-state" id="logo-state"><?= !empty($boutique['logo_public_id']) ? '✅' : '' ?></span>
                </div>

                <label><?= e(t('shop.f.banner', ['max' => (int) config('shop.banner_max', 10)])) ?></label>
                <div class="upload-zone" id="banner-zone" data-max="<?= (int) config('shop.banner_max', 10) ?>">
                    <div class="upload-actions"><label class="btn btn-ghost btn-sm" for="banner-input">🏞️ <?= e(t('shop.f.pick_banner')) ?></label></div>
                    <p class="hint"><?= e(t('shop.f.banner_hint')) ?></p>
                    <input type="file" id="banner-input" class="file-hidden" accept="image/jpeg,image/png,image/webp" multiple>
                    <input type="hidden" name="banners_json" id="banners-json" value="<?= e(json_encode($banners ?? [])) ?>">
                    <span class="kyc-slot-state" id="banner-state"></span>
                </div>
                <div class="upload-previews" id="banner-previews">
                    <?php foreach (($banners ?? []) as $bid): ?>
                        <div class="preview" data-public-id="<?= e((string) $bid) ?>">
                            <img src="<?= e(CloudinaryService::imageUrl((string) $bid, 200, 112)) ?>" alt="">
                            <button type="button" class="preview-remove">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label for="shop-tagline"><?= e(t('shop.f.tagline')) ?></label>
                <input type="text" id="shop-tagline" name="tagline" value="<?= $v('tagline') ?>" maxlength="<?= (int) config('shop.tagline_max', 120) ?>">
                <label for="shop-desc"><?= e(t('shop.f.description')) ?></label>
                <textarea id="shop-desc" name="description" rows="3" maxlength="<?= (int) config('shop.desc_max', 1500) ?>"><?= $v('description') ?></textarea>
                <label><?= e(t('shop.f.category')) ?></label>
                <div class="locked-field">
                    <span><?= !empty($boutique['category']) ? e(t('listing.cat.' . $boutique['category'])) : '—' ?></span>
                    <span class="locked-badge"><?= icon('shield', ['size' => 13]) ?> <?= e(t('shop.f.category_locked')) ?></span>
                </div>
                <p class="hint"><?= e(t('shop.f.category_lock_hint')) ?></p>

                <?php [$ctVals, $ctPrimary] = \App\Services\ContactChannels::forBoutique($boutique); ?>
                <?= render_partial('partials/contact_fields', ['values' => $ctVals, 'primary' => $ctPrimary, 'country' => (string) ($boutique['country_code'] ?? '')]) ?>
            </div>

            <div class="panel">
                <h2 class="panel-title"><?= e(t('shop.step2_title')) ?></h2>
                <label><?= e(t('shop.f.type')) ?></label>
                <div class="shop-type-choice">
                    <label class="type-card"><input type="radio" name="shop_type" value="physical" <?= $selType === 'physical' ? 'checked' : '' ?>>
                        <span class="type-card-body"><strong>🏬 <?= e(t('shop.type.physical')) ?></strong></span></label>
                    <label class="type-card"><input type="radio" name="shop_type" value="online" <?= $selType === 'online' ? 'checked' : '' ?>>
                        <span class="type-card-body"><strong>🌐 <?= e(t('shop.type.online')) ?></strong></span></label>
                </div>
                <div id="shop-address-wrap" <?= $selType === 'physical' ? '' : 'hidden' ?>>
                    <label for="shop-address"><?= e(t('shop.f.address')) ?></label>
                    <input type="text" id="shop-address" name="address" value="<?= $v('address') ?>" maxlength="220">
                    <?php if (has_error('address')): ?><p class="field-error"><?= e(error('address')) ?></p><?php endif; ?>
                    <?php $autoGeo = detected_geo(); ?>
                    <?= render_partial('partials/geo_fields', [
                        'city'      => old('city') !== '' ? old('city') : ((string) ($boutique['city'] ?? '') ?: (string) ($autoGeo['city'] ?? '')),
                        'cc'        => old('country_code') !== '' ? old('country_code') : ((string) ($boutique['country_code'] ?? '') ?: (string) ($autoGeo['country_code'] ?? '')),
                        'continent' => $boutique['continent'] ?? ($autoGeo['continent'] ?? null),
                        'lat'       => old('geo_lat') !== '' ? old('geo_lat') : ((string) ($boutique['geo_lat'] ?? '') ?: (string) ($autoGeo['lat'] ?? '')),
                        'lng'       => old('geo_lng') !== '' ? old('geo_lng') : ((string) ($boutique['geo_lng'] ?? '') ?: (string) ($autoGeo['lng'] ?? '')),
                    ]) ?>
                </div>
                <div class="grid-2">
                    <div><label for="shop-cur"><?= e(t('shop.f.currency')) ?></label>
                        <select id="shop-cur" name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>" <?= $selCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
                    <div><label for="shop-prep"><?= e(t('shop.f.prep')) ?></label>
                        <select id="shop-prep" name="prep_time"><option value=""><?= e(t('field.choose')) ?></option>
                            <?php foreach ($preps as $pp): ?><option value="<?= e($pp) ?>" <?= $selPrep === $pp ? 'selected' : '' ?>><?= e(t('shop.prep.' . $pp)) ?></option><?php endforeach; ?></select></div>
                </div>
                <label><?= e(t('shop.f.zones')) ?></label>
                <?php $zCity = old('city') !== '' ? old('city') : (string) ($boutique['city'] ?? ''); $zCc = old('country_code') !== '' ? old('country_code') : (string) ($boutique['country_code'] ?? ''); ?>
                <div class="lang-checks"><?php foreach ($zones as $z): ?><label class="check-pill"><input type="checkbox" name="zones[]" value="<?= e($z) ?>" <?= in_array($z, $selZones, true) ? 'checked' : '' ?>><span data-zone-label="<?= e($z) ?>"><?= e(shop_zone_label($z, $zCity, $zCc)) ?></span></label><?php endforeach; ?></div>
                <label><?= e(t('shop.f.methods')) ?></label>
                <div class="lang-checks"><?php foreach ($methods as $m): ?><label class="check-pill" <?= $m === 'pickup' ? 'data-pickup-pill' : '' ?> <?= ($m === 'pickup' && $selType === 'online') ? 'hidden' : '' ?>><input type="checkbox" name="methods[]" value="<?= e($m) ?>" <?= in_array($m, $selMethods, true) ? 'checked' : '' ?>><span><?= e(t('shop.method.' . $m)) ?></span></label><?php endforeach; ?></div>
                <p class="hint" id="online-methods-hint" <?= $selType === 'online' ? '' : 'hidden' ?>><?= e(t('shop.online_delivery_note')) ?></p>
                <?php if (has_error('methods')): ?><p class="field-error"><?= e(error('methods')) ?></p><?php endif; ?>
                <label for="shop-free"><?= e(t('shop.f.free_ship')) ?></label>
                <input type="text" id="shop-free" name="free_ship" value="<?= e($freeVal) ?>" inputmode="decimal">
                <?php if (has_error('free_ship')): ?><p class="field-error"><?= e(error('free_ship')) ?></p><?php endif; ?>
                <div class="grid-2">
                    <div>
                        <label for="shop-dfee"><?= e(t('shop.f.delivery_fee')) ?></label>
                        <input type="text" id="shop-dfee" name="delivery_fee" value="<?= e($dfeeVal) ?>" inputmode="decimal" placeholder="0">
                    </div>
                    <div>
                        <label for="shop-dintl"><?= e(t('shop.f.delivery_intl')) ?></label>
                        <input type="text" id="shop-dintl" name="delivery_intl" value="<?= e($dintlVal) ?>" inputmode="decimal" placeholder="0">
                    </div>
                </div>
                <label for="shop-ddelay"><?= e(t('shop.f.delivery_delay')) ?></label>
                <select id="shop-ddelay" name="delivery_delay">
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach ((array) config('shop.prep_options', []) as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $selDelay === $opt ? 'selected' : '' ?>><?= e(t('shop.prep.' . $opt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="panel">
                <h2 class="panel-title">💳 <?= e(t('shop.f.payment')) ?></h2>
                <?= render_partial('partials/payment_fields', [
                    'terms_sel'   => array_filter(explode(',', (string) ($boutique['payment_terms'] ?? ''))),
                    'methods_sel' => array_filter(explode(',', (string) ($boutique['payment_methods'] ?? ''))),
                    'provider'    => (string) ($boutique['payment_provider'] ?? ''),
                ]) ?>
            </div>

            <div class="panel">
                <h2 class="panel-title">📣 <?= e(t('shop.cfg.title')) ?></h2>
                <label for="shop-announce"><?= e(t('shop.cfg.announcement')) ?></label>
                <input type="text" id="shop-announce" name="announcement" maxlength="200" value="<?= e((string) ($boutique['announcement'] ?? '')) ?>" placeholder="<?= e(t('shop.cfg.announcement_ph')) ?>">
                <p class="hint"><?= e(t('shop.cfg.announcement_hint')) ?></p>
                <label class="check-pill check-pill--block">
                    <input type="checkbox" name="is_vacation" value="1" <?= !empty($boutique['is_vacation']) ? 'checked' : '' ?>>
                    <span>🏖️ <?= e(t('shop.cfg.vacation')) ?></span>
                </label>
                <label for="shop-vac-until"><?= e(t('shop.cfg.vacation_until')) ?></label>
                <input type="date" id="shop-vac-until" name="vacation_until" value="<?= e((string) ($boutique['vacation_until'] ?? '')) ?>">
                <p class="hint"><?= e(t('shop.cfg.vacation_hint')) ?></p>

                <?php $hoursStruct = BusinessHours::decode($boutique['hours_json'] ?? null); ?>
                <fieldset class="hours-editor">
                    <legend>🕒 <?= e(t('shop.cfg.hours')) ?></legend>
                    <p class="hint"><?= e(t('shop.hours.hint')) ?></p>
                    <?php foreach (BusinessHours::DAYS as $day): $slot = $hoursStruct[$day] ?? null; ?>
                        <div class="hours-row">
                            <label class="hours-day">
                                <input type="checkbox" name="h_<?= $day ?>" value="1" <?= $slot !== null ? 'checked' : '' ?>>
                                <span><?= e(t('shop.hours.day.' . $day)) ?></span>
                            </label>
                            <span class="hours-times">
                                <input type="time" name="h_<?= $day ?>_o" value="<?= e($slot['o'] ?? '09:00') ?>" aria-label="<?= e(t('shop.hours.from')) ?>">
                                <span aria-hidden="true">–</span>
                                <input type="time" name="h_<?= $day ?>_c" value="<?= e($slot['c'] ?? '18:00') ?>" aria-label="<?= e(t('shop.hours.to')) ?>">
                            </span>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <label class="check-pill check-pill--block">
                    <input type="checkbox" name="orders_within_hours" value="1" <?= !empty($boutique['orders_within_hours']) ? 'checked' : '' ?>>
                    <span><?= e(t('shop.hours.enforce')) ?></span>
                </label>
                <p class="hint"><?= e(t('shop.hours.enforce_hint')) ?></p>

                <label for="shop-minorder">🧺 <?= e(t('shop.cfg.min_order')) ?> (<?= e($selCur) ?>)</label>
                <input type="text" id="shop-minorder" name="min_order" inputmode="decimal" value="<?= e($fmtCents($boutique['min_order_cents'] ?? null)) ?>" placeholder="0">
                <p class="hint"><?= e(t('shop.cfg.min_order_hint')) ?></p>
            </div>

            <div class="panel">
                <h2 class="panel-title">🎨 <?= e(t('shop.cfg.custom_title')) ?></h2>
                <label class="check-pill check-pill--block">
                    <input type="checkbox" name="accent_on" value="1" <?= !empty($boutique['accent_color']) ? 'checked' : '' ?>>
                    <span><?= e(t('shop.cfg.accent')) ?></span>
                </label>
                <div class="accent-row">
                    <input type="color" id="shop-accent" name="accent_color" value="<?= e((string) ($boutique['accent_color'] ?: '#0b7a4b')) ?>" aria-label="<?= e(t('shop.cfg.accent')) ?>">
                    <p class="hint"><?= e(t('shop.cfg.accent_hint')) ?></p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(t('profile.save')) ?></button>
        </form>

        <div class="panel">
            <h2 class="panel-title">⚠️ <?= e(t('shop.danger.title')) ?></h2>
            <p class="muted"><?= e(t('shop.danger.desc')) ?></p>
            <a class="btn btn-danger" href="<?= e(url('/boutique/supprimer')) ?>"><?= e(t('shop.danger.delete_btn')) ?></a>
        </div>

        <p class="auth-alt"><a href="<?= e(url('/boutique/gerer')) ?>">← <?= e(t('shop.back_manage')) ?></a></p>
    </div>
</div>
