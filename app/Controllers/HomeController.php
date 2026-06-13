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
        // Produits sponsorisés (mise en avant payante — simulation).
        $sponsored = \App\Models\Product::promotedMarketplace(8);
        // Vitrine vivante : boutiques, restaurants et annonces actuellement en ligne.
        $annonces = \App\Models\Listing::recentActive(12);
        view('home', [
            'sponsored'       => $sponsored,
            'recently_viewed' => $recent,
            'for_you'         => $forYou,
            'reco_mains'      => \App\Services\Recommender::mainsFor(array_merge($sponsored, $recent, $forYou)),
            'boutiques'       => \App\Models\Boutique::recentPublished(12),
            'restaurants'     => \App\Models\Restaurant::recentPublished(12),
            'annonces'        => $annonces,
            'annonce_mains'   => \App\Models\Listing::mainPhotos(array_map(static fn (array $a): int => (int) $a['id'], $annonces)),
        ]);
    }

    /** Explorer public — recherche marketplace (mot-clé, catégorie, prix, tri). */
    public function explore(Request $request): void
    {
        $cats      = config('listings.categories', []);
        $countries = \App\Models\Product::searchCountries();
        $page  = max(1, (int) input_string('page', '1'));
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
            'limit'    => $limit,
            'offset'   => ($page - 1) * $limit,
        ];
        $products = \App\Models\Product::search($f);
        $ids = array_map(static fn (array $p): int => (int) $p['id'], $products);
        view('explore', [
            'page_title' => t('explore.title'),
            'categories' => $cats,
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

        $payload = [
            'app'            => 'ok',
            'db'             => $db,
            'session_driver' => config('app.session_driver'),
        ];
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

        // /health?mail_test=1 — real send to the configured sender's own address
        // (never an arbitrary recipient), throttled to 3/hour per IP.
        if (($_GET['mail_test'] ?? '') === '1') {
            if (!rate_limit_ok('mailtest:' . $request->ip(), 6, 3600)) {
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
