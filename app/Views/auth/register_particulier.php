<?php
/** @var array $countries  @var string $detected_country */
$selCountry = old('country_code', $detected_country);
$selDial    = old('dial_country', $detected_country);
$cm         = old('contact_method') ?: 'email';
$g          = old('gender');
// Verrouillé UNIQUEMENT si le pays détecté est dans la zone du marketplace
// (Afrique/Europe) ; le GPS le corrige ensuite silencieusement (voir app.js).
// Hors zone (IP datacenter/VPN → ex. « US ») : pays + indicatif restent libres.
$inRegion = static fn (string $cc): bool => in_array(\App\Services\GeoService::continentOf($cc), ['africa', 'europe'], true);
$lockCountry = isset($countries[$selCountry]) && $inRegion($selCountry);
$lockDial    = ($selDial !== '' && dial_code($selDial) !== '') && $inRegion($selDial);
$detected_city = (string) ($detected_city ?? '');
$cityVal  = (string) old('city', $detected_city);
$lockCity = $cityVal !== '' && $lockCountry;
?>
<div class="authx">
    <div class="pagehead">
        <a class="backlink" href="<?= e(url('/register')) ?>">← <?= e(t('register.back_choice')) ?></a>
        <p class="eyebrow"><?= e(t('auth.eyebrow.member')) ?></p>
        <h1><?= e(t('register.particulier_title')) ?></h1>
        <p class="sub"><?= e(t('register.particulier_desc')) ?></p>
    </div>

    <div class="grid">
        <div class="acard">
            <form method="post" action="<?= e(url('/register/particulier')) ?>" novalidate data-consent-gate>
                <?= csrf_field() ?>

                <!-- 1 · Identité -->
                <fieldset>
                    <legend><span class="n">1</span> <?= e(t('auth.fs.identity')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="full_name"><?= e(t('field.full_name')) ?> <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required autocomplete="name">
                        <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>
                    </div>
                    <div class="afield">
                        <label class="albl" for="nickname"><?= e(t('field.nickname')) ?> <span class="req">*</span></label>
                        <input type="text" id="nickname" name="nickname" value="<?= old('nickname') ?>" required autocomplete="nickname" maxlength="64">
                        <?php if (has_error('nickname')): ?><p class="field-error"><?= e(error('nickname')) ?></p><?php endif; ?>
                    </div>
                </fieldset>

                <!-- 2 · Contact -->
                <fieldset>
                    <legend><span class="n">2</span> <?= e(t('auth.fs.contact')) ?></legend>
                    <div class="contact-switch">
                        <input type="radio" id="cm-email" name="contact_method" value="email" class="contact-radio" <?= $cm !== 'phone' ? 'checked' : '' ?>>
                        <input type="radio" id="cm-phone" name="contact_method" value="phone" class="contact-radio" <?= $cm === 'phone' ? 'checked' : '' ?>>
                        <div class="contact-tabs">
                            <label for="cm-email" class="contact-tab"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg> <?= e(t('register.by_email')) ?></label>
                            <label for="cm-phone" class="contact-tab"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg> <?= e(t('register.by_phone')) ?></label>
                        </div>

                        <div class="contact-panel contact-panel-email">
                            <label class="albl" for="email"><?= e(t('field.email')) ?></label>
                            <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email">
                            <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>
                        </div>

                        <div class="contact-panel contact-panel-phone">
                            <label class="albl"><?= e(t('field.phone')) ?></label>
                            <div class="phone-row">
                                <select id="dial_country"<?= $lockDial ? ' disabled aria-disabled="true" tabindex="-1"' : ' name="dial_country"' ?>
                                        class="dial-select<?= $lockDial ? ' locked-field is-locked' : '' ?>" aria-label="<?= e(t('field.dial_code')) ?>">
                                    <?php foreach ($countries as $code => $name): $dc = dial_code($code); if ($dc === '') continue; ?>
                                        <option value="<?= e($code) ?>" <?= $selDial === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?> (+<?= e($dc) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($lockDial): ?><input type="hidden" name="dial_country" id="dial_country_value" value="<?= e($selDial) ?>"><?php endif; ?>
                                <input type="tel" name="phone_number" value="<?= old('phone_number') ?>" inputmode="tel" placeholder="<?= e(t('field.phone_placeholder')) ?>" class="phone-input" autocomplete="tel-national">
                            </div>
                            <p class="ahint"><?= e(t('field.phone_hint')) ?></p>
                            <?php if (has_error('phone')): ?><p class="field-error"><?= e(error('phone')) ?></p><?php endif; ?>
                        </div>
                    </div>
                </fieldset>

                <!-- 3 · Profil -->
                <fieldset>
                    <legend><span class="n">3</span> <?= e(t('auth.fs.profile')) ?></legend>
                    <div class="two">
                        <div class="afield">
                            <label class="albl" for="birthdate"><?= e(t('field.birthdate')) ?> <span class="req">*</span></label>
                            <input type="text" id="birthdate" name="birthdate" value="<?= old('birthdate') ?>"
                                   inputmode="numeric" placeholder="jj/mm/aaaa" maxlength="10" required>
                            <p class="ahint"><?= e(t('field.birthdate_hint')) ?></p>
                            <?php if (has_error('birthdate')): ?><p class="field-error"><?= e(error('birthdate')) ?></p><?php endif; ?>
                        </div>
                        <div class="afield">
                            <label class="albl" for="gender"><?= e(t('field.gender')) ?> <span class="req">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="" <?= $g === '' ? 'selected' : '' ?>><?= e(t('field.choose')) ?></option>
                                <option value="homme" <?= $g === 'homme' ? 'selected' : '' ?>><?= e(t('gender.homme')) ?></option>
                                <option value="femme" <?= $g === 'femme' ? 'selected' : '' ?>><?= e(t('gender.femme')) ?></option>
                                <option value="autre" <?= $g === 'autre' ? 'selected' : '' ?>><?= e(t('gender.autre')) ?></option>
                            </select>
                            <?php if (has_error('gender')): ?><p class="field-error"><?= e(error('gender')) ?></p><?php endif; ?>
                            <div class="other-box" data-other-for="#gender" data-other-value="autre" <?= $g === 'autre' ? '' : 'hidden' ?>>
                                <label class="albl" for="gender_other"><?= e(t('field.other_specify')) ?></label>
                                <input type="text" id="gender_other" name="gender_other" maxlength="40"
                                       value="<?= old('gender_other') ?>" placeholder="<?= e(t('field.other_specify_ph')) ?>">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- 4 · Localisation -->
                <fieldset>
                    <legend><span class="n">4</span> <?= e(t('auth.fs.location')) ?></legend>
                    <div class="two">
                        <div class="afield">
                            <label class="albl" for="country_code"><?= e(t('field.country')) ?> <span class="req">*</span></label>
                            <?php if ($lockCountry): ?>
                                <select id="country_code" class="locked-field is-locked" disabled aria-disabled="true" tabindex="-1">
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= e($code) ?>" <?= $selCountry === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="country_code" id="country_code_value" value="<?= e($selCountry) ?>">
                            <?php else: ?>
                                <select id="country_code" name="country_code" required>
                                    <option value=""><?= e(t('field.choose')) ?></option>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= e($code) ?>" <?= $selCountry === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
                        </div>
                        <div class="afield">
                            <label class="albl" for="city"><?= e(t('field.city')) ?></label>
                            <input type="text" id="city" name="city" value="<?= e($cityVal) ?>" autocomplete="address-level2"<?= $lockCity ? ' readonly class="is-locked" data-geo-prefill="1"' : '' ?>>
                            <span id="geo-detect-status" class="ahint geo-detect-status" aria-live="polite"></span>
                        </div>
                    </div>
                    <p class="ahint geo-lock-note" id="geo-lock-note" <?= ($lockCountry || $lockCity) ? '' : 'hidden' ?>>
                        <button type="button" id="geo-unlock" class="link-button"><?= e(t('geo.unlock')) ?></button>
                    </p>
                </fieldset>

                <!-- 5 · Sécurité -->
                <fieldset>
                    <legend><span class="n">5</span> <?= e(t('auth.fs.security')) ?></legend>
                    <?= render_partial('partials/pwd_field', [
                        'id' => 'password', 'label' => t('field.password'), 'strength' => true, 'error' => 'password',
                        'minlength' => (int) config('app.password_min_length', 12),
                        'hint' => t('auth.password_hint', ['min' => config('app.password_min_length', 12)]),
                    ]) ?>
                    <?= render_partial('partials/pwd_field', [
                        'id' => 'password_confirm', 'label' => t('field.password_confirm'),
                        'match' => 'password', 'error' => 'password_confirm',
                    ]) ?>
                </fieldset>

                <!-- 6 · Conditions -->
                <fieldset>
                    <legend><span class="n">6</span> <?= e(t('auth.fs.terms')) ?></legend>
                    <?= render_partial('partials/legal_consent') ?>
                </fieldset>

                <!-- 7 · Vérification -->
                <fieldset>
                    <legend><span class="n">7</span> <?= e(t('auth.fs.verify')) ?></legend>
                    <?= render_partial('partials/captcha') ?>
                </fieldset>

                <button type="submit" class="abtn abtn--cta" data-consent-submit style="margin-top:1.5rem"><?= e(t('register.particulier_submit')) ?></button>
            </form>
        </div>

        <?= render_partial('partials/auth_aside', ['variant' => 'member']) ?>
    </div>
</div>
