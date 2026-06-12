<?php
declare(strict_types=1);

namespace App\Models;

/**
 * restaurants — vitrine « Restaurant » d'un vendeur (une par compte). Même
 * base que la boutique (géolocalisation, contacts, devise) + spécifique
 * restauration : type de cuisine, modes de service, horaires, livraison.
 * La carte vit dans menu_categories / menu_items (modèle MenuItem). Table
 * auto-créée (TiDB).
 */
final class Restaurant
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS restaurants (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id        CHAR(36) NOT NULL UNIQUE,
                user_id          BIGINT UNSIGNED NOT NULL,
                slug             VARCHAR(48) NOT NULL UNIQUE,
                name             VARCHAR(80) NOT NULL,
                tagline          VARCHAR(140) NULL,
                description      TEXT NULL,
                cuisine          VARCHAR(120) NULL,
                logo_public_id   VARCHAR(255) NULL,
                banner_public_id VARCHAR(255) NULL,
                currency         CHAR(3) NOT NULL DEFAULT \'XOF\',
                services         VARCHAR(60) NULL,
                hours            VARCHAR(160) NULL,
                open_days        VARCHAR(30) NULL,
                open_time        VARCHAR(5) NULL,
                close_time       VARCHAR(5) NULL,
                address          VARCHAR(220) NULL,
                city             VARCHAR(80) NULL,
                country_code     CHAR(2) NULL,
                continent        VARCHAR(16) NULL,
                geo_lat          DECIMAL(9,6) NULL,
                geo_lng          DECIMAL(9,6) NULL,
                delivery_fee_cents BIGINT UNSIGNED NULL,
                delivery_min_cents BIGINT UNSIGNED NULL,
                prep_minutes     INT UNSIGNED NULL,
                contact_whatsapp  VARCHAR(120) NULL,
                contact_phone     VARCHAR(120) NULL,
                payment_terms    VARCHAR(80) NULL,
                payment_methods  VARCHAR(120) NULL,
                payment_provider VARCHAR(20) NULL,
                status           VARCHAR(12) NOT NULL DEFAULT \'draft\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_restaurants_user (user_id)
            )'
        );
        // Élargit cuisine (liste multi-choix) si encore en VARCHAR(20).
        try {
            $len = (int) db()->query(
                "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'restaurants'
                    AND COLUMN_NAME = 'cuisine'"
            )->fetchColumn();
            if ($len > 0 && $len < 120) {
                db()->exec('ALTER TABLE restaurants MODIFY cuisine VARCHAR(120) NULL');
            }
        } catch (\Throwable) {
            // information_schema indisponible : on réessaiera
        }
        // Horaires structurés : jours cochés + heures d'ouverture/fermeture.
        try {
            db()->query('SELECT open_days FROM restaurants LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE restaurants
                    ADD COLUMN open_days  VARCHAR(30) NULL,
                    ADD COLUMN open_time  VARCHAR(5) NULL,
                    ADD COLUMN close_time VARCHAR(5) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
    }

    public static function create(int $userId, array $d): string
    {
        self::ensureTable();
        $publicId = uuid();
        $stmt = db()->prepare(
            'INSERT INTO restaurants
                (public_id, user_id, slug, name, tagline, description, cuisine, currency,
                 services, hours, open_days, open_time, close_time,
                 address, city, country_code, continent, geo_lat, geo_lng,
                 delivery_fee_cents, delivery_min_cents, prep_minutes,
                 contact_whatsapp, contact_phone, status)
             VALUES
                (:pid, :uid, :slug, :name, :tagline, :desc, :cuisine, :cur,
                 :services, :hours, :odays, :otime, :ctime,
                 :address, :city, :cc, :continent, :lat, :lng,
                 :dfee, :dmin, :prep, :wa, :phone, \'draft\')'
        );
        $stmt->execute([
            'pid' => $publicId, 'uid' => $userId, 'slug' => $d['slug'], 'name' => $d['name'],
            'tagline' => $d['tagline'] ?? null, 'desc' => $d['description'] ?? null,
            'cuisine' => $d['cuisine'] ?? null, 'cur' => $d['currency'],
            'services' => $d['services'] ?? null, 'hours' => $d['hours'] ?? null,
            'odays' => $d['open_days'] ?? null, 'otime' => $d['open_time'] ?? null, 'ctime' => $d['close_time'] ?? null,
            'address' => $d['address'] ?? null, 'city' => $d['city'] ?? null,
            'cc' => $d['country_code'] ?? null, 'continent' => $d['continent'] ?? null,
            'lat' => $d['geo_lat'] ?? null, 'lng' => $d['geo_lng'] ?? null,
            'dfee' => $d['delivery_fee_cents'] ?? null, 'dmin' => $d['delivery_min_cents'] ?? null,
            'prep' => $d['prep_minutes'] ?? null, 'wa' => $d['contact_whatsapp'] ?? null,
            'phone' => $d['contact_phone'] ?? null,
        ]);
        return $publicId;
    }

    public static function update(int $id, array $d): void
    {
        self::ensureTable();
        $stmt = db()->prepare(
            'UPDATE restaurants SET name = :name, tagline = :tagline, description = :desc,
                cuisine = :cuisine, currency = :cur, services = :services, hours = :hours,
                open_days = :odays, open_time = :otime, close_time = :ctime,
                address = :address, city = :city, country_code = :cc, continent = :continent,
                geo_lat = :lat, geo_lng = :lng, delivery_fee_cents = :dfee,
                delivery_min_cents = :dmin, prep_minutes = :prep,
                contact_whatsapp = :wa, contact_phone = :phone,
                logo_public_id = :logo, banner_public_id = :banner
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $d['name'], 'tagline' => $d['tagline'] ?? null, 'desc' => $d['description'] ?? null,
            'cuisine' => $d['cuisine'] ?? null, 'cur' => $d['currency'],
            'services' => $d['services'] ?? null, 'hours' => $d['hours'] ?? null,
            'odays' => $d['open_days'] ?? null, 'otime' => $d['open_time'] ?? null, 'ctime' => $d['close_time'] ?? null,
            'address' => $d['address'] ?? null, 'city' => $d['city'] ?? null,
            'cc' => $d['country_code'] ?? null, 'continent' => $d['continent'] ?? null,
            'lat' => $d['geo_lat'] ?? null, 'lng' => $d['geo_lng'] ?? null,
            'dfee' => $d['delivery_fee_cents'] ?? null, 'dmin' => $d['delivery_min_cents'] ?? null,
            'prep' => $d['prep_minutes'] ?? null, 'wa' => $d['contact_whatsapp'] ?? null,
            'phone' => $d['contact_phone'] ?? null,
            'logo' => $d['logo_public_id'] ?? null, 'banner' => $d['banner_public_id'] ?? null,
            'id' => $id,
        ]);
    }

    public static function findByUserId(int $userId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM restaurants WHERE user_id = :u ORDER BY id LIMIT 1');
            $stmt->execute(['u' => $userId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findBySlug(string $slug): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM restaurants WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $slug]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        db()->prepare('UPDATE restaurants SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    }

    public static function slugAvailable(string $slug, ?int $exceptUserId = null): bool
    {
        if (in_array($slug, config('restaurant.slug_reserved', []), true)) {
            return false;
        }
        try {
            $stmt = db()->prepare('SELECT user_id FROM restaurants WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $slug]);
            $row = $stmt->fetch();
            if ($row === false) {
                return true;
            }
            return $exceptUserId !== null && (int) $row['user_id'] === $exceptUserId;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function uniqueSlug(string $base, ?int $exceptUserId = null): string
    {
        $base = substr(slugify($base) ?: 'resto', 0, (int) config('restaurant.slug_max', 40));
        if ($base === '') { $base = 'resto'; }
        $slug = $base;
        $i = 2;
        while (!self::slugAvailable($slug, $exceptUserId)) {
            $slug = $base . '-' . $i;
            if (++$i > 999) { $slug = $base . '-' . substr(uuid(), 0, 6); break; }
        }
        return $slug;
    }
}
