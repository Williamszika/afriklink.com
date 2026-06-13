<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\ProProfile;
use App\Models\ShopView;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;
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
        view('boutique/manage', [
            'active'   => 'vitrines',
            'boutique' => $boutique,
            'products' => $products,
            'filter'   => $filter,
            'mains'    => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'counts'   => \App\Models\Product::countFor((int) $boutique['id']),
            'orders_pending' => \App\Models\Order::countFor((int) $boutique['id'])['new'],
            'views_total'    => ShopView::totals((int) $boutique['id'])['total'],
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
            'cod_enabled' => $d3['cod_enabled'],
            'payment_terms' => $d3['payment_terms'] ?? [], 'payment_methods' => $d3['payment_methods'] ?? [],
            'payment_provider' => $d3['payment_provider'] ?? null,
            'contacts' => $d1['contacts'] ?? [], 'contact_primary' => $d1['contact_primary'] ?? '',
        ]);
        AuditLog::record((int) $user['id'], 'shop.updated', 'boutique', (int) $boutique['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('shop.updated_flash'));
        redirect('/boutique/gerer');
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
        $banners  = Boutique::banners((int) $boutique['id']);
        $ogImage  = $banners[0] ?? ($boutique['logo_public_id'] ?? null);
        view('boutique/show', [
            'boutique' => $boutique,
            'banners'  => $banners,
            'seller'   => User::findById((int) $boutique['user_id']) ?? [],
            'seller_verified' => $this->sellerVerified((int) $boutique['user_id']),
            'is_owner' => $isOwner,
            'products' => $products,
            'mains'    => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
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
        $photos = \App\Models\Product::photos((int) $product['id']);
        $main   = $photos[0]['cloud_public_id'] ?? null;
        view('boutique/product', [
            'boutique' => $boutique,
            'product'  => $product,
            'photos'   => $photos,
            'seller'   => User::findById((int) $boutique['user_id']) ?? [],
            'seller_verified' => $this->sellerVerified((int) $boutique['user_id']),
            'is_owner' => $isOwner,
            'page_title' => (string) $product['name'],
            'meta' => [
                'description' => $this->ogDescription(
                    format_price((int) $product['price_cents'], (string) $boutique['currency'])
                    . ' — ' . ($product['description'] ?: $boutique['name'])
                ),
                'image' => $main !== null ? CloudinaryService::imageUrl((string) $main, 1200, 630) : null,
                'url'   => url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']),
            ],
        ]);
    }

    /* ---- Commande en ligne (panier public) ------------------------- */

    /**
     * Le client envoie son panier : on re-valide CHAQUE ligne côté serveur
     * (produit de la boutique, en ligne, en stock). Prix et disponibilité ne
     * sont jamais lus depuis le client.
     */
    public function checkout(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || $boutique['status'] !== 'published') {
            abort(404);
        }
        $cur = (string) $boutique['currency'];

        $raw = json_decode((string) ($_POST['cart_json'] ?? '[]'), true);
        $lines = [];
        foreach (is_array($raw) ? $raw : [] as $entry) {
            $product = \App\Models\Product::findByPublicId((string) ($entry['id'] ?? ''));
            if ($product === null
                || (int) $product['boutique_id'] !== (int) $boutique['id']
                || $product['status'] !== 'active') {
                continue;
            }
            $qty = max(1, min(99, (int) ($entry['qty'] ?? 0)));
            // Stock : null = illimité ; sinon on plafonne (et on saute si épuisé).
            if ($product['stock'] !== null) {
                $stock = (int) $product['stock'];
                if ($stock <= 0) {
                    continue;
                }
                $qty = min($qty, $stock);
            }
            $lines[] = [
                'product_id'       => (int) $product['id'],
                'title'            => (string) $product['name'],
                'qty'              => $qty,
                'unit_price_cents' => (int) $product['price_cents'],
            ];
        }

        $name = trim((string) input_string('client_name', ''));
        $phone = trim((string) input_string('client_phone', ''));
        $methods = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
        $fulfillment = $methods !== []
            ? whitelist((string) input_string('fulfillment', ''), $methods, $methods[0])
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
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/boutique/' . $boutique['slug'] . '#commander');
        }

        $publicId = \App\Models\Order::createCart([
            'boutique_id'  => (int) $boutique['id'],
            'user_id'      => (int) $boutique['user_id'],
            'client_name'  => $name,
            'client_phone' => $phone !== '' ? $phone : null,
            'note'         => mb_substr((string) input_string('note', ''), 0, 500) ?: null,
            'fulfillment'  => $fulfillment,
            'currency'     => $cur,
        ], $lines);

        AuditLog::record((int) $boutique['user_id'], 'order.placed', 'boutique', (int) $boutique['id'], ['order' => $publicId], $request->ipBinary());
        clear_old();
        redirect('/boutique/commande/' . $publicId);
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
        view('boutique/order_confirmation', [
            'order'        => $order,
            'items'        => \App\Models\Order::items((int) $order['id']),
            'boutique'     => $boutique,
            'seller_phone' => $sellerPhone,
            'page_title'   => t('rorder.confirm_title'),
        ]);
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

        return [[
            'currency' => $currency, 'shop_type' => $shopType, 'address' => $address,
            'city' => $city, 'country_code' => $countryCode, 'continent' => $continent,
            'geo_lat' => $hasCoords ? round($lat, 6) : null,
            'geo_lng' => $hasCoords ? round($lng, 6) : null,
            'delivery_zones' => implode(',', $zones),
            'delivery_methods' => implode(',', $methods), 'prep_time' => $prep,
            'free_ship_cents' => $freeCents,
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
