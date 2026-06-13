<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\GeoService;
use App\Services\OrderNotifier;

/**
 * Verticale Restaurant — création de la vitrine, gestion de la carte
 * (catégories + plats) et page publique du menu. Le panier → commande →
 * paiement réutilise les modules existants (chantier suivant).
 */
final class RestaurantController
{
    /* ---- Création -------------------------------------------------- */

    public function create(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        if (Restaurant::findByUserId((int) $user['id']) !== null) {
            redirect('/restaurant/gerer');
        }
        view('restaurant/create', [
            'active' => 'vitrines',
            'user' => $user,
            'suggestSlug' => Restaurant::uniqueSlug((string) ($user['full_name'] ?? 'resto')),
        ] + SellerController::commonData($user));
    }

    public function store(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        if (Restaurant::findByUserId((int) $user['id']) !== null) {
            redirect('/restaurant/gerer');
        }
        [$data, $errors] = $this->validate((int) $user['id']);
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/restaurant/creer');
        }
        $publicId = Restaurant::create((int) $user['id'], $data);
        // Catégories de carte par défaut, pour démarrer.
        $resto = Restaurant::findBySlug($data['slug']);
        if ($resto !== null) {
            foreach ((array) config('restaurant.default_categories', []) as $key) {
                MenuItem::createCategory((int) $resto['id'], t('resto.cat.' . $key), $key === 'boissons' ? 'drink' : 'dish');
            }
        }
        AuditLog::record((int) $user['id'], 'restaurant.created', 'restaurant', null, ['public_id' => $publicId], $request->ipBinary());
        try {
            if (\App\Services\StorefrontAlert::send($user, 'restaurant', $data['name'], url('/restaurant/gerer'))) {
                flash('info', t('vitrine.mail_sent_flash'));
            }
        } catch (\Throwable) {
            // l'e-mail d'alerte ne doit jamais empêcher la création
        }
        flash('success', t('resto.created_flash'));
        redirect('/restaurant/gerer');
    }

    /* ---- Gestion --------------------------------------------------- */

    public function manage(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $resto = Restaurant::findByUserId((int) $user['id']);
        if ($resto === null) {
            redirect('/restaurant/creer');
        }
        view('restaurant/manage', [
            'active' => 'vitrines',
            'resto' => $resto,
            'categories' => MenuItem::categories((int) $resto['id']),
            'items' => MenuItem::forRestaurant((int) $resto['id']),
            'counts' => MenuItem::countFor((int) $resto['id']),
            'precat' => (string) input_string('cat', ''), // « + Plat » depuis une catégorie
        ] + SellerController::commonData($user));
    }

    public function publish(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $resto = Restaurant::findByUserId((int) $user['id']);
        if ($resto === null) { redirect('/restaurant/creer'); }
        $action = whitelist((string) input_string('action', ''), ['publish', 'unpublish'], null);
        if ($action === null) { abort(404); }
        Restaurant::setStatus((int) $resto['id'], $action === 'publish' ? 'published' : 'draft');
        flash('success', t($action === 'publish' ? 'resto.published_flash' : 'resto.unpublished_flash'));
        redirect('/restaurant/gerer');
    }

    /* ---- Carte : catégories --------------------------------------- */

    public function storeCategory(Request $request): void
    {
        $resto = $this->ownRestaurant();
        $std = (array) config('restaurant.standard_categories', []);
        $choice = whitelist((string) input_string('choice', ''), array_merge(array_keys($std), ['autre']), 'autre');

        if ($choice !== 'autre') {
            // Catégorie standard sélectionnée dans le déroulant : nom traduit + type déduit.
            $name = t('resto.cat.' . $choice);
            $kind = (string) $std[$choice];
        } else {
            // « Autre… » : nom libre + type choisi (sinon auto-détection boisson).
            $name = trim((string) input_string('name', ''));
            $kind = whitelist((string) input_string('kind', ''), config('restaurant.category_kinds', []), null)
                ?? (preg_match('/boisson|drink|breuvage|jus/i', $name) ? 'drink' : 'dish');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('error', t('resto.cat_invalid'));
        } elseif (MenuItem::categoryNameExists((int) $resto['id'], $name)) {
            flash('error', t('resto.cat_exists', ['name' => $name]));
        } else {
            MenuItem::createCategory((int) $resto['id'], mb_substr($name, 0, 60), $kind);
            flash('success', t('resto.cat_added'));
        }
        redirect('/restaurant/gerer');
    }

    /** Renomme une catégorie déjà créée (depuis la carte). */
    public function renameCategory(Request $request): void
    {
        $resto = $this->ownRestaurant();
        $cat = MenuItem::findCategory((string) $request->param('cid', ''));
        if ($cat === null || (int) $cat['restaurant_id'] !== (int) $resto['id']) {
            abort(404);
        }
        $name = trim((string) input_string('name', ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('error', t('resto.cat_invalid'));
        } elseif (MenuItem::categoryNameExists((int) $resto['id'], $name, (int) $cat['id'])) {
            flash('error', t('resto.cat_exists', ['name' => $name]));
        } elseif ($name !== (string) $cat['name']) {
            MenuItem::renameCategory((int) $cat['id'], $name);
            flash('success', t('resto.cat_renamed'));
        }
        redirect('/restaurant/gerer');
    }

    public function deleteCategory(Request $request): void
    {
        $resto = $this->ownRestaurant();
        $cat = MenuItem::findCategory((string) $request->param('cid', ''));
        if ($cat === null || (int) $cat['restaurant_id'] !== (int) $resto['id']) { abort(404); }
        MenuItem::deleteCategory((int) $cat['id']);
        flash('success', t('resto.cat_deleted'));
        redirect('/restaurant/gerer');
    }

    /* ---- Carte : plats -------------------------------------------- */

    public function storeItem(Request $request): void
    {
        $resto = $this->ownRestaurant();
        [$data, $errors] = $this->validateItem($resto);
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/restaurant/gerer');
        }
        MenuItem::createItem($data);
        flash('success', t('resto.item_added'));
        redirect('/restaurant/gerer');
    }

    public function setItemStatus(Request $request): void
    {
        $resto = $this->ownRestaurant();
        $item = MenuItem::findItem((string) $request->param('mid', ''));
        if ($item === null || (int) $item['restaurant_id'] !== (int) $resto['id']) { abort(404); }
        $action = whitelist((string) input_string('action', ''), ['available', 'unavailable', 'delete'], null);
        if ($action === 'delete') {
            MenuItem::deleteItem((int) $item['id']);
            flash('success', t('resto.item_deleted'));
        } elseif ($action !== null) {
            MenuItem::setAvailable((int) $item['id'], $action === 'available');
            flash('success', t('resto.item_updated'));
        }
        redirect('/restaurant/gerer');
    }

    /** Marque une contenance de boisson épuisée / de retour. */
    public function setVariantStatus(Request $request): void
    {
        $resto = $this->ownRestaurant();
        $item = MenuItem::findItem((string) $request->param('mid', ''));
        if ($item === null || (int) $item['restaurant_id'] !== (int) $resto['id']) {
            abort(404);
        }
        $vol = (string) input_string('vol', '');
        $action = whitelist((string) input_string('action', ''), ['out', 'in'], null);
        if ($action === null || !MenuItem::setVariantOut((int) $item['id'], $vol, $action === 'out')) {
            flash('error', t('resto.size_unknown'));
        } else {
            flash('success', t($action === 'out' ? 'resto.size_marked_out' : 'resto.size_marked_in', ['vol' => $vol]));
        }
        redirect('/restaurant/gerer');
    }

    /* ---- Commande (panier public) --------------------------------- */

    /** Le client envoie son panier : on re-valide chaque ligne côté serveur. */
    public function checkout(Request $request): void
    {
        $resto = Restaurant::findBySlug((string) $request->param('slug', ''));
        if ($resto === null || $resto['status'] !== 'published') {
            abort(404);
        }
        $cur = (string) $resto['currency'];

        // Panier reçu (JSON) : [{id, size?, qty}] — prix/dispo IGNORÉS du client.
        $raw = json_decode((string) ($_POST['cart_json'] ?? '[]'), true);
        $lines = [];
        foreach (is_array($raw) ? $raw : [] as $entry) {
            $item = MenuItem::findItem((string) ($entry['id'] ?? ''));
            if ($item === null || (int) $item['restaurant_id'] !== (int) $resto['id'] || (int) $item['is_available'] !== 1) {
                continue;
            }
            $qty = max(1, min(99, (int) ($entry['qty'] ?? 0)));
            $size = isset($entry['size']) ? (string) $entry['size'] : '';
            if ($size !== '') {
                // Contenance de boisson : doit exister et ne pas être épuisée.
                $variant = null;
                foreach (MenuItem::variants($item['variants'] ?? null) as $vr) {
                    if ($vr['v'] === $size && !$vr['out']) { $variant = $vr; break; }
                }
                if ($variant === null) { continue; }
                $lines[] = [
                    'title' => (string) $item['name'] . ' — ' . rtrim(rtrim($size, '0'), '.') . ' L',
                    'qty' => $qty, 'unit_price_cents' => (int) $variant['p'],
                ];
            } else {
                $lines[] = [
                    'title' => (string) $item['name'],
                    'qty' => $qty, 'unit_price_cents' => (int) $item['price_cents'],
                ];
            }
        }

        $name = trim((string) input_string('client_name', ''));
        $phone = trim((string) input_string('client_phone', ''));
        $service = whitelist((string) input_string('service', ''), RestaurantOrder::SERVICES, 'takeaway');
        $errors = [];
        if ($lines === []) { $errors['cart'] = t('rorder.empty'); }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) { $errors['client_name'] = t('order.err_client'); }
        if ($phone !== '' && !preg_match('/^\+?[0-9 .\-]{6,22}$/', $phone)) { $errors['client_phone'] = t('order.err_phone'); }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/restaurant/' . $resto['slug'] . '#commander');
        }

        $publicId = RestaurantOrder::create([
            'restaurant_id' => (int) $resto['id'],
            'seller_id' => (int) $resto['user_id'],
            'client_name' => $name,
            'client_phone' => $phone !== '' ? $phone : null,
            'service' => $service,
            'note' => mb_substr((string) input_string('note', ''), 0, 500) ?: null,
            'currency' => $cur,
        ], $lines);

        AuditLog::record((int) $resto['user_id'], 'rorder.placed', 'restaurant', (int) $resto['id'], ['order' => $publicId], $request->ipBinary());

        // Prévient le restaurateur (e-mail + SMS/WhatsApp). Best-effort.
        try {
            $total = 0;
            $notifyLines = [];
            foreach ($lines as $l) {
                $total += $l['qty'] * $l['unit_price_cents'];
                $notifyLines[] = ['qty' => $l['qty'], 'title' => $l['title'], 'line_total_cents' => $l['qty'] * $l['unit_price_cents']];
            }
            OrderNotifier::sellerNewOrder(
                User::findById((int) $resto['user_id']) ?? [],
                (string) $resto['name'],
                strtoupper(substr($publicId, 0, 6)),
                $notifyLines,
                $total,
                $cur,
                $name,
                $phone,
                url('/restaurant/commandes'),
            );
        } catch (\Throwable) {
            // notification best-effort
        }

        clear_old();
        redirect('/restaurant/commande/' . $publicId);
    }

    /** Confirmation client : récapitulatif + envoi au restaurant via WhatsApp. */
    public function orderConfirmation(Request $request): void
    {
        $order = RestaurantOrder::findByPublicId((string) $request->param('ref', ''));
        if ($order === null) {
            abort(404);
        }
        $restoRow = null;
        try {
            $stmt = db()->prepare('SELECT * FROM restaurants WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $order['restaurant_id']]);
            $restoRow = $stmt->fetch() ?: null;
        } catch (\Throwable) {
        }
        $sellerPhone = '';
        if ($restoRow !== null) {
            $sellerPhone = (string) (User::findById((int) $restoRow['user_id'])['phone'] ?? '');
        }
        view('restaurant/order_confirmation', [
            'order' => $order,
            'items' => RestaurantOrder::items((int) $order['id']),
            'resto' => $restoRow,
            'seller_phone' => $sellerPhone,
            'page_title' => t('rorder.confirm_title'),
        ]);
    }

    /* ---- Commandes côté restaurateur ------------------------------ */

    public function orders(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $resto = Restaurant::findByUserId((int) $user['id']);
        if ($resto === null) {
            redirect('/restaurant/creer');
        }
        $filter = whitelist((string) input_string('filtre', 'new'), array_merge(RestaurantOrder::STATUSES, ['all']), 'new');
        $orders = RestaurantOrder::forRestaurant((int) $resto['id'], $filter === 'all' ? null : $filter);
        $itemsByOrder = [];
        foreach ($orders as $o) {
            $itemsByOrder[(int) $o['id']] = RestaurantOrder::items((int) $o['id']);
        }
        view('restaurant/orders', [
            'active' => 'commandes',
            'resto' => $resto,
            'orders' => $orders,
            'items_by_order' => $itemsByOrder,
            'filter' => $filter,
        ] + SellerController::commonData($user));
    }

    public function setOrderStatus(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $resto = Restaurant::findByUserId((int) $user['id']);
        $order = RestaurantOrder::findByPublicId((string) $request->param('ref', ''));
        if ($resto === null || $order === null || (int) $order['restaurant_id'] !== (int) $resto['id']) {
            abort(404);
        }
        $action = whitelist((string) input_string('action', ''), ['confirm', 'ready', 'deliver', 'cancel'], null);
        if ($action !== null) {
            $to = RestaurantOrder::applyAction((int) $order['id'], (string) $order['status'], $action);
            if ($to !== null) {
                AuditLog::record((int) $user['id'], 'rorder.' . $action, 'rorder', (int) $order['id'], [], $request->ipBinary());
                flash('success', t('rorder.status_flash', ['status' => t('rorder.st.' . $to)]));
            }
        }
        redirect('/restaurant/commandes?filtre=' . whitelist((string) input_string('retour', 'new'), array_merge(RestaurantOrder::STATUSES, ['all']), 'new'));
    }

    /* ---- Page publique -------------------------------------------- */

    public function show(Request $request): void
    {
        $resto = Restaurant::findBySlug((string) $request->param('slug', ''));
        if ($resto === null) { abort(404); }
        $isOwner = (int) $resto['user_id'] === (int) (current_user_id() ?? 0);
        if ($resto['status'] !== 'published' && !$isOwner) { abort(404); }

        $cats = MenuItem::categories((int) $resto['id']);
        $items = MenuItem::forRestaurant((int) $resto['id'], !$isOwner); // public : seulement dispo
        // Groupe les plats par catégorie.
        $byCat = [];
        foreach ($items as $it) { $byCat[(int) $it['category_id']][] = $it; }

        view('restaurant/show', [
            'resto' => $resto,
            'categories' => $cats,
            'by_cat' => $byCat,
            'is_owner' => $isOwner,
            'seller' => User::findById((int) $resto['user_id']) ?? [],
            'page_title' => (string) $resto['name'],
            'meta' => [
                'description' => trim((string) ($resto['tagline'] ?: $resto['description'] ?? '')) ?: null,
                'url' => url('/restaurant/' . $resto['slug']),
            ],
        ]);
    }

    /* ---- Validation ------------------------------------------------ */

    private function validate(int $userId): array
    {
        $errors = [];
        $name = trim((string) input_string('name', ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > (int) config('restaurant.name_max', 80)) {
            $errors['name'] = t('validation.required');
        }
        $slug = slugify((string) (input_string('slug', '') ?: $name));
        if (mb_strlen($slug) < (int) config('restaurant.slug_min', 3) || !Restaurant::slugAvailable($slug, $userId)) {
            $slug = Restaurant::uniqueSlug($name !== '' ? $name : 'resto', $userId);
        }
        $cuisineList = array_values(array_intersect(config('restaurant.cuisines', []), (array) ($_POST['cuisine'] ?? [])));
        $cuisine = $cuisineList !== [] ? implode(',', array_slice($cuisineList, 0, 6)) : null;
        $cuisineOther = in_array('autre', $cuisineList, true)
            ? (mb_substr(trim((string) input_string('cuisine_other', '')), 0, 60) ?: null)
            : null;
        $currency = input_currency('currency', config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP'])) ?? 'XOF';
        $services = array_values(array_intersect(config('restaurant.services', []), (array) ($_POST['services'] ?? [])));

        // Localisation (réutilise la détection ; continent recalculé serveur).
        $city = trim((string) input_string('city', '')) ?: null;
        $cc = whitelist(strtoupper((string) input_string('country_code', '')), array_keys(config('countries', [])), null);
        $lat = filter_var((string) input_string('geo_lat', ''), FILTER_VALIDATE_FLOAT);
        $lng = filter_var((string) input_string('geo_lng', ''), FILTER_VALIDATE_FLOAT);
        $hasCoords = $lat !== false && $lng !== false && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        $wa = preg_replace('/[^0-9+]/', '', (string) input_string('contact_whatsapp', '')) ?: null;
        $phone = preg_replace('/[^0-9+]/', '', (string) input_string('contact_phone', '')) ?: null;

        // Horaires structurés : jours cochés + heures HH:MM ; on garde aussi
        // une étiquette lisible (« Lun–Sam · 11:00–23:00 ») dans hours.
        $openDays = array_values(array_intersect(config('restaurant.days', []), (array) ($_POST['open_days'] ?? [])));
        $timeOk = static fn (string $v): ?string => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
        $openTime = $timeOk((string) input_string('open_time', ''));
        $closeTime = $timeOk((string) input_string('close_time', ''));
        $openDaysCsv = $openDays !== [] ? implode(',', $openDays) : null;

        return [[
            'slug' => $slug, 'name' => mb_substr($name, 0, 80),
            'tagline' => (string) input_string('tagline', '') ?: null,
            'description' => (string) input_string('description', '') ?: null,
            'cuisine' => $cuisine, 'cuisine_other' => $cuisineOther, 'currency' => $currency,
            'services' => $services !== [] ? implode(',', $services) : null,
            'hours' => resto_hours_label($openDaysCsv, $openTime, $closeTime) ?: null,
            'open_days' => $openDaysCsv, 'open_time' => $openTime, 'close_time' => $closeTime,
            'address' => mb_substr((string) input_string('address', ''), 0, 220) ?: null,
            'city' => $city, 'country_code' => $cc, 'continent' => GeoService::continentOf($cc),
            'geo_lat' => $hasCoords ? round($lat, 6) : null, 'geo_lng' => $hasCoords ? round($lng, 6) : null,
            'delivery_fee_cents' => parse_price_to_cents((string) input_string('delivery_fee', ''), $currency),
            'delivery_min_cents' => parse_price_to_cents((string) input_string('delivery_min', ''), $currency),
            'prep_minutes' => (int) input_string('prep_minutes', '0') ?: null,
            'contact_whatsapp' => $wa, 'contact_phone' => $phone,
        ], $errors];
    }

    private function validateItem(array $resto): array
    {
        $errors = [];
        $cur = (string) $resto['currency'];
        $name = trim((string) input_string('name', ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > (int) config('restaurant.item_name_max', 80)) {
            $errors['item_name'] = t('validation.required');
        }
        $cat = MenuItem::findCategory((string) input_string('category', ''));
        if ($cat === null || (int) $cat['restaurant_id'] !== (int) $resto['id']) {
            $errors['category'] = t('validation.required');
        }
        $kind = (string) ($cat['kind'] ?? 'dish');

        $price = 0;
        $variants = null;
        $diets = [];

        if ($kind === 'drink') {
            // Boisson : contenances cochées, un prix par contenance ; le prix de
            // base (price_cents) = la moins chère, pour l'affichage et le tri.
            $checked = (array) ($_POST['vol'] ?? []);
            $prices  = (array) ($_POST['vol_price'] ?? []);
            $rows = [];
            $min = null;
            foreach ((array) config('restaurant.drink_volumes', []) as $v) {
                if (!in_array($v, $checked, true)) { continue; }
                $p = parse_price_to_cents((string) ($prices[$v] ?? ''), $cur);
                if ($p === null) { continue; }
                $rows[] = ['v' => $v, 'p' => $p];
                $min = $min === null ? $p : min($min, $p);
            }
            if ($rows === []) {
                $errors['item_price'] = t('resto.drink_need_volume');
            } else {
                $price = $min ?? 0;
                $variants = json_encode($rows, JSON_UNESCAPED_UNICODE);
            }
        } else {
            $p = parse_price_to_cents((string) input_string('price', ''), $cur);
            if ($p === null) {
                $errors['item_price'] = t('validation.price_invalid');
            } else {
                $price = $p;
            }
            $diets = array_values(array_intersect(config('restaurant.diets', []), (array) ($_POST['diets'] ?? [])));
        }

        return [[
            'restaurant_id' => (int) $resto['id'],
            'category_id' => $cat['id'] ?? 0,
            'name' => mb_substr($name, 0, 80),
            'description' => mb_substr((string) input_string('description', ''), 0, 400) ?: null,
            'price_cents' => $price,
            'variants' => $variants,
            'diets' => $diets !== [] ? implode(',', $diets) : null,
            'is_available' => (string) input_string('is_available', '1') === '1',
        ], $errors];
    }

    private function ownRestaurant(): array
    {
        $user = $this->sellerOrRedirect();
        $resto = Restaurant::findByUserId((int) $user['id']);
        if ($resto === null) { redirect('/restaurant/creer'); }
        return $resto;
    }

    private function sellerOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return $user;
    }
}
