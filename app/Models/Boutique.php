<?php
declare(strict_types=1);

namespace App\Models;

/**
 * boutiques — vitrine « Boutique en ligne » d'un vendeur (une par compte pour
 * l'instant). Le logo/bannière vivent sur Cloudinary (public, comme les photos
 * d'annonces) : on ne stocke ici que leurs identifiants. Table auto-créée.
 */
final class Boutique
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS boutiques (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id        CHAR(36) NOT NULL UNIQUE,
                user_id          BIGINT UNSIGNED NOT NULL,
                slug             VARCHAR(48) NOT NULL UNIQUE,
                name             VARCHAR(80) NOT NULL,
                tagline          VARCHAR(140) NULL,
                description      TEXT NULL,
                category         VARCHAR(32) NULL,
                logo_public_id   VARCHAR(255) NULL,
                banner_public_id VARCHAR(255) NULL,
                currency         CHAR(3) NOT NULL DEFAULT \'EUR\',
                delivery_zones   VARCHAR(80) NULL,
                delivery_methods VARCHAR(80) NULL,
                free_ship_cents  BIGINT UNSIGNED NULL,
                prep_time        VARCHAR(16) NULL,
                cod_enabled      TINYINT(1) NOT NULL DEFAULT 1,
                status           VARCHAR(12) NOT NULL DEFAULT \'draft\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_boutiques_user (user_id)
            )'
        );
    }

    /** Le slug est-il libre ? (hors la boutique de $exceptUserId, pour l'édition) */
    public static function slugAvailable(string $slug, ?int $exceptUserId = null): bool
    {
        if (in_array($slug, config('shop.slug_reserved', []), true)) {
            return false;
        }
        try {
            $sql = 'SELECT user_id FROM boutiques WHERE slug = :s LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute(['s' => $slug]);
            $row = $stmt->fetch();
            if ($row === false) {
                return true;
            }
            return $exceptUserId !== null && (int) $row['user_id'] === $exceptUserId;
        } catch (\Throwable) {
            return true; // table absente = libre
        }
    }

    /** Propose un slug unique dérivé d'une base (ajoute -2, -3… si pris). */
    public static function uniqueSlug(string $base, ?int $exceptUserId = null): string
    {
        $base = substr(slugify($base) ?: 'boutique', 0, (int) config('shop.slug_max', 40));
        if ($base === '' ) { $base = 'boutique'; }
        $slug = $base;
        $i = 2;
        while (!self::slugAvailable($slug, $exceptUserId)) {
            $slug = $base . '-' . $i;
            if (++$i > 999) { $slug = $base . '-' . substr(uuid(), 0, 6); break; }
        }
        return $slug;
    }

    public static function create(int $userId, array $d): string
    {
        self::ensureTable();
        $publicId = uuid();
        $stmt = db()->prepare(
            'INSERT INTO boutiques
                (public_id, user_id, slug, name, tagline, description, category,
                 logo_public_id, banner_public_id, currency, delivery_zones,
                 delivery_methods, free_ship_cents, prep_time, cod_enabled, status)
             VALUES
                (:public_id, :user_id, :slug, :name, :tagline, :description, :category,
                 :logo, :banner, :currency, :zones, :methods, :free, :prep, :cod, \'draft\')'
        );
        $stmt->execute([
            'public_id'  => $publicId,
            'user_id'    => $userId,
            'slug'       => $d['slug'],
            'name'       => $d['name'],
            'tagline'    => $d['tagline'],
            'description'=> $d['description'],
            'category'   => $d['category'],
            'logo'       => $d['logo_public_id'],
            'banner'     => $d['banner_public_id'],
            'currency'   => $d['currency'],
            'zones'      => $d['delivery_zones'],
            'methods'    => $d['delivery_methods'],
            'free'       => $d['free_ship_cents'],
            'prep'       => $d['prep_time'],
            'cod'        => $d['cod_enabled'] ? 1 : 0,
        ]);
        return $publicId;
    }

    public static function findByUserId(int $userId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM boutiques WHERE user_id = :uid ORDER BY id LIMIT 1');
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findBySlug(string $slug): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM boutiques WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $slug]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = db()->prepare('UPDATE boutiques SET status = :s WHERE id = :id');
        $stmt->execute(['s' => $status, 'id' => $id]);
    }
}
