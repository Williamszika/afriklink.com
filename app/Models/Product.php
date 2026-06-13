<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * products / product_photos — catalogue d'une boutique. Médias publics sur
 * Cloudinary (on ne stocke que les identifiants). Tables auto-créées.
 */
final class Product
{
    public static function ensureTables(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS products (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                boutique_id BIGINT UNSIGNED NOT NULL,
                user_id     BIGINT UNSIGNED NOT NULL,
                name        VARCHAR(150) NOT NULL,
                description TEXT NULL,
                price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                stock       INT NULL,
                video_public_id VARCHAR(255) NULL,
                video_duration  DECIMAL(6,2) NULL,
                status      VARCHAR(12) NOT NULL DEFAULT \'active\',
                position    INT NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_products_boutique (boutique_id, status, position)
            )'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS product_photos (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id      BIGINT UNSIGNED NOT NULL,
                cloud_public_id VARCHAR(255) NOT NULL,
                width           INT UNSIGNED NULL,
                height          INT UNSIGNED NULL,
                position        TINYINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_pphotos_product (product_id, position)
            )'
        );
    }

    /** @param list<array{public_id:string,width:?int,height:?int}> $photos */
    public static function create(int $boutiqueId, int $userId, array $data, array $photos): string
    {
        self::ensureTables();
        $pdo = db();
        $publicId = uuid();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (public_id, boutique_id, user_id, name, description, price_cents, stock, video_public_id, video_duration, status, position)
                 VALUES (:pid, :bid, :uid, :name, :desc, :price, :stock, :vid, :vdur, :status, :pos)'
            );
            $stmt->execute([
                'pid' => $publicId, 'bid' => $boutiqueId, 'uid' => $userId,
                'name' => $data['name'], 'desc' => $data['description'],
                'price' => $data['price_cents'], 'stock' => $data['stock'],
                'vid' => $data['video_public_id'] ?? null, 'vdur' => $data['video_duration'] ?? null,
                'status' => $data['status'], 'pos' => time() % 100000000,
            ]);
            $productId = (int) $pdo->lastInsertId();
            self::replacePhotos($pdo, $productId, $photos);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $publicId;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE products SET name = :name, description = :desc, price_cents = :price,
                stock = :stock, video_public_id = :vid, video_duration = :vdur, status = :status WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'], 'desc' => $data['description'], 'price' => $data['price_cents'],
            'stock' => $data['stock'], 'vid' => $data['video_public_id'] ?? null,
            'vdur' => $data['video_duration'] ?? null, 'status' => $data['status'], 'id' => $id,
        ]);
    }

    /** @param list<array{public_id:string,width:?int,height:?int}> $photos */
    public static function setPhotos(int $productId, array $photos): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::replacePhotos($pdo, $productId, $photos);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function replacePhotos(PDO $pdo, int $productId, array $photos): void
    {
        $pdo->prepare('DELETE FROM product_photos WHERE product_id = :pid')->execute(['pid' => $productId]);
        $ins = $pdo->prepare(
            'INSERT INTO product_photos (product_id, cloud_public_id, width, height, position)
             VALUES (:pid, :cpid, :w, :h, :pos)'
        );
        foreach (array_values($photos) as $i => $ph) {
            $ins->execute(['pid' => $productId, 'cpid' => $ph['public_id'], 'w' => $ph['width'], 'h' => $ph['height'], 'pos' => $i]);
        }
    }

    /** Ajoute les colonnes introduites après coup (idempotent, une fois par requête). */
    private static function migrate(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            db()->query('SELECT promoted_until FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products ADD COLUMN promoted_until DATETIME NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Produit épinglé : le vendeur le met en avant en tête de sa propre vitrine.
        try {
            db()->query('SELECT pinned FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0');
            } catch (\Throwable) {
                // déjà migré
            }
        }
    }

    /** @return list<array> produits d'une boutique (sponsorisés en tête, puis récents) */
    public static function forBoutique(int $boutiqueId, bool $activeOnly = false): array
    {
        self::migrate();
        try {
            $sql = 'SELECT * FROM products WHERE boutique_id = :bid';
            if ($activeOnly) { $sql .= ' AND status = \'active\''; }
            // Épinglés d'abord (choix du vendeur), puis sponsorisés (mise en avant
            // non expirée), puis les plus récents.
            $sql .= ' ORDER BY pinned DESC, (promoted_until IS NOT NULL AND promoted_until > NOW()) DESC, position DESC, id DESC';
            $stmt = db()->prepare($sql);
            $stmt->execute(['bid' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Active (jours) ou retire (null) la mise en avant « sponsorisé » d'un produit. */
    public static function setPromoted(int $id, ?int $days): void
    {
        self::migrate();
        try {
            if ($days !== null && $days > 0) {
                db()->prepare('UPDATE products SET promoted_until = (NOW() + INTERVAL ' . max(1, min(365, $days)) . ' DAY) WHERE id = :id')
                    ->execute(['id' => $id]);
            } else {
                db()->prepare('UPDATE products SET promoted_until = NULL WHERE id = :id')->execute(['id' => $id]);
            }
        } catch (\Throwable) {
        }
    }

    /** Produit actuellement sponsorisé ? (mise en avant non expirée) */
    public static function isPromoted(array $row): bool
    {
        $u = $row['promoted_until'] ?? null;
        return $u !== null && $u !== '' && strtotime((string) $u) > time();
    }

    /** Épingle (ou retire) un produit en tête de la vitrine du vendeur. */
    public static function setPinned(int $id, bool $pinned): void
    {
        self::migrate();
        try {
            db()->prepare('UPDATE products SET pinned = :p WHERE id = :id')
                ->execute(['p' => $pinned ? 1 : 0, 'id' => $id]);
        } catch (\Throwable) {
        }
    }

    /** Produits sponsorisés du marketplace (vitrines publiées). @return list<array> */
    public static function promotedMarketplace(int $limit = 8): array
    {
        self::migrate();
        try {
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published'
                    AND p.promoted_until IS NOT NULL AND p.promoted_until > NOW()
                  ORDER BY p.promoted_until DESC LIMIT " . max(1, min(24, $limit))
            );
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function countFor(int $boutiqueId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT COALESCE(SUM(status='active'),0) AS active, COUNT(*) AS total
                   FROM products WHERE boutique_id = :bid"
            );
            $stmt->execute(['bid' => $boutiqueId]);
            $r = $stmt->fetch() ?: [];
            return ['active' => (int) ($r['active'] ?? 0), 'total' => (int) ($r['total'] ?? 0)];
        } catch (\Throwable) {
            return ['active' => 0, 'total' => 0];
        }
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM products WHERE public_id = :pid LIMIT 1');
            $stmt->execute(['pid' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array> photos triées */
    public static function photos(int $productId): array
    {
        try {
            $stmt = db()->prepare('SELECT cloud_public_id, width, height, position FROM product_photos WHERE product_id = :pid ORDER BY position');
            $stmt->execute(['pid' => $productId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Première photo de chaque produit. @return array<int,string> */
    public static function mainPhotos(array $productIds): array
    {
        if ($productIds === []) { return []; }
        try {
            $in = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = db()->prepare("SELECT product_id, cloud_public_id FROM product_photos WHERE product_id IN ($in) AND position = 0");
            $stmt->execute(array_map('intval', array_values($productIds)));
            $map = [];
            foreach ($stmt->fetchAll() ?: [] as $r) { $map[(int) $r['product_id']] = (string) $r['cloud_public_id']; }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Recherche marketplace : produits en ligne des vitrines publiées, par
     * mot-clé / catégorie / fourchette de prix, triés (sponsorisés en tête).
     * @return list<array> produits + boutique_slug/name/currency/category
     */
    public static function search(array $f): array
    {
        self::migrate();
        $q   = trim((string) ($f['q'] ?? ''));
        $cat = (string) ($f['category'] ?? '');
        $pays = strtoupper(trim((string) ($f['country'] ?? '')));
        $city = trim((string) ($f['city'] ?? ''));
        $min = ($f['min'] ?? '') !== '' ? (int) $f['min'] : null;
        $max = ($f['max'] ?? '') !== '' ? (int) $f['max'] : null;
        $limit  = max(1, min(48, (int) ($f['limit'] ?? 24)));
        $offset = max(0, (int) ($f['offset'] ?? 0));

        $where = ["p.status = 'active'", "b.status = 'published'"];
        $args  = [];
        if ($q !== '') {
            // Placeholders distincts (EMULATE_PREPARES=false interdit la réutilisation).
            $where[] = '(p.name LIKE :q OR p.description LIKE :q2 OR b.name LIKE :q3)';
            $like = '%' . $q . '%';
            $args['q'] = $like; $args['q2'] = $like; $args['q3'] = $like;
        }
        if ($cat !== '') { $where[] = 'b.category = :cat'; $args['cat'] = $cat; }
        if ($pays !== '') { $where[] = 'b.country_code = :pays'; $args['pays'] = $pays; }
        if ($city !== '') { $where[] = 'b.city LIKE :city'; $args['city'] = '%' . $city . '%'; }
        if (!empty($f['in_stock'])) { $where[] = '(p.stock IS NULL OR p.stock > 0)'; }
        if ($min !== null) { $where[] = 'p.price_cents >= :pmin'; $args['pmin'] = $min * 100; }
        if ($max !== null) { $where[] = 'p.price_cents <= :pmax'; $args['pmax'] = $max * 100; }

        $order = match ((string) ($f['sort'] ?? 'recent')) {
            'price_asc'  => 'p.price_cents ASC',
            'price_desc' => 'p.price_cents DESC',
            default      => 'p.id DESC',
        };

        try {
            $sql = "SELECT p.*, b.slug AS boutique_slug, b.name AS boutique_name, b.currency AS currency, b.category AS boutique_category
                      FROM products p JOIN boutiques b ON b.id = p.boutique_id
                     WHERE " . implode(' AND ', $where) . "
                     ORDER BY (p.promoted_until IS NOT NULL AND p.promoted_until > NOW()) DESC, $order
                     LIMIT $limit OFFSET $offset";
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Pays (codes ISO) ayant des produits en ligne — alimente le filtre de recherche. @return list<string> */
    public static function searchCountries(): array
    {
        try {
            $rows = db()->query(
                "SELECT DISTINCT b.country_code FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published'
                    AND b.country_code IS NOT NULL AND b.country_code <> ''
                  ORDER BY b.country_code"
            )->fetchAll() ?: [];
            return array_map(static fn (array $r): string => (string) $r['country_code'], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Produits en ligne par identifiants publics, dans l'ordre fourni. @return list<array> */
    public static function onlineByPublicIds(array $publicIds): array
    {
        $publicIds = array_values(array_filter($publicIds));
        if ($publicIds === []) {
            return [];
        }
        try {
            $in   = implode(',', array_fill(0, count($publicIds), '?'));
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.name AS boutique_name, b.currency AS currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published' AND p.public_id IN ($in)"
            );
            $stmt->execute($publicIds);
            $byPid = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $byPid[(string) $r['public_id']] = $r;
            }
            $out = [];
            foreach ($publicIds as $pid) {
                if (isset($byPid[$pid])) {
                    $out[] = $byPid[$pid];
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        db()->prepare('UPDATE products SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM product_photos WHERE product_id = :id')->execute(['id' => $id]);
        db()->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
    }
}
