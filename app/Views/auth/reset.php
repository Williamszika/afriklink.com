<?php /** @var string $token */ ?>
<section class="auth-card">
    <h1><?= e(t('auth.reset.title')) ?></h1>
    <p class="muted"><?= e(t('auth.reset.subtitle')) ?></p>

    <form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label for="password"><?= e(t('field.password_new')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="new-password"
               minlength="<?= (int) config('app.password_min_length', 12) ?>">
        <p class="hint"><?= e(t('auth.password_hint', ['min' => config('app.password_min_length', 12)])) ?></p>
        <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>

        <label for="password_confirm"><?= e(t('field.password_confirm')) ?></label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        <?php if (has_error('password_confirm')): ?><p class="field-error"><?= e(error('password_confirm')) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.reset.submit')) ?></button>
    </form>
</section>
