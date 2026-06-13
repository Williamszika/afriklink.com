<?php
$sponsored       = $sponsored ?? [];
$recently_viewed = $recently_viewed ?? [];
$for_you         = $for_you ?? [];
$reco_mains      = $reco_mains ?? [];
$verticals = [
    ['key' => 'shop',       'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];
?>
<section class="hero">
    <div class="hero-logo"><?= render_partial('partials/logo', ['uid' => 'hero']) ?></div>
    <p class="hero-wordmark">Afrik<span>link</span></p>
    <h1><?= e(t('home.hero_title')) ?></h1>
    <p class="lead"><?= e(t('home.hero_subtitle')) ?></p>
    <div class="hero-actions">
        <?php if (current_user() !== null): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/vendre')) ?>"><?= e(t('dash.action.sell_title')) ?></a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>"><?= e(t('home.cta_register')) ?></a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/login')) ?>"><?= e(t('home.cta_login')) ?></a>
        <?php endif; ?>
    </div>
    <p class="hero-secondary"><a href="#verticals"><?= e(t('home.cta_explore')) ?></a></p>
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

<?php if (!empty($sponsored) || !empty($recently_viewed) || !empty($for_you)): ?>
<section class="reco-rails">
    <?php if (!empty($sponsored)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '✨', 'title' => t('reco.sponsored'), 'products' => $sponsored, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
    <?php if (!empty($recently_viewed)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '🕒', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
    <?php if (!empty($for_you)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '✨', 'title' => t('reco.for_you'), 'products' => $for_you, 'mains' => $reco_mains]) ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="trust">
    <p><?= e(t('home.trust')) ?></p>
</section>
