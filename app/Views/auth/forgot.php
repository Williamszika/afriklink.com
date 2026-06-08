<section class="auth-card">
    <h1><?= e(t('auth.forgot.title')) ?></h1>
    <p class="muted"><?= e(t('auth.forgot.subtitle')) ?></p>

    <form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="email"><?= e(t('field.email')) ?></label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email">

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.forgot.submit')) ?></button>
    </form>

    <p class="auth-alt">
        <a href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
    </p>
</section>
