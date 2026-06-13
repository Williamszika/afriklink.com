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
            $line = (int) $p['price_cents'] * $qty;
            $groups[$slug]['subtotal'] += $line;
            $groups[$slug]['lines'][] = ['product' => $p, 'qty' => $qty, 'main' => $mains[(int) $p['id']] ?? null, 'line_total' => $line];
        }

        view('cart/index', [
            'page_title' => t('cart.title'),
            'groups'     => array_values($groups),
            'count'      => Cart::count(),
        ]);
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
        }
        redirect('/panier');
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
