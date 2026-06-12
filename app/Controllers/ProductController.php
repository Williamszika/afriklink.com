<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Product;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;

/**
 * Catalogue de la boutique — ajout, modification, retrait des produits.
 * Photos envoyées directement à Cloudinary (public, re-vérifiées côté serveur).
 * Tout est cadré sur la boutique du vendeur connecté.
 */
final class ProductController
{
    public function create(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        view('boutique/product_form', ['mode' => 'create', 'boutique' => $b, 'product' => null,
            'photos' => [], 'media_ready' => CloudinaryService::configured()]);
    }

    public function store(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        [$data, $errors, $photos] = $this->validate($b, null);
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/boutique/produits/nouveau');
        }
        $publicId = Product::create((int) $b['id'], (int) $b['user_id'], $data, $photos);
        AuditLog::record((int) current_user_id(), 'product.created', 'product', null, ['public_id' => $publicId], $request->ipBinary());
        clear_old();
        flash('success', t('product.created_flash'));
        redirect('/boutique/gerer');
    }

    public function edit(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        $p = $this->ownProductOr404($request, $b);
        view('boutique/product_form', ['mode' => 'edit', 'boutique' => $b, 'product' => $p,
            'photos' => Product::photos((int) $p['id']), 'media_ready' => CloudinaryService::configured()]);
    }

    public function update(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        $p = $this->ownProductOr404($request, $b);
        [$data, $errors, $photos] = $this->validate($b, $p);
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/boutique/produits/' . $p['public_id'] . '/modifier');
        }
        Product::update((int) $p['id'], $data);
        // On ne remplace les photos que si le formulaire en a renvoyé (sinon on garde).
        if ($photos !== null) {
            Product::setPhotos((int) $p['id'], $photos);
        }
        AuditLog::record((int) current_user_id(), 'product.updated', 'product', (int) $p['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('product.updated_flash'));
        redirect('/boutique/gerer');
    }

    public function setStatus(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        $p = $this->ownProductOr404($request, $b);
        $action = whitelist((string) input_string('action', ''), ['activate', 'hide', 'delete'], null);
        if ($action === null) {
            abort(404);
        }
        if ($action === 'delete') {
            foreach (Product::photos((int) $p['id']) as $ph) {
                CloudinaryService::destroy('image', (string) $ph['cloud_public_id']);
            }
            Product::delete((int) $p['id']);
            flash('success', t('product.deleted_flash'));
        } else {
            Product::setStatus((int) $p['id'], $action === 'activate' ? 'active' : 'hidden');
            flash('success', t('product.status_flash'));
        }
        AuditLog::record((int) current_user_id(), 'product.' . $action, 'product', (int) $p['id'], [], $request->ipBinary());
        redirect('/boutique/gerer');
    }

    /* ---- Helpers ---------------------------------------------------- */

    /**
     * @return array{0:array,1:array<string,string>,2:?list<array>} données, erreurs, photos (null = inchangées)
     */
    private function validate(array $boutique, ?array $product): array
    {
        $errors = [];
        $nameMax = (int) config('shop.product_name_max', 150);
        $name = input_string('name');
        if ($name === null || mb_strlen($name) < 2 || mb_strlen($name) > $nameMax) {
            $errors['name'] = t('validation.product_name', ['max' => $nameMax]);
        }

        $currency = (string) $boutique['currency'];
        $priceCents = parse_price_to_cents((string) input_string('price', ''), $currency);
        if ($priceCents === null || $priceCents < 0 || $priceCents > 99999999900) {
            $errors['price'] = t('validation.price_invalid');
        }

        $stockRaw = trim((string) input_string('stock', ''));
        $stock = null; // null = illimité
        if ($stockRaw !== '') {
            if (!ctype_digit($stockRaw) || (int) $stockRaw > 1000000) {
                $errors['stock'] = t('validation.stock_invalid');
            } else {
                $stock = (int) $stockRaw;
            }
        }

        $descMax = (int) config('shop.product_desc_max', 3000);
        $description = input_string('description');
        if ($description !== null && mb_strlen($description) > $descMax) {
            $errors['description'] = t('validation.too_long', ['max' => $descMax]);
        }

        $status = whitelist((string) input_string('status', 'active'), ['active', 'hidden'], 'active');

        // Vidéo (optionnelle, 2 min max) — vérité serveur sur la durée.
        $videoId  = input_string('video_public_id');
        $videoDur = $product['video_duration'] ?? null;
        if ($videoId !== null && $videoId !== '') {
            if ($product !== null && $videoId === ($product['video_public_id'] ?? null)) {
                // inchangée à l'édition : on garde sans re-vérifier
            } else {
                $meta = CloudinaryService::verifyAsset('video', $videoId);
                $maxV = (int) config('shop.product_max_video_seconds', 120);
                if ($meta === null) {
                    $errors['video'] = t('validation.video_invalid');
                    $videoId = null;
                } elseif (($meta['duration'] ?? 0.0) > $maxV + 1) {
                    CloudinaryService::destroy('video', $videoId);
                    $errors['video'] = t('validation.video_too_long', ['max' => $maxV]);
                    $videoId = null;
                } else {
                    $videoDur = $meta['duration'];
                }
            }
        } else {
            $videoId = null;
            $videoDur = null;
        }

        // Photos : identifiants Cloudinary du JS. À la création, au moins une.
        $maxPhotos = (int) config('shop.product_max_photos', 6);
        $rawPhotos = json_decode((string) ($_POST['photos_json'] ?? '[]'), true);
        $ids = is_array($rawPhotos) ? array_values(array_unique(array_filter($rawPhotos, 'is_string'))) : [];
        $photos = null;
        $touched = ($_POST['photos_touched'] ?? '') === '1';

        if ($product === null || $touched) {
            if (count($ids) < 1 && $product === null) {
                $errors['photos'] = t('validation.photos_required');
            } elseif (count($ids) > $maxPhotos) {
                $errors['photos'] = t('validation.photos_too_many', ['max' => $maxPhotos]);
            } else {
                $photos = [];
                foreach ($ids as $pid) {
                    $meta = CloudinaryService::verifyAsset('image', $pid);
                    if ($meta === null) {
                        $errors['photos'] = t('validation.photos_invalid');
                        break;
                    }
                    $photos[] = ['public_id' => $pid, 'width' => $meta['width'], 'height' => $meta['height']];
                }
            }
        }

        return [[
            'name' => $name, 'description' => $description, 'price_cents' => $priceCents,
            'stock' => $stock, 'status' => $status,
            'video_public_id' => $videoId, 'video_duration' => $videoDur,
        ], $errors, $errors === [] ? $photos : null];
    }

    private function boutiqueOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        $b = Boutique::findByUserId((int) $user['id']);
        if ($b === null) {
            redirect('/boutique/creer');
        }
        return $b;
    }

    private function ownProductOr404(Request $request, array $boutique): array
    {
        $p = Product::findByPublicId((string) $request->param('pid', ''));
        if ($p === null || (int) $p['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        return $p;
    }
}
