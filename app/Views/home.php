<?php
$verticals = [
    ['key' => 'shop',       'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];
?>
<section class="hero">
    <h1><?= e(t('home.hero_title')) ?></h1>
    <p class="lead"><?= e(t('home.hero_subtitle')) ?></p>
    <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>"><?= e(t('home.cta_sell')) ?></a>
        <a class="btn btn-ghost btn-lg" href="#verticals"><?= e(t('home.cta_explore')) ?></a>
    </div>
</section>

<section id="verticals" class="verticals">
    <h2><?= e(t('home.verticals_title')) ?></h2>
    <div class="vertical-grid">
        <?php foreach ($verticals as $v): ?>
            <article class="vertical-card">
                <div class="vertical-icon" aria-hidden="true"><?= $v['icon'] ?></div>
                <h3><?= e(t('home.vertical.' . $v['key'] . '.title')) ?></h3>
                <p><?= e(t('home.vertical.' . $v['key'] . '.desc')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="trust">
    <p><?= e(t('home.trust')) ?></p>
</section>
