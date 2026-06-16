<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;
use App\Services\MailService;
use App\Services\Notifier;

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
            'photos' => [], 'collections' => Product::collectionsFor((int) $b['id'], false),
            'media_ready' => CloudinaryService::configured()]);
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
        $created = Product::findByPublicId($publicId);
        if ($created !== null) {
            $stock = $this->syncVariants((int) $created['id'], $b, $data['stock'], (int) $data['price_cents']);
            Product::setStock((int) $created['id'], $stock);
            Product::setCollection((int) $created['id'], $data['collection'] ?? null);
        }
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
            'photos' => Product::photos((int) $p['id']), 'variants' => ProductVariant::forProduct((int) $p['id']),
            'collections' => Product::collectionsFor((int) $b['id'], false),
            'media_ready' => CloudinaryService::configured()]);
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
        // Détecte un retour en stock : produit épuisé avant, disponible après.
        $wasOut = $p['stock'] !== null && (int) $p['stock'] <= 0;
        $nowIn  = $data['stock'] === null || (int) $data['stock'] > 0;

        Product::update((int) $p['id'], $data);
        Product::setCollection((int) $p['id'], $data['collection'] ?? null);
        // On ne remplace les photos que si le formulaire en a renvoyé (sinon on garde).
        if ($photos !== null) {
            Product::setPhotos((int) $p['id'], $photos);
        }
        // Variantes : réécrites depuis le formulaire ; products.stock recalé sur leur somme.
        $stock = $this->syncVariants((int) $p['id'], $b, $data['stock'], (int) $data['price_cents']);
        Product::setStock((int) $p['id'], $stock);
        $nowIn = $stock === null || $stock > 0;
        AuditLog::record((int) current_user_id(), 'product.updated', 'product', (int) $p['id'], [], $request->ipBinary());

        if ($wasOut && $nowIn) {
            try {
                $this->notifyRestock($p, $b);
            } catch (\Throwable) {
                // notification best-effort
            }
        }
        clear_old();
        flash('success', t('product.updated_flash'));
        redirect('/boutique/gerer');
    }

    /** Prévient les abonnés « retour en stock » (e-mail + SMS/WhatsApp), puis purge la liste. */
    private function notifyRestock(array $product, array $boutique): void
    {
        $subs = StockAlert::pendingForProduct((int) $product['id']);
        if ($subs === []) {
            return;
        }
        $url = url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']);
        $name = (string) $product['name'];
        $shop = (string) $boutique['name'];
        $subject = t('stock.mail_subject', ['name' => $name]);
        $body = t('stock.mail_body', ['name' => $name, 'shop' => $shop]);
        foreach ($subs as $s) {
            $email = trim((string) ($s['email'] ?? ''));
            if ($email !== '') {
                try {
                    MailService::send(
                        $email,
                        $subject,
                        '<p>' . e($body) . '</p>'
                        . '<p><a href="' . e($url) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">' . e(t('stock.mail_cta')) . '</a></p>'
                        . '<p style="color:#666;font-size:13px">' . e($url) . '</p>',
                        $body . "\n" . $url
                    );
                } catch (\Throwable) {
                }
            }
            $phone = Notifier::normalize((string) ($s['phone'] ?? ''));
            if ($phone !== '') {
                try {
                    Notifier::send($phone, t('stock.sms', ['name' => $name, 'shop' => $shop]) . ' ' . $url);
                } catch (\Throwable) {
                }
            }
        }
        StockAlert::clearForProduct((int) $product['id']);
    }

    public function setStatus(Request $request): void
    {
        $b = $this->boutiqueOrRedirect();
        $p = $this->ownProductOr404($request, $b);
        $action = whitelist((string) input_string('action', ''), ['activate', 'hide', 'delete', 'pin', 'unpin'], null);
        if ($action === null) {
            abort(404);
        }
        if ($action === 'delete') {
            foreach (Product::photos((int) $p['id']) as $ph) {
                CloudinaryService::destroy('image', (string) $ph['cloud_public_id']);
            }
            Product::delete((int) $p['id']);
            flash('success', t('product.deleted_flash'));
        } elseif ($action === 'pin' || $action === 'unpin') {
            Product::setPinned((int) $p['id'], $action === 'pin');
            flash('success', t($action === 'pin' ? 'product.pinned_flash' : 'product.unpinned_flash'));
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

        // Promotion facultative : prix réduit (> 0 et < prix normal) + fin facultative.
        $promoCents = null;
        $promoUntil = null;
        $promoRaw = trim((string) input_string('promo_price', ''));
        if ($promoRaw !== '') {
            $promoCents = parse_price_to_cents($promoRaw, $currency);
            if ($promoCents === null || $promoCents <= 0) {
                $errors['promo_price'] = t('validation.price_invalid');
                $promoCents = null;
            } elseif ($priceCents !== null && $promoCents >= $priceCents) {
                $errors['promo_price'] = t('validation.promo_below_price');
                $promoCents = null;
            }
        }
        if ($promoCents !== null) {
            $promoUntilRaw = trim((string) input_string('promo_until', ''));
            if ($promoUntilRaw !== '') {
                $ts = strtotime($promoUntilRaw . ' 23:59:59');
                if ($ts === false || $ts < time()) {
                    $errors['promo_until'] = t('validation.promo_date_invalid');
                } else {
                    $promoUntil = date('Y-m-d H:i:s', $ts);
                }
            }
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

        // Prêt-à-porter : genre + catégorie ; l'unité de vente découle de la catégorie.
        $audience = apparel_audience_clean(input_string('audience', ''));
        $garment  = apparel_category_clean(input_string('garment_category', ''));
        $saleUnit = apparel_category_unit($garment);

        // Rayon / catégorie (menu déroulant) : valeur choisie, ou saisie libre si « Autre ».
        $collSel    = (string) input_string('collection_select', '');
        $collection = $collSel === '__other__'
            ? mb_substr(trim((string) input_string('collection_other', '')), 0, 60)
            : mb_substr(trim($collSel), 0, 60);

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
            'promo_price_cents' => $promoCents, 'promo_until' => $promoUntil,
            'audience' => $audience !== '' ? $audience : null,
            'garment_category' => $garment !== '' ? $garment : null,
            'sale_unit' => $saleUnit,
            'stock' => $stock, 'status' => $status,
            'video_public_id' => $videoId, 'video_duration' => $videoDur,
            'collection' => $collection,
        ], $errors, $errors === [] ? $photos : null];
    }

    /**
     * Réconcilie les variantes soumises (la liste du formulaire fait foi) et
     * renvoie le stock total à porter sur products.stock (somme ; null si une
     * variante est illimitée). Sans variante saisie : variante par défaut alignée
     * sur le champ stock (le panier en ligne lit toujours products.stock en A1).
     */
    private function syncVariants(int $productId, array $boutique, ?int $fieldStock, int $priceCents): ?int
    {
        $cur    = (string) $boutique['currency'];
        $sizes  = (array) ($_POST['var_size'] ?? []);
        $colors = (array) ($_POST['var_color'] ?? []);
        $prices = (array) ($_POST['var_price'] ?? []);
        $stocks = (array) ($_POST['var_stock'] ?? []);
        $rows = [];
        $seen = [];
        foreach ($sizes as $i => $sz) {
            $size  = mb_substr(trim((string) $sz), 0, 60);
            $color = mb_substr(trim((string) ($colors[$i] ?? '')), 0, 60);
            $stockRaw = trim((string) ($stocks[$i] ?? ''));
            $priceRaw = trim((string) ($prices[$i] ?? ''));
            if ($size === '' && $color === '') {
                continue; // ligne vide
            }
            $key = mb_strtolower($size . '|' . $color);
            if (isset($seen[$key])) {
                continue; // combinaison taille+couleur en double
            }
            $seen[$key] = true;
            $attrs = [];
            if ($size !== '')  { $attrs['size']  = $size; }
            if ($color !== '') { $attrs['color'] = $color; }
            $price = $priceRaw !== '' ? parse_price_to_cents($priceRaw, $cur) : null;
            $rows[] = [
                'attributes' => $attrs,
                'label'      => mb_substr(implode(' · ', array_values($attrs)), 0, 120),
                'stock'      => ($stockRaw !== '' && ctype_digit($stockRaw)) ? (int) $stockRaw : null,
                'price'      => ($price !== null && $price >= 0) ? $price : null,
            ];
        }
        ProductVariant::deleteForProduct($productId);
        if ($rows === []) {
            ProductVariant::ensureDefault($productId, (int) $boutique['id'], $fieldStock, $priceCents);
            return $fieldStock;
        }
        $total = 0;
        $unlimited = false;
        foreach ($rows as $pos => $r) {
            ProductVariant::create($productId, (int) $boutique['id'], [
                'sku'         => ProductVariant::generateSku(),
                'attributes'  => $r['attributes'],
                'label'       => $r['label'] !== '' ? $r['label'] : null,
                'price_cents' => $r['price'],
                'stock'       => $r['stock'],
                'position'    => $pos,
                'is_default'  => $pos === 0,
            ]);
            if ($r['stock'] === null) { $unlimited = true; } else { $total += $r['stock']; }
        }
        return $unlimited ? null : $total;
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
