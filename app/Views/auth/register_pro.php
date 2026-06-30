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
<section class="auth-card auth-card--wide">
    <h1>🏪 <?= e(t('pro.title')) ?></h1>
    <p class="muted"><?= e(t('pro.subtitle')) ?></p>

    <form method="post" action="<?= e(url('/register/vendeur')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="company_name"><?= e(t('pro.field.company_name')) ?></label>
        <input type="text" id="company_name" name="company_name" value="<?= old('company_name') ?>" required
               maxlength="<?= (int) config('pro.company_max', 150) ?>" placeholder="<?= e(t('pro.field.company_name_ph')) ?>">
        <p class="hint"><?= e(t('pro.field.company_name_hint')) ?></p>
        <?php if (has_error('company_name')): ?><p class="field-error"><?= e(error('company_name')) ?></p><?php endif; ?>

        <label for="full_name"><?= e(t('pro.field.full_name')) ?></label>
        <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required maxlength="150"
               autocomplete="name" placeholder="<?= e(t('pro.field.full_name_ph')) ?>">
        <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>

        <label for="email"><?= e(t('pro.field.email')) ?></label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email"
               placeholder="contact@entreprise.com">
        <p class="hint"><?= e(t('pro.field.email_hint')) ?></p>
        <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>

        <label><?= e(t('pro.field.phone')) ?></label>
        <div class="phone-row">
            <select id="dial_country"<?= $lockDial ? ' disabled aria-disabled="true" tabindex="-1"' : ' name="dial_country"' ?>
                    class="dial-select<?= $lockDial ? ' locked-field' : '' ?>" aria-label="<?= e(t('field.dial_code')) ?>">
                <?php foreach ($countries as $code => $name): $dc = dial_code($code); if ($dc === '') { continue; } ?>
                    <option value="<?= e($code) ?>" <?= $selDial === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?> (+<?= e($dc) ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if ($lockDial): ?><input type="hidden" name="dial_country" id="dial_country_value" value="<?= e($selDial) ?>"><?php endif; ?>
            <input type="tel" name="phone_number" value="<?= old('phone_number') ?>" inputmode="tel"
                   placeholder="<?= e(t('field.phone_placeholder')) ?>" class="phone-input" autocomplete="tel-national">
        </div>
        <?php if (has_error('phone')): ?><p class="field-error"><?= e(error('phone')) ?></p><?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="country_code"><?= e(t('field.country')) ?></label>
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
            <div>
                <label for="city"><?= e(t('field.city')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="city" name="city" value="<?= e($cityVal) ?>" maxlength="120"<?= $lockCity ? ' readonly class="is-locked" data-geo-prefill="1"' : '' ?>>
                <?= render_partial('partials/geo_lock_controls', ['locked' => $lockCountry || $lockCity]) ?>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="password"><?= e(t('field.password')) ?></label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
                <p class="hint"><?= e(t('auth.password_hint', ['min' => config('app.password_min_length', 12)])) ?></p>
                <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="password_confirm"><?= e(t('field.password_confirm')) ?></label>
                <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                <?php if (has_error('password_confirm')): ?><p class="field-error"><?= e(error('password_confirm')) ?></p><?php endif; ?>
            </div>
        </div>

        <label class="check-row">
            <input type="checkbox" name="accept_legal" value="1" <?= old('accept_legal') === '1' ? 'checked' : '' ?> required>
            <span><?= e(t('pro.accept_legal')) ?></span>
        </label>
        <?php if (has_error('accept_legal')): ?><p class="field-error"><?= e(error('accept_legal')) ?></p><?php endif; ?>

        <?= render_partial('partials/captcha') ?>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('pro.submit')) ?></button>

        <p class="hint" style="margin-top:10px">💡 <?= e(t('pro.rest_later')) ?></p>
    </form>

    <p class="auth-alt"><a href="<?= e(url('/register')) ?>">← <?= e(t('register.back_choice')) ?></a></p>
</section>
