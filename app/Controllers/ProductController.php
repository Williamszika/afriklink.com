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

        // Téléphones / électronique : marque + modèle + état.
        $brand = mb_substr(trim((string) input_string('brand', '')), 0, 60);
        $model = mb_substr(trim((string) input_string('model', '')), 0, 80);
        $itemCondition = phone_condition_clean(input_string('item_condition', ''));

        // Rayon / catégorie : OBLIGATOIRE — pilote l'axe de déclinaison ET le sous-formulaire.
        $collSel    = (string) input_string('collection_select', '');
        $collection = $collSel === '__other__'
            ? mb_substr(trim((string) input_string('collection_other', '')), 0, 60)
            : mb_substr(trim($collSel), 0, 60);
        if ($collection === '') {
            $errors['collection'] = t('validation.collection_required');
        }

        // Beauté & cosmétiques — champs communs.
        $ean         = mb_substr(preg_replace('/[^0-9]/', '', (string) input_string('ean', '')) ?? '', 0, 20);
        $sku         = mb_substr(trim((string) input_string('sku', '')), 0, 40);
        $expiryRaw   = trim((string) input_string('expiry_date', ''));
        $expiryDate  = ($expiryRaw !== '' && strtotime($expiryRaw) !== false) ? date('Y-m-d', (int) strtotime($expiryRaw)) : null;
        $ingredients = mb_substr(trim((string) input_string('ingredients', '')), 0, 2000);

        if (product_vertical((string) ($boutique['category'] ?? '')) === 'phone' && elec_is_rayon($collection)) {
            // Électronique adaptatif (Accessoires / Audio…) : type-driven + specs + compat/état/garantie + axe libre.
            $productType = beauty_clean(input_string('product_type', ''), array_keys(elec_types($collection)));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atouts = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), elec_atouts($collection)));
            $ea = elec_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []));
            $compat = mb_substr(trim((string) input_string('compatibilite', '')), 0, 120);
            if ($compat !== '') { $ea['compatibilite'] = $compat; }
            $cond = beauty_clean(input_string('acc_condition', ''), elec_conditions());
            if ($cond !== '') { $ea['condition'] = $cond; }
            $gar = beauty_clean(input_string('acc_garantie', ''), elec_garanties());
            if ($gar !== '') { $ea['garantie'] = $gar; }
            $caps = keep_in_list((array) ($_POST['capteur'] ?? []), elec_sensors($collection));
            if ($caps !== []) { $ea['capteurs'] = $caps; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (product_vertical((string) ($boutique['category'] ?? '')) === 'phone') {
            // AUTRE — rayon électronique libre/personnalisé (hors rayons répertoriés) : type & compat
            // libres, caractéristiques (libellé→valeur), état/garantie, axe & atouts libres. Tout en JSON.
            $productType = mb_substr(trim((string) input_string('product_type', '')), 0, 60);
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            // Atouts libres (suggérés + personnalisés), nettoyés, max 20.
            $atKeep = [];
            foreach ((array) ($_POST['atouts'] ?? []) as $a) {
                $a = mb_substr(trim((string) $a), 0, 40);
                if ($a !== '' && !in_array($a, $atKeep, true)) { $atKeep[] = $a; }
                if (count($atKeep) >= 20) { break; }
            }
            $atouts = implode(', ', $atKeep);
            // Caractéristiques libres (libellé → valeur), max 20.
            $labels = (array) ($_POST['spec_label'] ?? []);
            $vals   = (array) ($_POST['spec_value'] ?? []);
            $specs = [];
            foreach ($labels as $i => $lb) {
                $lb = mb_substr(trim((string) $lb), 0, 40);
                $vv = mb_substr(trim((string) ($vals[$i] ?? '')), 0, 80);
                if ($lb !== '' && $vv !== '' && !isset($specs[$lb])) { $specs[$lb] = $vv; }
                if (count($specs) >= 20) { break; }
            }
            $ea = [];
            if ($specs !== []) { $ea['specs'] = $specs; }
            $compat = mb_substr(trim((string) input_string('compatibilite', '')), 0, 120);
            if ($compat !== '') { $ea['compatibilite'] = $compat; }
            $cond = beauty_clean(input_string('acc_condition', ''), elec_conditions());
            if ($cond !== '') { $ea['condition'] = $cond; }
            $gar = beauty_clean(input_string('acc_garantie', ''), elec_garanties());
            if ($gar !== '') { $ea['garantie'] = $gar; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (cuisine_capable((string) ($boutique['category'] ?? '')) && cuisine_is_rayon($collection)) {
            // Cuisine (Maison & meubles) : type-driven specs + état ; garantie réservée aux
            // appareils électriques (flag elec). Tout en JSON dans products.attributes.
            $productType = beauty_clean(input_string('product_type', ''), array_keys(cuisine_types($collection)));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atouts = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), cuisine_atouts($collection)));
            $ea = cuisine_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []));
            $cond = beauty_clean(input_string('acc_condition', ''), cuisine_conditions());
            if ($cond !== '') { $ea['condition'] = $cond; }
            $cMeta = cuisine_type_meta($collection, $productType);
            if ($cMeta !== null && !empty($cMeta['elec'])) {
                $gar = beauty_clean(input_string('acc_garantie', ''), cuisine_garanties());
                if ($gar !== '') { $ea['garantie'] = $gar; }
            }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (cuisine_capable((string) ($boutique['category'] ?? '')) && $collection !== '' && !cuisine_is_rayon($collection)) {
            // NOUVEAU RAYON Maison (hors des 6 répertoriés) : type & caractéristiques libres,
            // état, interrupteur « appareil électrique » → garantie. Tout en JSON.
            $productType = mb_substr(trim((string) input_string('product_type', '')), 0, 60);
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            // Atouts libres (suggérés + personnalisés), max 20.
            $atKeep = [];
            foreach ((array) ($_POST['atouts'] ?? []) as $a) {
                $a = mb_substr(trim((string) $a), 0, 40);
                if ($a !== '' && !in_array($a, $atKeep, true)) { $atKeep[] = $a; }
                if (count($atKeep) >= 20) { break; }
            }
            $atouts = implode(', ', $atKeep);
            // Caractéristiques libres (libellé → valeur), max 20.
            $labels = (array) ($_POST['spec_label'] ?? []);
            $vals   = (array) ($_POST['spec_value'] ?? []);
            $specs = [];
            foreach ($labels as $i => $lb) {
                $lb = mb_substr(trim((string) $lb), 0, 40);
                $vv = mb_substr(trim((string) ($vals[$i] ?? '')), 0, 80);
                if ($lb !== '' && $vv !== '' && !isset($specs[$lb])) { $specs[$lb] = $vv; }
                if (count($specs) >= 20) { break; }
            }
            $ea = [];
            if ($specs !== []) { $ea['specs'] = $specs; }
            $cond = beauty_clean(input_string('acc_condition', ''), cuisine_conditions());
            if ($cond !== '') { $ea['condition'] = $cond; }
            if (input_string('elec_on', '') === '1') {
                $ea['elec'] = true;
                $gar = beauty_clean(input_string('acc_garantie', ''), cuisine_garanties());
                if ($gar !== '') { $ea['garantie'] = $gar; }
            }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (alim_capable((string) ($boutique['category'] ?? '')) && alim_is_rayon($collection)) {
            // Alimentation adaptatif (Bio & naturel…) : type-driven specs + conservation,
            // DLC/DDM + date limite, allergènes. Tout en JSON dans products.attributes.
            $productType = beauty_clean(input_string('product_type', ''), array_keys(alim_types($collection)));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atouts = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), alim_atouts($collection)));
            $ea = alim_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []));
            $cons = beauty_clean(input_string('conservation', ''), alim_conservations());
            if ($cons !== '') { $ea['conservation'] = $cons; }
            $dlc = beauty_clean(input_string('dlc_type', ''), alim_dlc_types());
            if ($dlc !== '') { $ea['dlc_type'] = $dlc; }
            $dlRaw = trim((string) input_string('date_limite', ''));
            if ($dlRaw !== '' && strtotime($dlRaw) !== false) { $ea['date_limite'] = date('Y-m-d', (int) strtotime($dlRaw)); }
            $allerg = keep_in_list((array) ($_POST['allergenes'] ?? []), alim_allergenes());
            if ($allerg !== []) { $ea['allergenes'] = $allerg; }
            // Mode alcoolisé : déterminé par le type (config), pas par le POST (anti-triche).
            $aMeta = alim_type_meta($collection, $productType);
            if ($aMeta !== null && !empty($aMeta['alcool'])) { $ea['alcoolise'] = true; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (alim_capable((string) ($boutique['category'] ?? '')) && $collection !== '' && !alim_is_rayon($collection)) {
            // NOUVEAU RAYON Alimentation (hors des rayons répertoriés) : type & caractéristiques
            // libres, conservation, DLC/DDM + date, allergènes, atouts libres. Tout en JSON.
            $productType = mb_substr(trim((string) input_string('product_type', '')), 0, 60);
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            // Atouts libres (suggérés + personnalisés), max 20.
            $atKeep = [];
            foreach ((array) ($_POST['atouts'] ?? []) as $a) {
                $a = mb_substr(trim((string) $a), 0, 40);
                if ($a !== '' && !in_array($a, $atKeep, true)) { $atKeep[] = $a; }
                if (count($atKeep) >= 20) { break; }
            }
            $atouts = implode(', ', $atKeep);
            // Caractéristiques libres (libellé → valeur), max 20.
            $labels = (array) ($_POST['spec_label'] ?? []);
            $vals   = (array) ($_POST['spec_value'] ?? []);
            $specs = [];
            foreach ($labels as $i => $lb) {
                $lb = mb_substr(trim((string) $lb), 0, 40);
                $vv = mb_substr(trim((string) ($vals[$i] ?? '')), 0, 80);
                if ($lb !== '' && $vv !== '' && !isset($specs[$lb])) { $specs[$lb] = $vv; }
                if (count($specs) >= 20) { break; }
            }
            $ea = [];
            if ($specs !== []) { $ea['specs'] = $specs; }
            $cons = beauty_clean(input_string('conservation', ''), alim_conservations());
            if ($cons !== '') { $ea['conservation'] = $cons; }
            $dlc = beauty_clean(input_string('dlc_type', ''), alim_dlc_types());
            if ($dlc !== '') { $ea['dlc_type'] = $dlc; }
            $dlRaw = trim((string) input_string('date_limite', ''));
            if ($dlRaw !== '' && strtotime($dlRaw) !== false) { $ea['date_limite'] = date('Y-m-d', (int) strtotime($dlRaw)); }
            $allerg = keep_in_list((array) ($_POST['allergenes'] ?? []), alim_allergenes());
            if ($allerg !== []) { $ea['allergenes'] = $allerg; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif (auto_capable((string) ($boutique['category'] ?? '')) && auto_is_rayon($collection)) {
            // Auto & pièces adaptatif (Accessoires…) : type-driven specs (dont garantie),
            // état, et compatibilité véhicule (universel / véhicules). Tout en JSON.
            $productType = beauty_clean(input_string('product_type', ''), array_keys(auto_types($collection)));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atouts = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), auto_atouts($collection)));
            $ea = auto_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []));
            $cond = beauty_clean(input_string('acc_condition', ''), auto_conditions());
            if ($cond !== '') { $ea['condition'] = $cond; }
            if (auto_rayon_is_dimension($collection)) {
                // Pneus : la compatibilité EST la dimension, composée côté serveur depuis les specs
                // validées (anti-triche). + DOT, profondeur de gomme, monte d'origine.
                $dim = auto_tyre_dimension($ea);
                if ($dim !== '') { $ea['dimension'] = $dim; }
                $dotV = mb_substr(trim((string) input_string('dot', '')), 0, 20);
                if ($dotV !== '') { $ea['dot'] = $dotV; }
                $prof = preg_replace('/[^0-9.,]/', '', (string) input_string('profondeur_mm', ''));
                if ($prof !== null && $prof !== '') { $ea['profondeur_mm'] = min(30.0, max(0.0, (float) str_replace(',', '.', $prof))); }
                $monteV = mb_substr(trim((string) input_string('monte', '')), 0, 120);
                if ($monteV !== '') { $ea['monte'] = $monteV; }
            } elseif (input_string('universel', '') === '1') {
                // Compatibilité véhicule : universel (flag) OU liste de véhicules + réf. d'origine (OE/OEM).
                $ea['universel'] = true;
            } else {
                $compat = mb_substr(trim((string) input_string('compatibilite', '')), 0, 300);
                if ($compat !== '') { $ea['compatibilite'] = $compat; }
                $oem = mb_substr(trim((string) input_string('ref_oem', '')), 0, 60);
                if ($oem !== '') { $ea['ref_oem'] = $oem; }
            }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $ea['variant_axis'] = $axis; }
            $attributes = $ea !== [] ? (string) json_encode($ea, JSON_UNESCAPED_UNICODE) : null;
        } elseif ($collection === 'Ongles') {
            // Faux ongles : tout dans attributes (JSON) ; déclinaisons = forme × longueur.
            $productType = beauty_clean(input_string('product_type', ''), beauty_ongles('product_types'));
            $atouts      = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), beauty_ongles('atouts')));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $oa = [];
            $f  = beauty_clean(input_string('ong_forme', ''), beauty_ongles('formes'));
            $l  = beauty_clean(input_string('ong_longueur', ''), beauty_ongles('longueurs'));
            $mt = beauty_clean(input_string('ong_material', ''), beauty_ongles('materials'));
            if ($f !== '')  { $oa['forme'] = $f; }
            if ($l !== '')  { $oa['longueur'] = $l; }
            if ($mt !== '') { $oa['material'] = $mt; }
            foreach (['tips' => 'tips_count', 'sizes' => 'sizes_count', 'wear' => 'wear_days'] as $pf => $pk) {
                $n = preg_replace('/[^0-9]/', '', (string) input_string('ong_' . $pf, ''));
                if ($n !== null && $n !== '') { $oa[$pk] = min(999, (int) $n); }
            }
            foreach (['glue', 'stickers', 'lamp', 'reusable'] as $bk) {
                if ((string) input_string('ong_' . $bk, '') !== '') { $oa[$bk] = true; }
            }
            $dz = keep_in_list((array) ($_POST['ong_design'] ?? []), beauty_ongles('designs'));
            $cz = keep_in_list((array) ($_POST['ong_couleur'] ?? []), array_map(static fn ($c) => (string) $c[0], beauty_ongles('couleurs')));
            $kz = keep_in_list((array) ($_POST['ong_kit'] ?? []), beauty_ongles('kit'));
            if ($dz !== []) { $oa['designs'] = $dz; }
            if ($cz !== []) { $oa['couleurs'] = $cz; }
            if ($kz !== []) { $oa['kit'] = $kz; }
            $attributes = $oa !== [] ? (string) json_encode($oa, JSON_UNESCAPED_UNICODE) : null;
        } elseif ($collection === 'Parfums') {
            // Parfum : concentration (product_type) + specs + pyramide ; déclinaison = contenance.
            $productType = beauty_clean(input_string('product_type', ''), beauty_parfum('concentrations'));
            $atouts      = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), beauty_parfum('atouts')));
            $line = ''; $pao = beauty_clean(input_string('pao', ''), beauty_parfum('pao'));
            $vr = preg_replace('/[^0-9]/', '', (string) input_string('volume', ''));
            $volume = ($vr !== null && $vr !== '') ? min(999999.0, (float) $vr) : null;
            $volumeUnit = 'ml';
            $pa = [];
            foreach (['genre' => 'genres', 'famille' => 'familles', 'format' => 'formats', 'alcool' => 'alcool', 'sillage' => 'sillages', 'tenue' => 'tenues'] as $f => $listKey) {
                $v = beauty_clean(input_string('par_' . $f, ''), beauty_parfum($listKey));
                if ($v !== '') { $pa[$f] = $v; }
            }
            $notes = [];
            foreach (['tete', 'coeur', 'fond'] as $nk) {
                $nv = mb_substr(trim((string) input_string('par_note_' . $nk, '')), 0, 160);
                if ($nv !== '') { $notes[$nk] = $nv; }
            }
            if ($notes !== []) { $pa['notes'] = $notes; }
            $occ = keep_in_list((array) ($_POST['par_occasions'] ?? []), beauty_parfum('occasions'));
            if ($occ !== []) { $pa['occasions'] = $occ; }
            $attributes = $pa !== [] ? (string) json_encode($pa, JSON_UNESCAPED_UNICODE) : null;
        } elseif ($collection === 'Perruque') {
            // Perruque : specs cheveux + champs adaptatifs (humain → qualité/origine ; lace → couleur lace).
            $productType = beauty_clean(input_string('product_type', ''), beauty_perruque('constructions'));
            $atouts      = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), beauty_perruque('atouts')));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $pe = [];
            $hair = beauty_clean(input_string('per_hair_type', ''), beauty_perruque('hair_types'));
            if ($hair !== '') { $pe['hair_type'] = $hair; }
            foreach (['texture' => 'textures', 'densite' => 'densites', 'cap_size' => 'cap_sizes'] as $f => $lk) {
                $v = beauty_clean(input_string('per_' . $f, ''), beauty_perruque($lk));
                if ($v !== '') { $pe[$f] = $v; }
            }
            if ($hair === (string) config('beauty.perruque.human_type', '')) {
                $q = beauty_clean(input_string('per_qualite', ''), beauty_perruque('qualites'));
                $o = beauty_clean(input_string('per_origine', ''), beauty_perruque('origines'));
                if ($q !== '') { $pe['qualite'] = $q; }
                if ($o !== '') { $pe['origine'] = $o; }
            }
            if (in_array($productType, (array) beauty_perruque('lace_types'), true)) {
                $lc = beauty_clean(input_string('per_lace_color', ''), beauty_perruque('lace_colors'));
                if ($lc !== '') { $pe['lace_color'] = $lc; }
            }
            $coul = trim((string) input_string('per_couleur', ''));
            if (in_array($coul, array_map(static fn ($c) => (string) $c[0], beauty_perruque('couleurs')), true)) { $pe['couleur'] = $coul; }
            $ln = preg_replace('/[^0-9]/', '', (string) input_string('per_longueur', ''));
            if ($ln !== null && $ln !== '') { $pe['longueur'] = (int) $ln; }
            $attributes = $pe !== [] ? (string) json_encode($pe, JSON_UNESCAPED_UNICODE) : null;
        } elseif ($collection === 'Soins corps' || $collection === 'Soins visage') {
            // Soins (corps/visage) : caractéristiques propres au type (JSON) + actifs ; déclinaison = contenance.
            $kind = beauty_soins_kind($collection);
            $productType = beauty_clean(input_string('product_type', ''), array_keys(beauty_soins_types($collection)));
            $atouts      = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), beauty_soins($kind, 'atouts')));
            $line = '';
            $volRaw = trim((string) input_string('volume', ''));
            $volume = ($volRaw !== '' && is_numeric(str_replace(',', '.', $volRaw))) ? round((float) str_replace(',', '.', $volRaw), 2) : null;
            if ($volume !== null && ($volume < 0 || $volume > 999999)) { $volume = null; }
            $volumeUnit = beauty_clean(input_string('volume_unit', ''), ['ml', 'g', 'pcs']);
            if ($volumeUnit === '') { $volumeUnit = 'ml'; }
            $pao = beauty_clean(input_string('pao', ''), beauty_soins_pao());
            $sa = beauty_soins_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []), (array) ($_POST['soins_actif'] ?? []));
            $attributes = $sa !== [] ? (string) json_encode($sa, JSON_UNESCAPED_UNICODE) : null;
        } elseif (product_vertical((string) ($boutique['category'] ?? '')) === 'beauty' && $collection !== '' && $collection !== 'Maquillage') {
            // AUTRE — rayon beauté libre/personnalisé : type, caractéristiques (libellé→valeur),
            // axe de déclinaison et atouts TOUS libres (validation par longueur/quantité).
            $productType = mb_substr(trim((string) input_string('product_type', '')), 0, 60);
            $line = '';
            $volRaw = trim((string) input_string('volume', ''));
            $volume = ($volRaw !== '' && is_numeric(str_replace(',', '.', $volRaw))) ? round((float) str_replace(',', '.', $volRaw), 2) : null;
            if ($volume !== null && ($volume < 0 || $volume > 999999)) { $volume = null; }
            $volumeUnit = beauty_clean(input_string('volume_unit', ''), ['ml', 'g', 'pcs']);
            if ($volumeUnit === '') { $volumeUnit = 'ml'; }
            $pao = beauty_clean(input_string('pao', ''), ['6M', '12M', '18M', '24M', '36M']);
            // Atouts libres (suggérés + personnalisés) : nettoyés, max 20.
            $atKeep = [];
            foreach ((array) ($_POST['atouts'] ?? []) as $a) {
                $a = mb_substr(trim((string) $a), 0, 40);
                if ($a !== '' && !in_array($a, $atKeep, true)) { $atKeep[] = $a; }
                if (count($atKeep) >= 20) { break; }
            }
            $atouts = implode(', ', $atKeep);
            // Caractéristiques libres (libellé → valeur), max 20.
            $labels = (array) ($_POST['spec_label'] ?? []);
            $vals   = (array) ($_POST['spec_value'] ?? []);
            $specs = [];
            foreach ($labels as $i => $lb) {
                $lb = mb_substr(trim((string) $lb), 0, 40);
                $vv = mb_substr(trim((string) ($vals[$i] ?? '')), 0, 80);
                if ($lb !== '' && $vv !== '' && !isset($specs[$lb])) { $specs[$lb] = $vv; }
                if (count($specs) >= 20) { break; }
            }
            $aAttr = [];
            if ($specs !== []) { $aAttr['specs'] = $specs; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $aAttr['variant_axis'] = $axis; }
            $attributes = $aAttr !== [] ? (string) json_encode($aAttr, JSON_UNESCAPED_UNICODE) : null;
        } elseif (product_vertical((string) ($boutique['category'] ?? '')) === 'apparel' && apparel_is_rayon($collection)) {
            // Mode adaptatif (Chaussures…) : type-driven + genre/couleur/état + pointures (axe libre).
            $productType = beauty_clean(input_string('product_type', ''), array_keys(apparel_types($collection)));
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atouts = implode(', ', keep_in_list((array) ($_POST['atouts'] ?? []), apparel_rayon_atouts($collection)));
            $aa = apparel_attr_clean($collection, $productType, (array) ($_POST['attr'] ?? []));
            // Genre validé contre les publics DU TYPE (verrou serveur : un soutien-gorge n'accepte
            // que Femme ; un rayon féminin que Femme/Fille/Bébé — même si le POST est trafiqué).
            $genre = beauty_clean(input_string('genre', ''), apparel_type_public($collection, $productType));
            if ($genre !== '') { $aa['genre'] = $genre; }
            $couleur = beauty_clean(input_string('couleur', ''), apparel_rayon_couleurs($collection));
            if ($couleur !== '') { $aa['couleur'] = $couleur; }
            $cond = beauty_clean(input_string('appa_condition', ''), apparel_rayon_conditions($collection));
            if ($cond !== '') { $aa['condition'] = $cond; }
            $rayonPublic = apparel_rayon_public($collection);
            if ($rayonPublic !== '') { $aa['public'] = $rayonPublic; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $aa['variant_axis'] = $axis; }
            $attributes = $aa !== [] ? (string) json_encode($aa, JSON_UNESCAPED_UNICODE) : null;
            // Rattache aux colonnes mode (filtres/affichage) : genre → audience, rayon → garment (kids si enfant).
            $audMap = ['Femme' => 'femme', 'Homme' => 'homme', 'Mixte / unisexe' => 'unisexe', 'Enfant' => 'enfant', 'Fille' => 'enfant', 'Garçon' => 'enfant', 'Bébé' => 'enfant', 'Bébé (fille)' => 'enfant'];
            if (isset($audMap[$genre])) { $audience = $audMap[$genre]; }
            $garmentBase = (string) (((array) config('apparel.rayons', []))[$collection]['garment'] ?? '');
            if ($garmentBase === 'shoes' && in_array($genre, ['Enfant', 'Fille', 'Garçon', 'Bébé'], true)) { $garmentBase = 'shoes_kids'; }
            if ($garmentBase !== '') { $garment = $garmentBase; $saleUnit = apparel_category_unit($garment); }
        } elseif (product_vertical((string) ($boutique['category'] ?? '')) === 'apparel') {
            // NOUVEAU RAYON mode (libre/personnalisé) : type & specs libres, genre/couleur/état,
            // axe & atouts libres. Genre/couleur validés contre la config « autre » du slug.
            $productType = mb_substr(trim((string) input_string('product_type', '')), 0, 60);
            $line = ''; $volume = null; $volumeUnit = 'ml'; $pao = '';
            $atKeep = [];
            foreach ((array) ($_POST['atouts'] ?? []) as $a) {
                $a = mb_substr(trim((string) $a), 0, 40);
                if ($a !== '' && !in_array($a, $atKeep, true)) { $atKeep[] = $a; }
                if (count($atKeep) >= 20) { break; }
            }
            $atouts = implode(', ', $atKeep);
            // Caractéristiques libres (libellé → valeur), max 20.
            $labels = (array) ($_POST['spec_label'] ?? []);
            $vals   = (array) ($_POST['spec_value'] ?? []);
            $aa = [];
            foreach ($labels as $i => $lb) {
                $lb = mb_substr(trim((string) $lb), 0, 40);
                $vv = mb_substr(trim((string) ($vals[$i] ?? '')), 0, 80);
                if ($lb !== '' && $vv !== '' && !isset($aa[$lb]) && !in_array($lb, ['genre', 'couleur', 'condition', 'variant_axis'], true)) { $aa[$lb] = $vv; }
                if (count($aa) >= 20) { break; }
            }
            $genre = beauty_clean(input_string('genre', ''), apparel_autre_genres($collection));
            if ($genre !== '') { $aa['genre'] = $genre; }
            $couleur = beauty_clean(input_string('couleur', ''), apparel_autre('couleurs'));
            if ($couleur !== '') { $aa['couleur'] = $couleur; }
            $cond = beauty_clean(input_string('appa_condition', ''), apparel_conditions());
            if ($cond !== '') { $aa['condition'] = $cond; }
            $axis = mb_substr(trim((string) input_string('variant_axis', '')), 0, 24);
            if ($axis !== '') { $aa['variant_axis'] = $axis; }
            $attributes = $aa !== [] ? (string) json_encode($aa, JSON_UNESCAPED_UNICODE) : null;
            $audMap = ['Femme' => 'femme', 'Homme' => 'homme', 'Mixte / unisexe' => 'unisexe', 'Enfant' => 'enfant', 'Fille' => 'enfant', 'Garçon' => 'enfant', 'Bébé' => 'enfant'];
            if (isset($audMap[$genre])) { $audience = $audMap[$genre]; }
        } else {
            // Maquillage (v2 adaptatif au type) : caractéristiques propres au type en JSON.
            $productType = beauty_clean(input_string('product_type', ''), beauty_product_types());
            $line        = mb_substr(trim((string) input_string('line', '')), 0, 80);
            $volumeRaw   = trim((string) input_string('volume', ''));
            $volume      = ($volumeRaw !== '' && is_numeric(str_replace(',', '.', $volumeRaw)))
                ? round((float) str_replace(',', '.', $volumeRaw), 2) : null;
            if ($volume !== null && ($volume < 0 || $volume > 999999)) { $volume = null; }
            $volumeUnit  = beauty_clean(input_string('volume_unit', ''), beauty_volume_units());
            if ($volumeUnit === '') { $volumeUnit = 'ml'; }
            $pao         = beauty_clean(input_string('pao', ''), beauty_pao());
            $atouts      = beauty_atouts_clean((array) ($_POST['atouts'] ?? []));
            $attrsClean  = beauty_attr_clean($productType, (array) ($_POST['attr'] ?? []));
            $attributes  = $attrsClean !== [] ? (string) json_encode($attrsClean, JSON_UNESCAPED_UNICODE) : null;
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
            'promo_price_cents' => $promoCents, 'promo_until' => $promoUntil,
            'audience' => $audience !== '' ? $audience : null,
            'garment_category' => $garment !== '' ? $garment : null,
            'sale_unit' => $saleUnit,
            'brand' => $brand !== '' ? $brand : null,
            'model' => $model !== '' ? $model : null,
            'item_condition' => $itemCondition !== '' ? $itemCondition : null,
            'product_type' => $productType !== '' ? $productType : null,
            'line' => $line !== '' ? $line : null,
            'volume' => $volume,
            'volume_unit' => $volume !== null ? $volumeUnit : null,
            'pao' => $pao !== '' ? $pao : null,
            'expiry_date' => $expiryDate,
            'ean' => $ean !== '' ? $ean : null,
            'sku' => $sku !== '' ? $sku : null,
            'atouts' => $atouts !== '' ? $atouts : null,
            'ingredients' => $ingredients !== '' ? $ingredients : null,
            'attributes' => $attributes,
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
        $prices = (array) ($_POST['var_price'] ?? []);
        $stocks = (array) ($_POST['var_stock'] ?? []);
        $rows = [];
        $seen = [];
        if (isset($_POST['var_name'])) {
            // Éditeur BEAUTÉ : déclinaison = nom + pastille couleur (hex) + nuance (carnation).
            $names   = (array) $_POST['var_name'];
            $hexes   = (array) ($_POST['var_hex'] ?? []);
            $nuances = (array) ($_POST['var_nuance'] ?? []);
            $allowNuance = beauty_nuances();
            foreach ($names as $i => $nm) {
                $name = mb_substr(trim((string) $nm), 0, 60);
                if ($name === '') { continue; }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) { continue; }
                $seen[$key] = true;
                $attrs = ['size' => $name];
                $hex = trim((string) ($hexes[$i] ?? ''));
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) { $attrs['hex'] = strtoupper($hex); }
                $nz = trim((string) ($nuances[$i] ?? ''));
                if (in_array($nz, $allowNuance, true)) { $attrs['nuance'] = $nz; }
                $stockRaw = trim((string) ($stocks[$i] ?? ''));
                $priceRaw = trim((string) ($prices[$i] ?? ''));
                $price = $priceRaw !== '' ? parse_price_to_cents($priceRaw, $cur) : null;
                $rows[] = [
                    'attributes' => $attrs,
                    'label'      => mb_substr($name . ($nz !== '' ? ' · ' . $nz : ''), 0, 120),
                    'stock'      => ($stockRaw !== '' && ctype_digit($stockRaw)) ? (int) $stockRaw : null,
                    'price'      => ($price !== null && $price >= 0) ? $price : null,
                ];
            }
        } else {
            $sizes  = (array) ($_POST['var_size'] ?? []);
            $colors = (array) ($_POST['var_color'] ?? []);
            $hexes  = (array) ($_POST['var_hex'] ?? []); // perruque : pastille couleur par déclinaison
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
                // hex pris en compte sauf si l'éditeur « autre » a désactivé la couleur.
                $hex = trim((string) ($hexes[$i] ?? ''));
                if (((string) ($_POST['var_has_color'] ?? '1')) !== '0' && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) { $attrs['hex'] = strtoupper($hex); }
                $price = $priceRaw !== '' ? parse_price_to_cents($priceRaw, $cur) : null;
                $rows[] = [
                    'attributes' => $attrs,
                    'label'      => mb_substr(implode(' · ', array_values($attrs)), 0, 120),
                    'stock'      => ($stockRaw !== '' && ctype_digit($stockRaw)) ? (int) $stockRaw : null,
                    'price'      => ($price !== null && $price >= 0) ? $price : null,
                ];
            }
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
