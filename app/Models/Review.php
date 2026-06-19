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
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS reviews (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                boutique_id BIGINT UNSIGNED NOT NULL,
                product_id  BIGINT UNSIGNED NULL,
                user_id     BIGINT UNSIGNED NULL,
                author_name VARCHAR(80) NOT NULL,
                rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
                comment     VARCHAR(1000) NULL,
                verified    TINYINT(1) NOT NULL DEFAULT 0,
                status      VARCHAR(12) NOT NULL DEFAULT \'approved\',
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reviews_product (product_id, status, id),
                KEY idx_reviews_boutique (boutique_id, status)
            )'
        );
        // Colonnes ajoutées après coup (best-effort, comme partout en prod durcie).
        $cols = [
            'verified' => 'ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0 AFTER comment',
            'reply'    => 'ADD COLUMN reply VARCHAR(1000) NULL AFTER status',
            'reply_at' => 'ADD COLUMN reply_at DATETIME NULL AFTER reply',
            // Photos jointes à l'avis (identifiants Cloudinary, JSON) — façon Shein.
            'photos'   => 'ADD COLUMN photos VARCHAR(1000) NULL AFTER comment',
        ];
        foreach ($cols as $col => $ddl) {
            try {
                db()->query("SELECT {$col} FROM reviews LIMIT 1");
            } catch (\Throwable) {
                try {
                    db()->exec("ALTER TABLE reviews {$ddl}");
                } catch (\Throwable) {
                    // déjà migré ou DDL indisponible en prod
                }
            }
        }
    }

    /** Avis d'une boutique (toutes fiches confondues), nom du produit joint. @return list<array> */
    public static function forBoutique(int $boutiqueId, int $limit = 50): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT r.*, p.name AS product_name
                   FROM reviews r
                   LEFT JOIN products p ON p.id = r.product_id
                  WHERE r.boutique_id = :b AND r.status = 'approved'
                  ORDER BY r.id DESC LIMIT " . max(1, min(200, $limit))
            );
            $stmt->execute(['b' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Réponse publique du vendeur à un avis (vide = retire la réponse). Best-effort. */
    public static function setReply(int $id, ?string $reply): void
    {
        $reply = $reply !== null ? trim($reply) : '';
        try {
            if ($reply === '') {
                db()->prepare('UPDATE reviews SET reply = NULL, reply_at = NULL WHERE id = :id')->execute(['id' => $id]);
            } else {
                db()->prepare('UPDATE reviews SET reply = :r, reply_at = NOW() WHERE id = :id')
                    ->execute(['r' => mb_substr($reply, 0, 1000), 'id' => $id]);
            }
        } catch (\Throwable) {
            // colonne reply non provisionnée : sans gravité
        }
    }

    /** Nombre d'avis sans réponse du vendeur (pastille du menu). */
    public static function unansweredCountFor(int $boutiqueId): int
    {
        try {
            $stmt = db()->prepare("SELECT COUNT(*) FROM reviews WHERE boutique_id = :b AND status = 'approved' AND (reply IS NULL OR reply = '')");
            $stmt->execute(['b' => $boutiqueId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function create(array $d): string
    {
        self::ensureTable();
        $pid = uuid();
        // Photos (identifiants Cloudinary déjà vérifiés par le contrôleur) → JSON, max 6.
        $photos = array_values(array_filter(array_map(static fn ($p): string => trim((string) $p), (array) ($d['photos'] ?? []))));
        $photos = array_slice($photos, 0, 6);
        $photosJson = $photos !== [] ? (string) json_encode($photos, JSON_UNESCAPED_SLASHES) : null;
        try {
            $stmt = db()->prepare(
                'INSERT INTO reviews (public_id, boutique_id, product_id, user_id, author_name, rating, comment, photos, verified, status)
                 VALUES (:pid, :bid, :prod, :uid, :name, :rating, :comment, :photos, :verified, \'approved\')'
            );
            $stmt->execute([
                'pid' => $pid, 'bid' => $d['boutique_id'], 'prod' => $d['product_id'] ?? null,
                'uid' => $d['user_id'] ?? null, 'name' => mb_substr((string) $d['author_name'], 0, 80),
                'rating' => max(1, min(5, (int) $d['rating'])),
                'comment' => $d['comment'] !== null && $d['comment'] !== '' ? mb_substr((string) $d['comment'], 0, 1000) : null,
                'photos' => $photosJson,
                'verified' => !empty($d['verified']) ? 1 : 0,
            ]);
        } catch (\Throwable) {
            // Repli si la colonne « photos » n'est pas encore provisionnée en prod.
            db()->prepare(
                'INSERT INTO reviews (public_id, boutique_id, product_id, user_id, author_name, rating, comment, verified, status)
                 VALUES (:pid, :bid, :prod, :uid, :name, :rating, :comment, :verified, \'approved\')'
            )->execute([
                'pid' => $pid, 'bid' => $d['boutique_id'], 'prod' => $d['product_id'] ?? null,
                'uid' => $d['user_id'] ?? null, 'name' => mb_substr((string) $d['author_name'], 0, 80),
                'rating' => max(1, min(5, (int) $d['rating'])),
                'comment' => $d['comment'] !== null && $d['comment'] !== '' ? mb_substr((string) $d['comment'], 0, 1000) : null,
                'verified' => !empty($d['verified']) ? 1 : 0,
            ]);
        }
        return $pid;
    }

    /** Cet utilisateur a-t-il déjà laissé un avis sur ce produit ? (1 avis / produit) */
    public static function hasReviewed(int $productId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            $stmt = db()->prepare('SELECT 1 FROM reviews WHERE product_id = :p AND user_id = :u LIMIT 1');
            $stmt->execute(['p' => $productId, 'u' => $userId]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
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
