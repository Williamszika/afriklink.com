<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var int $completion */
$legalForms = config('pro.legal_forms', []);
$langs      = config('pro.languages', []);
$v = static fn (string $key): string => old($key) !== '' ? old($key) : e((string) ($profile[$key] ?? ''));
$selForm  = old('legal_form') ?: (string) ($profile['legal_form'] ?? '');
$selLangs = old('languages') !== '' ? [] : explode(',', (string) ($profile['languages'] ?? ''));
$waOn     = old('whatsapp_optin') === '1' || (old('whatsapp_optin') === '' && !empty($profile['whatsapp_optin']));
$verified = ($profile['verification_status'] ?? 'pending') === 'verified';
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🏢 <?= e(t('seller.profile_title')) ?></h1>
            <p class="muted"><?= e(t('seller.profile_subtitle')) ?></p>
        </div>

        <!-- Logo de l'entreprise -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('seller.logo_title')) ?></h2>
            <div class="logo-row">
                <?php if ($avatar_url !== null): ?>
                    <img class="avatar avatar-img" src="<?= e($avatar_url) ?>" alt="" width="72" height="72">
                <?php else: ?>
                    <div class="avatar" aria-hidden="true">🏪</div>
                <?php endif; ?>
                <div class="avatar-forms">
                    <form method="post" action="<?= e(url('/profile/photo')) ?>" enctype="multipart/form-data" class="avatar-upload">
                        <?= csrf_field() ?>
                        <input type="file" id="avatar-input" name="photo" accept="image/jpeg,image/png,image/webp" required>
                        <button type="submit" class="btn btn-primary btn-sm"><?= e(t('seller.logo_change')) ?></button>
                    </form>
                    <?php if ($avatar_url !== null): ?>
                        <form method="post" action="<?= e(url('/profile/photo/delete')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('profile.photo_delete')) ?></button>
                        </form>
                    <?php endif; ?>
                    <p class="hint"><?= e(t('profile.photo_hint')) ?></p>
                    <?php if (has_error('photo')): ?><p class="field-error"><?= e(error('photo')) ?></p><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informations entreprise -->
        <form method="post" action="<?= e(url('/vendeur/profil')) ?>" class="panel" novalidate>
            <?= csrf_field() ?>
            <h2 class="panel-title"><?= e(t('pro.step1_title')) ?></h2>

            <label for="company_name"><?= e(t('pro.field.company_name')) ?></label>
            <input type="text" id="company_name" name="company_name" value="<?= $v('company_name') ?>" required
                   maxlength="<?= (int) config('pro.company_max', 150) ?>">
            <?php if (has_error('company_name')): ?><p class="field-error"><?= e(error('company_name')) ?></p><?php endif; ?>

            <label for="description"><?= e(t('pro.field.description')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="<?= (int) config('pro.description_max', 500) ?>"
                      placeholder="<?= e(t('pro.field.description_ph')) ?>"><?= $v('description') ?></textarea>
            <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

            <div class="grid-2">
                <div>
                    <label for="legal_form"><?= e(t('pro.field.legal_form')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <select id="legal_form" name="legal_form">
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php foreach ($legalForms as $lf): ?>
                            <option value="<?= e($lf) ?>" <?= $selForm === $lf ? 'selected' : '' ?>><?= e(t('pro.legal.' . $lf)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="legal_name"><?= e(t('pro.field.legal_name')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="legal_name" name="legal_name" value="<?= $v('legal_name') ?>" maxlength="150">
                    <?php if (has_error('legal_name')): ?><p class="field-error"><?= e(error('legal_name')) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="reg_number"><?= e(t('pro.field.reg_number')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="reg_number" name="reg_number" value="<?= $v('reg_number') ?>" maxlength="64"
                           placeholder="<?= e(t('pro.field.reg_number_ph')) ?>">
                    <?php if (has_error('reg_number')): ?><p class="field-error"><?= e(error('reg_number')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="vat_number"><?= e(t('pro.field.vat_number')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="vat_number" name="vat_number" value="<?= $v('vat_number') ?>" maxlength="32">
                </div>
            </div>
            <?php if (!$verified): ?>
                <div class="notice notice-info"><p>🛡️ <?= e(t('pro.field.reg_number_trust')) ?></p></div>
            <?php endif; ?>

            <label for="address"><?= e(t('pro.field.address')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="address" name="address" value="<?= $v('address') ?>" maxlength="220"
                   placeholder="<?= e(t('pro.field.address_ph')) ?>" autocomplete="street-address">
            <?php if (has_error('address')): ?><p class="field-error"><?= e(error('address')) ?></p><?php endif; ?>

            <label for="website"><?= e(t('pro.field.website')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="website" name="website" value="<?= $v('website') ?>" maxlength="200"
                   placeholder="https://… / facebook.com/…">
            <?php if (has_error('website')): ?><p class="field-error"><?= e(error('website')) ?></p><?php endif; ?>

            <label class="check-row">
                <input type="checkbox" name="whatsapp_optin" value="1" <?= $waOn ? 'checked' : '' ?>>
                <span><?= e(t('pro.field.whatsapp')) ?></span>
            </label>

            <label><?= e(t('pro.field.languages')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <div class="lang-checks">
                <?php foreach ($langs as $lg): ?>
                    <label class="check-pill">
                        <input type="checkbox" name="languages[]" value="<?= e($lg) ?>" <?= in_array($lg, $selLangs, true) ? 'checked' : '' ?>>
                        <span><?= e(t('pro.lang.' . $lg)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(t('profile.save')) ?></button>
        </form>

    </div>
</div>
