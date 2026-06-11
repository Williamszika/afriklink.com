<?php
/** @var string $content */
$user = current_user();
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="<?= e(t('home.hero_subtitle')) ?>">
    <title><?= e(config('app.name', 'Afriklink')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/logo-cauri.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-logo"><?= render_partial('partials/logo', ['uid' => 'hdr']) ?></span>
            <span class="brand-text">Afrik<span>link</span></span>
        </a>

        <nav class="main-nav" aria-label="<?= e(t('nav.home')) ?>">
            <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
            <a href="<?= e(url('/')) ?>#verticals"><?= e(t('nav.shops')) ?></a>
        </nav>

        <div class="nav-actions">
            <span class="lang-switch">
                <?php foreach (config('app.locales', ['fr', 'en']) as $loc): ?>
                    <a class="<?= $loc === current_locale() ? 'is-active' : '' ?>"
                       href="<?= e(url('/lang/' . $loc)) ?>"><?= e(strtoupper($loc)) ?></a>
                <?php endforeach; ?>
            </span>

            <?php if ($user !== null): ?>
                <?php if (is_staff($user)): ?>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/kyc')) ?>">🛡️ <?= e(t('nav.moderation')) ?></a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost"><?= e(t('nav.logout')) ?></button>
                </form>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
                <a class="btn btn-primary" href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php $flashes = get_flashes(); ?>
<?php if ($flashes !== []): ?>
    <div class="container flashes">
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="flash flash-<?= e($type) ?>"><?= e($message) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="container main-content">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e(config('app.name', 'AfrikaLink')) ?></p>
        <nav class="footer-nav" aria-label="legal">
            <a href="<?= e(url('/')) ?>"><?= e(t('footer.impressum')) ?></a>
            <a href="<?= e(url('/')) ?>"><?= e(t('footer.terms')) ?></a>
            <a href="<?= e(url('/')) ?>"><?= e(t('footer.privacy')) ?></a>
        </nav>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
