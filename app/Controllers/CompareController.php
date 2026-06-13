<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Request;
use App\Services\Compare;

/** Comparateur de produits — page /comparer + bascule du bouton ⇄. */
final class CompareController
{
    public function index(Request $request): void
    {
        $ids      = Compare::ids();
        $products = $ids !== [] ? Product::onlineByPublicIds($ids) : [];
        $pids     = array_map(static fn (array $p): int => (int) $p['id'], $products);
        view('compare/index', [
            'page_title' => t('compare.title'),
            'products'   => $products,
            'mains'      => Product::mainPhotos($pids),
            'ratings'    => Review::summaryForProducts($pids),
        ]);
    }

    public function toggle(Request $request): void
    {
        $now   = Compare::toggle((string) $request->param('pid', ''));
        $count = Compare::count();

        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_response(['comparing' => $now, 'count' => $count]);
        }
        $to = trim((string) input_string('to', '/'));
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/';
        }
        redirect(mb_substr($to, 0, 300));
    }
}
