<?php
/** @var string $content  @var ?string $page_title  @var ?array $meta */
$user = current_user();
// Titre + balises de partage (Open Graph) par page : les pages publiques
// (boutique, produit) passent page_title/meta pour un bel aperçu quand le
// lien est partagé sur WhatsApp ou Facebook.
$appName  = (string) config('app.name', 'Afriklink');
$pageTitle = isset($page_title) && $page_title !== '' ? $page_title . ' — ' . $appName : $appName;
$metaDesc  = (string) (($meta['description'] ?? '') !== '' ? $meta['description'] : t('home.hero_subtitle'));
$metaUrl   = (string) ($meta['url'] ?? '');
$metaImage = (string) ($meta['image'] ?? '');
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <title><?= e($pageTitle) ?></title>
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <meta property="og:title" content="<?= e($page_title ?? $appName) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:type" content="<?= e((string) ($meta['type'] ?? 'website')) ?>">
    <meta property="og:locale" content="<?= e(current_locale() === 'fr' ? 'fr_FR' : 'en_GB') ?>">
    <?php if ($metaUrl !== ''): ?>
        <meta property="og:url" content="<?= e($metaUrl) ?>">
        <link rel="canonical" href="<?= e($metaUrl) ?>">
    <?php endif; ?>
    <?php if ($metaImage !== ''): ?>
        <meta property="og:image" content="<?= e($metaImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?= e($metaImage) ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/logo-cauri.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (!empty($meta['jsonld'])): ?>
        <script type="application/ld+json"><?= $meta['jsonld'] /* JSON déjà encodé ; bloc de données, exempt de CSP script-src */ ?></script>
    <?php endif; ?>
</head>
<?php
// Auto-activation de la position précise : une fois juste après connexion
// (le drapeau de session est consommé ici), sinon le JS reste silencieux si
// la permission est déjà accordée. La localisation détectée (IP par défaut)
// est disponible tout de suite, sans permission.
$geoAutoprompt = !empty($_SESSION['geo_autoprompt']);
unset($_SESSION['geo_autoprompt']);
$geo = detected_geo();
$geoChip = trim(implode(', ', array_filter([$geo['city'] ?? null, $geo['country'] ?? null]))) ?: ($geo['country'] ?? '');
$geoFlag = !empty($geo['country_code']) ? flag_emoji((string) $geo['country_code']) : '📍';
$navPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
?>
<body<?= $geoAutoprompt ? ' data-geo-autoprompt="1"' : '' ?> data-geo-session-url="<?= e(url('/api/geo/session')) ?>">

<!-- Barre d'infos : position détectée (drapeau + ville) à gauche, langues à droite -->
<div class="topbar">
    <div class="container topbar-inner">
        <button type="button" class="geo-chip" data-geo-chip
                title="<?= e(t('geo.chip_title')) ?>" <?= $geoChip === '' ? 'hidden' : '' ?>>
            <span class="geo-chip-flag" data-geo-chip-flag aria-hidden="true"><?= $geoFlag ?></span>
            <span data-geo-chip-text><?= e($geoChip) ?></span>
        </button>
        <span class="topbar-spacer"></span>
        <span class="lang-switch">
            <?php foreach (config('app.locales', ['fr', 'en']) as $loc): ?>
                <a class="<?= $loc === current_locale() ? 'is-active' : '' ?>"
                   href="<?= e(url('/lang/' . $loc)) ?>"><?= e(strtoupper($loc)) ?></a>
            <?php endforeach; ?>
        </span>
    </div>
</div>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-logo"><?= render_partial('partials/logo', ['uid' => 'hdr']) ?></span>
            <span class="brand-text">Afrik<span>link</span></span>
        </a>

        <form class="header-search" method="get" action="<?= e(url('/explorer')) ?>" role="search">
            <input type="search" name="q" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
            <button type="submit" aria-label="<?= e(t('explore.search_btn')) ?>">🔎</button>
        </form>

        <nav class="main-nav" aria-label="<?= e(t('nav.primary')) ?>">
            <a href="<?= e(url('/')) ?>" class="<?= $navPath === '/' ? 'is-active' : '' ?>"><?= e(t('nav.home')) ?></a>
            <a href="<?= e(url('/explorer')) ?>" class="<?= str_starts_with($navPath, '/explorer') ? 'is-active' : '' ?>"><?= e(t('nav.explore')) ?></a>
        </nav>

        <div class="nav-actions">
            <?php
            $wishCount = \App\Services\Wishlist::count();
            $cartCount = 0;
            foreach (($_SESSION['caisse'] ?? []) as $cItems) {
                foreach ((array) $cItems as $cIt) { $cartCount += (int) ($cIt['qty'] ?? 0); }
            }
            $cartShop = (string) ($_SESSION['cart_shop'] ?? '');
            ?>
            <a class="btn btn-ghost nav-icon" href="<?= e(url('/favoris')) ?>" title="<?= e(t('wish.title')) ?>" aria-label="<?= e(t('wish.title')) ?>">❤️<span class="nav-badge" data-wish-count <?= $wishCount > 0 ? '' : 'hidden' ?>><?= (int) $wishCount ?></span></a>
            <?php if ($cartCount > 0 && $cartShop !== ''): ?>
                <a class="btn btn-ghost nav-icon" href="<?= e(url('/boutique/' . $cartShop . '/caisse')) ?>" title="<?= e(t('bcart.view_cart')) ?>" aria-label="<?= e(t('bcart.view_cart')) ?>">🛒<span class="nav-badge"><?= (int) $cartCount ?></span></a>
            <?php endif; ?>
            <?php if ($user !== null): ?>
                <?php if (is_staff($user)): ?>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/kyc')) ?>">🛡️ <?= e(t('nav.moderation')) ?></a>
                <?php endif; ?>
                <?php $msgUnread = \App\Models\Conversation::unreadCountFor((int) $user['id']); ?>
                <a class="btn btn-ghost nav-msg" href="<?= e(url('/messages')) ?>" title="<?= e(t('msg.title')) ?>" aria-label="<?= e(t('msg.title')) ?>">💬<?php if ($msgUnread > 0): ?> <span class="nav-msg-badge"><?= (int) $msgUnread ?></span><?php endif; ?></a>
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
            <a href="<?= e(url('/mentions-legales')) ?>"><?= e(t('footer.impressum')) ?></a>
            <a href="<?= e(url('/cgv')) ?>"><?= e(t('footer.terms')) ?></a>
            <a href="<?= e(url('/confidentialite')) ?>"><?= e(t('footer.privacy')) ?></a>
            <a href="<?= e(url('/confidentialite')) ?>#cookies"><?= e(t('footer.cookies')) ?></a>
        </nav>
    </div>
</footer>

<?= render_partial('partials/cookie_consent') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
