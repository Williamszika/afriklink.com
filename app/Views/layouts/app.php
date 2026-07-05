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
<html lang="<?= e(current_locale()) ?>" dir="<?= e(locale_dir()) ?>">
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
    <?php
    // Données structurées SITE (Organization + WebSite) présentes sur toutes les
    // pages : identité stable pour Google (résultats enrichis, Sitelinks
    // Searchbox) et pour les moteurs de réponse (ChatGPT, Perplexity, Gemini)
    // qui citent des sources fiables. Bloc de DONNÉES (type application/ld+json,
    // non exécuté) : exempt de la CSP script-src.
    $siteLd = json_encode([
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'Organization',
                '@id'   => url('/') . '#org',
                'name'  => $appName,
                'url'   => url('/'),
                'logo'  => asset('img/logo-cauri.svg'),
            ],
            [
                '@type'       => 'WebSite',
                '@id'         => url('/') . '#site',
                'name'        => $appName,
                'url'         => url('/'),
                'inLanguage'  => current_locale(),
                'publisher'   => ['@id' => url('/') . '#org'],
                'potentialAction' => [
                    '@type'  => 'SearchAction',
                    'target' => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => url('/explorer') . '?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>
    <?php if ($siteLd !== false): ?>
        <script type="application/ld+json"><?= $siteLd ?></script>
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

<?php
$langNames = ['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch', 'es' => 'Español', 'it' => 'Italiano', 'pt' => 'Português', 'nl' => 'Nederlands', 'ar' => 'العربية'];
$wishCount    = \App\Services\Wishlist::count();
$cartCount    = \App\Services\Cart::count();
$compareCount = \App\Services\Compare::count();
// Rayons de navigation (liens réels : l'explorateur filtré par catégorie).
$pubhdCats = ['mode', 'electronique', 'maison', 'beaute', 'alimentation', 'artisanat'];
?>
<header class="pubhd">
    <div class="container pubhd-bar">
        <a class="pubhd-brand" href="<?= e(url('/')) ?>">
            <span class="pubhd-logo"><?= render_partial('partials/logo', ['uid' => 'hdr']) ?></span>
            <span class="pubhd-wordmark">Afrik<span>link</span></span>
        </a>

        <form class="pubhd-search" method="get" action="<?= e(url('/explorer')) ?>" role="search">
            <span class="pubhd-search-ic" aria-hidden="true"><?= icon('search', ['size' => 18]) ?></span>
            <input type="search" name="q" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
            <button type="submit" class="pubhd-search-btn" aria-label="<?= e(t('explore.search_btn')) ?>"><?= icon('search', ['size' => 18]) ?></button>
        </form>

        <div class="pubhd-actions">
            <button type="button" class="pubhd-geo" data-geo-chip title="<?= e(t('geo.chip_title')) ?>" <?= $geoChip === '' ? 'hidden' : '' ?>>
                <span class="pubhd-geo-flag" data-geo-chip-flag aria-hidden="true"><?= $geoFlag ?></span>
                <span class="pubhd-geo-txt" data-geo-chip-text><?= e($geoChip) ?></span>
            </button>

            <details class="pubhd-dd lang-dd">
                <summary title="Langue / Language"><?= e(strtoupper(current_locale())) ?> ▾</summary>
                <div class="pubhd-menu">
                    <?php foreach (config('app.locales', ['fr', 'en']) as $loc): ?>
                        <a class="<?= $loc === current_locale() ? 'is-active' : '' ?>" href="<?= e(url('/lang/' . $loc)) ?>"><?= e(strtoupper($loc)) ?> · <?= e($langNames[$loc] ?? $loc) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <details class="pubhd-dd">
                <summary title="<?= e(t('field.currency')) ?>" aria-label="<?= e(t('field.currency')) ?>"><?= e(current_currency()) ?> ▾</summary>
                <div class="pubhd-menu">
                    <?php foreach (config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']) as $c): ?>
                        <a class="<?= $c === current_currency() ? 'is-active' : '' ?>" href="<?= e(url('/devise/' . $c)) ?>"><?= e($c) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>

            <div class="nav-dd pubhd-ddw" data-dd>
                <a class="pubhd-ic" data-dd-toggle data-dd-url="<?= e(url('/favoris/apercu')) ?>" href="<?= e(url('/favoris')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('wish.title')) ?>" aria-label="<?= e(t('wish.title')) ?>"><?= icon('heart') ?><span class="pubhd-badge" data-wish-count <?= $wishCount > 0 ? '' : 'hidden' ?>><?= (int) $wishCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>
            <div class="nav-dd pubhd-ddw pubhd-hide-sm" data-dd>
                <a class="pubhd-ic" data-dd-toggle data-dd-url="<?= e(url('/comparer/apercu')) ?>" href="<?= e(url('/comparer')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('compare.title')) ?>" aria-label="<?= e(t('compare.title')) ?>"><?= icon('compare') ?><span class="pubhd-badge" data-compare-count <?= $compareCount > 0 ? '' : 'hidden' ?>><?= (int) $compareCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>
            <div class="nav-dd pubhd-ddw" data-dd>
                <a class="pubhd-ic" data-dd-toggle data-dd-url="<?= e(url('/panier/apercu')) ?>" href="<?= e(url('/panier')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('cart.title')) ?>" aria-label="<?= e(t('cart.title')) ?>"><?= icon('cart') ?><span class="pubhd-badge pubhd-badge--hot" data-global-cart-count <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int) $cartCount ?></span></a>
                <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
            </div>

            <?php if ($user !== null): ?>
                <?php if (is_staff($user)): ?>
                    <a class="pubhd-ic pubhd-hide-sm" href="<?= e(url('/admin')) ?>" title="<?= e(t('nav.admin')) ?>" aria-label="<?= e(t('nav.admin')) ?>"><?= icon('grid', ['size' => 18]) ?></a>
                    <a class="pubhd-ic pubhd-hide-sm" href="<?= e(url('/admin/kyc')) ?>" title="<?= e(t('nav.moderation')) ?>" aria-label="<?= e(t('nav.moderation')) ?>"><?= icon('shield', ['size' => 18]) ?></a>
                    <a class="pubhd-ic pubhd-hide-sm" href="<?= e(url('/admin/annonces')) ?>" title="<?= e(t('ann.admin_title')) ?>" aria-label="<?= e(t('ann.admin_title')) ?>"><?= icon('megaphone', ['size' => 18]) ?><?php if (is_admin($user) && ($annPending = \App\Models\Announcement::pendingCount()) > 0): ?><span class="pubhd-badge"><?= (int) $annPending ?></span><?php endif; ?></a>
                    <a class="pubhd-ic pubhd-hide-sm" href="<?= e(url('/admin/retraits')) ?>" title="<?= e(t('wallet.admin_title')) ?>" aria-label="<?= e(t('wallet.admin_title')) ?>"><?= icon('wallet', ['size' => 18]) ?><?php if (($wdPending = \App\Models\Wallet::pendingCount()) > 0): ?><span class="pubhd-badge"><?= (int) $wdPending ?></span><?php endif; ?></a>
                    <a class="pubhd-ic pubhd-hide-sm" href="<?= e(url('/admin/email')) ?>" title="<?= e(t('admin.mail.title')) ?>" aria-label="<?= e(t('admin.mail.title')) ?>">✉️</a>
                <?php endif; ?>
                <?php $notifUnread = \App\Models\Notification::unreadCount((int) $user['id']); ?>
                <div class="nav-dd pubhd-ddw" data-dd>
                    <a class="pubhd-ic" data-dd-toggle data-dd-url="<?= e(url('/notifications/apercu')) ?>" href="<?= e(url('/notifications')) ?>" aria-haspopup="true" aria-expanded="false" title="<?= e(t('notif.title')) ?>" aria-label="<?= e(t('notif.title')) ?>"><?= icon('bell') ?><span class="pubhd-badge pubhd-badge--hot" <?= $notifUnread > 0 ? '' : 'hidden' ?>><?= (int) $notifUnread ?></span></a>
                    <div class="nav-dd-panel" data-dd-panel hidden><div class="nav-dd-body" data-dd-body><p class="nav-dd-loading"><?= e(t('common.loading')) ?></p></div></div>
                </div>
                <a class="btn btn-green btn-sm pubhd-cta" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form pubhd-hide-sm">
                    <?= csrf_field() ?>
                    <button type="submit" class="pubhd-login"><?= e(t('nav.logout')) ?></button>
                </form>
            <?php else: ?>
                <a class="pubhd-login pubhd-hide-sm" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
                <a class="btn btn-green btn-sm pubhd-cta" href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="container pubhd-cats" aria-label="<?= e(t('home.categories_title')) ?>">
        <a class="pubhd-cat is-all <?= str_starts_with($navPath, '/explorer') ? 'is-active' : '' ?>" href="<?= e(url('/explorer')) ?>"><?= e(t('nav.browse_all')) ?></a>
        <?php foreach ($pubhdCats as $ck): ?>
            <a class="pubhd-cat" href="<?= e(url('/explorer?categorie=' . $ck)) ?>"><?= e(t('listing.cat.' . $ck)) ?></a>
        <?php endforeach; ?>
        <a class="pubhd-cat pubhd-cat--end <?= $navPath === '/a-propos' ? 'is-active' : '' ?>" href="<?= e(url('/a-propos')) ?>"><?= e(t('nav.about')) ?></a>
    </nav>
</header>

<?= render_partial('partials/geo_suggest') ?>

<?php if (empty($hide_ticker)): ?><?= render_partial('partials/ticker') ?><?php endif; ?>

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

<footer class="pubft">
    <div class="container pubft-in">
        <div class="pubft-top">
            <div class="pubft-brand">
                <a class="pubhd-brand" href="<?= e(url('/')) ?>">
                    <span class="pubhd-logo"><?= render_partial('partials/logo', ['uid' => 'ftr']) ?></span>
                    <span class="pubhd-wordmark">Afrik<span>link</span></span>
                </a>
                <p class="pubft-desc"><?= e(t('home.hero_subtitle')) ?></p>
                <?= render_partial('partials/payment_strip', ['label' => false, 'secure' => false]) ?>
            </div>
            <div class="pubft-col">
                <h4><?= e(t('footer.col_discover')) ?></h4>
                <ul>
                    <li><a href="<?= e(url('/explorer')) ?>"><?= e(t('nav.explore')) ?></a></li>
                    <li><a href="<?= e(url('/a-propos')) ?>"><?= e(t('nav.about')) ?></a></li>
                    <li><a href="<?= e(url('/vendeurs-verifies')) ?>"><?= e(t('home.why.verified_t')) ?></a></li>
                    <li><a href="<?= e(url('/paiements-securises')) ?>"><?= e(t('home.why.secure_t')) ?></a></li>
                </ul>
            </div>
            <div class="pubft-col">
                <h4><?= e(t('footer.col_sell')) ?></h4>
                <ul>
                    <li><a href="<?= e(url('/register/vendeur')) ?>"><?= e(t('home.cta_sell')) ?></a></li>
                    <li><a href="<?= e(url('/local-international')) ?>"><?= e(t('home.why.ship_t')) ?></a></li>
                    <li><a href="<?= e(url('/assistance')) ?>"><?= e(t('footer.support')) ?></a></li>
                </ul>
            </div>
            <div class="pubft-col">
                <h4><?= e(t('footer.col_legal')) ?></h4>
                <ul>
                    <li><a href="<?= e(url('/mentions-legales')) ?>"><?= e(t('footer.impressum')) ?></a></li>
                    <li><a href="<?= e(url('/cgv')) ?>"><?= e(t('footer.terms')) ?></a></li>
                    <li><a href="<?= e(url('/retractation')) ?>"><?= e(t('footer.withdrawal')) ?></a></li>
                    <li><a href="<?= e(url('/confidentialite')) ?>"><?= e(t('footer.privacy')) ?></a></li>
                    <li><a href="<?= e(url('/confidentialite')) ?>#cookies"><?= e(t('footer.cookies')) ?></a></li>
                </ul>
            </div>
            <div class="pubft-news">
                <h4><?= e(t('newsletter.title')) ?></h4>
                <form method="post" action="<?= e(url('/newsletter')) ?>" class="pubft-news-form">
                    <?= csrf_field() ?>
                    <input type="email" name="email" required maxlength="191" placeholder="<?= e(t('newsletter.ph')) ?>" aria-label="<?= e(t('newsletter.title')) ?>">
                    <button type="submit" class="btn btn-gold btn-sm"><?= e(t('newsletter.btn')) ?></button>
                </form>
            </div>
        </div>
        <div class="pubft-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(config('app.name', 'Afriklink')) ?></span>
            <span><?= e(t('home.hero_kicker')) ?></span>
        </div>
    </div>
</footer>

<?= render_partial('partials/cookie_consent') ?>
<?php if ($user !== null && empty($user['email']) && (string) ($_COOKIE['nl_seen'] ?? '') !== '1'): ?>
    <?= render_partial('partials/newsletter_popup') ?>
<?php endif; ?>
<?php if (empty($hide_assistant)): ?><?= render_partial('partials/agnes') ?><?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
