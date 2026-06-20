<?php
/** Présentation du site — reprend le hero (déplacé depuis l'accueil) + explication des univers. */
use App\Services\CloudinaryService;
$loggedIn = current_user() !== null;
$sellHref = $loggedIn ? url('/boutique/creer') : url('/register/vendeur');
$uniHub = [
    ['key' => 'shop',       'icon' => '🛍️', 'href' => url('/explorer'),        'sub' => t('home.hub.browse')],
    ['key' => 'restaurant', 'icon' => '🍽️', 'href' => url('/register/vendeur'), 'sub' => t('home.hub.first')],
    ['key' => 'salon',      'icon' => '💈', 'href' => url('/register/vendeur'), 'sub' => t('home.hub.first')],
    ['key' => 'service',    'icon' => '🛠️', 'href' => url('/register/vendeur'), 'sub' => t('home.hub.first')],
];
$verticals = [
    ['key' => 'shop',       'icon' => '🛍️', 'alt' => ''],
    ['key' => 'restaurant', 'icon' => '🍽️', 'alt' => 'afk-uni__ic--alt1'],
    ['key' => 'salon',      'icon' => '💈', 'alt' => 'afk-uni__ic--alt2'],
    ['key' => 'service',    'icon' => '🛠️', 'alt' => 'afk-uni__ic--alt3'],
];
$pillars = [
    ['ic' => '🔒', 'href' => '/paiements-securises', 't' => t('home.why.secure_t'),  'd' => t('home.why.secure_d')],
    ['ic' => '🛡️', 'href' => '/vendeurs-verifies',   't' => t('home.why.verified_t'), 'd' => t('home.why.verified_d')],
    ['ic' => '🌍', 'href' => '/local-international',  't' => t('home.why.ship_t'),     'd' => t('home.why.ship_d')],
    ['ic' => '💬', 'href' => '/assistance',          't' => t('home.why.support_t'),  'd' => t('home.why.support_d')],
];
?>
<p class="muted" style="margin:8px 0"><a href="<?= e(url('/')) ?>">← <?= e(current_locale() === 'en' ? 'Home' : 'Accueil') ?></a></p>

<!-- Hero présentation (déplacé depuis l'accueil) -->
<section class="afk-hero">
    <div class="afk-hero__text">
        <span class="afk-eyebrow">◆ <?= e(t('home.hero_kicker')) ?></span>
        <h1 class="afk-h1"><?= e(t('home.hero_title')) ?></h1>
        <p class="afk-lede"><?= e(t('home.hero_subtitle')) ?></p>
        <form class="afk-hero-search" method="get" action="<?= e(url('/explorer')) ?>" role="search">
            <div class="afk-search">
                <span class="afk-ic" aria-hidden="true"><?= icon('search', ['size' => 18]) ?></span>
                <input type="search" name="q" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
            </div>
            <button class="afk-btn afk-btn--gold" type="submit"><?= e(t('explore.search_btn')) ?></button>
        </form>
        <div class="afk-trust">
            <span class="afk-trust__item"><?= icon('lock', ['size' => 15]) ?> <?= e(t('home.why.secure_t')) ?></span>
            <span class="afk-trust__item"><?= icon('shield', ['size' => 15]) ?> <?= e(t('home.why.verified_t')) ?></span>
            <span class="afk-trust__item"><?= icon('globe', ['size' => 15]) ?> <?= e(t('home.why.ship_t')) ?></span>
        </div>
    </div>
    <aside class="afk-waxpanel afk-hub">
        <div class="afk-waxpanel__emblem"><span class="brand-logo"><?= render_partial('partials/logo', ['uid' => 'about']) ?></span></div>
        <p class="afk-hub__title"><?= e(t('home.hub.title')) ?></p>
        <div class="afk-hub__verts">
            <?php foreach ($uniHub as $u): ?>
                <a class="afk-hub__vert" href="<?= e($u['href']) ?>">
                    <span class="afk-hub__ic" aria-hidden="true"><?= $u['icon'] ?></span>
                    <span class="afk-hub__name"><?= e(t('home.vertical.' . $u['key'] . '.title')) ?></span>
                    <span class="afk-hub__sub"><?= e($u['sub']) ?> →</span>
                </a>
            <?php endforeach; ?>
        </div>
        <a class="afk-btn afk-btn--gold afk-hub__cta" href="<?= e($sellHref) ?>"><?= e(t('home.hub.cta')) ?></a>
    </aside>
</section>

<!-- Présentation -->
<section class="afk-block">
    <div class="afk-head"><div><span class="afk-kicker"><?= e(t('about.kicker')) ?></span><h2 class="afk-h2"><?= e(t('about.mission_title')) ?></h2></div></div>
    <p class="afk-lede" style="max-width:760px"><?= e(t('about.mission')) ?></p>
</section>

<!-- Les 4 univers -->
<section class="afk-block">
    <div class="afk-head"><div><h2 class="afk-h2"><?= e(t('home.verticals_title')) ?></h2></div></div>
    <div class="afk-grid afk-grid-4">
        <?php foreach ($verticals as $v): ?>
            <article class="afk-uni">
                <div class="afk-uni__ic <?= e($v['alt']) ?>" aria-hidden="true"><?= $v['icon'] ?></div>
                <h3 class="afk-h3"><?= e(t('home.vertical.' . $v['key'] . '.title')) ?></h3>
                <p><?= e(t('home.vertical.' . $v['key'] . '.desc')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Nos garanties -->
<section class="afk-block">
    <div class="afk-head"><div><h2 class="afk-h2"><?= e(t('about.trust_title')) ?></h2></div></div>
    <div class="afk-grid afk-grid-4">
        <?php foreach ($pillars as $p): ?>
            <a class="afk-uni" href="<?= e(url($p['href'])) ?>" style="text-decoration:none;color:inherit">
                <div class="afk-uni__ic" aria-hidden="true"><?= $p['ic'] ?></div>
                <h3 class="afk-h3"><?= e($p['t']) ?></h3>
                <p><?= e($p['d']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- CTA -->
<section class="afk-seller afk-block">
    <div>
        <h2 class="afk-h2"><?= e(t('home.seller_cta_title')) ?></h2>
        <p><?= e(t('home.seller_cta_text')) ?></p>
    </div>
    <a class="afk-btn afk-btn--dark afk-btn--lg" href="<?= e($sellHref) ?>"><?= e(t('home.seller_cta_btn')) ?></a>
</section>
