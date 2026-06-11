<?php
/** @var int $step  @var array $draft  @var ?string $detected_country  @var array<string,string> $countries */
$s1 = $draft['step1'] ?? [];
$s2 = $draft['step2'] ?? [];
$legalForms = config('pro.legal_forms', []);
$langs      = config('pro.languages', []);

/** valeur affichée : saisie en erreur (old) sinon brouillon de session */
$val = static fn (string $key, array $stepData): string => old($key) !== '' ? old($key) : e((string) ($stepData[$key] ?? ''));

$steps = [1 => t('pro.step1_label'), 2 => t('pro.step2_label'), 3 => t('pro.step3_label')];
?>
<section class="auth-card auth-card--wide">
    <h1>🏢 <?= e(t('pro.title')) ?></h1>
    <p class="muted"><?= e(t('pro.subtitle')) ?></p>

    <ol class="wizard-steps" aria-label="<?= e(t('pro.progress')) ?>">
        <?php foreach ($steps as $n => $label): ?>
            <li class="<?= $n === $step ? 'is-current' : ($n < $step ? 'is-done' : '') ?>">
                <span class="wizard-num"><?= $n < $step ? '✓' : $n ?></span>
                <span class="wizard-label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (has_error('recap')): ?><p class="field-error"><?= e(error('recap')) ?></p><?php endif; ?>

    <form method="post" action="<?= e(url('/register/professionnel')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="etape" value="<?= (int) $step ?>">

        <?php if ($step === 1): ?>
            <h2 class="wizard-h2"><?= e(t('pro.step1_title')) ?></h2>

            <label for="company_name"><?= e(t('pro.field.company_name')) ?></label>
            <input type="text" id="company_name" name="company_name" value="<?= $val('company_name', $s1) ?>" required
                   maxlength="<?= (int) config('pro.company_max', 150) ?>" placeholder="<?= e(t('pro.field.company_name_ph')) ?>">
            <p class="hint"><?= e(t('pro.field.company_name_hint')) ?></p>
            <?php if (has_error('company_name')): ?><p class="field-error"><?= e(error('company_name')) ?></p><?php endif; ?>

            <label for="legal_form"><?= e(t('pro.field.legal_form')) ?></label>
            <select id="legal_form" name="legal_form" required>
                <option value=""><?= e(t('field.choose')) ?></option>
                <?php $selForm = old('legal_form') ?: (string) ($s1['legal_form'] ?? ''); ?>
                <?php foreach ($legalForms as $lf): ?>
                    <option value="<?= e($lf) ?>" <?= $selForm === $lf ? 'selected' : '' ?>><?= e(t('pro.legal.' . $lf)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('legal_form')): ?><p class="field-error"><?= e(error('legal_form')) ?></p><?php endif; ?>

            <label for="legal_name"><?= e(t('pro.field.legal_name')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="legal_name" name="legal_name" value="<?= $val('legal_name', $s1) ?>" maxlength="150">

            <div class="grid-2">
                <div>
                    <label for="reg_number"><?= e(t('pro.field.reg_number')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="reg_number" name="reg_number" value="<?= $val('reg_number', $s1) ?>" maxlength="64"
                           placeholder="<?= e(t('pro.field.reg_number_ph')) ?>">
                    <?php if (has_error('reg_number')): ?><p class="field-error"><?= e(error('reg_number')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="vat_number"><?= e(t('pro.field.vat_number')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="vat_number" name="vat_number" value="<?= $val('vat_number', $s1) ?>" maxlength="32">
                </div>
            </div>
            <div class="notice notice-info">
                <p>🛡️ <?= e(t('pro.field.reg_number_trust')) ?></p>
            </div>

            <label for="description"><?= e(t('pro.field.description')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="<?= (int) config('pro.description_max', 500) ?>"
                      placeholder="<?= e(t('pro.field.description_ph')) ?>"><?= $val('description', $s1) ?></textarea>
            <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block"><?= e(t('pro.next')) ?> →</button>

        <?php elseif ($step === 2): ?>
            <h2 class="wizard-h2"><?= e(t('pro.step2_title')) ?></h2>

            <?php
            $selCountry = old('country_code') ?: (string) ($s2['country_code'] ?? ($detected_country ?? ''));
            $selDial    = old('dial_country') ?: (string) ($s2['dial_country'] ?? $selCountry);
            ?>
            <div class="grid-2">
                <div>
                    <label for="country_code"><?= e(t('field.country')) ?></label>
                    <select id="country_code" name="country_code" required>
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $selCountry === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="city"><?= e(t('field.city')) ?></label>
                    <input type="text" id="city" name="city" value="<?= $val('city', $s2) ?>" required maxlength="120">
                    <?php if (has_error('city')): ?><p class="field-error"><?= e(error('city')) ?></p><?php endif; ?>
                </div>
            </div>

            <label for="address"><?= e(t('pro.field.address')) ?></label>
            <input type="text" id="address" name="address" value="<?= $val('address', $s2) ?>" required maxlength="220"
                   placeholder="<?= e(t('pro.field.address_ph')) ?>" autocomplete="street-address">
            <?php if (has_error('address')): ?><p class="field-error"><?= e(error('address')) ?></p><?php endif; ?>

            <label for="email"><?= e(t('pro.field.email')) ?></label>
            <input type="email" id="email" name="email" value="<?= $val('email', $s2) ?>" required autocomplete="email"
                   placeholder="contact@entreprise.com">
            <p class="hint"><?= e(t('pro.field.email_hint')) ?></p>
            <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>

            <label><?= e(t('pro.field.phone')) ?></label>
            <div class="phone-row">
                <select name="dial_country" class="dial-select" aria-label="<?= e(t('field.dial_code')) ?>">
                    <?php foreach ($countries as $code => $name): $dc = dial_code($code); if ($dc === '') { continue; } ?>
                        <option value="<?= e($code) ?>" <?= $selDial === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?> (+<?= e($dc) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="tel" name="phone_number" value="<?= $val('phone_national', $s2) ?>" inputmode="tel"
                       placeholder="<?= e(t('field.phone_placeholder')) ?>" class="phone-input" autocomplete="tel-national">
            </div>
            <?php if (has_error('phone')): ?><p class="field-error"><?= e(error('phone')) ?></p><?php endif; ?>

            <label class="check-row">
                <input type="checkbox" name="whatsapp_optin" value="1"
                    <?= (old('whatsapp_optin') === '1' || (old('whatsapp_optin') === '' && !empty($s2['whatsapp_optin']))) ? 'checked' : '' ?>>
                <span><?= e(t('pro.field.whatsapp')) ?></span>
            </label>

            <label for="website"><?= e(t('pro.field.website')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="website" name="website" value="<?= $val('website', $s2) ?>" maxlength="200"
                   placeholder="https://… / facebook.com/…">
            <?php if (has_error('website')): ?><p class="field-error"><?= e(error('website')) ?></p><?php endif; ?>

            <label><?= e(t('pro.field.languages')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <?php $selLangs = explode(',', (string) ($s2['languages'] ?? '')); ?>
            <div class="lang-checks">
                <?php foreach ($langs as $lg): ?>
                    <label class="check-pill">
                        <input type="checkbox" name="languages[]" value="<?= e($lg) ?>" <?= in_array($lg, $selLangs, true) ? 'checked' : '' ?>>
                        <span><?= e(t('pro.lang.' . $lg)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="wizard-nav">
                <a class="btn btn-ghost" href="<?= e(url('/register/professionnel?etape=1')) ?>">← <?= e(t('pro.back')) ?></a>
                <button type="submit" class="btn btn-primary"><?= e(t('pro.next')) ?> →</button>
            </div>

        <?php else: ?>
            <h2 class="wizard-h2"><?= e(t('pro.step3_title')) ?></h2>

            <div class="recap">
                <p class="recap-head"><strong><?= e(t('pro.recap_title')) ?></strong></p>
                <dl class="recap-list">
                    <dt><?= e(t('pro.field.company_name')) ?></dt><dd><?= e((string) ($s1['company_name'] ?? '')) ?></dd>
                    <dt><?= e(t('pro.field.legal_form')) ?></dt><dd><?= e(t('pro.legal.' . ($s1['legal_form'] ?? 'autre'))) ?></dd>
                    <?php if (!empty($s1['legal_name'])): ?><dt><?= e(t('pro.field.legal_name')) ?></dt><dd><?= e((string) $s1['legal_name']) ?></dd><?php endif; ?>
                    <dt><?= e(t('pro.field.reg_number')) ?></dt>
                    <dd><?= !empty($s1['reg_number']) ? e((string) $s1['reg_number']) : '<span class="muted">' . e(t('pro.recap_none')) . '</span>' ?></dd>
                    <dt><?= e(t('field.country')) ?> / <?= e(t('field.city')) ?></dt>
                    <dd><?= flag_emoji((string) ($s2['country_code'] ?? '')) ?> <?= e($countries[$s2['country_code'] ?? ''] ?? '') ?> — <?= e((string) ($s2['city'] ?? '')) ?></dd>
                    <dt><?= e(t('pro.field.address')) ?></dt><dd><?= e((string) ($s2['address'] ?? '')) ?></dd>
                    <dt><?= e(t('pro.field.email')) ?></dt><dd><?= e((string) ($s2['email'] ?? '')) ?></dd>
                    <dt><?= e(t('field.phone')) ?></dt><dd><?= e((string) ($s2['phone'] ?? '')) ?><?= !empty($s2['whatsapp_optin']) ? ' · WhatsApp ✓' : '' ?></dd>
                </dl>
                <p class="hint">
                    <a href="<?= e(url('/register/professionnel?etape=1')) ?>"><?= e(t('pro.recap_edit1')) ?></a> ·
                    <a href="<?= e(url('/register/professionnel?etape=2')) ?>"><?= e(t('pro.recap_edit2')) ?></a>
                </p>
            </div>

            <label for="full_name"><?= e(t('pro.field.full_name')) ?></label>
            <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required maxlength="150"
                   autocomplete="name" placeholder="<?= e(t('pro.field.full_name_ph')) ?>">
            <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>

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
                <input type="checkbox" name="accept_terms" value="1" <?= old('accept_terms') === '1' ? 'checked' : '' ?> required>
                <span><?= e(t('pro.accept_terms')) ?></span>
            </label>
            <?php if (has_error('accept_terms')): ?><p class="field-error"><?= e(error('accept_terms')) ?></p><?php endif; ?>

            <label class="check-row">
                <input type="checkbox" name="accept_privacy" value="1" <?= old('accept_privacy') === '1' ? 'checked' : '' ?> required>
                <span><?= e(t('pro.accept_privacy')) ?></span>
            </label>
            <?php if (has_error('accept_privacy')): ?><p class="field-error"><?= e(error('accept_privacy')) ?></p><?php endif; ?>

            <div class="wizard-nav">
                <a class="btn btn-ghost" href="<?= e(url('/register/professionnel?etape=2')) ?>">← <?= e(t('pro.back')) ?></a>
                <button type="submit" class="btn btn-primary"><?= e(t('pro.submit')) ?></button>
            </div>
        <?php endif; ?>
    </form>

    <p class="auth-alt"><a href="<?= e(url('/register')) ?>">← <?= e(t('register.back_choice')) ?></a></p>
</section>
