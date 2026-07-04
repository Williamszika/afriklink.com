<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var array $prefs */
$emailOk     = !empty($user['email_verified_at']);
$curLocale   = current_locale();
$curCurrency = current_currency();
$locales     = config('app.locales', ['fr', 'en']);
$currencies  = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$prefs       = $prefs ?? ['notify_email' => true, 'notify_sms' => true, 'payout_method' => null, 'payout_destination' => null];
$pwMin       = (int) config('app.password_min_length', 12);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sset">

        <div class="sset-topbar">
            <h1><?= e(t('settings.title')) ?></h1>
            <p><?= e(t('settings.subtitle')) ?></p>
        </div>

        <!-- COMPTE -->
        <div class="sset-panel">
            <h2 class="sset-sec"><span aria-hidden="true">👤</span> <?= e(t('settings.account_title')) ?></h2>
            <p class="sset-sub"><?= e(t('settings.account_sub')) ?></p>

            <div class="sset-ro">
                <span class="ic" aria-hidden="true"><?= icon('mail', ['size' => 17]) ?></span>
                <span class="rt">
                    <span class="val"><?= e((string) ($user['email'] ?? '—')) ?></span>
                    <span class="k"><?= e(t('field.email')) ?></span>
                </span>
                <?php if ($emailOk): ?>
                    <span class="sset-vbadge"><?= icon('check', ['size' => 11]) ?> <?= e(t('dash.badge_verified')) ?></span>
                <?php else: ?>
                    <a class="sset-resend" href="<?= e(url('/verify-email/notice')) ?>"><?= e(t('dash.badge_unverified')) ?> · <?= e(t('verify.resend')) ?></a>
                <?php endif; ?>
            </div>
            <div class="sset-ro">
                <span class="ic" aria-hidden="true"><?= icon('chat', ['size' => 17]) ?></span>
                <span class="rt">
                    <span class="val"><?= e((string) ($user['phone'] ?? '—')) ?></span>
                    <span class="k"><?= e(t('field.phone')) ?></span>
                </span>
            </div>
            <p class="sset-locked"><?= icon('lock', ['size' => 14]) ?> <?= e(t('settings.contact_locked')) ?></p>
        </div>

        <!-- LANGUE & DEVISE -->
        <form method="post" action="<?= e(url('/profile/preferences')) ?>" class="sset-panel" novalidate>
            <?= csrf_field() ?>
            <h2 class="sset-sec"><span aria-hidden="true">🌍</span> <?= e(t('settings.prefs_title')) ?></h2>
            <p class="sset-sub"><?= e(t('settings.prefs_hint')) ?></p>

            <div class="sset-field">
                <label class="sset-lbl"><?= e(t('field.locale')) ?></label>
                <div class="sset-pills">
                    <?php foreach ($locales as $loc): ?>
                        <label class="sset-pill">
                            <input type="radio" name="locale" value="<?= e($loc) ?>" class="sset-pill-input" <?= $curLocale === $loc ? 'checked' : '' ?>>
                            <span class="sset-pill-face"><?= e(t('settings.lang.' . $loc)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sset-field">
                <label class="sset-lbl"><?= e(t('field.currency')) ?></label>
                <div class="sset-pills">
                    <?php foreach ($currencies as $cur): ?>
                        <label class="sset-pill">
                            <input type="radio" name="currency" value="<?= e($cur) ?>" class="sset-pill-input" <?= $curCurrency === $cur ? 'checked' : '' ?>>
                            <span class="sset-pill-face"><?= e($cur) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sset-savebar"><button type="submit" class="btn btn-gold"><?= e(t('profile.save')) ?></button></div>
        </form>

        <!-- NOTIFICATIONS & VERSEMENT (un seul envoi) -->
        <form method="post" action="<?= e(url('/vendeur/reglages')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="sset-panel">
                <h2 class="sset-sec"><span aria-hidden="true">🔔</span> <?= e(t('settings.notify_title')) ?></h2>
                <p class="sset-sub"><?= e(t('settings.notify_hint')) ?></p>
                <label class="sset-toggle">
                    <span class="l"><?= icon('mail', ['size' => 18]) ?> <?= e(t('settings.notify_email')) ?></span>
                    <input type="checkbox" name="notify_email" value="1" class="sset-switch-input" <?= !empty($prefs['notify_email']) ? 'checked' : '' ?>>
                    <span class="sset-switch" aria-hidden="true"></span>
                </label>
                <label class="sset-toggle">
                    <span class="l"><?= icon('chat', ['size' => 18]) ?> <?= e(t('settings.notify_sms')) ?></span>
                    <input type="checkbox" name="notify_sms" value="1" class="sset-switch-input" <?= !empty($prefs['notify_sms']) ? 'checked' : '' ?>>
                    <span class="sset-switch" aria-hidden="true"></span>
                </label>
            </div>

            <div class="sset-panel">
                <h2 class="sset-sec"><span aria-hidden="true">💳</span> <?= e(t('settings.payout_title')) ?></h2>
                <p class="sset-sub"><?= e(t('settings.payout_hint')) ?></p>
                <div class="sset-field">
                    <label class="sset-lbl"><?= e(t('wallet.method')) ?></label>
                    <div class="sset-pills">
                        <label class="sset-pill">
                            <input type="radio" name="payout_method" value="" class="sset-pill-input" <?= empty($prefs['payout_method']) ? 'checked' : '' ?>>
                            <span class="sset-pill-face"><?= e(t('settings.payout_none')) ?></span>
                        </label>
                        <label class="sset-pill">
                            <input type="radio" name="payout_method" value="mobile_money" class="sset-pill-input" <?= ($prefs['payout_method'] ?? '') === 'mobile_money' ? 'checked' : '' ?>>
                            <span class="sset-pill-face"><?= e(t('wallet.method.mobile_money')) ?></span>
                        </label>
                        <label class="sset-pill">
                            <input type="radio" name="payout_method" value="bank" class="sset-pill-input" <?= ($prefs['payout_method'] ?? '') === 'bank' ? 'checked' : '' ?>>
                            <span class="sset-pill-face"><?= e(t('wallet.method.bank')) ?></span>
                        </label>
                    </div>
                </div>
                <div class="sset-field">
                    <label class="sset-lbl" for="payout_destination"><?= e(t('wallet.destination')) ?> <span class="opt">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="payout_destination" name="payout_destination" maxlength="160" value="<?= e((string) ($prefs['payout_destination'] ?? '')) ?>" placeholder="<?= e(t('wallet.destination_ph')) ?>">
                </div>
                <div class="sset-savebar"><button type="submit" class="btn btn-gold"><?= e(t('profile.save')) ?></button></div>
            </div>
        </form>

        <!-- MOT DE PASSE -->
        <form method="post" action="<?= e(url('/profile/password')) ?>" class="sset-panel" id="sec-password" novalidate>
            <?= csrf_field() ?>
            <h2 class="sset-sec"><span aria-hidden="true">🔒</span> <?= e(t('profile.password_title')) ?></h2>
            <p class="sset-sub"><?= e(t('settings.password_sub')) ?></p>

            <?= render_partial('partials/pwd_field', [
                'id' => 'current_password', 'label' => t('profile.current_password'),
                'autocomplete' => 'current-password', 'error' => 'current_password',
            ]) ?>
            <div class="sset-two">
                <?= render_partial('partials/pwd_field', [
                    'id' => 'password', 'label' => t('profile.new_password'), 'strength' => true,
                    'minlength' => $pwMin, 'hint' => t('auth.password_hint', ['min' => $pwMin]), 'error' => 'password',
                ]) ?>
                <?= render_partial('partials/pwd_field', [
                    'id' => 'password_confirm', 'label' => t('field.password_confirm'),
                    'match' => 'password', 'error' => 'password_confirm',
                ]) ?>
            </div>
            <div class="sset-savebar"><button type="submit" class="btn btn-green"><?= e(t('profile.change_password')) ?></button></div>
        </form>

        <!-- DONNÉES PERSONNELLES (RGPD) -->
        <div class="sset-panel">
            <h2 class="sset-sec"><span aria-hidden="true">🛡️</span> <?= e(t('privacy.title')) ?></h2>
            <p class="sset-sub"><?= e(t('privacy.profile_teaser')) ?></p>
            <div class="sset-gdpr">
                <div class="gt">
                    <div class="gh"><?= e(t('privacy.export_or_delete')) ?></div>
                    <div class="gs"><?= e(t('privacy.you_stay_in_control')) ?></div>
                </div>
                <a class="btn btn-ghost" href="<?= e(url('/profile/donnees')) ?>"><?= e(t('privacy.open')) ?> →</a>
            </div>
            <div class="sset-danger">
                <?= icon('info', ['size' => 15]) ?>
                <span><?= e(t('settings.delete_warning')) ?></span>
            </div>
        </div>

    </div>
</div>
