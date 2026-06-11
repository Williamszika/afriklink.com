<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;

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
        view('boutique/manage', ['active' => 'vitrines', 'boutique' => $boutique]
            + SellerController::commonData($user));
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
        view('boutique/show', [
            'boutique' => $boutique,
            'seller'   => User::findById((int) $boutique['user_id']) ?? [],
            'is_owner' => $isOwner,
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

        $logo   = input_string('logo_public_id');
        $banner = input_string('banner_public_id');

        $tagline = input_string('tagline');
        if ($tagline !== null && mb_strlen($tagline) > (int) config('shop.tagline_max', 120)) {
            $tagline = mb_substr($tagline, 0, (int) config('shop.tagline_max', 120));
        }
        $description = input_string('description');
        if ($description !== null && mb_strlen($description) > (int) config('shop.desc_max', 1500)) {
            $errors['description'] = t('validation.too_long', ['max' => config('shop.desc_max', 1500)]);
        }
        $category = whitelist((string) input_string('category', ''), config('listings.categories', []), null);

        return [[
            'name' => $name, 'slug' => $slug, 'logo_public_id' => $logo, 'banner_public_id' => $banner,
            'tagline' => $tagline, 'description' => $description, 'category' => $category,
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
            'delivery_zones' => implode(',', $zones),
            'delivery_methods' => implode(',', $methods), 'prep_time' => $prep,
            'free_ship_cents' => $freeCents,
        ], $errors];
    }

    private function validateStep3(): array
    {
        return [['cod_enabled' => (string) ($_POST['cod_enabled'] ?? '1') === '1'], []];
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

        // Vérité serveur sur le logo/bannière (s'ils existent sur notre compte).
        $logo   = $this->verifiedImage($s1['logo_public_id'] ?? null);
        $banner = $this->verifiedImage($s1['banner_public_id'] ?? null);

        $publicId = Boutique::create($userId, [
            'slug'             => $s1['slug'],
            'name'             => $s1['name'],
            'tagline'          => $s1['tagline'],
            'description'      => $s1['description'],
            'category'         => $s1['category'],
            'logo_public_id'   => $logo,
            'banner_public_id' => $banner,
            'currency'         => $s2['currency'],
            'shop_type'        => $s2['shop_type'] ?? 'online',
            'address'          => $s2['address'] ?? null,
            'delivery_zones'   => $s2['delivery_zones'],
            'delivery_methods' => $s2['delivery_methods'],
            'free_ship_cents'  => $s2['free_ship_cents'],
            'prep_time'        => $s2['prep_time'],
            'cod_enabled'      => $step3['cod_enabled'],
        ]);

        AuditLog::record($userId, 'shop.created', 'boutique', null, ['public_id' => $publicId], $request->ipBinary());
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
