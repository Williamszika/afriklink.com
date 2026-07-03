<?php
/** @var ?string $detected_country  @var ?string $detected_city  @var array<string,string> $countries */
$selCountry = old('country_code') ?: ($detected_country ?? '');
$selDial    = old('dial_country') ?: $selCountry;
// Géolocalisation : pays + indicatif + ville préremplis et VERROUILLÉS si la
// détection (IP edge) tombe dans la zone du marketplace. Le GPS affine, et
// « Modifier » (escape hatch) rouvre tout. Hors zone (VPN/datacenter) : libre.
$inRegion    = static fn (string $cc): bool => in_array(\App\Services\GeoService::continentOf($cc), ['africa', 'europe'], true);
$lockCountry = isset($countries[$selCountry]) && $inRegion($selCountry);
$lockDial    = ($selDial !== '' && dial_code($selDial) !== '') && $inRegion($selDial);
$cityVal     = (string) old('city', (string) ($detected_city ?? ''));
$lockCity    = $cityVal !== '' && $lockCountry;
?>
<div class="authx">
    <div class="pagehead">
        <a class="backlink" href="<?= e(url('/register')) ?>">← <?= e(t('register.back_choice')) ?></a>
        <p class="eyebrow"><?= e(t('auth.eyebrow.seller')) ?></p>
        <h1>🏪 <?= e(t('pro.title')) ?></h1>
        <p class="sub"><?= e(t('pro.subtitle')) ?></p>
    </div>

    <div class="grid">
        <div class="acard">
            <form method="post" action="<?= e(url('/register/vendeur')) ?>" novalidate data-consent-gate data-submit-once>
                <?= csrf_field() ?>

                <!-- 1 · Activité -->
                <fieldset>
                    <legend><span class="n">1</span> <?= e(t('auth.fs.activity')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="company_name"><?= e(t('pro.field.company_name')) ?> <span class="req">*</span></label>
                        <input type="text" id="company_name" name="company_name" value="<?= old('company_name') ?>" required
                               maxlength="<?= (int) config('pro.company_max', 150) ?>" placeholder="<?= e(t('pro.field.company_name_ph')) ?>">
                        <p class="ahint"><?= e(t('pro.field.company_name_hint')) ?></p>
                        <?php if (has_error('company_name')): ?><p class="field-error"><?= e(error('company_name')) ?></p><?php endif; ?>
                    </div>
                    <div class="afield">
                        <label class="albl" for="full_name"><?= e(t('pro.field.full_name')) ?> <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required maxlength="150"
                               autocomplete="name" placeholder="<?= e(t('pro.field.full_name_ph')) ?>">
                        <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>
                    </div>
                </fieldset>

                <!-- 2 · Contact -->
                <fieldset>
                    <legend><span class="n">2</span> <?= e(t('auth.fs.contact')) ?></legend>
                    <div class="afield">
                        <label class="albl" for="email"><?= e(t('pro.field.email')) ?> <span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email"
                               placeholder="contact@entreprise.com">
                        <p class="ahint"><?= e(t('pro.field.email_hint')) ?></p>
                        <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>
                    </div>
                    <div class="afield">
                        <label class="albl"><?= e(t('pro.field.phone')) ?> <span class="req">*</span></label>
                        <div class="phone-row">
                            <select id="dial_country"<?= $lockDial ? ' disabled aria-disabled="true" tabindex="-1"' : ' name="dial_country"' ?>
                                    class="dial-select<?= $lockDial ? ' locked-field is-locked' : '' ?>" aria-label="<?= e(t('field.dial_code')) ?>">
                                <?php foreach ($countries as $code => $name): $dc = dial_code($code); if ($dc === '') { continue; } ?>
                                    <option value="<?= e($code) ?>" <?= $selDial === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?> (+<?= e($dc) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($lockDial): ?><input type="hidden" name="dial_country" id="dial_country_value" value="<?= e($selDial) ?>"><?php endif; ?>
                            <input type="tel" name="phone_number" value="<?= old('phone_number') ?>" inputmode="tel"
                                   placeholder="<?= e(t('field.phone_placeholder')) ?>" class="phone-input" autocomplete="tel-national">
                        </div>
                        <?php if (has_error('phone')): ?><p class="field-error"><?= e(error('phone')) ?></p><?php endif; ?>
                    </div>
                </fieldset>

                <!-- 3 · Localisation -->
                <fieldset>
                    <legend><span class="n">3</span> <?= e(t('auth.fs.location')) ?></legend>
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
                            <label class="albl" for="city"><?= e(t('field.city')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="text" id="city" name="city" value="<?= e($cityVal) ?>" maxlength="120"<?= $lockCity ? ' readonly class="is-locked" data-geo-prefill="1"' : '' ?>>
                            <?= render_partial('partials/geo_lock_controls', ['locked' => $lockCountry || $lockCity]) ?>
                        </div>
                    </div>
                </fieldset>

                <!-- 4 · Sécurité -->
                <fieldset>
                    <legend><span class="n">4</span> <?= e(t('auth.fs.security')) ?></legend>
                    <div class="two">
                        <?= render_partial('partials/pwd_field', [
                            'id' => 'password', 'label' => t('field.password'), 'strength' => true, 'error' => 'password',
                            'minlength' => (int) config('app.password_min_length', 12),
                            'hint' => t('auth.password_hint', ['min' => config('app.password_min_length', 12)]),
                        ]) ?>
                        <?= render_partial('partials/pwd_field', [
                            'id' => 'password_confirm', 'label' => t('field.password_confirm'),
                            'match' => 'password', 'error' => 'password_confirm',
                        ]) ?>
                    </div>
                </fieldset>

                <!-- 5 · Conditions -->
                <fieldset>
                    <legend><span class="n">5</span> <?= e(t('auth.fs.terms')) ?></legend>
                    <?= render_partial('partials/legal_consent') ?>
                </fieldset>

                <!-- 6 · Vérification -->
                <fieldset>
                    <legend><span class="n">6</span> <?= e(t('auth.fs.verify')) ?></legend>
                    <?= render_partial('partials/captcha') ?>
                </fieldset>

                <button type="submit" class="abtn abtn--cta" data-consent-submit style="margin-top:1.5rem"><?= e(t('pro.submit')) ?></button>
                <p class="submit-note"><span aria-hidden="true">💡</span> <?= e(t('pro.rest_later')) ?></p>
            </form>
        </div>

        <?= render_partial('partials/auth_aside') ?>
    </div>
</div>
