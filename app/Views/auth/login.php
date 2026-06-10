<section class="auth-card">
    <h1><?= e(t('auth.login.title')) ?></h1>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="identifier"><?= e(t('field.identifier')) ?></label>
        <input type="text" id="identifier" name="identifier" value="<?= old('identifier') ?>" required autocomplete="username"
               placeholder="<?= e(t('field.identifier_placeholder')) ?>">

        <label for="password"><?= e(t('field.password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.login.submit')) ?></button>
    </form>

    <p class="auth-alt">
        <a href="<?= e(url('/forgot-password')) ?>"><?= e(t('auth.forgot_link')) ?></a>
    </p>
    <p class="auth-alt"><?= e(t('auth.no_account')) ?>
        <a href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
    </p>
</section>
