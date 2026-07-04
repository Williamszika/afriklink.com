<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var int $completion */
$legalForms = config('pro.legal_forms', []);
$langs      = config('pro.languages', []);
$v = static fn (string $key): string => old($key) !== '' ? old($key) : e((string) ($profile[$key] ?? ''));
$selForm  = old('legal_form') ?: (string) ($profile['legal_form'] ?? '');
$selLangs = old('languages') !== '' ? [] : explode(',', (string) ($profile['languages'] ?? ''));
$waOn     = old('whatsapp_optin') === '1' || (old('whatsapp_optin') === '' && !empty($profile['whatsapp_optin']));
$verified = ($profile['verification_status'] ?? 'pending') === 'verified';

$company = old('company_name') !== '' ? old('company_name') : (string) ($profile['company_name'] ?? '');
$initial = mb_strtoupper(mb_substr($company !== '' ? $company : (string) ($user['full_name'] ?? '?'), 0, 1));
$shopName = $company !== '' ? $company : t('seller.your_shop');
$isCustomForm = $selForm !== '' && !in_array($selForm, $legalForms, true);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sprof">

        <div class="sprof-topbar">
            <div>
                <h1><?= e(t('seller.profile_title')) ?></h1>
                <p><?= e(t('seller.profile_subtitle')) ?></p>
            </div>
            <button type="submit" form="sprof-form" class="btn btn-gold"><?= e(t('profile.save')) ?></button>
        </div>

        <div class="sprof-grid">

            <!-- COLONNE PRINCIPALE : formulaire unique, deux panneaux visuels -->
            <form id="sprof-form" method="post" action="<?= e(url('/vendeur/profil')) ?>" class="sprof-col" novalidate>
                <?= csrf_field() ?>

                <div class="sprof-panel">
                    <h2 class="sprof-sec"><span aria-hidden="true">🏢</span> <?= e(t('pro.step1_title')) ?></h2>
                    <p class="sprof-sub"><?= e(t('seller.profile_company_sub')) ?></p>

                    <div class="sprof-field">
                        <label for="company_name"><?= e(t('pro.field.company_name')) ?></label>
                        <input type="text" id="company_name" name="company_name" value="<?= $v('company_name') ?>" required maxlength="<?= (int) config('pro.company_max', 150) ?>">
                        <?php if (has_error('company_name')): ?><p class="sprof-err"><?= e(error('company_name')) ?></p><?php endif; ?>
                    </div>

                    <div class="sprof-field">
                        <label for="description"><?= e(t('pro.field.description')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="<?= (int) config('pro.description_max', 500) ?>" placeholder="<?= e(t('pro.field.description_ph')) ?>"><?= $v('description') ?></textarea>
                        <?php if (has_error('description')): ?><p class="sprof-err"><?= e(error('description')) ?></p><?php endif; ?>
                    </div>

                    <div class="sprof-two">
                        <div class="sprof-field">
                            <label for="legal_form"><?= e(t('pro.field.legal_form')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                            <select id="legal_form" name="legal_form">
                                <option value=""><?= e(t('field.choose')) ?></option>
                                <?php foreach ($legalForms as $lf): ?>
                                    <option value="<?= e($lf) ?>" <?= ($isCustomForm ? $lf === 'autre' : $selForm === $lf) ? 'selected' : '' ?>><?= e(t('pro.legal.' . $lf)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="other-box sprof-other" data-other-for="#legal_form" data-other-value="autre" <?= ($isCustomForm || $selForm === 'autre') ? '' : 'hidden' ?>>
                                <label for="legal_form_other"><?= e(t('field.other_specify')) ?></label>
                                <input type="text" id="legal_form_other" name="legal_form_other" maxlength="60" value="<?= old('legal_form_other') ?: ($isCustomForm ? e($selForm) : '') ?>" placeholder="<?= e(t('field.other_specify_ph')) ?>">
                            </div>
                        </div>
                        <div class="sprof-field">
                            <label for="legal_name"><?= e(t('pro.field.legal_name')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="text" id="legal_name" name="legal_name" value="<?= $v('legal_name') ?>" maxlength="150">
                            <?php if (has_error('legal_name')): ?><p class="sprof-err"><?= e(error('legal_name')) ?></p><?php endif; ?>
                        </div>
                    </div>

                    <div class="sprof-two">
                        <div class="sprof-field">
                            <label for="reg_number"><?= e(t('pro.field.reg_number')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="text" id="reg_number" name="reg_number" value="<?= $v('reg_number') ?>" maxlength="64" placeholder="<?= e(t('pro.field.reg_number_ph')) ?>">
                            <?php if (has_error('reg_number')): ?><p class="sprof-err"><?= e(error('reg_number')) ?></p><?php endif; ?>
                        </div>
                        <div class="sprof-field">
                            <label for="vat_number"><?= e(t('pro.field.vat_number')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                            <input type="text" id="vat_number" name="vat_number" value="<?= $v('vat_number') ?>" maxlength="32">
                        </div>
                    </div>

                    <?php if (!$verified): ?>
                        <div class="sprof-note">
                            <?= icon('shield', ['size' => 17]) ?>
                            <span><?= e(t('pro.field.reg_number_trust')) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sprof-panel">
                    <h2 class="sprof-sec"><span aria-hidden="true">📍</span> <?= e(t('seller.profile_contact')) ?></h2>
                    <p class="sprof-sub"><?= e(t('seller.profile_contact_sub')) ?></p>

                    <div class="sprof-field">
                        <label for="address"><?= e(t('pro.field.address')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="address" name="address" value="<?= $v('address') ?>" maxlength="220" placeholder="<?= e(t('pro.field.address_ph')) ?>" autocomplete="street-address">
                        <?php if (has_error('address')): ?><p class="sprof-err"><?= e(error('address')) ?></p><?php endif; ?>
                    </div>

                    <div class="sprof-field">
                        <label for="website"><?= e(t('pro.field.website')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="website" name="website" value="<?= $v('website') ?>" maxlength="200" placeholder="https://… / facebook.com/…">
                        <?php if (has_error('website')): ?><p class="sprof-err"><?= e(error('website')) ?></p><?php endif; ?>
                    </div>

                    <div class="sprof-field">
                        <label class="sprof-switch-row">
                            <span class="l"><?= icon('chat', ['size' => 18]) ?> <?= e(t('pro.field.whatsapp')) ?></span>
                            <input type="checkbox" name="whatsapp_optin" value="1" class="sprof-switch-input" <?= $waOn ? 'checked' : '' ?>>
                            <span class="sprof-switch" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="sprof-field">
                        <label><?= e(t('pro.field.languages')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                        <div class="sprof-chips">
                            <?php foreach ($langs as $lg): ?>
                                <label class="sprof-chip">
                                    <input type="checkbox" name="languages[]" value="<?= e($lg) ?>" class="sprof-chip-input" <?= in_array($lg, $selLangs, true) ? 'checked' : '' ?>>
                                    <span class="sprof-chip-face"><?= e(t('pro.lang.' . $lg)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="sprof-savebar">
                    <button type="submit" class="btn btn-gold"><?= e(t('profile.save')) ?></button>
                </div>
            </form>

            <!-- ASIDE : logo + badge -->
            <aside class="sprof-aside">
                <div class="sprof-logo-card">
                    <?php if ($avatar_url !== null): ?>
                        <img class="sprof-logo-img" src="<?= e($avatar_url) ?>" alt="" width="120" height="120">
                    <?php else: ?>
                        <div class="sprof-logo-tile" aria-hidden="true"><?= e($initial) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/profile/photo')) ?>" enctype="multipart/form-data" class="sprof-logo-form">
                        <?= csrf_field() ?>
                        <input type="file" id="avatar-input" name="photo" accept="image/jpeg,image/png,image/webp" required class="sprof-file">
                        <button type="submit" class="btn btn-green btn-sm"><?= e(t('seller.logo_change')) ?></button>
                    </form>
                    <?php if ($avatar_url !== null): ?>
                        <form method="post" action="<?= e(url('/profile/photo/delete')) ?>" class="sprof-logo-del">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('profile.photo_delete')) ?></button>
                        </form>
                    <?php endif; ?>
                    <p class="sprof-logo-note"><?= e(t('profile.photo_hint')) ?></p>
                    <?php if (has_error('photo')): ?><p class="sprof-err"><?= e(error('photo')) ?></p><?php endif; ?>
                </div>

                <div class="sprof-badge-card <?= $verified ? 'is-unlocked' : 'is-locked' ?>">
                    <div class="bh">
                        <span class="bic" aria-hidden="true"><?= $verified ? icon('check', ['size' => 20]) : icon('lock', ['size' => 20]) ?></span>
                        <div class="bt">
                            <b><?= e($verified ? t('seller.badge_unlocked') : t('seller.badge_locked')) ?></b>
                            <span><?= e(t('seller.verified_seller')) ?></span>
                        </div>
                    </div>
                    <p><?= e($verified ? t('seller.badge_unlocked_desc') : t('seller.badge_locked_desc')) ?></p>
                    <div class="sprof-preview">
                        <span class="pav" aria-hidden="true"><?= e($initial) ?></span>
                        <span class="pn">
                            <span class="pnm"><?= e($shopName) ?></span>
                            <?php if ($verified): ?><span class="sprof-vbadge"><?= icon('check', ['size' => 11]) ?> <?= e(t('seller.verified_mini')) ?></span><?php endif; ?>
                        </span>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</div>
