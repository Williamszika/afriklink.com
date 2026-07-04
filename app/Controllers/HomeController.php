<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

final class HomeController
{
    public function index(Request $request): void
    {
        // Recommandations personnalisées (algorithmiques, par cookie de navigation).
        $recent = \App\Services\Recommender::recentlyViewed(6);
        $forYou = \App\Services\Recommender::forYou(
            8,
            array_map(static fn (array $p): string => (string) $p['public_id'], $recent)
        );
        // Mise en avant payante (« À la une ») : campagnes actives, en rotation.
        // Repli sur l'ancien drapeau promoted_until si aucune campagne en cours.
        $sponsored = \App\Models\AdCampaign::activeProducts('home');
        if ($sponsored === []) {
            $sponsored = \App\Models\Product::promotedMarketplace(8);
        } else {
            \App\Models\AdCampaign::recordImpressions(array_map(static fn (array $p): int => (int) $p['campaign_id'], $sponsored));
        }
        $promoAnnonces = \App\Models\Listing::promotedMarketplace(8);
        // Carrousel de pub en tête : produits sponsorisés + produits en promo (deals).
        $promoProducts = \App\Models\Product::onPromo(6);
        // Produits du catalogue à afficher dès l'ouverture de l'accueil
        // (« local d'abord » : boutiques du pays détecté en tête).
        $products  = \App\Models\Product::recentMarketplace(12, (string) (detected_geo()['country_code'] ?? ''));
        // Pré-charge les traductions de contenu (évite le N+1 si actives).
        \App\Models\ContentTranslation::preload('product', array_map(static fn (array $p): int => (int) $p['id'], $products), current_locale());
        // Vitrine vivante : boutiques, restaurants et annonces actuellement en ligne.
        $annonces  = \App\Models\Listing::recentActive(12);
        $boutiques = \App\Models\Boutique::recentPublished(12);
        view('home', [
            // SEO / AEO : titre descriptif (50-60 car. avec « — Afriklink ») et
            // méta-description riche (~155 car.) pour un meilleur aperçu Google
            // et une meilleure citabilité par les moteurs de réponse.
            'page_title'      => t('home.hero_title'),
            'meta'            => ['description' => t('home.seo_desc')],
            'categories'      => \App\Services\Categories::live(),
            'sponsored'       => $sponsored,
            'promo_products'  => $promoProducts,
            'promo_product_mains' => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $promoProducts)),
            'products'        => $products,
            'product_mains'   => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'recently_viewed' => $recent,
            'for_you'         => $forYou,
            'reco_mains'      => \App\Services\Recommender::mainsFor(array_merge($sponsored, $recent, $forYou)),
            'boutiques'       => $boutiques,
            'verified_sellers' => \App\Models\ProProfile::verifiedAmong(array_map(static fn (array $b): int => (int) $b['user_id'], $boutiques)),
            'restaurants'     => \App\Models\Restaurant::recentPublished(12),
            'annonces'        => $annonces,
            'annonce_mains'   => \App\Models\Listing::mainPhotos(array_map(static fn (array $a): int => (int) $a['id'], $annonces)),
            'promo_annonces'  => $promoAnnonces,
            'promo_annonce_mains' => \App\Models\Listing::mainPhotos(array_map(static fn (array $a): int => (int) $a['id'], $promoAnnonces)),
        ]);
    }

    /**
     * Espace publicitaire public — « Mise en avant » : regroupe les offres
     * sponsorisées (simulation) que voient les visiteurs : boutiques à la une,
     * produits et annonces promus.
     */
    public function spotlight(Request $request): void
    {
        $products  = \App\Models\Product::promotedMarketplace(24);
        $annonces  = \App\Models\Listing::promotedMarketplace(24);
        $boutiques = \App\Models\Boutique::spotlight(12);
        view('spotlight', [
            'page_title'  => t('spotlight.title'),
            'meta'        => ['description' => t('spotlight.lead')],
            'products'    => $products,
            'product_mains' => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'annonces'    => $annonces,
            'annonce_mains' => \App\Models\Listing::mainPhotos(array_map(static fn (array $a): int => (int) $a['id'], $annonces)),
            'boutiques'   => $boutiques,
            'verified_sellers' => \App\Models\ProProfile::verifiedAmong(array_map(static fn (array $b): int => (int) $b['user_id'], $boutiques)),
        ]);
    }

    /** Clic sur une offre sponsorisée : comptabilise le clic puis redirige vers l'objet. */
    public function sponsoredClick(Request $request): void
    {
        $target = \App\Models\AdCampaign::clickThrough((string) $request->param('pid', ''));
        redirect($target ?? '/');
    }

    /** Explorer public — recherche marketplace (mot-clé, catégorie, prix, tri). */
    public function explore(Request $request): void
    {
        $cats      = config('listings.categories', []);
        $countries = \App\Models\Product::searchCountries();
        // Page bornée : un OFFSET arbitrairement grand (page=1e6) force MySQL à
        // parcourir des millions de lignes — DoS à bon marché. 200 pages = large.
        $page  = max(1, min(200, (int) input_string('page', '1')));
        $limit = 24;
        $f = [
            'q'        => trim((string) input_string('q', '')),
            'category' => whitelist((string) input_string('categorie', ''), $cats, ''),
            'country'  => whitelist(strtoupper((string) input_string('pays', '')), $countries, ''),
            'city'     => trim((string) input_string('ville', '')),
            'in_stock' => input_string('stock', '') === '1',
            'min'      => preg_replace('/\D+/', '', (string) input_string('min', '')),
            'max'      => preg_replace('/\D+/', '', (string) input_string('max', '')),
            'sort'     => whitelist((string) input_string('tri', 'recent'), ['recent', 'price_asc', 'price_desc'], 'recent'),
            'audience' => apparel_audience_clean(input_string('genre', '')),
            'garment'  => apparel_category_clean(input_string('vetement', '')),
            // « Local d'abord » : à pertinence égale, le pays/ville détecté remonte.
            'near_cc'   => (string) (detected_geo()['country_code'] ?? ''),
            'near_city' => (string) (detected_geo()['city'] ?? ''),
            'limit'    => $limit,
            'offset'   => ($page - 1) * $limit,
        ];
        $products = \App\Models\Product::search($f);
        $ids = array_map(static fn (array $p): int => (int) $p['id'], $products);
        \App\Models\ContentTranslation::preload('product', $ids, current_locale());
        view('explore', [
            'page_title' => t('explore.title'),
            // Filtre : toutes les catégories restent sélectionnables ($cats sert à la
            // validation), mais on les ORDONNE par volume de contenu publié.
            'categories' => \App\Services\Categories::ordered(),
            'countries'  => $countries,
            'f'          => $f,
            'page'       => $page,
            'has_next'   => count($products) === $limit,
            'products'   => $products,
            'mains'      => \App\Models\Product::mainPhotos($ids),
            'ratings'    => \App\Models\Review::summaryForProducts($ids),
        ]);
    }

    /**
     * One-click diagnostics (no secrets exposed): is the app up, is the database
     * reachable, which session driver is active. Lets a dashboard-only operator
     * verify the TiDB wiring right after setting the Vercel env vars.
     */
    public function health(Request $request): void
    {
        $configured = !empty($_ENV['DB_HOST']) && !empty($_ENV['DB_NAME']) && !empty($_ENV['DB_USER']);
        $db = 'unconfigured';
        $detail = null;
        $hint = null;
        if ($configured) {
            try {
                db()->query('SELECT 1');
                $db = 'ok';
            } catch (\Throwable $e) {
                $db = 'error';
                $detail = $e->getMessage();
                $hint = self::classifyDbError($detail);
                log_message('error', 'health db check failed: ' . $detail);
            }
        }

        // L'info DÉTAILLÉE (pile technique, état mail/média/paiement…) est un
        // vecteur de reconnaissance : réservée au STAFF ou à un appelant porteur
        // du secret CRON (Authorization: Bearer). Les sondes de disponibilité
        // anonymes ne voient que {app, db} + leur propre diagnostic de rôle.
        $authHdr    = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $bearer     = str_starts_with($authHdr, 'Bearer ') ? substr($authHdr, 7) : '';
        $cronSecret = trim((string) ($_ENV['CRON_SECRET'] ?? ''));
        $detailed   = is_staff() || ($cronSecret !== '' && $bearer !== '' && hash_equals($cronSecret, $bearer));

        $payload = ['app' => 'ok', 'db' => $db];
        // Diagnostic « suis-je admin ? » : chacun voit SON propre rôle/e-mail
        // (masqué) — jamais celui d'autrui.
        $cu = current_user();
        $payload['you_are_staff'] = $cu === null ? 'non_connecté' : is_staff();
        if ($cu !== null && trim((string) ($cu['email'] ?? '')) !== '') {
            $payload['your_login_email'] = self::maskEmail((string) $cu['email']);
        }
        if (!$detailed) {
            json_response($payload); // appelant anonyme / non-staff : strict minimum
        }
        $payload['session_driver'] = config('app.session_driver');
        if ($hint !== null) {
            $payload['db_hint'] = $hint; // safe category, no secrets
        }
        if ($detail !== null && config('app.debug', false)) {
            $payload['db_error'] = $detail; // raw message only with APP_DEBUG=true
        }

        // Mail configuration state (no secrets; the from address is masked and only a
        // harmless key fingerprint — public prefix + length — is shown).
        $from = trim((string) ($_ENV['MAIL_FROM'] ?? ''));
        $key  = trim((string) ($_ENV['MAIL_API_KEY'] ?? ''));
        $payload['mail'] = [
            'driver'  => $_ENV['MAIL_DRIVER'] ?? 'log',
            'api_key' => $key === ''
                ? 'missing'
                : sprintf('%s… (%d caractères)', substr($key, 0, 8), strlen($key)),
            'from'    => $from === '' ? 'missing' : self::maskEmail($from),
        ];
        // Catch the most common mistake immediately: the SMTP key (xsmtpsib-) pasted
        // where the REST API key (xkeysib-) is required. Brevo answers 401 otherwise.
        if ($key !== '') {
            if (str_starts_with($key, 'xkeysib-')) {
                $payload['mail']['key_check'] = 'ok_cle_api';
            } elseif (str_starts_with($key, 'xsmtpsib-')) {
                $payload['mail']['key_check'] =
                    'mauvaise_cle — ceci est la clé SMTP. Il faut la clé API (onglet « Clés API », commence par xkeysib-).';
            } else {
                $payload['mail']['key_check'] =
                    'format_inattendu — une clé API Brevo commence par xkeysib-.';
            }
        }

        // Hébergement médias (annonces) : diagnostic Cloudinary (ok / unconfigured / misconfigured).
        $payload['media'] = \App\Services\CloudinaryService::diagnostic();
        $payload['payment'] = \App\Services\Payment\PaymentProviders::diagnostic();
        $payload['captcha'] = \App\Services\Captcha::mode();

        // Relecteurs KYC configurés (nombre seulement, jamais les adresses).
        $payload['staff_emails'] = count(config('app.admin_emails', []));

        // (Le diagnostic de rôle « you_are_staff » est déjà posé plus haut, et
        // visible de tous pour leur propre compte ; ici on est en mode détaillé.)

        // /health?mail_test=1 — real send to the configured sender's own address
        // (never an arbitrary recipient). Reached only by staff/Bearer (detailed
        // mode), throttled to 6/hour per IP and FAIL-CLOSED (cost-bearing: if the
        // limiter DB is down we refuse rather than allow quota-burn).
        if (($_GET['mail_test'] ?? '') === '1') {
            if (!rate_limit_ok('mailtest:' . $request->ip(), 6, 3600, false)) {
                $payload['mail']['test'] = 'throttled';
            } elseif ($from === '') {
                $payload['mail']['test'] = 'failed: MAIL_FROM manquant';
            } else {
                $sent = \App\Services\MailService::send(
                    $from,
                    'Test de configuration — Afriklink',
                    '<p>✅ La configuration e-mail d’Afriklink fonctionne. (Test envoyé depuis /health)</p>'
                );
                $payload['mail']['test'] = $sent
                    ? 'sent'
                    : 'failed: ' . (\App\Services\MailService::$lastError ?? 'cause inconnue');
            }
        }

        json_response($payload);
    }

    /** b…m@gmail.com — enough to recognise the address without publishing it. */
    private static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '(invalide)';
        }
        return $email[0] . '…' . substr($email, $at - 1);
    }

    /** Map a DB connection error to a safe category (no secrets leaked). */
    private static function classifyDbError(string $message): string
    {
        $m = strtolower($message);
        return match (true) {
            str_contains($m, '1045') || str_contains($m, 'access denied')        => 'bad_credentials',
            str_contains($m, '1049') || str_contains($m, 'unknown database')     => 'unknown_database',
            str_contains($m, 'ssl') || str_contains($m, 'certificate') || str_contains($m, 'tls') => 'tls_problem',
            str_contains($m, '2002') || str_contains($m, '2005') || str_contains($m, 'getaddrinfo')
                || str_contains($m, 'refused') || str_contains($m, 'timed out')  => 'cannot_reach_host',
            default                                                               => 'other',
        };
    }

    /** Switch the interface language and remember it in a cookie. */
    public function switchLanguage(Request $request): void
    {
        $locale = (string) $request->param('locale', '');
        $allowed = config('app.locales', ['fr', 'en']);

        if (in_array($locale, $allowed, true)) {
            setcookie('locale', $locale, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'secure'   => request_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        back('/');
    }

    /** Switch the display currency and remember it in a cookie (mirror of switchLanguage). */
    public function switchCurrency(Request $request): void
    {
        $currency = strtoupper((string) $request->param('currency', ''));
        $allowed  = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);

        if (in_array($currency, $allowed, true)) {
            setcookie('currency', $currency, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'secure'   => request_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        back('/');
    }

    /**
     * Suggestion régionale : applique la langue + devise du pays détecté
     * (action « appliquer ») ou mémorise le refus (action « ignorer »), puis
     * revient sur la page. Pose un cookie region_hint pour ne plus re-proposer.
     */
    public function region(Request $request): void
    {
        $action = (string) $request->param('action', '');
        $base   = ['path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax'];

        if ($action === 'appliquer') {
            $lang = (string) input_string('lang', '');
            $cur  = strtoupper((string) input_string('cur', ''));
            if (in_array($lang, config('app.locales', ['fr', 'en']), true)) {
                setcookie('locale', $lang, ['expires' => time() + 31536000] + $base);
            }
            if (in_array($cur, config('app.currencies', ['EUR']), true)) {
                setcookie('currency', $cur, ['expires' => time() + 31536000] + $base);
            }
            setcookie('region_hint', 'applied', ['expires' => time() + 31536000] + $base);
        } elseif ($action === 'ignorer') {
            // Refus mémorisé ~30 jours : on pourra re-proposer plus tard.
            setcookie('region_hint', 'kept', ['expires' => time() + 2592000] + $base);
        } else {
            abort(404);
        }

        $to = trim((string) input_string('to', '/'));
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/';
        }
        redirect(mb_substr($to, 0, 300));
    }

    /** Plan du site (sitemap.xml) : vitrines, produits, restaurants et annonces publiés. */
    public function sitemap(Request $request): void
    {
        $urls = [url('/'), url('/explorer'), url('/mentions-legales'), url('/confidentialite'), url('/cgv')];
        try {
            foreach (db()->query("SELECT id, slug FROM boutiques WHERE status = 'published' ORDER BY id DESC LIMIT 2000")->fetchAll() ?: [] as $s) {
                $urls[] = url('/boutique/' . $s['slug']);
                $stmt = db()->prepare("SELECT public_id FROM products WHERE boutique_id = :b AND status = 'active' LIMIT 500");
                $stmt->execute(['b' => (int) $s['id']]);
                foreach ($stmt->fetchAll() ?: [] as $p) {
                    $urls[] = url('/boutique/' . $s['slug'] . '/p/' . $p['public_id']);
                }
            }
        } catch (\Throwable) {
        }
        try {
            foreach (db()->query("SELECT slug FROM restaurants WHERE status = 'published' LIMIT 1000")->fetchAll() ?: [] as $r) {
                $urls[] = url('/restaurant/' . $r['slug']);
            }
        } catch (\Throwable) {
        }
        try {
            foreach (db()->query("SELECT public_id FROM listings WHERE status = 'active' ORDER BY id DESC LIMIT 5000")->fetchAll() ?: [] as $l) {
                $urls[] = url('/annonce/' . $l['public_id']);
            }
        } catch (\Throwable) {
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_values(array_unique($urls)) as $u) {
            $out .= '  <url><loc>' . htmlspecialchars((string) $u, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc></url>' . "\n";
        }
        $out .= '</urlset>';
        echo $out;
        exit;
    }

    /** robots.txt : autorise l'indexation et pointe vers le sitemap. */
    public function robots(Request $request): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /vendeur/\n"
            . "Disallow: /api/\n"
            . "Disallow: /boutique/gerer\n"
            . "Disallow: /restaurant/gerer\n"
            . "Sitemap: " . url('/sitemap.xml') . "\n";
        exit;
    }
}
