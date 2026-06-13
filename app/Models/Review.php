<?php
declare(strict_types=1);

namespace App\Models;

/**
 * reviews — avis & notes laissés sur un produit (et, par agrégation, sur la
 * boutique). Note de 1 à 5 étoiles + commentaire. Auto-publié, mais le vendeur
 * peut masquer un avis abusif (status). Table auto-créée.
 */
final class Review
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS reviews (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                boutique_id BIGINT UNSIGNED NOT NULL,
                product_id  BIGINT UNSIGNED NULL,
                user_id     BIGINT UNSIGNED NULL,
                author_name VARCHAR(80) NOT NULL,
                rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
                comment     VARCHAR(1000) NULL,
                status      VARCHAR(12) NOT NULL DEFAULT \'approved\',
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reviews_product (product_id, status, id),
                KEY idx_reviews_boutique (boutique_id, status)
            )'
        );
    }

    public static function create(array $d): string
    {
        self::ensureTable();
        $pid = uuid();
        db()->prepare(
            'INSERT INTO reviews (public_id, boutique_id, product_id, user_id, author_name, rating, comment, status)
             VALUES (:pid, :bid, :prod, :uid, :name, :rating, :comment, \'approved\')'
        )->execute([
            'pid' => $pid,
            'bid' => $d['boutique_id'],
            'prod' => $d['product_id'] ?? null,
            'uid' => $d['user_id'] ?? null,
            'name' => mb_substr((string) $d['author_name'], 0, 80),
            'rating' => max(1, min(5, (int) $d['rating'])),
            'comment' => $d['comment'] !== null && $d['comment'] !== '' ? mb_substr((string) $d['comment'], 0, 1000) : null,
        ]);
        return $pid;
    }

    /** @return list<array> avis publiés d'un produit (récents d'abord) */
    public static function forProduct(int $productId, int $limit = 50): array
    {
        try {
            $stmt = db()->prepare("SELECT * FROM reviews WHERE product_id = :p AND status = 'approved' ORDER BY id DESC LIMIT " . max(1, min(200, $limit)));
            $stmt->execute(['p' => $productId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{avg:float,count:int} moyenne + nombre d'avis d'un produit */
    public static function summaryForProduct(int $productId): array
    {
        return self::summaryWhere('product_id = :id', ['id' => $productId]);
    }

    /** @return array{avg:float,count:int} moyenne + nombre d'avis d'une boutique */
    public static function summaryForBoutique(int $boutiqueId): array
    {
        return self::summaryWhere('boutique_id = :id', ['id' => $boutiqueId]);
    }

    /** Moyennes par produit, en une requête. @return array<int,array{avg:float,count:int}> */
    public static function summaryForProducts(array $productIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if ($ids === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("SELECT product_id, AVG(rating) AS avg, COUNT(*) AS n FROM reviews WHERE status = 'approved' AND product_id IN ($in) GROUP BY product_id");
            $stmt->execute($ids);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(int) $r['product_id']] = ['avg' => round((float) $r['avg'], 1), 'count' => (int) $r['n']];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findByPublicId(string $pid): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM reviews WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $pid]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['approved', 'hidden'], true)) {
            return;
        }
        db()->prepare('UPDATE reviews SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    }

    /** @return array{avg:float,count:int} */
    private static function summaryWhere(string $cond, array $args): array
    {
        try {
            $stmt = db()->prepare("SELECT AVG(rating) AS avg, COUNT(*) AS n FROM reviews WHERE status = 'approved' AND {$cond}");
            $stmt->execute($args);
            $r = $stmt->fetch() ?: [];
            return ['avg' => round((float) ($r['avg'] ?? 0), 1), 'count' => (int) ($r['n'] ?? 0)];
        } catch (\Throwable) {
            return ['avg' => 0.0, 'count' => 0];
        }
    }
}
