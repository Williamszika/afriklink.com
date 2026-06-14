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
        ddl_safe(
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
                payment_terms    VARCHAR(80) NULL,
                payment_methods  VARCHAR(120) NULL,
                payment_provider VARCHAR(20) NULL,
                contact_whatsapp  VARCHAR(120) NULL,
                contact_sms       VARCHAR(120) NULL,
                contact_telegram  VARCHAR(120) NULL,
                contact_facebook  VARCHAR(160) NULL,
                contact_instagram VARCHAR(120) NULL,
                contact_tiktok    VARCHAR(120) NULL,
                contact_primary   VARCHAR(80) NULL,
                status           VARCHAR(12) NOT NULL DEFAULT \'draft\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_boutiques_user (user_id)
            )'
        );
        // Bannière = diaporama : jusqu'à 10 images (identifiants Cloudinary).
        ddl_safe(
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
            db()->query('SELECT payment_terms FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN payment_terms   VARCHAR(80) NULL,
                    ADD COLUMN payment_methods VARCHAR(120) NULL,
                    ADD COLUMN payment_provider VARCHAR(20) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
        try {
            db()->query('SELECT payment_provider FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques ADD COLUMN payment_provider VARCHAR(20) NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        try {
            db()->query('SELECT delivery_fee_cents FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN delivery_fee_cents  BIGINT UNSIGNED NULL,
                    ADD COLUMN delivery_intl_cents BIGINT UNSIGNED NULL,
                    ADD COLUMN delivery_delay      VARCHAR(16) NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        try {
            db()->query('SELECT return_policy FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques ADD COLUMN return_policy TEXT NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        try {
            db()->query('SELECT announcement FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN announcement    VARCHAR(200) NULL,
                    ADD COLUMN is_vacation     TINYINT(1) NOT NULL DEFAULT 0,
                    ADD COLUMN vacation_until  DATE NULL,
                    ADD COLUMN open_hours       VARCHAR(120) NULL,
                    ADD COLUMN min_order_cents  BIGINT UNSIGNED NULL,
                    ADD COLUMN accent_color     VARCHAR(9) NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Horaires structurés (JSON par jour) + pause des commandes hors horaires.
        try {
            db()->query('SELECT hours_json FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN hours_json          VARCHAR(400) NULL,
                    ADD COLUMN orders_within_hours TINYINT(1) NOT NULL DEFAULT 0');
            } catch (\Throwable) {
                // déjà migré
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
                    ADD COLUMN contact_primary   VARCHAR(80) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
        // Élargit contact_primary (liste de canaux principaux) si encore en VARCHAR(12).
        try {
            $len = (int) db()->query(
                "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boutiques'
                    AND COLUMN_NAME = 'contact_primary'"
            )->fetchColumn();
            if ($len > 0 && $len < 80) {
                db()->exec('ALTER TABLE boutiques MODIFY contact_primary VARCHAR(80) NULL');
            }
        } catch (\Throwable) {
            // information_schema indisponible : on tentera plus tard
        }
        // Affiliation opt-in par boutique : activation + taux de commission (apporteur).
        try {
            db()->query('SELECT affiliation_enabled FROM boutiques LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE boutiques
                    ADD COLUMN affiliation_enabled  TINYINT(1) NOT NULL DEFAULT 0,
                    ADD COLUMN affiliation_rate_pct TINYINT UNSIGNED NOT NULL DEFAULT 5');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
    }

    /** Met à jour la politique de retour (panneau « gérer », sans toucher au reste). */
    public static function updatePolicy(int $id, ?string $returnPolicy): void
    {
        self::ensureTable();
        db()->prepare('UPDATE boutiques SET return_policy = :rp WHERE id = :id')
            ->execute(['rp' => $returnPolicy, 'id' => $id]);
    }

    /* ---- Affiliation (opt-in par boutique) ------------------------------- */

    /** Borne un taux de commission d'affiliation dans [1, 30] %, défaut 5. */
    public static function clampAffiliationRate(int $rate): int
    {
        return max(1, min(30, $rate > 0 ? $rate : 5));
    }

    /**
     * Réglages d'affiliation d'une boutique. Défensif : si la colonne n'existe
     * pas encore (migration en attente) ou erreur, l'affiliation est inactive.
     * @return array{enabled:bool, rate:int}
     */
    public static function affiliationOf(int $boutiqueId): array
    {
        try {
            $stmt = db()->prepare('SELECT affiliation_enabled, affiliation_rate_pct FROM boutiques WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $boutiqueId]);
            $row = $stmt->fetch();
            if ($row === false) {
                return ['enabled' => false, 'rate' => 5];
            }
            return [
                'enabled' => (bool) (int) $row['affiliation_enabled'],
                'rate'    => self::clampAffiliationRate((int) $row['affiliation_rate_pct']),
            ];
        } catch (\Throwable) {
            return ['enabled' => false, 'rate' => 5];
        }
    }

    /** Active/désactive le programme + fixe le taux. L'appartenance est vérifiée en SQL. */
    public static function setAffiliation(int $boutiqueId, int $ownerUserId, bool $enabled, int $rate): bool
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                'UPDATE boutiques SET affiliation_enabled = :e, affiliation_rate_pct = :r
                  WHERE id = :id AND user_id = :u'
            );
            $stmt->execute([
                'e' => $enabled ? 1 : 0, 'r' => self::clampAffiliationRate($rate),
                'id' => $boutiqueId, 'u' => $ownerUserId,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Annuaire : boutiques publiées ayant activé l'affiliation, taux décroissant. @return list<array> */
    public static function participating(int $limit = 60): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                "SELECT id, user_id, slug, name, tagline, category, logo_public_id, city, country_code, affiliation_rate_pct
                   FROM boutiques
                  WHERE status = 'published' AND affiliation_enabled = 1
                  ORDER BY affiliation_rate_pct DESC, id DESC LIMIT " . max(1, min(100, $limit))
            );
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Construit les paramètres SQL des canaux de contact à partir de
     * $d['contacts'] (assoc canal=>valeur) et $d['contact_primary'].
     * @return array<string,?string>
     */
    /** Liste (array ou chaîne) → CSV propre, ou null si vide. */
    private static function csv(array|string $v): ?string
    {
        if (is_string($v)) {
            $v = array_filter(array_map('trim', explode(',', $v)));
        }
        $v = array_values(array_filter(array_map('strval', $v), static fn ($x): bool => $x !== ''));
        return $v !== [] ? implode(',', $v) : null;
    }

    private static function contactParams(array $d): array
    {
        $contacts = $d['contacts'] ?? [];
        $out = [];
        foreach (\App\Services\ContactChannels::CHANNELS as $ch) {
            $out['c_' . $ch] = $contacts[$ch] ?? null;
        }
        // Canaux principaux : liste (array ou chaîne), gardés dans l'ordre
        // d'affichage et limités aux canaux réellement renseignés.
        $primary = $d['contact_primary'] ?? [];
        if (is_string($primary)) {
            $primary = array_filter(array_map('trim', explode(',', $primary)));
        }
        $primary = array_values(array_intersect(
            \App\Services\ContactChannels::CHANNELS,
            array_filter((array) $primary, static fn ($ch): bool => isset($contacts[$ch]))
        ));
        $out['c_primary'] = $primary !== [] ? implode(',', $primary) : null;
        return $out;
    }

    /** @return list<array> boutiques publiées récentes (pour la vitrine d'accueil) */
    public static function recentPublished(int $limit = 12): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT id, user_id, slug, name, tagline, category, logo_public_id
                   FROM boutiques WHERE status = 'published' ORDER BY id DESC LIMIT " . max(1, min(48, $limit))
            );
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Boutiques « à la une » de l'espace publicitaire : vitrines publiées ayant
     * au moins un produit actuellement sponsorisé (mise en avant non expirée).
     * @return list<array>
     */
    public static function spotlight(int $limit = 12): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT DISTINCT b.id, b.user_id, b.slug, b.name, b.tagline, b.category, b.logo_public_id
                   FROM boutiques b JOIN products p ON p.boutique_id = b.id
                  WHERE b.status = 'published' AND p.status = 'active'
                    AND p.promoted_until IS NOT NULL AND p.promoted_until > NOW()
                  ORDER BY b.id DESC LIMIT " . max(1, min(24, $limit))
            );
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
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
                     delivery_zones, delivery_methods, free_ship_cents, delivery_fee_cents, delivery_intl_cents, delivery_delay, prep_time, cod_enabled,
                     payment_terms, payment_methods, payment_provider,
                     contact_whatsapp, contact_sms, contact_telegram, contact_facebook,
                     contact_instagram, contact_tiktok, contact_primary, status)
                 VALUES
                    (:public_id, :user_id, :slug, :name, :tagline, :description, :category,
                     :logo, :banner, :currency, :shop_type, :address,
                     :city, :cc, :continent, :lat, :lng,
                     :zones, :methods, :free, :dfee, :dintl, :ddelay, :prep, :cod,
                     :pay_terms, :pay_methods, :pay_provider,
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
                'dfee'       => $d['delivery_fee_cents'] ?? null,
                'dintl'      => $d['delivery_intl_cents'] ?? null,
                'ddelay'     => $d['delivery_delay'] ?? null,
                'prep'       => $d['prep_time'],
                'cod'        => $d['cod_enabled'] ? 1 : 0,
                'pay_terms'  => self::csv($d['payment_terms'] ?? []),
                'pay_methods'=> self::csv($d['payment_methods'] ?? []),
                'pay_provider' => $d['payment_provider'] ?? null,
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
                delivery_methods = :methods, free_ship_cents = :free,
                delivery_fee_cents = :dfee, delivery_intl_cents = :dintl, delivery_delay = :ddelay,
                prep_time = :prep, cod_enabled = :cod,
                payment_terms = :pay_terms, payment_methods = :pay_methods, payment_provider = :pay_provider,
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
            'dfee' => $d['delivery_fee_cents'] ?? null, 'dintl' => $d['delivery_intl_cents'] ?? null, 'ddelay' => $d['delivery_delay'] ?? null,
            'prep' => $d['prep_time'], 'cod' => $d['cod_enabled'] ? 1 : 0, 'id' => $id,
            'pay_terms' => self::csv($d['payment_terms'] ?? []), 'pay_methods' => self::csv($d['payment_methods'] ?? []),
            'pay_provider' => $d['payment_provider'] ?? null,
        ] + self::contactParams($d));
        self::setBanners($id, $banners);
    }

    /** Met à jour uniquement les colonnes de configuration avancée fournies. */
    public static function updateConfig(int $id, array $cfg): void
    {
        self::ensureTable();
        // Liste blanche stricte (noms de colonnes sûrs, jamais d'entrée client).
        $allowed = ['announcement', 'is_vacation', 'vacation_until', 'open_hours', 'min_order_cents', 'accent_color', 'hours_json', 'orders_within_hours'];
        // Une colonne par requête : si une colonne récente n'est pas encore
        // provisionnée en prod (schéma non migré faute de droits DDL), seule
        // CELLE-LÀ échoue — les autres (couleur d'accent, annonce…) sont bien
        // enregistrées au lieu de faire échouer tout l'UPDATE groupé.
        foreach ($allowed as $c) {
            if (!array_key_exists($c, $cfg)) {
                continue;
            }
            try {
                db()->prepare("UPDATE boutiques SET {$c} = :v WHERE id = :id")
                    ->execute(['v' => $cfg[$c], 'id' => $id]);
            } catch (\Throwable) {
                // Colonne absente (schéma non migré) : on ignore cette clé.
            }
        }
    }
}
