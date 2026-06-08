<section class="auth-card">
    <h1><?= e(t('auth.register.title')) ?></h1>
    <p class="muted"><?= e(t('auth.register.subtitle')) ?></p>

    <form method="post" action="<?= e(url('/register')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="email"><?= e(t('field.email')) ?></label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email">
        <?php if (has_error('email')): ?><p class="field-error"><?= e(error('email')) ?></p><?php endif; ?>

        <label for="password"><?= e(t('field.password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="new-password"
               minlength="<?= (int) config('app.password_min_length', 12) ?>">
        <p class="hint"><?= e(t('auth.password_hint', ['min' => config('app.password_min_length', 12)])) ?></p>
        <?php if (has_error('password')): ?><p class="field-error"><?= e(error('password')) ?></p><?php endif; ?>

        <label for="password_confirm"><?= e(t('field.password_confirm')) ?></label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        <?php if (has_error('password_confirm')): ?><p class="field-error"><?= e(error('password_confirm')) ?></p><?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="locale"><?= e(t('field.locale')) ?></label>
                <select id="locale" name="locale">
                    <?php foreach (config('app.locales', ['fr', 'en']) as $loc): ?>
                        <option value="<?= e($loc) ?>" <?= $loc === current_locale() ? 'selected' : '' ?>>
                            <?= e(strtoupper($loc)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="country"><?= e(t('field.country')) ?></label>
                <input type="text" id="country" name="country" value="<?= old('country') ?>"
                       maxlength="2" placeholder="DE" autocomplete="country">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.register.submit')) ?></button>
    </form>

    <p class="auth-alt"><?= e(t('auth.have_account')) ?>
        <a href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
    </p>
</section>
