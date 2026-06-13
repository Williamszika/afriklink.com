<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * listings / listing_photos — annonces entre particuliers.
 * Données en TiDB ; les médias (photos, vidéo) vivent sur Cloudinary, on ne
 * stocke ici que leurs identifiants. Les tables se créent toutes seules au
 * premier dépôt (même principe que user_avatars) — aucune migration manuelle.
 */
final class Listing
{
    public static function ensureTables(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS listings (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id       CHAR(36) NOT NULL UNIQUE,
                user_id         BIGINT UNSIGNED NOT NULL,
                title           VARCHAR(150) NOT NULL,
                description     TEXT NOT NULL,
                category        VARCHAR(32) NOT NULL,
                price_cents     BIGINT UNSIGNED NOT NULL,
                currency        CHAR(3) NOT NULL DEFAULT \'EUR\',
                item_condition  VARCHAR(16) NOT NULL,
                country_code    CHAR(2) NULL,
                city            VARCHAR(120) NULL,
                whatsapp_optin  TINYINT(1) NOT NULL DEFAULT 0,
                status          VARCHAR(16) NOT NULL DEFAULT \'active\',
                video_public_id VARCHAR(255) NULL,
                video_duration  DECIMAL(6,2) NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_listings_user (user_id, status),
                KEY idx_listings_cat (category, status, created_at)
            )'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS listing_photos (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                listing_id      BIGINT UNSIGNED NOT NULL,
                cloud_public_id VARCHAR(255) NOT NULL,
                width           INT UNSIGNED NULL,
                height          INT UNSIGNED NULL,
                position        TINYINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_photos_listing (listing_id, position)
            )'
        );
    }

    /**
     * Crée l'annonce et ses photos en une transaction.
     * @param list<array{public_id:string,width:?int,height:?int}> $photos
     * @return string public_id de l'annonce créée
     */
    public static function create(array $data, array $photos): string
    {
        self::ensureTables();
        $pdo = db();
        $publicId = uuid();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO listings
                    (public_id, user_id, title, description, category, price_cents, currency,
                     item_condition, country_code, city, whatsapp_optin, status,
                     video_public_id, video_duration)
                 VALUES
                    (:public_id, :user_id, :title, :description, :category, :price_cents, :currency,
                     :item_condition, :country_code, :city, :whatsapp_optin, \'active\',
                     :video_public_id, :video_duration)'
            );
            $stmt->execute([
                'public_id'       => $publicId,
                'user_id'         => $data['user_id'],
                'title'           => $data['title'],
                'description'     => $data['description'],
                'category'        => $data['category'],
                'price_cents'     => $data['price_cents'],
                'currency'        => $data['currency'],
                'item_condition'  => $data['item_condition'],
                'country_code'    => $data['country_code'],
                'city'            => $data['city'],
                'whatsapp_optin'  => $data['whatsapp_optin'] ? 1 : 0,
                'video_public_id' => $data['video_public_id'],
                'video_duration'  => $data['video_duration'],
            ]);
            $listingId = (int) $pdo->lastInsertId();

            $photoStmt = $pdo->prepare(
                'INSERT INTO listing_photos (listing_id, cloud_public_id, width, height, position)
                 VALUES (:lid, :pid, :w, :h, :pos)'
            );
            foreach (array_values($photos) as $i => $photo) {
                $photoStmt->execute([
                    'lid' => $listingId,
                    'pid' => $photo['public_id'],
                    'w'   => $photo['width'],
                    'h'   => $photo['height'],
                    'pos' => $i,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $publicId;
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM listings WHERE public_id = :pid LIMIT 1');
            $stmt->execute(['pid' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null; // tables pas encore créées
        }
    }

    /** @return list<array> photos triées (position 0 = principale) */
    public static function photos(int $listingId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT cloud_public_id, width, height, position
                   FROM listing_photos WHERE listing_id = :lid ORDER BY position'
            );
            $stmt->execute(['lid' => $listingId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Première photo de chaque annonce d'une liste d'ids. @return array<int,string> */
    /** @return list<array> annonces actives récentes (pour la vitrine d'accueil) */
    public static function recentActive(int $limit = 12): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT id, public_id, title, price_cents, currency, category
                   FROM listings WHERE status = 'active' ORDER BY id DESC LIMIT " . max(1, min(48, $limit))
            );
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function mainPhotos(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($listingIds), '?'));
            $stmt = db()->prepare(
                "SELECT listing_id, cloud_public_id
                   FROM listing_photos WHERE listing_id IN ($in) AND position = 0"
            );
            $stmt->execute(array_map('intval', array_values($listingIds)));
            $map = [];
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $map[(int) $row['listing_id']] = (string) $row['cloud_public_id'];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array> annonces d'un vendeur, les plus récentes d'abord */
    public static function forUser(int $userId, int $limit = 50): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM listings
                  WHERE user_id = :uid AND status <> \'deleted\'
                  ORDER BY created_at DESC LIMIT ' . max(1, $limit)
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Compteurs pour le tableau de bord. @return array{listings:int,sold:int} */
    public static function countsFor(int $userId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT
                    COALESCE(SUM(status IN ('active','paused')), 0) AS listings,
                    COALESCE(SUM(status = 'sold'), 0)               AS sold
                   FROM listings WHERE user_id = :uid"
            );
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch() ?: [];
            return ['listings' => (int) ($row['listings'] ?? 0), 'sold' => (int) ($row['sold'] ?? 0)];
        } catch (\Throwable) {
            return ['listings' => 0, 'sold' => 0];
        }
    }

    public static function updateFields(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE listings SET
                title = :title, description = :description, category = :category,
                price_cents = :price_cents, currency = :currency,
                item_condition = :item_condition, city = :city,
                whatsapp_optin = :whatsapp_optin
             WHERE id = :id'
        );
        $stmt->execute([
            'title'          => $data['title'],
            'description'    => $data['description'],
            'category'       => $data['category'],
            'price_cents'    => $data['price_cents'],
            'currency'       => $data['currency'],
            'item_condition' => $data['item_condition'],
            'city'           => $data['city'],
            'whatsapp_optin' => $data['whatsapp_optin'] ? 1 : 0,
            'id'             => $id,
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = db()->prepare('UPDATE listings SET status = :s WHERE id = :id');
        $stmt->execute(['s' => $status, 'id' => $id]);
    }
}
