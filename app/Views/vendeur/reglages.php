<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
$emailOk    = !empty($user['email_verified_at']);
$curLocale  = current_locale();
$curCurrency = current_currency();
$locales    = config('app.locales', ['fr', 'en']);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>⚙️ <?= e(t('settings.title')) ?></h1>
            <p class="muted"><?= e(t('settings.subtitle')) ?></p>
        </div>

        <!-- Compte -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('settings.account_title')) ?></h2>
            <dl class="meta">
                <dt><?= e(t('field.email')) ?></dt>
                <dd>
                    <?= e((string) ($user['email'] ?? '—')) ?>
                    <?php if ($emailOk): ?>
                        <span class="badge badge-ok"><?= e(t('dash.badge_verified')) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warn"><?= e(t('dash.badge_unverified')) ?></span>
                        — <a href="<?= e(url('/verify-email/notice')) ?>"><?= e(t('verify.resend')) ?></a>
                    <?php endif; ?>
                </dd>
                <dt><?= e(t('field.phone')) ?></dt>
                <dd><?= e((string) ($user['phone'] ?? '—')) ?></dd>
            </dl>
            <p class="hint"><?= e(t('settings.contact_locked')) ?></p>
        </div>

        <!-- Langue & devise -->
        <form method="post" action="<?= e(url('/profile/preferences')) ?>" class="panel" novalidate>
            <?= csrf_field() ?>
            <h2 class="panel-title"><?= e(t('settings.prefs_title')) ?></h2>
            <div class="grid-2">
                <div>
                    <label for="locale"><?= e(t('field.locale')) ?></label>
                    <select id="locale" name="locale">
                        <?php foreach ($locales as $loc): ?>
                            <option value="<?= e($loc) ?>" <?= $curLocale === $loc ? 'selected' : '' ?>><?= e(t('settings.lang.' . $loc)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="currency"><?= e(t('field.currency')) ?></label>
                    <select id="currency" name="currency">
                        <?php foreach ($currencies as $cur): ?>
                            <option value="<?= e($cur) ?>" <?= $curCurrency === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="hint"><?= e(t('settings.prefs_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(t('profile.save')) ?></button>
        </form>

        <!-- Mot de passe -->
        <form method="post" action="<?= e(url('/profile/password')) ?>" class="panel" id="sec-password" novalidate>
            <?= csrf_field() ?>
            <h2 class="panel-title"><?= e(t('profile.password_title')) ?></h2>

            <label for="current_password"><?= e(t('profile.current_password')) ?></label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            <?php if (has_error('current_password')): ?><p class="field-error"><?= e(error('current_password')) ?></p><?php endif; ?>

            <div class="grid-2">
                <div>
                    <label for="password"><?= e(t('profile.new_password')) ?></label>
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

            <button type="submit" class="btn btn-primary"><?= e(t('profile.change_password')) ?></button>
        </form>

    </div>
</div>
