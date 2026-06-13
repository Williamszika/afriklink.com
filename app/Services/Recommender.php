<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * Moteur de recommandations — 100 % algorithmique, sans clé ni service externe.
 *
 *  • « Vu récemment » : historique de navigation du visiteur, mémorisé dans un
 *    cookie first-party (uniquement des identifiants publics de produits).
 *  • « Recommandé pour vous » : filtrage par centres d'intérêt — les catégories
 *    des boutiques récemment consultées ; repli sur les nouveautés si l'historique
 *    est vide (démarrage à froid).
 *  • « Souvent achetés ensemble » : co-achats réels extraits des commandes
 *    (filtrage collaboratif léger sur order_items).
 *
 * Tout dégrade proprement : en l'absence de données, chaque méthode renvoie [].
 */
final class Recommender
{
    private const COOKIE   = 'afk_seen';
    private const MAX_SEEN = 24;
    private const PID_RX   = '/^[A-Za-z0-9\-]{8,40}$/';

    /** Mémorise la consultation d'un produit (le plus récent en tête, dédoublonné). */
    public static function recordView(string $publicId): void
    {
        $publicId = trim($publicId);
        if ($publicId === '' || !preg_match(self::PID_RX, $publicId)) {
            return;
        }
        $ids = self::seenIds();
        array_unshift($ids, $publicId);
        $ids = array_slice(array_values(array_unique($ids)), 0, self::MAX_SEEN);
        $value = implode(',', $ids);
        @setcookie(self::COOKIE, $value, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value; // visible dès la requête courante
    }

    /** @return list<string> identifiants publics consultés, du plus récent au plus ancien */
    public static function seenIds(): array
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($raw === '') {
            return [];
        }
        $ids = array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $s): bool => $s !== '' && preg_match(self::PID_RX, $s) === 1
        );
        return array_slice(array_values(array_unique($ids)), 0, self::MAX_SEEN);
    }

    /** @return list<array> produits vus récemment (en ligne), dans l'ordre de consultation */
    public static function recentlyViewed(int $limit = 6, ?string $excludePublicId = null): array
    {
        $ids = self::seenIds();
        if ($excludePublicId !== null && $excludePublicId !== '') {
            $ids = array_values(array_filter($ids, static fn (string $s): bool => $s !== $excludePublicId));
        }
        if ($ids === []) {
            return [];
        }
        $byPid = [];
        foreach (self::rowsByPublicIds($ids) as $r) {
            $byPid[(string) $r['public_id']] = $r;
        }
        $out = [];
        foreach ($ids as $pid) {
            if (isset($byPid[$pid])) {
                $out[] = $byPid[$pid];
            }
        }
        return array_slice($out, 0, max(1, $limit));
    }

    /**
     * « Recommandé pour vous » : produits en ligne des catégories que le visiteur
     * a consultées, en excluant ceux déjà vus ; repli sur les nouveautés.
     * @param list<string> $excludePublicIds
     * @return list<array>
     */
    public static function forYou(int $limit = 8, array $excludePublicIds = []): array
    {
        $seen = self::seenIds();
        $cats = [];
        if ($seen !== []) {
            foreach (self::rowsByPublicIds($seen) as $r) {
                $c = (string) ($r['boutique_category'] ?? '');
                if ($c !== '') {
                    $cats[$c] = true;
                }
            }
        }
        $cats    = array_keys($cats);
        $exclude = array_values(array_unique(array_filter(array_merge($seen, $excludePublicIds))));

        try {
            $args = [];
            $sql  = "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency
                       FROM products p JOIN boutiques b ON b.id = p.boutique_id
                      WHERE p.status = 'active' AND b.status = 'published'";
            if ($cats !== []) {
                $sql .= ' AND b.category IN (' . implode(',', array_fill(0, count($cats), '?')) . ')';
                foreach ($cats as $c) {
                    $args[] = $c;
                }
            }
            if ($exclude !== []) {
                $sql .= ' AND p.public_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
                foreach ($exclude as $x) {
                    $args[] = $x;
                }
            }
            $sql .= ' ORDER BY p.id DESC LIMIT ' . max(1, min(24, $limit));
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * « Souvent achetés ensemble » : produits le plus souvent commandés dans les
     * mêmes commandes que $productId (hors commandes annulées).
     * @return list<array>
     */
    public static function frequentlyBoughtTogether(int $productId, int $limit = 4): array
    {
        if ($productId <= 0) {
            return [];
        }
        try {
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency
                   FROM (
                        SELECT oi2.product_id AS pid, COUNT(*) AS together
                          FROM order_items oi1
                          JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id <> oi1.product_id
                          JOIN orders o ON o.id = oi1.order_id
                         WHERE oi1.product_id = :p AND o.status <> 'cancelled'
                         GROUP BY oi2.product_id
                         ORDER BY together DESC
                         LIMIT 12
                   ) t
                   JOIN products p  ON p.id = t.pid
                   JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published'
                  ORDER BY t.together DESC, p.id DESC
                  LIMIT " . max(1, min(8, $limit))
            );
            $stmt->execute(['p' => $productId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Première photo de chaque produit d'une liste. @return array<int,string> */
    public static function mainsFor(array $products): array
    {
        return Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));
    }

    /** Charge des produits en ligne par identifiants publics (avec boutique). @return list<array> */
    private static function rowsByPublicIds(array $publicIds): array
    {
        $publicIds = array_values(array_filter($publicIds));
        if ($publicIds === []) {
            return [];
        }
        try {
            $in   = implode(',', array_fill(0, count($publicIds), '?'));
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency, b.category AS boutique_category
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published' AND p.public_id IN ($in)"
            );
            $stmt->execute($publicIds);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
