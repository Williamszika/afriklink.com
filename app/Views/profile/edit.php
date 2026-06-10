<?php
/** @var array $user  @var array $countries */
$hasEmail = !empty($user['email']);
$contact  = $hasEmail ? (string) ($user['email'] ?? '') : (string) ($user['phone'] ?? '');
$bd       = old('birthdate') ?: (!empty($user['birthdate']) ? date('d/m/Y', strtotime((string) $user['birthdate'])) : '');
$g        = old('gender') ?: (string) ($user['gender'] ?? '');
$cc       = old('country_code') ?: strtoupper((string) ($user['country_code'] ?? ''));
$city     = old('city') ?: (string) ($user['city'] ?? '');
?>
<section class="profile">
    <div class="profile-head">
        <h1><?= e(t('profile.title')) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a>
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
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="country_code"><?= e(t('field.country')) ?></label>
                    <select id="country_code" name="country_code" required>
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $cc === $code ? 'selected' : '' ?>><?= flag_emoji($code) ?> <?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="city"><?= e(t('field.city')) ?></label>
                    <input type="text" id="city" name="city" value="<?= e($city) ?>" autocomplete="address-level2">
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
</section>
