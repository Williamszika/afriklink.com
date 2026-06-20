<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Product;
use App\Request;
use App\Services\Cart;

/**
 * Panier persistant multi-boutiques : /panier (consulter & modifier),
 * /panier/ajouter (synchro depuis le storefront), /panier/{slug}/caisse
 * (passer à la caisse d'une boutique, réutilise le flux existant).
 */
final class CartController
{
    public function index(Request $request): void
    {
        $cart     = Cart::raw();
        $pids     = Cart::allPids();
        $products = $pids !== [] ? Product::onlineByPublicIds($pids) : [];
        $mains    = Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));

        $groups = [];
        foreach ($products as $p) {
            $bid = (int) $p['boutique_id'];
            $pid = (string) $p['public_id'];
            $qty = (int) ($cart[$bid][$pid] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $slug = (string) $p['boutique_slug'];
            if (!isset($groups[$slug])) {
                $groups[$slug] = ['slug' => $slug, 'name' => (string) $p['boutique_name'], 'currency' => (string) $p['currency'], 'lines' => [], 'subtotal' => 0];
            }
            $unit = product_effective_unit_cents($p, (int) $p['price_cents']);
            $line = $unit * $qty;
            $groups[$slug]['subtotal'] += $line;
            $groups[$slug]['lines'][] = ['product' => $p, 'qty' => $qty, 'unit' => $unit, 'main' => $mains[(int) $p['id']] ?? null, 'line_total' => $line];
        }

        view('cart/index', [
            'page_title' => t('cart.title'),
            'groups'     => array_values($groups),
            'count'      => Cart::count(),
        ]);
    }

    /** Aperçu du panier (menu déroulant de l'en-tête) — fragment HTML. */
    public function preview(Request $request): void
    {
        $cart     = Cart::raw();
        $pids     = Cart::allPids();
        $products = $pids !== [] ? Product::onlineByPublicIds($pids) : [];
        $mains    = Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));
        $items = [];
        foreach ($products as $p) {
            $bid = (int) $p['boutique_id'];
            $pid = (string) $p['public_id'];
            $qty = (int) ($cart[$bid][$pid] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $m = $mains[(int) $p['id']] ?? null;
            $items[] = [
                'url'  => url('/boutique/' . $p['boutique_slug'] . '/p/' . $pid),
                'name' => (string) $p['name'],
                'sub'  => $qty . '× ' . format_price(product_effective_unit_cents($p, (int) $p['price_cents']), (string) $p['currency']),
                'main' => $m !== null ? \App\Services\CloudinaryService::imageUrl($m, 80, 80) : null,
            ];
            if (count($items) >= 6) {
                break;
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo render_partial('partials/nav_dropdown', ['items' => $items, 'all_url' => url('/panier'), 'all_label' => t('common.see_all'), 'empty' => t('cart.empty')]);
        exit;
    }

    /** Synchro depuis le storefront (fetch) : fixe la quantité d'un produit. */
    public function add(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) input_string('slug', ''));
        $pid      = (string) input_string('pid', '');
        $product  = Product::findByPublicId($pid);
        $ok = $boutique !== null && ($boutique['status'] ?? '') === 'published'
            && $product !== null && (int) $product['boutique_id'] === (int) $boutique['id'] && ($product['status'] ?? '') === 'active';
        if ($ok) {
            Cart::setQty((int) $boutique['id'], $pid, (int) input_string('qty', '1'));
            self::snapshot();
        }
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_response(['count' => Cart::count(), 'qty' => $ok ? Cart::qty((int) $boutique['id'], $pid) : 0]);
        }
        redirect('/boutique/' . ($boutique['slug'] ?? ''));
    }

    /** Modifie une ligne depuis la page /panier (sans JS). */
    public function update(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) input_string('slug', ''));
        if ($boutique !== null) {
            Cart::setQty((int) $boutique['id'], (string) input_string('pid', ''), (int) input_string('qty', '0'));
            self::snapshot();
        }
        redirect('/panier');
    }

    /**
     * Miroir du panier pour la relance (acheteurs connectés avec e-mail). Les
     * comptes par téléphone (sans e-mail) ne sont pas captés. Best-effort.
     */
    private static function snapshot(): void
    {
        $u = current_user();
        $email = trim((string) ($u['email'] ?? ''));
        if ($u === null || $email === '') {
            return;
        }
        \App\Models\AbandonedCart::capture((int) $u['id'], $email, Cart::raw());
    }

    /** Désinscription des rappels de panier (lien dans l'e-mail). */
    public function stopReminders(Request $request): void
    {
        $email = \App\Models\AbandonedCart::optout((string) $request->param('token', ''));
        view('newsletter/unsubscribed', [
            'page_title' => t('cart.remind.optout_title'),
            'ok'         => $email !== null,
            'email'      => $email,
            'context'    => 'cart',
        ]);
    }

    /** Passe à la caisse d'une boutique : copie ses lignes vers la caisse de session. */
    public function checkout(Request $request): void
    {
        $boutique = Boutique::findBySlug((string) $request->param('slug', ''));
        if ($boutique === null || $boutique['status'] !== 'published') {
            abort(404);
        }
        $bid    = (int) $boutique['id'];
        $caisse = [];
        foreach ((Cart::raw()[$bid] ?? []) as $pid => $qty) {
            if ((int) $qty > 0) {
                $caisse[] = ['id' => (string) $pid, 'qty' => (int) $qty];
            }
        }
        if ($caisse === []) {
            redirect('/panier');
        }
        $_SESSION['caisse'][$bid] = $caisse;
        $_SESSION['cart_shop']    = (string) $boutique['slug'];
        redirect('/boutique/' . $boutique['slug'] . '/caisse');
    }
}
