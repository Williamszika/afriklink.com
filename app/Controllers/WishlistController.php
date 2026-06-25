<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Product;
use App\Request;
use App\Services\Wishlist;

/**
 * Liste de souhaits (favoris) — page /favoris + bascule du cœur. La bascule
 * répond en JSON pour le fetch (sans rechargement) ou redirige sans JS.
 */
final class WishlistController
{
    public function index(Request $request): void
    {
        $ids      = Wishlist::ids();
        $products = $ids !== [] ? Product::onlineByPublicIds($ids) : [];
        view('wishlist/index', [
            'page_title' => t('wish.title'),
            'products'   => $products,
            'mains'      => Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
        ]);
    }

    /** Aperçu des favoris (menu déroulant) — fragment HTML. */
    public function preview(Request $request): void
    {
        $ids      = Wishlist::ids();
        $products = $ids !== [] ? Product::onlineByPublicIds($ids) : [];
        $mains    = Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));
        $items = [];
        foreach (array_slice($products, 0, 6) as $p) {
            $m = $mains[(int) $p['id']] ?? null;
            $items[] = [
                'url'   => url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']),
                'name'  => (string) $p['name'],
                'price' => format_price((int) $p['price_cents'], (string) $p['currency']),
                'main'  => $m !== null ? \App\Services\CloudinaryService::imageUrl($m, 80, 80, true) : null,
            ];
        }
        header('Content-Type: text/html; charset=utf-8');
        echo render_partial('partials/nav_dropdown', ['items' => $items, 'all_url' => url('/favoris'), 'all_label' => t('common.see_all'), 'empty' => t('wish.empty')]);
        exit;
    }

    public function toggle(Request $request): void
    {
        $now   = Wishlist::toggle((string) $request->param('pid', ''));
        $count = Wishlist::count();

        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_response(['wished' => $now, 'count' => $count]);
        }
        $to = trim((string) input_string('to', '/'));
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/';
        }
        redirect(mb_substr($to, 0, 300));
    }
}
