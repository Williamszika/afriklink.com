<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>429 · <?= e(config('app.name', 'AfrikaLink')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="error-page">
    <main class="error-box">
        <p class="error-code">429</p>
        <h1><?= e(t('error.429_title')) ?></h1>
        <p class="muted"><?= e(t('error.429_body')) ?></p>
        <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('error.back_home')) ?></a>
    </main>
</body>
</html>
