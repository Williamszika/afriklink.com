<?php
/** @var array $countries  @var string $detected_country */
$selCountry = old('country_code', $detected_country);
$selDial    = old('dial_country', $detected_country);
$cm         = old('contact_method') ?: 'email';
$g          = old('gender');
?>
<section class="auth-card auth-card--wide">
    <h1><?= e(t('register.particulier_title')) ?></h1>
    <p class="muted"><?= e(t('register.particulier_desc')) ?></p>

    <form method="post" action="<?= e(url('/register/particulier')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="full_name"><?= e(t('field.full_name')) ?></label>
        <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required autocomplete="name">
        <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>

        <label for="nickname"><?= e(t('field.nickname')) ?></label>
        <input type="text" id="nickname" name="nickname" value="<?= old('nickname') ?>" required autocomplete="nickname" maxlength="64">
        <?php if (has_error('nickname')): ?><p class="field-error"><?= e(error('nickname')) ?></p><?php endif; ?>

        <div class="contact-switch">
            <input type="radio" id="cm-email" name="contact_method" value="email" class="contact-radio" <?= $cm !== 'phone' ? 'checked' : '' ?>>
            <input type="radio" id="cm-phone" name="contact_method" value="phone" class="contact-radio" <?= $cm === 'phone' ? 'checked' : '' ?>>
            <div class="contact-tabs">
                <label for="cm-email" class="contact-tab"><?= e(t('register.by_email')) ?></label>
                <label for="cm-phone" class="contact-tab"><?= e(t('register.by_phone')) ?></label>
            </div>

            <div class="contact-panel contact-panel-email">
                <label for="email"><?= e(t('field.email')) ?></label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email">
                <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>
            </div>

            <div class="contact-panel contact-panel-phone">
                <label><?= e(t('field.phone')) ?></label>
                <div class="phone-row">
                    <select name="dial_country" class="dial-select" aria-label="<?= e(t('field.dial_code')) ?>">
                        <?php foreach ($countries as $code => $name): $dc = dial_code($code); if ($dc === '') continue; ?>
                            <option value="<?= e($code) ?>" <?= $selDial === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?> (+<?= e($dc) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="tel" name="phone_number" value="<?= old('phone_number') ?>" inputmode="tel" placeholder="<?= e(t('field.phone_placeholder')) ?>" class="phone-input" autocomplete="tel-national">
                </div>
                <p class="hint"><?= e(t('field.phone_hint')) ?></p>
                <?php if (has_error('phone')): ?><p class="field-error"><?= e(error('phone')) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="birthdate"><?= e(t('field.birthdate')) ?></label>
                <input type="text" id="birthdate" name="birthdate" value="<?= old('birthdate') ?>"
                       inputmode="numeric" placeholder="jj/mm/aaaa" maxlength="10" required>
                <p class="hint"><?= e(t('field.birthdate_hint')) ?></p>
                <?php if (has_error('birthdate')): ?><p class="field-error"><?= e(error('birthdate')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="gender"><?= e(t('field.gender')) ?></label>
                <select id="gender" name="gender" required>
                    <option value="" <?= $g === '' ? 'selected' : '' ?>><?= e(t('field.choose')) ?></option>
                    <option value="homme" <?= $g === 'homme' ? 'selected' : '' ?>><?= e(t('gender.homme')) ?></option>
                    <option value="femme" <?= $g === 'femme' ? 'selected' : '' ?>><?= e(t('gender.femme')) ?></option>
                    <option value="autre" <?= $g === 'autre' ? 'selected' : '' ?>><?= e(t('gender.autre')) ?></option>
                </select>
                <?php if (has_error('gender')): ?><p class="field-error"><?= e(error('gender')) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="country_code"><?= e(t('field.country')) ?></label>
                <select id="country_code" name="country_code" required>
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php if ($selCountry !== '' && !isset($countries[$selCountry])): ?>
                        <option value="<?= e($selCountry) ?>" selected><?= e($selCountry) ?></option>
                    <?php endif; ?>
                    <?php foreach ($countries as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $selCountry === $code ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="city"><?= e(t('field.city')) ?></label>
                <input type="text" id="city" name="city" value="<?= old('city') ?>" autocomplete="address-level2">
            </div>
        </div>

        <label for="password"><?= e(t('field.password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="new-password"
               minlength="<?= (int) config('app.password_min_length', 12) ?>">
        <p class="hint"><?= e(t('auth.password_hint', ['min' => config('app.password_min_length', 12)])) ?></p>
        <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>

        <label for="password_confirm"><?= e(t('field.password_confirm')) ?></label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        <?php if (has_error('password_confirm')): ?><p class="field-error"><?= e(error('password_confirm')) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('register.particulier_submit')) ?></button>
    </form>

    <p class="auth-alt"><a href="<?= e(url('/register')) ?>">← <?= e(t('register.back_choice')) ?></a></p>
</section>
