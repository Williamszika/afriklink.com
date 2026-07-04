<?php
/** @var array $user  @var array $countries  @var ?string $avatar_version */
$hasEmail  = !empty($user['email']);
$contact   = $hasEmail ? (string) ($user['email'] ?? '') : (string) ($user['phone'] ?? '');
$bd        = old('birthdate') ?: (!empty($user['birthdate']) ? date('d/m/Y', strtotime((string) $user['birthdate'])) : '');
$g         = old('gender') ?: (string) ($user['gender'] ?? '');
$autoGeo   = detected_geo(); // localisation détectée (IP/GPS) en secours quand le profil est vide
$savedCc   = strtoupper((string) ($user['country_code'] ?? ''));
$savedCity = (string) ($user['city'] ?? '');
$cc        = old('country_code') ?: ($savedCc ?: (string) ($autoGeo['country_code'] ?? ''));
$city      = old('city') ?: ($savedCity ?: (string) ($autoGeo['city'] ?? ''));
// Profil VIERGE (aucun pays/ville enregistrés, aucune saisie en cours) → pays et
// ville préremplis ET VERROUILLÉS depuis la géolocalisation quand elle tombe en
// zone (Afrique/Europe), comme à l'inscription. « Modifier » rouvre tout ; le GPS
// affine. Un profil déjà renseigné reste librement modifiable.
$geoFresh    = old('country_code') === '' && old('city') === '' && $savedCc === '' && $savedCity === '';
$lockCountry = $geoFresh && (string) ($autoGeo['country_code'] ?? '') !== ''
    && in_array(\App\Services\GeoService::continentOf((string) ($autoGeo['country_code'] ?? '')), ['africa', 'europe'], true);
$lockCity    = $lockCountry && (string) ($autoGeo['city'] ?? '') !== '';
$avatarUrl = avatar_url($user, $avatar_version ?? null);
?>
<section class="profile">
    <div class="profile-head">
        <h1><?= e(t('profile.title')) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a>
    </div>

    <!-- Photo de profil -->
    <div class="panel">
        <h2 class="panel-title"><?= e(t('profile.photo_title')) ?></h2>
        <div class="avatar-row">
            <?php if ($avatarUrl !== null): ?>
                <img class="avatar avatar-img avatar-lg" src="<?= e($avatarUrl) ?>" alt="" width="88" height="88">
            <?php else: ?>
                <div class="avatar avatar-lg" aria-hidden="true"><?= e(user_initials($user)) ?></div>
            <?php endif; ?>
            <div class="avatar-forms">
                <form method="post" action="<?= e(url('/profile/photo')) ?>" enctype="multipart/form-data" class="avatar-upload">
                    <?= csrf_field() ?>
                    <input type="file" id="avatar-input" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('profile.photo_change')) ?></button>
                </form>
                <?php if ($avatarUrl !== null): ?>
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

    <!-- Informations personnelles -->
    <div class="panel">
        <h2 class="panel-title"><?= e(t('profile.info_title')) ?></h2>
        <form method="post" action="<?= e(url('/profile')) ?>" novalidate>
            <?= csrf_field() ?>

            <label for="full_name"><?= e(t('field.full_name')) ?></label>
            <input type="text" id="full_name" name="full_name"
                   value="<?= old('full_name') ?: e((string) ($user['full_name'] ?? '')) ?>" required autocomplete="name">
            <?php if (has_error('full_name')): ?><p class="field-error"><?= e(error('full_name')) ?></p><?php endif; ?>

            <label for="nickname"><?= e(t('field.nickname')) ?></label>
            <input type="text" id="nickname" name="nickname"
                   value="<?= old('nickname') ?: e((string) ($user['nickname'] ?? '')) ?>" required maxlength="64" autocomplete="nickname">
            <?php if (has_error('nickname')): ?><p class="field-error"><?= e(error('nickname')) ?></p><?php endif; ?>

            <div class="grid-2">
                <div>
                    <label for="birthdate"><?= e(t('field.birthdate')) ?></label>
                    <input type="text" id="birthdate" name="birthdate" value="<?= e($bd) ?>"
                           inputmode="numeric" placeholder="jj/mm/aaaa" maxlength="10" required>
                    <?php if (has_error('birthdate')): ?><p class="field-error"><?= e(error('birthdate')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="gender"><?= e(t('field.gender')) ?></label>
                    <select id="gender" name="gender" required>
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <option value="homme" <?= $g === 'homme' ? 'selected' : '' ?>><?= e(t('gender.homme')) ?></option>
                        <option value="femme" <?= $g === 'femme' ? 'selected' : '' ?>><?= e(t('gender.femme')) ?></option>
                        <option value="autre" <?= $g === 'autre' ? 'selected' : '' ?>><?= e(t('gender.autre')) ?></option>
                    </select>
                    <?php if (has_error('gender')): ?><p class="field-error"><?= e(error('gender')) ?></p><?php endif; ?>
                    <div class="other-box" data-other-for="#gender" data-other-value="autre" <?= $g === 'autre' ? '' : 'hidden' ?>>
                        <label for="gender_other"><?= e(t('field.other_specify')) ?></label>
                        <input type="text" id="gender_other" name="gender_other" maxlength="40"
                               value="<?= old('gender_other') ?: e((string) ($user['gender_other'] ?? '')) ?>"
                               placeholder="<?= e(t('field.other_specify_ph')) ?>">
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="country_code"><?= e(t('field.country')) ?></label>
                    <?php if ($lockCountry): ?>
                        <select id="country_code" class="locked-field is-locked" disabled aria-disabled="true" tabindex="-1">
                            <option value=""><?= e(t('field.choose')) ?></option>
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?= e($code) ?>" <?= $cc === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="country_code" id="country_code_value" value="<?= e($cc) ?>">
                    <?php else: ?>
                        <select id="country_code" name="country_code" required>
                            <option value=""><?= e(t('field.choose')) ?></option>
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?= e($code) ?>" <?= $cc === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="city"><?= e(t('field.city')) ?></label>
                    <input type="text" id="city" name="city" value="<?= e($city) ?>" autocomplete="address-level2"<?= $lockCity ? ' readonly class="is-locked" data-geo-prefill="1"' : '' ?>>
                    <?= render_partial('partials/geo_lock_controls', ['locked' => $lockCountry || $lockCity]) ?>
                </div>
            </div>

            <p class="hint">
                <?= $hasEmail ? '✉️ ' : '📱 ' ?><?= e($contact) ?> · <?= e(t('profile.contact_locked')) ?>
            </p>

            <button type="submit" class="btn btn-primary"><?= e(t('profile.save')) ?></button>
        </form>
    </div>

    <!-- Mot de passe -->
    <div class="panel" id="password-section">
        <h2 class="panel-title"><?= e(t('profile.password_title')) ?></h2>
        <form method="post" action="<?= e(url('/profile/password')) ?>" novalidate>
            <?= csrf_field() ?>

            <label for="current_password"><?= e(t('profile.current_password')) ?></label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            <?php if (has_error('current_password')): ?><p class="field-error"><?= e(error('current_password')) ?></p><?php endif; ?>

            <label for="password"><?= e(t('profile.new_password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   minlength="<?= (int) config('app.password_min_length', 12) ?>">
            <p class="hint"><?= e(t('auth.password_hint', ['min' => config('app.password_min_length', 12)])) ?></p>
            <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>

            <label for="password_confirm"><?= e(t('field.password_confirm')) ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <?php if (has_error('password_confirm')): ?><p class="field-error"><?= e(error('password_confirm')) ?></p><?php endif; ?>

            <button type="submit" class="btn btn-primary"><?= e(t('profile.change_password')) ?></button>
        </form>
    </div>

    <!-- RGPD : accès, portabilité et effacement de mes données -->
    <div class="panel">
        <h2 class="panel-title"><?= e(t('privacy.title')) ?></h2>
        <p class="hint"><?= e(t('privacy.profile_teaser')) ?></p>
        <div class="dsar-actions">
            <a class="btn btn-ghost" href="<?= e(url('/profile/donnees')) ?>"><?= e(t('privacy.open')) ?> →</a>
        </div>
    </div>
</section>
