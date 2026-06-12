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

    /** @return list<array> produits d'une boutique (les plus récents d'abord) */
    public static function forBoutique(int $boutiqueId, bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM products WHERE boutique_id = :bid';
            if ($activeOnly) { $sql .= ' AND status = \'active\''; }
            $sql .= ' ORDER BY position DESC, id DESC';
            $stmt = db()->prepare($sql);
            $stmt->execute(['bid' => $boutiqueId]);
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
