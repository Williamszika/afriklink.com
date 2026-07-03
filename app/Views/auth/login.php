<?php /** Connexion — design .authx (voir CSS « AUTH v2 » dans app.css). */ ?>
<div class="authx">
    <div class="pagehead">
        <p class="eyebrow"><?= e(t('auth.eyebrow.login')) ?></p>
        <h1><?= e(t('auth.login.title')) ?></h1>
    </div>

    <div class="grid grid--solo">
        <div class="acard">
            <form method="post" action="<?= e(url('/login')) ?>" novalidate data-submit-once>
                <?= csrf_field() ?>

                <div class="afield">
                    <label class="albl" for="identifier"><?= e(t('field.identifier')) ?></label>
                    <input type="text" id="identifier" name="identifier" value="<?= old('identifier') ?>" required
                           autocomplete="username" placeholder="<?= e(t('field.identifier_placeholder')) ?>">
                </div>

                <?= render_partial('partials/pwd_field', [
                    'id' => 'password', 'label' => t('field.password'), 'autocomplete' => 'current-password',
                ]) ?>

                <button type="submit" class="abtn abtn--primary" style="margin-top:1.3rem"><?= e(t('auth.login.submit')) ?></button>

                <p class="auth-alt"><a href="<?= e(url('/forgot-password')) ?>"><?= e(t('auth.forgot_link')) ?></a></p>
                <p class="auth-alt"><?= e(t('auth.no_account')) ?>
                    <a href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
                </p>
            </form>
        </div>
    </div>
</div>
