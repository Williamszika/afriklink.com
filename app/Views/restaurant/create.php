<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var string $suggestSlug */
$cuisines   = config('restaurant.cuisines', []);
$services   = config('restaurant.services', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$autoGeo    = detected_geo();
$selCur = old('currency') ?: ((currency_for_country((string) ($autoGeo['country_code'] ?? '')) ?? '') ?: (string) ($user['preferred_currency'] ?? 'XOF'));
$baseUrl = preg_replace('#^https?://#', '', rtrim((string) (config('app.url') ?: 'afriklink.com'), '/'));
$selCuis = old_array('cuisine');
$selServ = old('services') !== '' ? old_array('services') : ['dine_in', 'takeaway', 'delivery'];
$selDays = old_array('open_days') !== [] ? old_array('open_days') : ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$slugFallback = t('resto.slug_fallback');
?>
<div class="authx">
    <div class="pagehead">
        <a class="backlink" href="<?= e(url('/vendeur/vitrines')) ?>">← <?= e(t('seller.nav.storefronts')) ?></a>
        <p class="eyebrow"><?= e(t('resto.create_eyebrow')) ?></p>
        <h1>🍽️ <?= e(t('resto.create_title')) ?></h1>
        <p class="sub"><?= e(t('resto.create_sub')) ?></p>
    </div>

    <div class="grid">
        <div class="acard">
            <form method="post" action="<?= e(url('/restaurant/creer')) ?>" novalidate data-submit-once>
                <?= csrf_field() ?>

                <!-- 1 · Le restaurant -->
                <fieldset>
                    <legend><span class="n">1</span> <?= e(t('resto.fs.identity')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="r-name"><?= e(t('resto.f.name')) ?> <span class="req">*</span></label>
                        <input type="text" id="r-name" name="name" value="<?= old('name') ?>" required maxlength="80"
                               placeholder="<?= e(t('resto.f.name_ph')) ?>" data-slug-source>
                        <div class="slug-preview"><?= e($baseUrl) ?>/restaurant/<b data-slug-out data-slug-fallback="<?= e($slugFallback) ?>"><?= e($suggestSlug !== '' ? $suggestSlug : $slugFallback) ?></b></div>
                        <?php if (has_error('name')): ?><p class="field-error"><?= e(error('name')) ?></p><?php endif; ?>
                    </div>
                    <div class="afield">
                        <label class="albl"><?= e(t('resto.f.cuisine')) ?> <span class="opt">(<?= e(t('resto.f.cuisine_hint')) ?>)</span></label>
                        <div class="lang-checks">
                            <?php foreach ($cuisines as $c): ?>
                                <label class="check-pill"><input type="checkbox" <?= $c === 'autre' ? 'id="cuisine-autre"' : '' ?> name="cuisine[]" value="<?= e($c) ?>" <?= in_array($c, $selCuis, true) ? 'checked' : '' ?>><span><?= e(t('resto.cuisine.' . $c)) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="other-box" data-other-for="#cuisine-autre" <?= in_array('autre', $selCuis, true) ? '' : 'hidden' ?>>
                            <label class="albl" for="cuisine_other"><?= e(t('field.other_specify')) ?></label>
                            <input type="text" id="cuisine_other" name="cuisine_other" maxlength="60"
                                   value="<?= old('cuisine_other') ?>" placeholder="<?= e(t('resto.cuisine_other_ph')) ?>">
                        </div>
                    </div>
                    <div class="afield">
                        <label class="albl" for="r-tagline"><?= e(t('resto.f.tagline')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="r-tagline" name="tagline" value="<?= old('tagline') ?>" maxlength="140" placeholder="<?= e(t('resto.f.tagline_ph')) ?>">
                    </div>
                </fieldset>

                <!-- 2 · Localisation & contact -->
                <fieldset>
                    <legend><span class="n">2</span> <?= e(t('resto.fs.location')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="r-address"><?= e(t('resto.f.address')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="r-address" name="address" value="<?= old('address') ?>" maxlength="220" autocomplete="street-address">
                    </div>
                    <div class="afield geo-authx">
                        <?php /* Géolocalisation VERROUILLÉE comme partout : ville/pays préremplis et
                           verrouillés dès que la détection (IP/GPS) fournit des coordonnées ; « Modifier »
                           rouvre, 📍 affine, et le serveur recalcule depuis lat/lng. */ ?>
                        <?= render_partial('partials/geo_fields', [
                            'city' => old('city') !== '' ? old('city') : (string) ($autoGeo['city'] ?? ''),
                            'cc'   => old('country_code') !== '' ? old('country_code') : (string) ($autoGeo['country_code'] ?? ''),
                            'continent' => $autoGeo['continent'] ?? null,
                            'lat'  => old('geo_lat') !== '' ? old('geo_lat') : (string) ($autoGeo['lat'] ?? ''),
                            'lng'  => old('geo_lng') !== '' ? old('geo_lng') : (string) ($autoGeo['lng'] ?? ''),
                        ]) ?>
                    </div>
                    <div class="two">
                        <div class="afield">
                            <label class="albl" for="r-wa"><?= e(t('resto.f.whatsapp')) ?></label>
                            <input type="tel" id="r-wa" name="contact_whatsapp" value="<?= old('contact_whatsapp') ?>" maxlength="22" data-dialcode="1" inputmode="tel">
                        </div>
                        <div class="afield">
                            <label class="albl" for="r-phone"><?= e(t('resto.f.phone')) ?></label>
                            <input type="tel" id="r-phone" name="contact_phone" value="<?= old('contact_phone') ?>" maxlength="22" data-dialcode="1" inputmode="tel">
                        </div>
                    </div>
                </fieldset>

                <!-- 3 · Service & horaires -->
                <fieldset>
                    <legend><span class="n">3</span> <?= e(t('resto.fs.service')) ?></legend>
                    <div class="afield">
                        <label class="albl"><?= e(t('resto.f.services')) ?></label>
                        <div class="lang-checks">
                            <?php foreach ($services as $s): ?>
                                <label class="check-pill"><input type="checkbox" name="services[]" value="<?= e($s) ?>" <?= in_array($s, $selServ, true) ? 'checked' : '' ?>><span><?= e(t('resto.service.' . $s)) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="afield">
                        <label class="albl"><?= e(t('resto.f.open_days')) ?></label>
                        <div class="lang-checks">
                            <?php foreach (config('restaurant.days', []) as $d): ?>
                                <label class="check-pill"><input type="checkbox" name="open_days[]" value="<?= e($d) ?>" <?= in_array($d, $selDays, true) ? 'checked' : '' ?>><span><?= e(t('resto.day.' . $d)) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="two">
                        <div class="afield">
                            <label class="albl" for="r-open"><?= e(t('resto.f.open_time')) ?></label>
                            <input type="time" id="r-open" name="open_time" value="<?= old('open_time') ?: '11:00' ?>">
                        </div>
                        <div class="afield">
                            <label class="albl" for="r-close"><?= e(t('resto.f.close_time')) ?></label>
                            <input type="time" id="r-close" name="close_time" value="<?= old('close_time') ?: '22:00' ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- 4 · Devise -->
                <fieldset>
                    <legend><span class="n">4</span> <?= e(t('resto.fs.currency')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="r-cur"><?= e(t('shop.f.currency')) ?> <span class="req">*</span></label>
                        <select id="r-cur" name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>" <?= $selCur === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
                        <p class="ahint"><?= e(t('resto.currency_hint')) ?></p>
                    </div>
                </fieldset>

                <button type="submit" class="abtn abtn--cta" style="margin-top:1.5rem"><?= e(t('resto.create_submit')) ?> →</button>
                <p class="submit-note"><span aria-hidden="true">💡</span> <?= e(t('resto.rest_later')) ?></p>
            </form>
        </div>

        <aside class="aside">
            <div class="promo">
                <p class="eyebrow"><?= e(t('resto.aside.how')) ?></p>
                <h3><?= e(t('resto.aside.title')) ?></h3>
                <div class="mstep"><span class="num">1</span><div><b><?= e(t('resto.aside.s1_t')) ?></b><p><?= e(t('resto.aside.s1_d')) ?></p></div></div>
                <div class="mstep"><span class="num">2</span><div><b><?= e(t('resto.aside.s2_t')) ?></b><p><?= e(t('resto.aside.s2_d')) ?></p></div></div>
                <div class="mstep"><span class="num">3</span><div><b><?= e(t('resto.aside.s3_t')) ?></b><p><?= e(t('resto.aside.s3_d')) ?></p></div></div>
            </div>
            <div class="draftnote"><span aria-hidden="true">ℹ️</span> <?= e(t('resto.aside.draft')) ?></div>
        </aside>
    </div>
</div>
