<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var string $suggestSlug */
$cuisines = config('restaurant.cuisines', []);
$services = config('restaurant.services', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$selCur = old('currency') ?: ((currency_for_country((string) (detected_geo()['country_code'] ?? '')) ?? '') ?: (string) ($user['preferred_currency'] ?? 'XOF'));
$autoGeo = detected_geo();
?>
<section class="auth-card auth-card--wide">
    <h1><?= icon('utensils', ['size' => 24]) ?> <?= e(t('resto.create_title')) ?></h1>
    <p class="muted"><?= e(t('resto.create_sub')) ?></p>

    <form method="post" action="<?= e(url('/restaurant/creer')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="r-name"><?= e(t('resto.f.name')) ?></label>
        <input type="text" id="r-name" name="name" value="<?= old('name') ?>" required maxlength="80" placeholder="<?= e(t('resto.f.name_ph')) ?>">
        <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

        <label for="r-tagline"><?= e(t('resto.f.tagline')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="r-tagline" name="tagline" value="<?= old('tagline') ?>" maxlength="140" placeholder="<?= e(t('resto.f.tagline_ph')) ?>">

        <label><?= e(t('resto.f.cuisine')) ?> <span class="muted">(<?= e(t('resto.f.cuisine_hint')) ?>)</span></label>
        <?php $selCuis = old_array('cuisine'); ?>
        <div class="lang-checks">
            <?php foreach ($cuisines as $c): ?>
                <label class="check-pill"><input type="checkbox" <?= $c === 'autre' ? 'id="cuisine-autre"' : '' ?> name="cuisine[]" value="<?= e($c) ?>" <?= in_array($c, $selCuis, true) ? 'checked' : '' ?>><span><?= e(t('resto.cuisine.' . $c)) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="other-box" data-other-for="#cuisine-autre" <?= in_array('autre', $selCuis, true) ? '' : 'hidden' ?>>
            <label for="cuisine_other"><?= e(t('field.other_specify')) ?></label>
            <input type="text" id="cuisine_other" name="cuisine_other" maxlength="60"
                   value="<?= old('cuisine_other') ?>" placeholder="<?= e(t('resto.cuisine_other_ph')) ?>">
        </div>

        <label for="r-cur"><?= e(t('shop.f.currency')) ?></label>
        <select id="r-cur" name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>" <?= $selCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>

        <label><?= e(t('resto.f.services')) ?></label>
        <div class="lang-checks">
            <?php $selServ = old('services') !== '' ? [] : ['dine_in', 'takeaway', 'delivery']; ?>
            <?php foreach ($services as $s): ?>
                <label class="check-pill"><input type="checkbox" name="services[]" value="<?= e($s) ?>" <?= in_array($s, $selServ, true) ? 'checked' : '' ?>><span><?= e(t('resto.service.' . $s)) ?></span></label>
            <?php endforeach; ?>
        </div>

        <label><?= e(t('resto.f.open_days')) ?></label>
        <?php $selDays = old_array('open_days') !== [] ? old_array('open_days') : ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']; ?>
        <div class="lang-checks">
            <?php foreach (config('restaurant.days', []) as $d): ?>
                <label class="check-pill"><input type="checkbox" name="open_days[]" value="<?= e($d) ?>" <?= in_array($d, $selDays, true) ? 'checked' : '' ?>><span><?= e(t('resto.day.' . $d)) ?></span></label>
            <?php endforeach; ?>
        </div>

        <div class="grid-2">
            <div>
                <label for="r-open"><?= e(t('resto.f.open_time')) ?></label>
                <input type="time" id="r-open" name="open_time" value="<?= old('open_time') ?: '11:00' ?>">
            </div>
            <div>
                <label for="r-close"><?= e(t('resto.f.close_time')) ?></label>
                <input type="time" id="r-close" name="close_time" value="<?= old('close_time') ?: '22:00' ?>">
            </div>
        </div>

        <label for="r-address"><?= e(t('resto.f.address')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="r-address" name="address" value="<?= old('address') ?>" maxlength="220" autocomplete="street-address">
        <?= render_partial('partials/geo_fields', [
            'city' => old('city') !== '' ? old('city') : (string) ($autoGeo['city'] ?? ''),
            'cc'   => old('country_code') !== '' ? old('country_code') : (string) ($autoGeo['country_code'] ?? ''),
            'continent' => $autoGeo['continent'] ?? null,
            'lat'  => old('geo_lat'), 'lng' => old('geo_lng'),
        ]) ?>

        <div class="grid-2">
            <div>
                <label for="r-wa"><?= e(t('resto.f.whatsapp')) ?></label>
                <input type="tel" id="r-wa" name="contact_whatsapp" value="<?= old('contact_whatsapp') ?>" maxlength="22" data-dialcode="1" inputmode="tel">
            </div>
            <div>
                <label for="r-phone"><?= e(t('resto.f.phone')) ?></label>
                <input type="tel" id="r-phone" name="contact_phone" value="<?= old('contact_phone') ?>" maxlength="22" data-dialcode="1" inputmode="tel">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('resto.create_submit')) ?> →</button>
    </form>
    <p class="auth-alt"><a href="<?= e(url('/vendeur/vitrines')) ?>">← <?= e(t('seller.nav.storefronts')) ?></a></p>
</section>
