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
// Canonique par défaut : l'URL de la page courante sur le domaine configuré
// (APP_URL). On retire la query string pour ne jamais exposer de jeton
// (?token=…) ni dupliquer le contenu sur des paramètres de tracking.
if ($metaUrl === '') {
    $reqPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $metaUrl = url($reqPath === false || $reqPath === '' ? '/' : $reqPath);
}
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
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
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
        <details class="cur-switch">
            <summary title="<?= e(t('field.currency')) ?>" aria-label="<?= e(t('field.currency')) ?>"><?= e(current_currency()) ?> ▾</summary>
            <div class="cur-menu">
                <?php foreach (config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']) as $c): ?>
                    <a class="<?= $c === current_currency() ? 'is-active' : '' ?>" href="<?= e(url('/devise/' . $c)) ?>"><?= e($c) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
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
            <button type="submit" aria-label="<?= e(t('explore.search_btn')) ?>"><?= icon('search', ['size' => 18]) ?></button>
        </form>

        <nav class="main-nav" aria-label="<?= e(t('nav.primary')) ?>">
            <a href="<?= e(url('/')) ?>" class="<?= $navPath === '/' ? 'is-active' : '' ?>"><?= e(t('nav.home')) ?></a>
            <a href="<?= e(url('/explorer')) ?>" class="<?= str_starts_with($navPath, '/explorer') ? 'is-active' : '' ?>"><?= e(t('nav.explore')) ?></a>
        </nav>

        <div class="nav-actions">
            <?php
            $wishCount = \App\Services\Wishlist::count();
            $cartCount    = \App\Services\Cart::count();
            $compareCount = \App\Services\Compare::count();
            ?>
            <div class="nav-dd" data-dd>
                <a class="btn btn-ghost nav-icon" data-dd-toggle data-dd-url="<?= e(url('/panier/apercu')) ?>" href="<?= e(url('/panier')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('cart.title')) ?>" aria-label="<?= e(t('cart.title')) ?>"><?= icon('cart') ?><span class="nav-badge" data-global-cart-count <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int) $cartCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>
            <div class="nav-dd" data-dd>
                <a class="btn btn-ghost nav-icon" data-dd-toggle data-dd-url="<?= e(url('/favoris/apercu')) ?>" href="<?= e(url('/favoris')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('wish.title')) ?>" aria-label="<?= e(t('wish.title')) ?>"><?= icon('heart') ?><span class="nav-badge" data-wish-count <?= $wishCount > 0 ? '' : 'hidden' ?>><?= (int) $wishCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>
            <div class="nav-dd" data-dd>
                <a class="btn btn-ghost nav-icon" data-dd-toggle data-dd-url="<?= e(url('/comparer/apercu')) ?>" href="<?= e(url('/comparer')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('compare.title')) ?>" aria-label="<?= e(t('compare.title')) ?>"><?= icon('compare') ?><span class="nav-badge" data-compare-count <?= $compareCount > 0 ? '' : 'hidden' ?>><?= (int) $compareCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>
            <?php if ($user !== null): ?>
                <?php if (is_staff($user)): ?>
                    <a class="btn btn-ghost" href="<?= e(url('/admin')) ?>"><?= icon('grid', ['size' => 18]) ?> <?= e(t('nav.admin')) ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/kyc')) ?>"><?= icon('shield', ['size' => 18]) ?> <?= e(t('nav.moderation')) ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/annonces')) ?>" title="<?= e(t('ann.admin_title')) ?>"><?= icon('megaphone', ['size' => 18]) ?> <?php if (is_admin($user) && ($annPending = \App\Models\Announcement::pendingCount()) > 0): ?><span class="nav-badge"><?= (int) $annPending ?></span><?php endif; ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/retraits')) ?>" title="<?= e(t('wallet.admin_title')) ?>"><?= icon('wallet', ['size' => 18]) ?> <?php if (($wdPending = \App\Models\Wallet::pendingCount()) > 0): ?><span class="nav-badge"><?= (int) $wdPending ?></span><?php endif; ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('/admin/email')) ?>" title="<?= e(t('admin.mail.title')) ?>" aria-label="<?= e(t('admin.mail.title')) ?>">✉️</a>
                <?php endif; ?>
                <?php $notifUnread = \App\Models\Notification::unreadCount((int) $user['id']); ?>
                <div class="nav-dd" data-dd>
                    <a class="btn btn-ghost nav-icon" data-dd-toggle data-dd-url="<?= e(url('/notifications/apercu')) ?>" href="<?= e(url('/notifications')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('notif.title')) ?>" aria-label="<?= e(t('notif.title')) ?>"><?= icon('bell') ?><span class="nav-badge" <?= $notifUnread > 0 ? '' : 'hidden' ?>><?= (int) $notifUnread ?></span></a>
                    <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
                </div>
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

<?= render_partial('partials/ticker') ?>

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
        <div class="footer-top">
            <div class="footer-brand">
                <a class="brand" href="<?= e(url('/')) ?>">
                    <span class="brand-logo"><?= render_partial('partials/logo', ['uid' => 'ftr']) ?></span>
                    <span class="brand-text">Afrik<span>link</span></span>
                </a>
                <p class="footer-tag"><?= e(t('home.hero_subtitle')) ?></p>
            </div>
            <nav class="footer-nav" aria-label="legal">
                <a href="<?= e(url('/mentions-legales')) ?>"><?= e(t('footer.impressum')) ?></a>
                <a href="<?= e(url('/cgv')) ?>"><?= e(t('footer.terms')) ?></a>
                <a href="<?= e(url('/confidentialite')) ?>"><?= e(t('footer.privacy')) ?></a>
                <a href="<?= e(url('/confidentialite')) ?>#cookies"><?= e(t('footer.cookies')) ?></a>
            </nav>
            <div class="footer-news">
                <p class="footer-news-title"><?= e(t('newsletter.title')) ?></p>
                <form method="post" action="<?= e(url('/newsletter')) ?>" class="footer-news-form">
                    <?= csrf_field() ?>
                    <input type="email" name="email" required maxlength="191" placeholder="<?= e(t('newsletter.ph')) ?>" aria-label="<?= e(t('newsletter.title')) ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('newsletter.btn')) ?></button>
                </form>
            </div>
        </div>
        <p class="footer-bottom">&copy; <?= date('Y') ?> <?= e(config('app.name', 'Afriklink')) ?></p>
    </div>
</footer>

<?= render_partial('partials/cookie_consent') ?>
<?php if ($user !== null && empty($user['email']) && (string) ($_COOKIE['nl_seen'] ?? '') !== '1'): ?>
    <?= render_partial('partials/newsletter_popup') ?>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
