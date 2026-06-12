<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\GeoService;

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
                MenuItem::createCategory((int) $resto['id'], t('resto.cat.' . $key));
            }
        }
        AuditLog::record((int) $user['id'], 'restaurant.created', 'restaurant', null, ['public_id' => $publicId], $request->ipBinary());
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
        $name = trim((string) input_string('name', ''));
        if (mb_strlen($name) >= 2 && mb_strlen($name) <= 60) {
            MenuItem::createCategory((int) $resto['id'], mb_substr($name, 0, 60));
            flash('success', t('resto.cat_added'));
        } else {
            flash('error', t('resto.cat_invalid'));
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

        return [[
            'slug' => $slug, 'name' => mb_substr($name, 0, 80),
            'tagline' => (string) input_string('tagline', '') ?: null,
            'description' => (string) input_string('description', '') ?: null,
            'cuisine' => $cuisine, 'currency' => $currency,
            'services' => $services !== [] ? implode(',', $services) : null,
            'hours' => mb_substr((string) input_string('hours', ''), 0, 160) ?: null,
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
        $name = trim((string) input_string('name', ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > (int) config('restaurant.item_name_max', 80)) {
            $errors['item_name'] = t('validation.required');
        }
        $cat = MenuItem::findCategory((string) input_string('category', ''));
        if ($cat === null || (int) $cat['restaurant_id'] !== (int) $resto['id']) {
            $errors['category'] = t('validation.required');
        }
        $price = parse_price_to_cents((string) input_string('price', ''), (string) $resto['currency']);
        if ($price === null) {
            $errors['item_price'] = t('validation.price_invalid');
        }
        $diets = array_values(array_intersect(config('restaurant.diets', []), (array) ($_POST['diets'] ?? [])));

        return [[
            'restaurant_id' => (int) $resto['id'],
            'category_id' => $cat['id'] ?? 0,
            'name' => mb_substr($name, 0, 80),
            'description' => mb_substr((string) input_string('description', ''), 0, 400) ?: null,
            'price_cents' => $price ?? 0,
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
