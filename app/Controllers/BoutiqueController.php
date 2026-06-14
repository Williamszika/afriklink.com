<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProProfile;
use App\Models\Review;
use App\Models\ShopView;
use App\Models\StockAlert;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;
use App\Services\OrderNotifier;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentProviders;
use App\Services\Payment\PaymentRequest;
use App\Services\Payment\PaymentResult;
use App\Services\QrCode;

/**
 * Boutique en ligne — assistant de création en 3 étapes (côté serveur, marche
 * sans JavaScript ; brouillon gardé en session jusqu'à la validation finale).
 * 1. Identité  2. Livraison & devise  3. Paiement & lancement.
 * Logo/bannière : envoi direct navigateur → Cloudinary (public), re-vérifié ici.
 */
final class BoutiqueController
{
    private const DRAFT = 'shop_draft';

    /* ---- Assistant ------------------------------------------------- */

    public function create(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        if (Boutique::findByUserId((int) $user['id']) !== null) {
            redirect('/boutique/gerer'); // une boutique par compte (pour l'instant)
        }
        $step = $this->clampStep((int) input_string('etape', '1'));
        view('boutique/create', [
            'step'        => $step,
            'draft'       => $_SESSION[self::DRAFT] ?? [],
            'user'        => $user,
            'media_ready' => CloudinaryService::configured(),
            'suggestSlug' => Boutique::uniqueSlug((string) ($user['full_name'] ?? 'boutique')),
        ]);
    }

    public function submit(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $step = $this->clampStep((int) input_string('etape', '1'));

        [$data, $errors] = match ($step) {
            1 => $this->validateStep1((int) $user['id']),
            2 => $this->validateStep2(),
            3 => $this->validateStep3(),
        };

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/boutique/creer?etape=' . $step);
        }

        if ($step < 3) {
            $_SESSION[self::DRAFT]['step' . $step] = $data;
            clear_old();
            redirect('/boutique/creer?etape=' . ($step + 1));
        }

        $this->finalize($request, $user, $data);
    }

    /** Vérification de disponibilité du slug en direct (JS de l'étape 1). */
    public function checkSlug(Request $request): void
    {
        $this->sellerOrRedirect();
        $slug = slugify((string) input_string('slug', ''));
        $min  = (int) config('shop.slug_min', 3);
        $max  = (int) config('shop.slug_max', 40);
        $valid = mb_strlen($slug) >= $min && mb_strlen($slug) <= $max;
        json_response([
            'slug'      => $slug,
            'valid'     => $valid,
            'available' => $valid && Boutique::slugAvailable($slug, (int) current_user_id()),
        ]);
    }

    /* ---- Gestion (après création) ---------------------------------- */

    public function manage(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $products = \App\Models\Product::forBoutique((int) $boutique['id']);
        $filter = whitelist((string) input_string('filtre', 'tous'), ['tous', 'en_ligne', 'masques'], 'tous');
        // Prévision de stock / réassort (algorithmique, à partir des ventes réelles).
        $forecasts = \App\Services\StockForecast::forProducts($products);
        $counts    = \App\Models\Product::countFor((int) $boutique['id']);
        view('boutique/manage', [
            'active'   => 'vitrines',
            'boutique' => $boutique,
            'products' => $products,
            'filter'   => $filter,
            'forecasts'     => $forecasts,
            'restock_count' => \App\Services\StockForecast::restockCount($forecasts),
            'readiness' => $this->shopReadiness($boutique, (int) $counts['active']),
            'mains'    => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'counts'   => $counts,
            'orders_pending' => \App\Models\Order::countFor((int) $boutique['id'])['new'],
            'views_total'    => ShopView::totals((int) $boutique['id'])['total'],
            'discounts'      => \App\Models\Discount::forBoutique((int) $boutique['id']),
            'shipping_zones' => \App\Models\ShippingZone::forBoutique((int) $boutique['id']),
        ] + SellerController::commonData($user));
    }

    /* ---- Édition de la boutique ------------------------------------ */

    public function edit(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        view('boutique/edit', ['active' => 'vitrines', 'boutique' => $boutique, 'user' => $user,
            'banners' => Boutique::banners((int) $boutique['id']),
            'media_ready' => CloudinaryService::configured()] + SellerController::commonData($user));
    }

    public function updateShop(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        [$d1, $e1] = $this->validateStep1((int) $user['id']);
        [$d2, $e2] = $this->validateStep2();
        [$d3, $e3] = $this->validateStep3();
        $errors = $e1 + $e2 + $e3;
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/boutique/modifier');
        }

        $d2 = $this->verifiedGeo($d2, $boutique);
        Boutique::update((int) $boutique['id'], [
            'name' => $d1['name'], 'tagline' => $d1['tagline'], 'description' => $d1['description'],
            'category' => $d1['category'],
            'logo_public_id' => $this->resolveImage($d1['logo_public_id'] ?? null, $boutique['logo_public_id'] ?? null),
            'banners' => $this->verifiedBanners($d1['banner_ids'] ?? [], Boutique::banners((int) $boutique['id'])),
            'currency' => $d2['currency'], 'shop_type' => $d2['shop_type'], 'address' => $d2['address'],
            'city' => $d2['city'], 'country_code' => $d2['country_code'], 'continent' => $d2['continent'],
            'geo_lat' => $d2['geo_lat'], 'geo_lng' => $d2['geo_lng'],
            'delivery_zones' => $d2['delivery_zones'], 'delivery_methods' => $d2['delivery_methods'],
            'free_ship_cents' => $d2['free_ship_cents'], 'prep_time' => $d2['prep_time'],
            'delivery_fee_cents' => $d2['delivery_fee_cents'] ?? null,
            'delivery_intl_cents' => $d2['delivery_intl_cents'] ?? null,
            'delivery_delay' => $d2['delivery_delay'] ?? null,
            'cod_enabled' => $d3['cod_enabled'],
            'payment_terms' => $d3['payment_terms'] ?? [], 'payment_methods' => $d3['payment_methods'] ?? [],
            'payment_provider' => $d3['payment_provider'] ?? null,
            'contacts' => $d1['contacts'] ?? [], 'contact_primary' => $d1['contact_primary'] ?? '',
        ]);
        // Configuration avancée : annonce + mode congé + horaires + commande
        // minimum + couleur d'accent.
        $announce = trim((string) input_string('announcement', ''));
        $isVac    = input_string('is_vacation', '') === '1' ? 1 : 0;
        $vacUntil = (string) input_string('vacation_until', '');
        $vacUntil = preg_match('/^\d{4}-\d{2}-\d{2}$/', $vacUntil) === 1 ? $vacUntil : null;
        $hours    = trim((string) input_string('open_hours', ''));
        $minRaw   = trim((string) input_string('min_order', ''));
        $minCents = $minRaw !== '' ? parse_price_to_cents($minRaw, (string) $boutique['currency']) : null;
        // Couleur d'accent : appliquée seulement si activée ET au format #rrggbb.
        $accent    = strtolower(trim((string) input_string('accent_color', '')));
        $useAccent = input_string('accent_on', '') === '1' && preg_match('/^#[0-9a-f]{6}$/', $accent) === 1;
        Boutique::updateConfig((int) $boutique['id'], [
            'announcement'    => $announce !== '' ? mb_substr($announce, 0, 200) : null,
            'is_vacation'     => $isVac,
            'vacation_until'  => $isVac ? $vacUntil : null,
            'open_hours'      => $hours !== '' ? mb_substr($hours, 0, 120) : null,
            'min_order_cents' => $minCents !== null && $minCents > 0 ? $minCents : null,
            'accent_color'    => $useAccent ? $accent : null,
        ]);
        AuditLog::record((int) $user['id'], 'shop.updated', 'boutique', (int) $boutique['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('shop.updated_flash'));
        redirect('/boutique/gerer');
    }

    /**
     * État de complétion de la boutique : checklist + score + « prêt à publier ».
     * @return array{items:list<array{key:string,done:bool,req:bool}>,score:int,ready:bool,missing:list<string>}
     */
    private function shopReadiness(array $boutique, int $activeProducts): array
    {
        $bid       = (int) $boutique['id'];
        $hasMedia  = !empty($boutique['logo_public_id']) || !empty($boutique['banner_public_id']) || Boutique::banners($bid) !== [];
        $contacts  = ($boutique['contact_whatsapp'] ?? '') . ($boutique['contact_sms'] ?? '') . ($boutique['contact_telegram'] ?? '')
            . ($boutique['contact_facebook'] ?? '') . ($boutique['contact_instagram'] ?? '') . ($boutique['contact_tiktok'] ?? '');
        $items = [
            ['key' => 'media',    'done' => $hasMedia,                                              'req' => true,  'href' => '/boutique/modifier'],
            ['key' => 'product',  'done' => $activeProducts > 0,                                     'req' => true,  'href' => '/boutique/produits/nouveau'],
            ['key' => 'contact',  'done' => trim($contacts) !== '',                                  'req' => true,  'href' => '/boutique/modifier'],
            ['key' => 'delivery', 'done' => trim((string) ($boutique['delivery_methods'] ?? '')) !== '', 'req' => true, 'href' => '/boutique/modifier'],
            ['key' => 'payment',  'done' => trim((string) ($boutique['payment_methods'] ?? '')) !== '' || !empty($boutique['cod_enabled']), 'req' => false, 'href' => '/boutique/modifier'],
            ['key' => 'desc',     'done' => trim((string) ($boutique['description'] ?? '')) !== '',  'req' => false, 'href' => '/boutique/modifier'],
        ];
        $done = 0;
        $missing = [];
        foreach ($items as $it) {
            if ($it['done']) { $done++; } elseif ($it['req']) { $missing[] = (string) $it['key']; }
        }
        return [
            'items'    => $items,
            'score'    => (int) round($done * 100 / count($items)),
            'ready'    => $missing === [],
            'missing'  => $missing,
            'warnings' => \App\Services\ShopReadiness::warnings($boutique, $activeProducts),
        ];
    }

    public function publish(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $action = whitelist((string) input_string('action', ''), ['publish', 'unpublish'], null);
        if ($action === null) {
            abort(404);
        }
        // Contrôle avant publication : on bloque tant que le minimum n'est pas réuni.
        if ($action === 'publish') {
            $readiness = $this->shopReadiness($boutique, (int) \App\Models\Product::countFor((int) $boutique['id'])['active']);
            if (!$readiness['ready']) {
                $labels = array_map(static fn (string $k): string => t('shop.ready.' . $k), $readiness['missing']);
                flash('error', t('shop.ready.blocked', ['list' => implode(', ', $labels)]));
                redirect('/boutique/gerer');
            }
        }
        Boutique::setStatus((int) $boutique['id'], $action === 'publish' ? 'published' : 'draft');
        flash('success', t($action === 'publish' ? 'shop.published_flash' : 'shop.unpublished_flash'));
        redirect('/boutique/gerer');
    }

    /** QR code SVG de l'adresse publique (aperçu, ou téléchargement si ?download=1). */
    public function qr(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $svg = QrCode::svg(url('/boutique/' . $boutique['slug']));
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        if (input_string('download', '') === '1') {
            header('Content-Disposition: attachment; filename="afriklink-qr-' . $boutique['slug'] . '.svg"');
        }
        echo $svg;
        exit;
    }

    /* ---- Page publique --------------------------------------------- */

    public function show(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null) {
            abort(404);
        }
        $isOwner = (int) $boutique['user_id'] === (int) (current_user_id() ?? 0);
        if ($boutique['status'] !== 'published' && !$isOwner) {
            abort(404); // brouillon : visible seulement par le propriétaire
        }
        $this->countView($boutique, $isOwner);
        $products = \App\Models\Product::forBoutique((int) $boutique['id'], true);
        $ids      = array_map(static fn (array $p): int => (int) $p['id'], $products);
        $ratings  = Review::summaryForProducts($ids);
        // Tri choisi via la barre d'outils de la vitrine (défaut = ordre recommandé/sponsorisé).
        $sort = whitelist((string) input_string('tri', ''), ['recent', 'price_asc', 'price_desc', 'rating'], '');
        if ($sort !== '' && $products !== []) {
            usort($products, static function (array $a, array $b) use ($sort, $ratings): int {
                return match ($sort) {
                    'price_asc'  => (int) $a['price_cents'] <=> (int) $b['price_cents'],
                    'price_desc' => (int) $b['price_cents'] <=> (int) $a['price_cents'],
                    'rating'     => ($ratings[(int) $b['id']]['avg'] ?? 0) <=> ($ratings[(int) $a['id']]['avg'] ?? 0),
                    default      => (int) $b['id'] <=> (int) $a['id'],
                };
            });
        }
        $banners  = Boutique::banners((int) $boutique['id']);
        $ogImage  = $banners[0] ?? ($boutique['logo_public_id'] ?? null);
        view('boutique/show', [
            'boutique' => $boutique,
            'banners'  => $banners,
            'seller'   => User::findById((int) $boutique['user_id']) ?? [],
            'seller_verified' => $this->sellerVerified((int) $boutique['user_id']),
            'is_owner' => $isOwner,
            'products' => $products,
            'sort'     => $sort,
            'mains'    => \App\Models\Product::mainPhotos($ids),
            'ratings'  => $ratings,
            'shop_rating' => Review::summaryForBoutique((int) $boutique['id']),
            'page_title' => (string) $boutique['name'],
            'meta' => [
                'description' => $this->ogDescription((string) ($boutique['tagline'] ?: $boutique['description'] ?? '')),
                'image'       => $ogImage !== null && $ogImage !== '' ? CloudinaryService::imageUrl($ogImage, 1200, 630) : null,
                'url'         => url('/boutique/' . $boutique['slug']),
            ],
        ]);
    }

    /** Page produit publique : /boutique/{slug}/p/{pid} */
    public function product(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null) {
            abort(404);
        }
        $product = \App\Models\Product::findByPublicId((string) $request->param('pid', ''));
        if ($product === null || (int) $product['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $isOwner = (int) $boutique['user_id'] === (int) (current_user_id() ?? 0);
        if (!$isOwner && ($boutique['status'] !== 'published' || $product['status'] !== 'active')) {
            abort(404);
        }
        $this->countView($boutique, $isOwner, (int) $product['id']);
        // Personnalisation : mémorise la consultation pour « Vu récemment » / « Recommandé pour vous ».
        \App\Services\Recommender::recordView((string) $product['public_id']);
        $photos = \App\Models\Product::photos((int) $product['id']);
        $main   = $photos[0]['cloud_public_id'] ?? null;
        // Produits recommandés : autres produits en ligne de la même boutique.
        $related = array_values(array_filter(
            \App\Models\Product::forBoutique((int) $boutique['id'], true),
            static fn (array $p): bool => (int) $p['id'] !== (int) $product['id']
        ));
        $related = array_slice($related, 0, 4);
        $rating = Review::summaryForProduct((int) $product['id']);
        // Recommandations : co-achats réels + historique de navigation du visiteur.
        $fbt    = \App\Services\Recommender::frequentlyBoughtTogether((int) $product['id'], 4);
        $recent = \App\Services\Recommender::recentlyViewed(6, (string) $product['public_id']);
        // Affiliation : lien « partager & gagner » pour un membre connecté (hors propriétaire).
        $viewerId = (int) (current_user_id() ?? 0);
        $affLink  = ($viewerId > 0 && !$isOwner && ($boutique['status'] ?? '') === 'published')
            ? url('/r/' . \App\Models\Affiliate::codeFor($viewerId) . '?to=' . rawurlencode('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']))
            : null;
        view('boutique/product', [
            'boutique' => $boutique,
            'product'  => $product,
            'variants' => \App\Models\ProductVariant::forProduct((int) $product['id']),
            'photos'   => $photos,
            'seller'   => User::findById((int) $boutique['user_id']) ?? [],
            'seller_verified' => $this->sellerVerified((int) $boutique['user_id']),
            'is_owner' => $isOwner,
            'reviews'  => Review::forProduct((int) $product['id']),
            'rating'   => $rating,
            'related'  => $related,
            'related_mains' => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $related)),
            'fbt'           => $fbt,
            'recently_viewed' => $recent,
            'reco_mains'      => \App\Services\Recommender::mainsFor(array_merge($fbt, $recent)),
            'aff_link'        => $affLink,
            'aff_rate'        => \App\Models\Affiliate::RATE_PCT,
            'page_title' => (string) $product['name'],
            'meta' => [
                'description' => $this->ogDescription(
                    format_price((int) $product['price_cents'], (string) $boutique['currency'])
                    . ' — ' . ($product['description'] ?: $boutique['name'])
                ),
                'image' => $main !== null ? CloudinaryService::imageUrl((string) $main, 1200, 630) : null,
                'url'   => url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']),
                'type'  => 'product',
                'jsonld' => $this->productJsonLd($product, $boutique, $main, $rating),
            ],
        ]);
    }

    /** Données structurées Schema.org (Product) : étoiles + prix dans Google. */
    private function productJsonLd(array $product, array $boutique, ?string $mainImage, array $rating): string
    {
        $cur = (string) $boutique['currency'];
        $inStock = $product['stock'] === null || (int) $product['stock'] > 0;
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => (string) $product['name'],
            'description' => mb_substr(trim((string) ($product['description'] ?? '')), 0, 500) ?: (string) $boutique['name'],
            'brand'    => ['@type' => 'Brand', 'name' => (string) $boutique['name']],
            'offers'   => [
                '@type'         => 'Offer',
                'price'         => number_format((int) $product['price_cents'] / 100, currency_is_integer($cur) ? 0 : 2, '.', ''),
                'priceCurrency' => $cur,
                'availability'  => 'https://schema.org/' . ($inStock ? 'InStock' : 'OutOfStock'),
                'url'           => url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']),
            ],
        ];
        if ($mainImage !== null && $mainImage !== '') {
            $data['image'] = [CloudinaryService::imageUrl($mainImage, 1200, 1200)];
        }
        if (($rating['count'] ?? 0) > 0) {
            $data['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $rating['avg'],
                'reviewCount' => (int) $rating['count'],
            ];
        }
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /* ---- Avis & notes ---------------------------------------------- */

    /**
     * Assistant d'achat (chatbot) : répond aux questions fréquentes à partir des
     * infos réelles de la boutique, sinon oriente vers le vendeur. Renvoie du JSON.
     */
    public function assistant(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || !$this->canShop($boutique)) {
            json_response(['error' => 'not_found'], 404);
        }
        $question = trim((string) input_string('question', ''));
        if ($question === '' || mb_strlen($question) > 500) {
            json_response(['error' => 'invalid'], 422);
        }
        $seller = User::findById((int) $boutique['user_id']) ?? [];
        $wa = preg_replace('/\D+/', '', (string) ($boutique['contact_whatsapp'] ?? '') ?: (string) ($seller['phone'] ?? ''));
        $reply = \App\Services\Assistant::answer($question, [
            'shop'             => (string) $boutique['name'],
            'delivery_delay'   => (string) ($boutique['delivery_delay'] ?? ''),
            'delivery_methods' => array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? '')))),
            'payment_terms'    => array_values(array_filter(explode(',', (string) ($boutique['payment_terms'] ?? '')))),
            'payment_methods'  => array_values(array_filter(explode(',', (string) ($boutique['payment_methods'] ?? '')))),
            'return_policy'    => (string) ($boutique['return_policy'] ?? ''),
            'wa'               => (string) $wa,
        ]);
        json_response($reply);
    }

    /** Un client dépose un avis sur un produit (public, anti-spam par throttle). */
    public function storeReview(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || $boutique['status'] !== 'published') {
            abort(404);
        }
        $product = \App\Models\Product::findByPublicId((string) $request->param('pid', ''));
        if ($product === null || (int) $product['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $back = '/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'];
        $rating = (int) input_string('rating', '0');
        $name = trim((string) input_string('author_name', ''));
        $comment = trim((string) input_string('comment', ''));
        if ($rating < 1 || $rating > 5 || mb_strlen($name) < 2) {
            flash('error', t('review.invalid'));
            keep_old($_POST);
            redirect($back . '#avis');
        }
        // « Achat vérifié » : l'auteur fournit (optionnellement) l'e-mail ou le téléphone
        // utilisé pour une commande. Si une commande non annulée de ce produit existe à ce
        // contact, l'avis reçoit le badge de confiance.
        $contact = trim((string) input_string('purchase_contact', ''));
        $isEmail = $contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
        $verified = $contact !== '' && Order::hasPurchase(
            (int) $product['id'],
            $isEmail ? $contact : null,
            $isEmail ? null : $contact
        );
        Review::create([
            'boutique_id' => (int) $boutique['id'],
            'product_id'  => (int) $product['id'],
            'user_id'     => current_user_id(),
            'author_name' => $name,
            'rating'      => $rating,
            'comment'     => $comment !== '' ? $comment : null,
            'verified'    => $verified,
        ]);
        AuditLog::record((int) ($boutique['user_id']), 'review.posted', 'product', (int) $product['id'], ['rating' => $rating, 'verified' => $verified ? 1 : 0], $request->ipBinary());
        \App\Models\Notification::push((int) $boutique['user_id'], 'review', t('notif.review'), $name . ' · ' . $rating . '★', $back);
        flash('success', t('review.thanks'));
        redirect($back . '#avis');
    }

    /** Le vendeur masque un avis abusif sur l'un de ses produits. */
    public function hideReview(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $review = Review::findByPublicId((string) $request->param('rid', ''));
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($review === null || $boutique === null || (int) $review['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        Review::setStatus((int) $review['id'], 'hidden');
        flash('success', t('review.hidden'));
        $back = (string) input_string('back', '/boutique/gerer');
        redirect(str_starts_with($back, '/boutique/') ? $back : '/boutique/gerer');
    }

    /** Un client demande à être prévenu du retour en stock d'un produit épuisé. */
    public function storeStockAlert(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || $boutique['status'] !== 'published') {
            abort(404);
        }
        $product = \App\Models\Product::findByPublicId((string) $request->param('pid', ''));
        if ($product === null || (int) $product['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $back = '/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'];
        if ($product['stock'] === null || (int) $product['stock'] > 0) {
            flash('info', t('stock.already_in'));
            redirect($back);
        }
        $email = trim((string) input_string('email', ''));
        $phone = trim((string) input_string('phone', ''));
        $emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $phoneOk = $phone !== '' && preg_match('/^\+?[0-9 .\-]{6,22}$/', $phone) === 1;
        if (!$emailOk && !$phoneOk) {
            keep_old($_POST);
            flash('error', t('order.err_contact'));
            redirect($back . '#stock-alert');
        }
        StockAlert::subscribe((int) $product['id'], (int) $boutique['id'], $emailOk ? $email : null, $phoneOk ? $phone : null);
        flash('success', t('stock.subscribed'));
        redirect($back);
    }

    /** Le vendeur enregistre sa politique de retour (panneau « gérer »). */
    public function updatePolicy(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $policy = trim((string) input_string('return_policy', ''));
        Boutique::updatePolicy((int) $boutique['id'], $policy !== '' ? mb_substr($policy, 0, 2000) : null);
        flash('success', t('shop.policy_saved'));
        redirect('/boutique/gerer');
    }

    /** Le vendeur crée un code promo (pourcentage ou montant) pour sa boutique. */
    public function createDiscount(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $cur  = (string) $boutique['currency'];
        $code = strtoupper(trim((string) input_string('code', '')));
        $type = whitelist((string) input_string('type', 'percent'), ['percent', 'amount'], 'percent');
        if (preg_match('/^[A-Z0-9_-]{2,40}$/', $code) !== 1) {
            flash('error', t('promo.err_code'));
            redirect('/boutique/gerer#promos');
        }
        $value = $type === 'amount'
            ? (int) (parse_price_to_cents(trim((string) input_string('value', '')), $cur) ?? 0)
            : max(0, min(100, (int) input_string('value', '0')));
        if ($value <= 0) {
            flash('error', t('promo.err_value'));
            redirect('/boutique/gerer#promos');
        }
        $minRaw = trim((string) input_string('min_order', ''));
        $min = $minRaw !== '' ? parse_price_to_cents($minRaw, $cur) : null;
        \App\Models\Discount::create((int) $boutique['id'], [
            'code' => $code, 'type' => $type, 'value' => $value,
            'min_order_cents' => $min !== null && $min > 0 ? $min : null,
        ]);
        flash('success', t('promo.created'));
        redirect('/boutique/gerer#promos');
    }

    /** Active ou désactive un code promo de la boutique. */
    public function toggleDiscount(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $id = (int) $request->param('id', '0');
        $action = whitelist((string) input_string('action', ''), ['disable', 'enable'], null);
        if ($action !== null && $id > 0) {
            \App\Models\Discount::setStatus($id, (int) $boutique['id'], $action === 'disable' ? 'disabled' : 'active');
            flash('success', t('promo.updated'));
        }
        redirect('/boutique/gerer#promos');
    }

    /* ---- Zones de livraison (groupes de pays × tarif + franco par zone) ---- */

    public function createShippingZone(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        if (\App\Models\ShippingZone::count((int) $boutique['id']) >= 12) {
            flash('error', t('ship.zone.err_max'));
            redirect('/boutique/gerer#zones');
        }
        $cur  = (string) $boutique['currency'];
        $name = trim((string) input_string('name', ''));
        if ($name === '') {
            flash('error', t('ship.zone.err_name'));
            redirect('/boutique/gerer#zones');
        }
        // « Reste du monde » (catch-all) = aucun pays ; sinon liste validée contre config.
        $rest  = input_string('rest', '') === '1';
        $valid = array_keys(config('countries', []));
        $countries = $rest ? [] : array_values(array_intersect(
            array_map(static fn ($c): string => strtoupper(trim((string) $c)), (array) ($_POST['countries'] ?? [])),
            $valid
        ));
        if (!$rest && $countries === []) {
            flash('error', t('ship.zone.err_countries'));
            redirect('/boutique/gerer#zones');
        }
        $fee     = (int) (parse_price_to_cents(trim((string) input_string('fee', '0')), $cur) ?? 0);
        $freeRaw = trim((string) input_string('free_above', ''));
        $freeAbove = $freeRaw !== '' ? parse_price_to_cents($freeRaw, $cur) : null;
        $delay   = whitelist((string) input_string('delay', ''), config('shop.prep_options', []), null);

        \App\Models\ShippingZone::create((int) $boutique['id'], [
            'name'             => $name,
            'countries'        => $countries !== [] ? implode(',', $countries) : null,
            'fee_cents'        => $fee,
            'free_above_cents' => $freeAbove !== null && $freeAbove > 0 ? $freeAbove : null,
            'delay'            => $delay,
            'position'         => \App\Models\ShippingZone::count((int) $boutique['id']),
        ]);
        flash('success', t('ship.zone.created'));
        redirect('/boutique/gerer#zones');
    }

    public function deleteShippingZone(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        \App\Models\ShippingZone::delete((string) $request->param('zid', ''), (int) $boutique['id']);
        flash('success', t('ship.zone.deleted'));
        redirect('/boutique/gerer#zones');
    }

    /* ---- Caisse & commande en ligne (panier public) ---------------- */

    /**
     * Le client envoie son panier (depuis la vitrine) ; on revalide chaque
     * ligne côté serveur, on garde le panier en session, puis on l'emmène à la
     * caisse (schéma Post/Redirect/Get : la page caisse est rafraîchissable).
     */
    public function caisseStore(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || !$this->canShop($boutique)) {
            abort(404);
        }
        $raw = json_decode((string) ($_POST['cart_json'] ?? '[]'), true);
        $entries = [];
        foreach (is_array($raw) ? $raw : [] as $e) {
            $id = (string) ($e['id'] ?? '');
            if ($id !== '') {
                $entries[] = ['id' => $id, 'qty' => max(1, min(99, (int) ($e['qty'] ?? 0)))];
            }
        }
        $lines = $this->validateCartLines($boutique, $entries);
        if ($lines === []) {
            flash('error', t('rorder.empty'));
            redirect('/boutique/' . $boutique['slug']);
        }
        $_SESSION['caisse'][(int) $boutique['id']] = array_map(
            static fn (array $l): array => ['id' => $l['public_id'], 'qty' => $l['qty']],
            $lines
        );
        $_SESSION['cart_shop'] = (string) $boutique['slug']; // pour l'icône panier de l'en-tête
        redirect('/boutique/' . $boutique['slug'] . '/caisse');
    }

    /** La caisse : récapitulatif du panier + moyen / type de paiement + validation. */
    public function caisse(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || !$this->canShop($boutique)) {
            abort(404);
        }
        $lines = $this->validatedCart($boutique);
        if ($lines === []) {
            redirect('/boutique/' . $boutique['slug']);
        }
        $total = array_sum(array_map(static fn (array $l): int => $l['qty'] * $l['unit_price_cents'], $lines));
        $fulfillments = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
        // Frais de livraison par mode (seuil de gratuité appliqué).
        $shipMap = [];
        foreach ($fulfillments as $m) {
            $shipMap[$m] = $this->shippingFor($boutique, $m, $total);
        }
        view('boutique/caisse', [
            'boutique'     => $boutique,
            'lines'        => $lines,
            'total'        => $total,
            'ship_map'     => $shipMap,
            'delivery_delay' => (string) ($boutique['delivery_delay'] ?? ''),
            'preview'      => $boutique['status'] !== 'published',
            'terms'        => array_values(array_filter(explode(',', (string) ($boutique['payment_terms'] ?? '')))),
            'pay_methods'  => array_values(array_filter(explode(',', (string) ($boutique['payment_methods'] ?? '')))),
            'fulfillments' => $fulfillments,
            'page_title'   => t('caisse.title', ['shop' => (string) $boutique['name']]),
        ]);
    }

    /**
     * Le client valide la caisse : on re-valide le panier (session) côté serveur
     * (produit de la boutique, en ligne, en stock). Prix et disponibilité ne
     * sont jamais lus depuis le client.
     */
    public function checkout(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || !$this->canShop($boutique)) {
            abort(404);
        }
        // Aperçu propriétaire : sur un brouillon, la vraie commande est bloquée.
        if ($boutique['status'] !== 'published') {
            flash('info', t('shop.preview_blocked'));
            redirect('/boutique/' . $boutique['slug'] . '/caisse');
        }
        // Mode congé : commandes suspendues.
        if (!empty($boutique['is_vacation'])) {
            flash('error', t('shop.vacation_blocked'));
            redirect('/boutique/' . $boutique['slug']);
        }
        $cur = (string) $boutique['currency'];
        // Le panier est tenu côté caisse (session), validé serveur ; jamais lu du client.
        $lines = $this->validatedCart($boutique);

        // Commande minimum : on bloque tant que le sous-total n'atteint pas le seuil.
        $minOrder = (int) ($boutique['min_order_cents'] ?? 0);
        if ($minOrder > 0 && $lines !== []) {
            $sub = array_sum(array_map(static fn (array $l): int => $l['qty'] * $l['unit_price_cents'], $lines));
            if ($sub < $minOrder) {
                keep_old($_POST);
                flash('error', t('shop.min_order_blocked', ['min' => format_price($minOrder, $cur)]));
                redirect('/boutique/' . $boutique['slug'] . '/caisse');
            }
        }

        $name = trim((string) input_string('client_name', ''));
        $phone = trim((string) input_string('client_phone', ''));
        $email = trim((string) input_string('client_email', ''));
        $address = trim((string) input_string('client_address', ''));
        // Position partagée par le client (GPS du navigateur) — pour une livraison précise.
        $lat = filter_var((string) input_string('geo_lat', ''), FILTER_VALIDATE_FLOAT);
        $lng = filter_var((string) input_string('geo_lng', ''), FILTER_VALIDATE_FLOAT);
        $hasGeo = $lat !== false && $lng !== false && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
        $methods = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
        $fulfillment = $methods !== []
            ? whitelist((string) input_string('fulfillment', ''), $methods, $methods[0])
            : null;
        // Condition de paiement choisie par le client, parmi celles proposées par la boutique.
        $terms = array_values(array_filter(explode(',', (string) ($boutique['payment_terms'] ?? ''))));
        $paymentTerm = $terms !== []
            ? whitelist((string) input_string('payment_term', ''), $terms, $terms[0])
            : null;
        // Moyen de paiement (« mode ») choisi par le client, parmi ceux acceptés.
        $payMethods = array_values(array_filter(explode(',', (string) ($boutique['payment_methods'] ?? ''))));
        $paymentMethod = $payMethods !== []
            ? whitelist((string) input_string('payment_method', ''), $payMethods, $payMethods[0])
            : null;

        $errors = [];
        if ($lines === []) {
            $errors['cart'] = t('rorder.empty');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['client_name'] = t('order.err_client');
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9 .\-]{6,22}$/', $phone)) {
            $errors['client_phone'] = t('order.err_phone');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['client_email'] = t('order.err_email');
        }
        // Le client doit laisser au moins un moyen d'être recontacté.
        if ($phone === '' && $email === '') {
            $errors['contact'] = t('order.err_contact');
        }
        // Adresse obligatoire si le mode choisi est une livraison.
        if ($address === '' && in_array($fulfillment, ['local', 'international'], true)) {
            $errors['client_address'] = t('order.err_address');
        }
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect($lines === [] ? '/boutique/' . $boutique['slug'] : '/boutique/' . $boutique['slug'] . '/caisse');
        }

        $subtotal = array_sum(array_map(static fn (array $l): int => $l['qty'] * $l['unit_price_cents'], $lines));
        $shipping = $this->shippingFor($boutique, $fulfillment, $subtotal);
        // Code promo (optionnel) : revalidé côté serveur sur cette boutique.
        $promoCode = trim((string) input_string('promo_code', ''));
        $discount = 0;
        $discountRow = null;
        if ($promoCode !== '') {
            $discountRow = \App\Models\Discount::findValidCode((int) $boutique['id'], $promoCode);
            if ($discountRow === null) {
                keep_old($_POST);
                flash('error', t('promo.invalid'));
                redirect('/boutique/' . $boutique['slug'] . '/caisse');
            }
            $discount = \App\Models\Discount::reductionFor($discountRow, $subtotal);
        }
        $publicId = Order::createCart([
            'boutique_id'  => (int) $boutique['id'],
            'user_id'      => (int) $boutique['user_id'],
            'client_name'  => $name,
            'client_phone' => $phone !== '' ? $phone : null,
            'client_email' => $email !== '' ? $email : null,
            'client_address' => $address !== '' ? mb_substr($address, 0, 220) : null,
            'geo_lat'        => $hasGeo ? round($lat, 6) : null,
            'geo_lng'        => $hasGeo ? round($lng, 6) : null,
            'note'           => mb_substr((string) input_string('note', ''), 0, 500) ?: null,
            'fulfillment'    => $fulfillment,
            'payment_term'   => $paymentTerm,
            'payment_method' => $paymentMethod,
            'shipping_cents' => $shipping,
            'discount_cents' => $discount,
            'discount_code'  => $discountRow !== null ? (string) $discountRow['code'] : null,
            'currency'       => $cur,
        ], $lines);
        if ($discountRow !== null) {
            \App\Models\Discount::recordUse((int) $discountRow['id']);
        }

        unset($_SESSION['caisse'][(int) $boutique['id']]); // panier consommé
        \App\Services\Cart::clearBoutique((int) $boutique['id']); // vide aussi le panier persistant
        AuditLog::record((int) $boutique['user_id'], 'order.placed', 'boutique', (int) $boutique['id'], ['order' => $publicId], $request->ipBinary());
        // Affiliation : créditer l'apporteur si le visiteur vient d'un lien /r/{code} (one-shot, hors auto-parrainage).
        \App\Models\Affiliate::attribute($publicId, (int) $boutique['id'], (int) $boutique['user_id'], $subtotal, $cur);

        // Notifications (best-effort, n'empêchent jamais la commande) :
        // le vendeur reçoit l'alerte « nouvelle commande », le client une confirmation.
        $ref = strtoupper(substr($publicId, 0, 6));
        $total = 0;
        $notifyLines = [];
        foreach ($lines as $l) {
            $total += $l['qty'] * $l['unit_price_cents'];
            $notifyLines[] = ['qty' => $l['qty'], 'title' => $l['title'], 'line_total_cents' => $l['qty'] * $l['unit_price_cents']];
        }
        // Notification (cloche) au vendeur : nouvelle commande.
        \App\Models\Notification::push((int) $boutique['user_id'], 'order', t('notif.order'), '#' . $ref . ' · ' . $name, '/vendeur/commandes');
        try {
            OrderNotifier::sellerNewOrder(
                User::findById((int) $boutique['user_id']) ?? [],
                (string) $boutique['name'], $ref, $notifyLines, $total, $cur, $name, $phone, url('/vendeur/commandes'),
            );
        } catch (\Throwable) {
        }
        try {
            OrderNotifier::clientOrderPlaced(
                $email, $phone, (string) $boutique['name'], $ref, $total, $cur, $paymentTerm,
                url('/boutique/commande/' . $publicId),
            );
        } catch (\Throwable) {
        }

        clear_old();
        // « Valider le paiement » : selon le type choisi, on enchaîne directement le
        // règlement (avance / paiement avant livraison) ou on va à la confirmation
        // (paiement à la livraison — rien à régler en ligne).
        $order = Order::findByPublicId($publicId);
        $payUrl = $order !== null ? $this->beginPayment($order, $boutique) : null;
        redirect($payUrl ?? ('/boutique/commande/' . $publicId));
    }

    /** Confirmation client : récapitulatif + envoi à la boutique via WhatsApp. */
    public function orderConfirmation(Request $request): void
    {
        $order = \App\Models\Order::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        $boutique = null;
        try {
            $stmt = db()->prepare('SELECT * FROM boutiques WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $order['boutique_id']]);
            $boutique = $stmt->fetch() ?: null;
        } catch (\Throwable) {
        }
        $sellerPhone = $boutique !== null
            ? (string) (User::findById((int) $boutique['user_id'])['phone'] ?? '')
            : '';
        $status = (string) ($order['status'] ?? 'new');
        $payStatus = (string) ($order['payment_status'] ?? 'unpaid');
        $dueCents = Order::amountDue($order);
        // Le règlement en ligne est proposé tant qu'il reste un montant dû et non payé
        // (le client peut payer à la caisse, ou plus tard depuis ce lien).
        $payReady = $dueCents > 0 && $payStatus !== 'paid';
        view('boutique/order_confirmation', [
            'order'        => $order,
            'items'        => Order::items((int) $order['id']),
            'boutique'     => $boutique,
            'seller_phone' => $sellerPhone,
            'term'         => (string) ($order['payment_term'] ?? ''),
            'status'       => $status,
            'pay_status'   => $payStatus,
            'due_cents'    => $dueCents,
            'rest_cents'   => Order::restDue($order),
            'pay_ready'    => $payReady,
            'page_title'   => t('rorder.confirm_title'),
        ]);
    }

    /* ---- Paiement en ligne de la commande (public) ----------------- */

    /** Démarre (ou reprend) le paiement d'une commande depuis la confirmation. */
    public function payStart(Request $request): void
    {
        $order = Order::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        if (($order['payment_status'] ?? '') === 'paid') {
            redirect('/boutique/commande/' . $order['public_id']);
        }
        $boutique = $this->boutiqueOf((int) $order['boutique_id']);
        if ($boutique === null) {
            abort(404);
        }
        // Rien à régler en ligne (paiement à la livraison) → retour à la confirmation.
        redirect($this->beginPayment($order, $boutique) ?? ('/boutique/commande/' . $order['public_id']));
    }

    /** Page de paiement « bac à sable » (simulation) — publique, tient lieu de PSP. */
    public function paySandbox(Request $request): void
    {
        $order = Order::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        if (($order['payment_status'] ?? '') === 'paid') {
            redirect('/boutique/commande/' . $order['public_id']);
        }
        $boutique = $this->boutiqueOf((int) $order['boutique_id']);
        // Dès qu'un vrai PSP est branché, le bac à sable disparaît (sécurité).
        if ($boutique === null || PaymentProviders::resolve($boutique['payment_provider'] ?? null)->key() !== 'simulation') {
            abort(404);
        }
        $payment = Payment::latestForOrder((int) $order['id']);
        view('boutique/pay_sandbox', [
            'order'        => $order,
            'boutique'     => $boutique,
            // Montant réellement demandé (acompte ou total), porté par l'intention de paiement.
            'amount_cents' => $payment !== null ? (int) $payment['amount_cents'] : Order::amountDue($order),
            'is_deposit'   => (string) ($order['payment_term'] ?? '') === 'deposit',
            'page_title'   => t('pay.sandbox_title'),
        ]);
    }

    /** Décision du bac à sable : payé / annulé. */
    public function paySettle(Request $request): void
    {
        $order = Order::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        $payment = Payment::latestForOrder((int) $order['id']);
        // Le bac à sable ne règle QUE des paiements en simulation : un vrai
        // paiement passe forcément par la vérification du PSP (payReturn).
        if ($payment !== null && (string) $payment['provider'] !== 'simulation') {
            abort(404);
        }
        $outcome = whitelist((string) input_string('outcome', ''), ['pay', 'cancel'], 'cancel');
        if ($payment !== null) {
            if ($outcome === 'pay') {
                Payment::setStatus((string) $payment['public_id'], PaymentResult::PAID);
                Order::setPaymentStatus((int) $order['id'], 'paid');
                AuditLog::record((int) $order['user_id'], 'order.paid', 'order', (int) $order['id'], [], $request->ipBinary());
            } else {
                Payment::setStatus((string) $payment['public_id'], PaymentResult::CANCELLED);
                Order::setPaymentStatus((int) $order['id'], 'unpaid');
            }
        }
        redirect('/boutique/commande/' . $order['public_id']);
    }

    /** Retour d'un PSP réel : on vérifie le statut et on met la commande à jour. */
    public function payReturn(Request $request): void
    {
        $order = Order::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        $payment = Payment::latestForOrder((int) $order['id']);
        if ($payment !== null) {
            $provider = PaymentProviders::resolve((string) $payment['provider']);
            try {
                $res = $provider->verify((string) $payment['public_id'], $_GET);
            } catch (PaymentException) {
                $res = new PaymentResult((string) $payment['public_id'], PaymentResult::FAILED);
            }
            Payment::setStatus((string) $payment['public_id'], $res->status);
            Order::setPaymentStatus((int) $order['id'], $res->isPaid() ? 'paid' : ($res->status === 'pending' ? 'pending' : 'unpaid'));
        }
        redirect('/boutique/commande/' . $order['public_id']);
    }

    /** Frais de livraison selon le mode choisi + seuil de gratuité. */
    private function shippingFor(array $boutique, ?string $fulfillment, int $subtotal): int
    {
        $fee = match ((string) $fulfillment) {
            'local'        => (int) ($boutique['delivery_fee_cents'] ?? 0),
            'international' => (int) ($boutique['delivery_intl_cents'] ?? 0),
            default        => 0, // retrait / main à main : pas de frais
        };
        $free = (int) ($boutique['free_ship_cents'] ?? 0);
        if ($free > 0 && $subtotal >= $free) {
            $fee = 0; // livraison offerte au-delà du seuil
        }
        return max(0, $fee);
    }

    /** Panier de la caisse (session) revalidé en lignes prêtes à commander. @return list<array> */
    private function validatedCart(array $boutique): array
    {
        $entries = $_SESSION['caisse'][(int) $boutique['id']] ?? [];
        return $this->validateCartLines($boutique, is_array($entries) ? $entries : []);
    }

    /**
     * Revalide des entrées {id, qty} contre la boutique : produit présent, en
     * ligne, en stock ; quantité plafonnée ; prix figé au prix courant.
     * @param list<array{id:string,qty:int}> $entries @return list<array>
     */
    private function validateCartLines(array $boutique, array $entries): array
    {
        $lines = [];
        foreach ($entries as $entry) {
            $id = (string) ($entry['id'] ?? '');
            // L'identifiant du panier peut désigner un produit simple OU une variante.
            $variant = \App\Models\ProductVariant::findByPublicId($id);
            $product = $variant !== null
                ? \App\Models\Product::findById((int) $variant['product_id'])
                : \App\Models\Product::findByPublicId($id);
            if ($product === null
                || (int) $product['boutique_id'] !== (int) $boutique['id']
                || $product['status'] !== 'active') {
                continue;
            }
            // Stock + prix : de la variante choisie si fournie, sinon du produit.
            $stock = $variant !== null ? $variant['stock'] : $product['stock'];
            $price = ($variant !== null && $variant['price_cents'] !== null)
                ? (int) $variant['price_cents'] : (int) $product['price_cents'];
            $qty = max(1, min(99, (int) ($entry['qty'] ?? 0)));
            if ($stock !== null) {
                $stock = (int) $stock;
                if ($stock <= 0) {
                    continue;
                }
                $qty = min($qty, $stock);
            }
            $label = $variant !== null ? trim((string) ($variant['label'] ?? '')) : '';
            $lines[] = [
                'product_id'       => (int) $product['id'],
                'variant_id'       => $variant !== null ? (int) $variant['id'] : null,
                'public_id'        => $variant !== null ? (string) $variant['public_id'] : (string) $product['public_id'],
                'title'            => (string) $product['name'] . ($label !== '' ? ' — ' . $label : ''),
                'qty'              => $qty,
                'unit_price_cents' => $price,
            ];
        }
        return $lines;
    }

    /**
     * Démarre le règlement d'une commande selon sa condition de paiement :
     * crée l'intention et renvoie l'URL où envoyer le client (bac à sable ou
     * PSP réel), ou null s'il n'y a rien à régler en ligne (paiement à la
     * livraison) ou en cas d'échec fournisseur.
     */
    private function beginPayment(array $order, array $boutique): ?string
    {
        if (($order['payment_status'] ?? '') === 'paid') {
            return null;
        }
        $amount = Order::amountDue($order);
        if ($amount <= 0) {
            return null; // à la livraison : pas de paiement en ligne
        }
        $ref = strtoupper(substr((string) $order['public_id'], 0, 6));
        $provider = PaymentProviders::resolve($boutique['payment_provider'] ?? null);
        $payRef = Payment::create([
            'boutique_id'  => (int) $boutique['id'],
            'order_id'     => (int) $order['id'],
            'user_id'      => (int) $boutique['user_id'],
            'provider'     => $provider->key(),
            'amount_cents' => $amount,
            'currency'     => (string) $order['currency'],
            'description'  => t('pay.order_desc', ['ref' => $ref]),
        ]);
        Order::setPaymentStatus((int) $order['id'], 'pending', $payRef);

        if ($provider->key() === 'simulation') {
            return '/boutique/commande/' . $order['public_id'] . '/regler';
        }
        try {
            $init = $provider->createPayment(new PaymentRequest(
                reference: $payRef,
                amountCents: $amount,
                currency: (string) $order['currency'],
                description: t('pay.order_desc', ['ref' => $ref]),
                returnUrl: url('/boutique/commande/' . $order['public_id'] . '/retour'),
                customerName: (string) $order['client_name'],
                customerPhone: (string) ($order['client_phone'] ?? ''),
            ));
        } catch (PaymentException) {
            Order::setPaymentStatus((int) $order['id'], 'failed');
            return null;
        }
        if ($init->providerRef !== '') {
            Payment::setStatus($payRef, PaymentResult::PENDING, $init->providerRef);
        }
        return $init->redirectUrl;
    }

    /** Accès à la caisse : vitrine publiée (tout le monde) ou propriétaire (aperçu d'un brouillon). */
    private function canShop(array $boutique): bool
    {
        return ($boutique['status'] ?? '') === 'published'
            || (int) $boutique['user_id'] === (int) (current_user_id() ?? 0);
    }

    /** Charge une boutique par son identifiant (pour les flux de paiement publics). */
    private function boutiqueOf(int $id): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM boutiques WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /* ---- Validation des étapes ------------------------------------- */

    private function validateStep1(int $userId): array
    {
        $errors = [];
        $nameMax = (int) config('shop.name_max', 80);

        $name = input_string('name');
        if ($name === null || mb_strlen($name) < 2 || mb_strlen($name) > $nameMax) {
            $errors['name'] = t('validation.shop_name', ['max' => $nameMax]);
        }

        // Slug : saisi ou dérivé du nom ; normalisé ; unique.
        $slug = slugify((string) (input_string('slug', '') ?: (string) $name));
        $min = (int) config('shop.slug_min', 3);
        $max = (int) config('shop.slug_max', 40);
        if (mb_strlen($slug) < $min || mb_strlen($slug) > $max) {
            $errors['slug'] = t('validation.shop_slug', ['min' => $min, 'max' => $max]);
        } elseif (!Boutique::slugAvailable($slug, $userId)) {
            $errors['slug'] = t('validation.shop_slug_taken');
        }

        $logo = input_string('logo_public_id');

        // Bannière = diaporama : jusqu'à 10 identifiants Cloudinary (banners_json).
        $rawBanners = json_decode((string) ($_POST['banners_json'] ?? '[]'), true);
        $bannerIds  = is_array($rawBanners) ? array_values(array_unique(array_filter($rawBanners, 'is_string'))) : [];
        $bannerIds  = array_slice($bannerIds, 0, (int) config('shop.banner_max', 10));

        $tagline = input_string('tagline');
        if ($tagline !== null && mb_strlen($tagline) > (int) config('shop.tagline_max', 120)) {
            $tagline = mb_substr($tagline, 0, (int) config('shop.tagline_max', 120));
        }
        $description = input_string('description');
        if ($description !== null && mb_strlen($description) > (int) config('shop.desc_max', 1500)) {
            $errors['description'] = t('validation.too_long', ['max' => config('shop.desc_max', 1500)]);
        }
        $category = whitelist((string) input_string('category', ''), config('listings.categories', []), null);

        // Canaux de contact : valeurs normalisées (vides ignorées) + canal principal.
        $contacts = [];
        foreach (\App\Services\ContactChannels::CHANNELS as $ch) {
            $val = \App\Services\ContactChannels::normalize($ch, (string) input_string('contact_' . $ch, ''));
            if ($val !== null) {
                $contacts[$ch] = $val;
            }
        }
        // Canaux principaux : plusieurs possibles (cases à cocher), gardés dans
        // l'ordre d'affichage et limités à ceux réellement renseignés.
        $rawPrimary = $_POST['contact_primary'] ?? [];
        $rawPrimary = is_array($rawPrimary) ? array_map('strval', $rawPrimary) : [(string) $rawPrimary];
        $primary = array_values(array_filter(
            array_intersect(\App\Services\ContactChannels::CHANNELS, $rawPrimary),
            static fn ($ch): bool => isset($contacts[$ch])
        ));

        return [[
            'name' => $name, 'slug' => $slug, 'logo_public_id' => $logo, 'banner_ids' => $bannerIds,
            'tagline' => $tagline, 'description' => $description, 'category' => $category,
            'contacts' => $contacts, 'contact_primary' => $primary,
        ], $errors];
    }

    private function validateStep2(): array
    {
        $errors = [];
        $currency = input_currency('currency', config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']));
        if ($currency === null) {
            $errors['currency'] = t('validation.required');
            $currency = 'EUR';
        }

        // Boutique physique (avec adresse, retrait possible) ou 100 % en ligne
        // (pas d'adresse, tout en livraison).
        $shopType = whitelist((string) input_string('shop_type', ''), ['physical', 'online'], null);
        if ($shopType === null) {
            $errors['shop_type'] = t('validation.required');
            $shopType = 'online';
        }

        $address = null;
        if ($shopType === 'physical') {
            $address = input_string('address');
            if ($address === null || mb_strlen($address) < 5 || mb_strlen($address) > 220) {
                $errors['address'] = t('validation.shop_address');
            }
        }

        // Localisation : ville saisie ou remplie par la géolocalisation du
        // navigateur ; pays sur liste blanche ; continent déduit du pays
        // côté serveur (jamais fourni par le client). Coordonnées en option.
        $city = trim((string) input_string('city', ''));
        $city = $city !== '' ? mb_substr($city, 0, 80) : null;
        $countryCode = whitelist(strtoupper((string) input_string('country_code', '')), array_keys(config('countries', [])), null);
        $continent = \App\Services\GeoService::continentOf($countryCode);
        $lat = filter_var((string) input_string('geo_lat', ''), FILTER_VALIDATE_FLOAT);
        $lng = filter_var((string) input_string('geo_lng', ''), FILTER_VALIDATE_FLOAT);
        $hasCoords = $lat !== false && $lng !== false && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        $zones   = array_values(array_intersect((array) ($_POST['zones'] ?? []), config('shop.delivery_zones', [])));
        $methods = array_values(array_intersect((array) ($_POST['methods'] ?? []), config('shop.delivery_methods', [])));

        if ($shopType === 'online') {
            // 100 % en ligne : pas de retrait en main propre, livraison obligatoire.
            $methods = array_values(array_diff($methods, ['pickup']));
            if ($methods === []) {
                $errors['methods'] = t('validation.shop_methods_online');
            }
        }

        $prep    = whitelist((string) input_string('prep_time', ''), config('shop.prep_options', []), null);

        $freeRaw = trim((string) input_string('free_ship', ''));
        $freeCents = null;
        if ($freeRaw !== '') {
            $freeCents = parse_price_to_cents($freeRaw, $currency);
            if ($freeCents === null) {
                $errors['free_ship'] = t('validation.price_invalid');
            }
        }

        // Frais & délai de livraison (facultatifs) : local + international + délai.
        $dfeeRaw = trim((string) input_string('delivery_fee', ''));
        $dfee = $dfeeRaw !== '' ? parse_price_to_cents($dfeeRaw, $currency) : null;
        $dintlRaw = trim((string) input_string('delivery_intl', ''));
        $dintl = $dintlRaw !== '' ? parse_price_to_cents($dintlRaw, $currency) : null;
        $ddelay = whitelist((string) input_string('delivery_delay', ''), (array) config('shop.prep_options', []), null);

        return [[
            'currency' => $currency, 'shop_type' => $shopType, 'address' => $address,
            'city' => $city, 'country_code' => $countryCode, 'continent' => $continent,
            'geo_lat' => $hasCoords ? round($lat, 6) : null,
            'geo_lng' => $hasCoords ? round($lng, 6) : null,
            'delivery_zones' => implode(',', $zones),
            'delivery_methods' => implode(',', $methods), 'prep_time' => $prep,
            'free_ship_cents' => $freeCents,
            'delivery_fee_cents' => $dfee, 'delivery_intl_cents' => $dintl, 'delivery_delay' => $ddelay,
        ], $errors];
    }

    private function validateStep3(): array
    {
        // Conditions de paiement (quand) + moyens de paiement (comment) que le
        // vendeur accepte : cases à cocher, gardées dans l'ordre du config.
        $terms = $this->checkedList('payment_terms', (array) config('shop.payment_terms', []));
        $methods = $this->checkedList('payment_methods', (array) config('shop.payment_methods', []));
        $provider = whitelist((string) input_string('payment_provider', ''), array_keys((array) config('payment.providers', [])), null);
        return [[
            'cod_enabled'      => in_array('on_delivery', $terms, true) || in_array('cash', $methods, true),
            'payment_terms'    => $terms,
            'payment_methods'  => $methods,
            'payment_provider' => $provider,
        ], []];
    }

    /** Valeurs cochées d'un champ tableau, filtrées par une liste blanche et ordonnées. @return list<string> */
    private function checkedList(string $field, array $allowed): array
    {
        $raw = $_POST[$field] ?? [];
        $raw = is_array($raw) ? array_map('strval', $raw) : [(string) $raw];
        return array_values(array_intersect($allowed, $raw));
    }

    /* ---- Création finale ------------------------------------------- */

    private function finalize(Request $request, array $user, array $step3): void
    {
        $draft = $_SESSION[self::DRAFT] ?? [];
        $s1 = $draft['step1'] ?? null;
        $s2 = $draft['step2'] ?? null;
        if ($s1 === null || $s2 === null) {
            redirect('/boutique/creer?etape=1');
        }
        $userId = (int) $user['id'];

        // Re-contrôle slug (a pu être pris entre deux étapes).
        if (!Boutique::slugAvailable($s1['slug'], $userId)) {
            $s1['slug'] = Boutique::uniqueSlug($s1['slug'], $userId);
        }

        // Vérité serveur sur le logo et les bannières (existent sur notre compte).
        $logo    = $this->verifiedImage($s1['logo_public_id'] ?? null);
        $banners = $this->verifiedBanners($s1['banner_ids'] ?? [], []);
        $s2      = $this->verifiedGeo($s2, null);

        $publicId = Boutique::create($userId, [
            'slug'             => $s1['slug'],
            'name'             => $s1['name'],
            'tagline'          => $s1['tagline'],
            'description'      => $s1['description'],
            'category'         => $s1['category'],
            'logo_public_id'   => $logo,
            'banners'          => $banners,
            'currency'         => $s2['currency'],
            'shop_type'        => $s2['shop_type'] ?? 'online',
            'address'          => $s2['address'] ?? null,
            'city'             => $s2['city'] ?? null,
            'country_code'     => $s2['country_code'] ?? null,
            'continent'        => $s2['continent'] ?? null,
            'geo_lat'          => $s2['geo_lat'] ?? null,
            'geo_lng'          => $s2['geo_lng'] ?? null,
            'delivery_zones'   => $s2['delivery_zones'],
            'delivery_methods' => $s2['delivery_methods'],
            'free_ship_cents'  => $s2['free_ship_cents'],
            'delivery_fee_cents'  => $s2['delivery_fee_cents'] ?? null,
            'delivery_intl_cents' => $s2['delivery_intl_cents'] ?? null,
            'delivery_delay'      => $s2['delivery_delay'] ?? null,
            'prep_time'        => $s2['prep_time'],
            'cod_enabled'      => $step3['cod_enabled'],
            'payment_terms'    => $step3['payment_terms'] ?? [],
            'payment_methods'  => $step3['payment_methods'] ?? [],
            'payment_provider' => $step3['payment_provider'] ?? null,
            'contacts'         => $s1['contacts'] ?? [],
            'contact_primary'  => $s1['contact_primary'] ?? '',
        ]);

        AuditLog::record($userId, 'shop.created', 'boutique', null, ['public_id' => $publicId], $request->ipBinary());
        try {
            if (\App\Services\StorefrontAlert::send($user, 'boutique', (string) $s1['name'], url('/boutique/gerer'))) {
                flash('info', t('vitrine.mail_sent_flash'));
            }
        } catch (\Throwable) {
            // l'e-mail d'alerte ne doit jamais empêcher la création
        }
        unset($_SESSION[self::DRAFT]);
        clear_old();
        flash('success', t('shop.created_flash'));
        redirect('/boutique/gerer');
    }

    /* ---- Helpers ---------------------------------------------------- */

    private function verifiedImage(?string $publicId): ?string
    {
        if ($publicId === null || $publicId === '' || !CloudinaryService::configured()) {
            return null;
        }
        return CloudinaryService::verifyAsset('image', $publicId) !== null ? $publicId : null;
    }

    /**
     * Vérifie une liste de bannières. Une image déjà connue (présente dans
     * $existing) est gardée sans re-vérifier ; une nouvelle est vérifiée auprès
     * de Cloudinary. @param list<string> $ids @param list<string> $existing
     * @return list<string>
     */
    private function verifiedBanners(array $ids, array $existing): array
    {
        $out = [];
        foreach (array_slice($ids, 0, (int) config('shop.banner_max', 10)) as $id) {
            if (!is_string($id) || $id === '') { continue; }
            if (in_array($id, $existing, true) || $this->verifiedImage($id) !== null) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** À l'édition : conserve l'image existante si inchangée ou si la vérif échoue. */
    private function resolveImage(?string $submitted, ?string $existing): ?string
    {
        if ($submitted === null || $submitted === '') {
            return null;
        }
        if ($submitted === $existing) {
            return $existing;
        }
        return $this->verifiedImage($submitted) ?? $existing;
    }

    /** Page « Statistiques » : vues de la vitrine et des produits. */
    public function stats(Request $request): void
    {
        $user     = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        $products = \App\Models\Product::forBoutique((int) $boutique['id']);
        $names = [];
        foreach ($products as $p) {
            $names[(int) $p['id']] = (string) $p['name'];
        }
        view('boutique/stats', [
            'active'     => 'vitrines',
            'boutique'   => $boutique,
            'totals'     => ShopView::totals((int) $boutique['id']),
            'daily'      => ShopView::daily((int) $boutique['id'], 14),
            'by_product' => ShopView::byProduct((int) $boutique['id']),
            'names'      => $names,
        ] + SellerController::commonData($user));
    }

    /**
     * Compte une vue publique (vitrine si $productId = 0) : jamais le
     * propriétaire, jamais les robots, une seule vue par visiteur et par
     * jour pour une même page (session).
     */
    private function countView(array $boutique, bool $isOwner, int $productId = 0): void
    {
        if ($isOwner || ($boutique['status'] ?? '') !== 'published') {
            return;
        }
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|httpclient/i', $ua)) {
            return;
        }
        $key = $boutique['id'] . ':' . $productId . ':' . date('Ymd');
        $seen = (array) ($_SESSION['shop_seen'] ?? []);
        if (isset($seen[$key])) {
            return;
        }
        if (count($seen) > 200) {
            $seen = []; // borne la taille de la session
        }
        $seen[$key] = 1;
        $_SESSION['shop_seen'] = $seen;
        ShopView::record((int) $boutique['id'], $productId);
    }

    /**
     * Verrou serveur de la localisation : si des coordonnées GPS sont
     * fournies, ville/pays/continent sont recalculés DEPUIS les coordonnées
     * (les valeurs saisies ne font pas foi). Si la position n'a pas changé,
     * on réutilise la localisation déjà vérifiée sans appel réseau.
     */
    private function verifiedGeo(array $d2, ?array $existing): array
    {
        $lat = $d2['geo_lat'];
        $lng = $d2['geo_lng'];
        if ($lat === null || $lng === null) {
            return $d2; // pas de position : saisie manuelle assumée
        }
        if ($existing !== null
            && $existing['geo_lat'] !== null && $existing['geo_lng'] !== null
            && abs((float) $existing['geo_lat'] - $lat) < 0.0000005
            && abs((float) $existing['geo_lng'] - $lng) < 0.0000005
            && !empty($existing['country_code'])) {
            $d2['city']         = $existing['city'];
            $d2['country_code'] = $existing['country_code'];
            $d2['continent']    = $existing['continent'];
            return $d2;
        }
        $geo = \App\Services\GeoService::reverse($lat, $lng, current_locale());
        if ($geo !== null && $geo['country_code'] !== null) {
            $d2['city']         = $geo['city'] !== null ? mb_substr($geo['city'], 0, 80) : $d2['city'];
            $d2['country_code'] = $geo['country_code'];
            $d2['continent']    = $geo['continent'];
        }
        return $d2;
    }

    /** Le vendeur a-t-il terminé la vérification d'identité (KYC niveau 3) ? */
    private function sellerVerified(int $userId): bool
    {
        return (ProProfile::findByUserId($userId)['verification_status'] ?? '') === 'verified';
    }

    /** Description pour l'aperçu de partage : une ligne, 160 caractères max. */
    private function ogDescription(string $raw): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if ($clean === '' || $clean === '—') {
            return null;
        }
        return mb_strlen($clean) > 160 ? mb_substr($clean, 0, 157) . '…' : $clean;
    }

    private function sellerOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return $user;
    }

    private function clampStep(int $step): int
    {
        $draft = $_SESSION[self::DRAFT] ?? [];
        $reach = 1;
        if (isset($draft['step1'])) { $reach = 2; }
        if (isset($draft['step1'], $draft['step2'])) { $reach = 3; }
        return max(1, min($step, $reach));
    }
}
