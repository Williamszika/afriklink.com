<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var string $suggestSlug */
$cuisines = config('restaurant.cuisines', []);
$services = config('restaurant.services', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$selCur = old('currency') ?: (string) ($user['preferred_currency'] ?? 'XOF');
$autoGeo = detected_geo();
?>
<section class="auth-card auth-card--wide">
    <h1>🍽️ <?= e(t('resto.create_title')) ?></h1>
    <p class="muted"><?= e(t('resto.create_sub')) ?></p>

    <form method="post" action="<?= e(url('/restaurant/creer')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="r-name"><?= e(t('resto.f.name')) ?></label>
        <input type="text" id="r-name" name="name" value="<?= old('name') ?>" required maxlength="80" placeholder="<?= e(t('resto.f.name_ph')) ?>">
        <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>

        <label for="r-tagline"><?= e(t('resto.f.tagline')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="r-tagline" name="tagline" value="<?= old('tagline') ?>" maxlength="140" placeholder="<?= e(t('resto.f.tagline_ph')) ?>">

        <div class="grid-2">
            <div>
                <label for="r-cuisine"><?= e(t('resto.f.cuisine')) ?></label>
                <select id="r-cuisine" name="cuisine">
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach ($cuisines as $c): ?>
                        <option value="<?= e($c) ?>" <?= old('cuisine') === $c ? 'selected' : '' ?>><?= e(t('resto.cuisine.' . $c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="r-cur"><?= e(t('shop.f.currency')) ?></label>
                <select id="r-cur" name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>" <?= $selCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
            </div>
        </div>

        <label><?= e(t('resto.f.services')) ?></label>
        <div class="lang-checks">
            <?php $selServ = old('services') !== '' ? [] : ['dine_in', 'takeaway', 'delivery']; ?>
            <?php foreach ($services as $s): ?>
                <label class="check-pill"><input type="checkbox" name="services[]" value="<?= e($s) ?>" <?= in_array($s, $selServ, true) ? 'checked' : '' ?>><span><?= e(t('resto.service.' . $s)) ?></span></label>
            <?php endforeach; ?>
        </div>

        <label for="r-hours"><?= e(t('resto.f.hours')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="r-hours" name="hours" value="<?= old('hours') ?>" maxlength="160" placeholder="<?= e(t('resto.f.hours_ph')) ?>">

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
