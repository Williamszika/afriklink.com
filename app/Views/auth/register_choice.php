<section class="auth-card auth-card--wide">
    <h1><?= e(t('register.choice_title')) ?></h1>
    <p class="muted"><?= e(t('register.choice_subtitle')) ?></p>

    <div class="choice-grid">
        <a class="choice-card" href="<?= e(url('/register/particulier')) ?>">
            <div class="choice-icon" aria-hidden="true">👤</div>
            <h2><?= e(t('register.particulier_title')) ?></h2>
            <p><?= e(t('register.particulier_desc')) ?></p>
            <span class="choice-cta"><?= e(t('register.choose')) ?> →</span>
        </a>
        <a class="choice-card" href="<?= e(url('/register/professionnel')) ?>">
            <div class="choice-icon" aria-hidden="true">🏢</div>
            <h2><?= e(t('register.pro_title')) ?></h2>
            <p><?= e(t('register.pro_desc')) ?></p>
            <span class="choice-cta"><?= e(t('register.choose')) ?> →</span>
        </a>
    </div>

    <p class="auth-alt"><?= e(t('auth.have_account')) ?>
        <a href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
    </p>
</section>
