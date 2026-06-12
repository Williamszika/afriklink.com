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
                shop_type        VARCHAR(12) NOT NULL DEFAULT \'online\',
                address          VARCHAR(220) NULL,
                city             VARCHAR(80) NULL,
                country_code     CHAR(2) NULL,
                continent        VARCHAR(16) NULL,
                geo_lat          DECIMAL(9,6) NULL,
                geo_lng          DECIMAL(9,6) NULL,
                delivery_zones   VARCHAR(80) NULL,
                delivery_methods VARCHAR(80) NULL,
                free_ship_cents  BIGINT UNSIGNED NULL,
                prep_time        VARCHAR(16) NULL,
                cod_enabled      TINYINT(1) NOT NULL DEFAULT 1,
                contact_whatsapp  VARCHAR(120) NULL,
                contact_sms       VARCHAR(120) NULL,
                contact_telegram  VARCHAR(120) NULL,
                contact_facebook  VARCHAR(160) NULL,
                contact_instagram VARCHAR(120) NULL,
                contact_tiktok    VARCHAR(120) NULL,
                contact_primary   VARCHAR(12) NULL,
                status           VARCHAR(12) NOT NULL DEFAULT \'draft\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_boutiques_user (user_id)
            )'
        );
        // Bannière = diaporama : jusqu'à 10 images (identifiants Cloudinary).
        db()->exec(
            'CREATE TABLE IF NOT EXISTS boutique_banners (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                boutique_id     BIGINT UNSIGNED NOT NULL,
                cloud_public_id VARCHAR(255) NOT NULL,
                position        TINYINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_banners_boutique (boutique_id, position)
            )'
        );
        self::migrate();
    }

    /** Remplace les bannières (diaporama) d'une boutique. @param list<string> $ids */
    public static function setBanners(int $boutiqueId, array $ids): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM boutique_banners WHERE boutique_id = :b')->execute(['b' => $boutiqueId]);
        $ins = $pdo->prepare('INSERT INTO boutique_banners (boutique_id, cloud_public_id, position) VALUES (:b, :c, :p)');
        foreach (array_values($ids) as $i => $id) {
            if ($i >= (int) config('shop.banner_max', 10)) { break; }
            $ins->execute(['b' => $boutiqueId, 'c' => $id, 'p' => $i]);
        }
        // banner_public_id (colonne) = 1ʳᵉ image, pour les aperçus et le fallback.
        $pdo->prepare('UPDATE boutiques SET banner_public_id = :c WHERE id = :id')
            ->execute(['c' => $ids[0] ?? null, 'id' => $boutiqueId]);
    }

    /** @return list<string> identifiants des bannières, ordonnés */
    public static function banners(int $boutiqueId): array
    {
        try {
            $stmt = db()->prepare('SELECT cloud_public_id FROM boutique_banners WHERE boutique_id = :b ORDER BY position');
            $stmt->execute(['b' => $boutiqueId]);
            return array_map(static fn (array $r): string => (string) $r['cloud_public_id'], $stmt->fetchAll() ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Ajoute shop_type/address puis la géolocalisation (idempotent). */
    private static function migrate(): void
    {
        try {
            db()->query('SELECT shop_type FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN shop_type VARCHAR(12) NOT NULL DEFAULT \'online\',
                    ADD COLUMN address VARCHAR(220) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
        try {
            db()->query('SELECT city FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN city VARCHAR(80) NULL,
                    ADD COLUMN country_code CHAR(2) NULL,
                    ADD COLUMN continent VARCHAR(16) NULL,
                    ADD COLUMN geo_lat DECIMAL(9,6) NULL,
                    ADD COLUMN geo_lng DECIMAL(9,6) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
        try {
            db()->query('SELECT contact_primary FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN contact_whatsapp  VARCHAR(120) NULL,
                    ADD COLUMN contact_sms       VARCHAR(120) NULL,
                    ADD COLUMN contact_telegram  VARCHAR(120) NULL,
                    ADD COLUMN contact_facebook  VARCHAR(160) NULL,
                    ADD COLUMN contact_instagram VARCHAR(120) NULL,
                    ADD COLUMN contact_tiktok    VARCHAR(120) NULL,
                    ADD COLUMN contact_primary   VARCHAR(12) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
    }

    /**
     * Construit les paramètres SQL des canaux de contact à partir de
     * $d['contacts'] (assoc canal=>valeur) et $d['contact_primary'].
     * @return array<string,?string>
     */
    private static function contactParams(array $d): array
    {
        $contacts = $d['contacts'] ?? [];
        $out = [];
        foreach (\App\Services\ContactChannels::CHANNELS as $ch) {
            $out['c_' . $ch] = $contacts[$ch] ?? null;
        }
        $primary = (string) ($d['contact_primary'] ?? '');
        $out['c_primary'] = isset($contacts[$primary]) ? $primary : null;
        return $out;
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
        $banners  = array_values($d['banners'] ?? []);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO boutiques
                    (public_id, user_id, slug, name, tagline, description, category,
                     logo_public_id, banner_public_id, currency, shop_type, address,
                     city, country_code, continent, geo_lat, geo_lng,
                     delivery_zones, delivery_methods, free_ship_cents, prep_time, cod_enabled,
                     contact_whatsapp, contact_sms, contact_telegram, contact_facebook,
                     contact_instagram, contact_tiktok, contact_primary, status)
                 VALUES
                    (:public_id, :user_id, :slug, :name, :tagline, :description, :category,
                     :logo, :banner, :currency, :shop_type, :address,
                     :city, :cc, :continent, :lat, :lng,
                     :zones, :methods, :free, :prep, :cod,
                     :c_whatsapp, :c_sms, :c_telegram, :c_facebook,
                     :c_instagram, :c_tiktok, :c_primary, \'draft\')'
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
                'banner'     => $banners[0] ?? null,
                'currency'   => $d['currency'],
                'shop_type'  => $d['shop_type'],
                'address'    => $d['address'],
                'city'       => $d['city'] ?? null,
                'cc'         => $d['country_code'] ?? null,
                'continent'  => $d['continent'] ?? null,
                'lat'        => $d['geo_lat'] ?? null,
                'lng'        => $d['geo_lng'] ?? null,
                'zones'      => $d['delivery_zones'],
                'methods'    => $d['delivery_methods'],
                'free'       => $d['free_ship_cents'],
                'prep'       => $d['prep_time'],
                'cod'        => $d['cod_enabled'] ? 1 : 0,
            ] + self::contactParams($d));
            $id = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO boutique_banners (boutique_id, cloud_public_id, position) VALUES (:b, :c, :p)');
            foreach ($banners as $i => $bid) {
                if ($i >= (int) config('shop.banner_max', 10)) { break; }
                $ins->execute(['b' => $id, 'c' => $bid, 'p' => $i]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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

    public static function update(int $id, array $d): void
    {
        self::ensureTable(); // applique les migrations (nouvelles colonnes) avant l'UPDATE
        $banners = array_values($d['banners'] ?? []);
        $stmt = db()->prepare(
            'UPDATE boutiques SET
                name = :name, tagline = :tagline, description = :description, category = :category,
                logo_public_id = :logo, banner_public_id = :banner, currency = :currency,
                shop_type = :shop_type, address = :address,
                city = :city, country_code = :cc, continent = :continent, geo_lat = :lat, geo_lng = :lng,
                delivery_zones = :zones,
                delivery_methods = :methods, free_ship_cents = :free, prep_time = :prep, cod_enabled = :cod,
                contact_whatsapp = :c_whatsapp, contact_sms = :c_sms, contact_telegram = :c_telegram,
                contact_facebook = :c_facebook, contact_instagram = :c_instagram, contact_tiktok = :c_tiktok,
                contact_primary = :c_primary
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $d['name'], 'tagline' => $d['tagline'], 'description' => $d['description'],
            'category' => $d['category'], 'logo' => $d['logo_public_id'], 'banner' => $banners[0] ?? null,
            'currency' => $d['currency'], 'shop_type' => $d['shop_type'], 'address' => $d['address'],
            'city' => $d['city'] ?? null, 'cc' => $d['country_code'] ?? null,
            'continent' => $d['continent'] ?? null, 'lat' => $d['geo_lat'] ?? null, 'lng' => $d['geo_lng'] ?? null,
            'zones' => $d['delivery_zones'], 'methods' => $d['delivery_methods'], 'free' => $d['free_ship_cents'],
            'prep' => $d['prep_time'], 'cod' => $d['cod_enabled'] ? 1 : 0, 'id' => $id,
        ] + self::contactParams($d));
        self::setBanners($id, $banners);
    }
}
