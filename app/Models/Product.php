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
        ddl_safe(
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
                affiliate_enabled  TINYINT(1) NOT NULL DEFAULT 0,
                affiliate_rate_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_products_boutique (boutique_id, status, position)
            )'
        );
        ddl_safe(
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
        ProductVariant::ensureTable();
        self::migrateAffiliation();
        self::migrate(); // colonnes promo/épinglé/rayon : garanties même avant toute vue catalogue
    }

    /** Ajoute les colonnes d'affiliation par produit aux tables existantes. Mémoïsé. */
    private static function migrateAffiliation(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            db()->query('SELECT affiliate_enabled FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products
                    ADD COLUMN affiliate_enabled  TINYINT(1) NOT NULL DEFAULT 0,
                    ADD COLUMN affiliate_rate_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
    }

    /** @param list<array{public_id:string,width:?int,height:?int}> $photos */
    public static function create(int $boutiqueId, int $userId, array $data, array $photos): string
    {
        self::ensureTables();
        $pdo = db();
        $publicId = uuid();
        $pdo->beginTransaction();
        try {
            // Colonnes de base (toujours présentes) + colonnes optionnelles écrites
            // SEULEMENT si elles existent : la création ne casse jamais même si la base
            // n'a pas (encore) été migrée (ex. droits ALTER restreints sur TiDB).
            $cols = [
                'public_id' => $publicId, 'boutique_id' => $boutiqueId, 'user_id' => $userId,
                'name' => $data['name'], 'description' => $data['description'],
                'price_cents' => $data['price_cents'], 'stock' => $data['stock'],
                'status' => $data['status'], 'position' => time() % 100000000,
            ];
            $cols += self::optionalColumns($data);
            $names = array_keys($cols);
            $pdo->prepare('INSERT INTO products (`' . implode('`,`', $names) . '`) VALUES (:' . implode(', :', $names) . ')')->execute($cols);
            $productId = (int) $pdo->lastInsertId();
            self::replacePhotos($pdo, $productId, $photos);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // Variante par défaut (hors transaction : CREATE TABLE = commit implicite).
        // Reprend le stock/prix du produit ; base du stock partagé online + POS.
        if ($productId > 0) {
            ProductVariant::ensureDefault($productId, $boutiqueId, $data['stock'] ?? null, (int) ($data['price_cents'] ?? 0));
        }
        return $publicId;
    }

    public static function update(int $id, array $data): void
    {
        // Mêmes colonnes de base + optionnelles (uniquement si présentes) que create().
        $set = [
            'name' => $data['name'], 'description' => $data['description'],
            'price_cents' => $data['price_cents'], 'stock' => $data['stock'], 'status' => $data['status'],
        ] + self::optionalColumns($data);
        $assign = implode(', ', array_map(static fn (string $c): string => "`$c` = :$c", array_keys($set)));
        $set['id'] = $id;
        db()->prepare("UPDATE products SET {$assign} WHERE id = :id")->execute($set);
    }

    /** Colonnes produit OPTIONNELLES, incluses seulement si elles existent en base. */
    private static function optionalColumns(array $data): array
    {
        $exists = self::existingColumns();
        $candidates = [
            'promo_price_cents' => $data['promo_price_cents'] ?? null,
            'promo_until'       => $data['promo_until'] ?? null,
            'audience'          => $data['audience'] ?? null,
            'garment_category'  => $data['garment_category'] ?? null,
            'sale_unit'         => $data['sale_unit'] ?? 'piece',
            'brand'             => $data['brand'] ?? null,
            'model'             => $data['model'] ?? null,
            'item_condition'    => $data['item_condition'] ?? null,
            'product_type'      => $data['product_type'] ?? null,
            'volume'            => $data['volume'] ?? null,
            'volume_unit'       => $data['volume_unit'] ?? null,
            'finish'            => $data['finish'] ?? null,
            'skin_type'         => $data['skin_type'] ?? null,
            'coverage'          => $data['coverage'] ?? null,
            'pao'               => $data['pao'] ?? null,
            'expiry_date'       => $data['expiry_date'] ?? null,
            'ean'               => $data['ean'] ?? null,
            'sku'               => $data['sku'] ?? null,
            'atouts'            => $data['atouts'] ?? null,
            'ingredients'       => $data['ingredients'] ?? null,
            'line'              => $data['line'] ?? null,
            'attributes'        => $data['attributes'] ?? null,
            'video_public_id'   => $data['video_public_id'] ?? null,
            'video_duration'    => $data['video_duration'] ?? null,
        ];
        $out = [];
        foreach ($candidates as $col => $val) {
            if (isset($exists[$col])) {
                $out[$col] = $val;
            }
        }
        return $out;
    }

    /** Colonnes RÉELLES de la table products (mémoïsé) — pour ne jamais écrire une colonne absente. */
    private static function existingColumns(): array
    {
        static $cols = null;
        if ($cols === null) {
            $cols = [];
            try {
                foreach (db()->query('SHOW COLUMNS FROM products')->fetchAll() ?: [] as $r) {
                    $cols[(string) ($r['Field'] ?? '')] = true;
                }
            } catch (\Throwable) {
                $cols = [];
            }
        }
        return $cols;
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
        // Rayon / catégorie du produit (le vendeur range ses articles).
        try {
            db()->query('SELECT collection FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products ADD COLUMN collection VARCHAR(60) NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Promotion : prix réduit (NULL = pas de promo) + fin facultative (NULL = sans limite).
        try {
            db()->query('SELECT promo_price_cents FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products ADD COLUMN promo_price_cents BIGINT UNSIGNED NULL, ADD COLUMN promo_until DATETIME NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Prêt-à-porter : genre (homme/femme/unisexe/enfant), catégorie de vêtement,
        // et unité de vente ('piece' ou 'meter' pour les tissus/pagnes au mètre).
        try {
            db()->query('SELECT garment_category FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec("ALTER TABLE products
                    ADD COLUMN audience VARCHAR(12) NULL,
                    ADD COLUMN garment_category VARCHAR(40) NULL,
                    ADD COLUMN sale_unit VARCHAR(8) NOT NULL DEFAULT 'piece'");
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Téléphones / électronique : marque, modèle, état (neuf/occasion/reconditionné).
        try {
            db()->query('SELECT brand FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products
                    ADD COLUMN brand VARCHAR(60) NULL,
                    ADD COLUMN model VARCHAR(80) NULL,
                    ADD COLUMN item_condition VARCHAR(20) NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Beauté & cosmétiques : type de produit, contenance + unité, finition,
        // type de peau, couvrance, PAO, péremption, EAN, SKU, atouts (CSV), INCI.
        try {
            db()->query('SELECT product_type FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec("ALTER TABLE products
                    ADD COLUMN product_type VARCHAR(40) NULL,
                    ADD COLUMN volume DECIMAL(8,2) NULL,
                    ADD COLUMN volume_unit VARCHAR(8) NULL,
                    ADD COLUMN finish VARCHAR(20) NULL,
                    ADD COLUMN skin_type VARCHAR(20) NULL,
                    ADD COLUMN coverage VARCHAR(12) NULL,
                    ADD COLUMN pao VARCHAR(8) NULL,
                    ADD COLUMN expiry_date DATE NULL,
                    ADD COLUMN ean VARCHAR(20) NULL,
                    ADD COLUMN sku VARCHAR(40) NULL,
                    ADD COLUMN atouts VARCHAR(255) NULL,
                    ADD COLUMN ingredients TEXT NULL");
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Beauté v2 : caractéristiques propres au type (JSON souple) + gamme/ligne.
        try {
            db()->query('SELECT line FROM products LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE products
                    ADD COLUMN line VARCHAR(80) NULL,
                    ADD COLUMN attributes TEXT NULL');
            } catch (\Throwable) {
                // déjà migré
            }
        }
        // Couche variantes (stock partagé online + POS) : création idempotente,
        // protégée pour ne jamais casser une page publique si elle échoue.
        try { ProductVariant::ensureTable(); } catch (\Throwable) {}
    }

    /** Enregistre le rayon (catégorie) d'un produit. Best-effort (colonne facultative). */
    public static function setCollection(int $id, ?string $collection): void
    {
        $c = trim((string) ($collection ?? ''));
        try {
            db()->prepare('UPDATE products SET collection = :c WHERE id = :id')
                ->execute(['c' => $c !== '' ? mb_substr($c, 0, 60) : null, 'id' => $id]);
        } catch (\Throwable) {
            // colonne collection non provisionnée : sans gravité
        }
    }

    /** Rayons (catégories) distincts d'une boutique. @return list<string> */
    public static function collectionsFor(int $boutiqueId, bool $activeOnly = true): array
    {
        try {
            $sql = "SELECT DISTINCT collection FROM products
                     WHERE boutique_id = :b AND collection IS NOT NULL AND collection <> ''";
            if ($activeOnly) {
                $sql .= " AND status = 'active'";
            }
            $stmt = db()->prepare($sql . ' ORDER BY collection');
            $stmt->execute(['b' => $boutiqueId]);
            return array_values(array_map('strval', array_column($stmt->fetchAll() ?: [], 'collection')));
        } catch (\Throwable) {
            return [];
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

    /** Produits récents du marketplace (vitrines publiées) pour l'accueil — en stock d'abord. @return list<array> */
    public static function recentMarketplace(int $limit = 12): array
    {
        self::migrate();
        try {
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status = 'active' AND b.status = 'published'
                  ORDER BY (p.stock > 0) DESC, p.id DESC LIMIT " . max(1, min(48, $limit))
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

    public static function findById(int $id): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
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

    /**
     * Produits AFFILIÉS (réglés produit par produit) des boutiques publiées, pour
     * l'annuaire/catalogue « à partager ». Filtres : q (nom), category, boutique (slug), sort.
     * @return list<array> p.* + boutique_slug/name/currency/category + affiliate_rate_bps
     */
    public static function participating(int $limit = 12, array $filters = []): array
    {
        try {
            $where = ["b.status = 'published'", "p.status = 'active'", 'p.affiliate_enabled = 1'];
            $args  = [];
            if (!empty($filters['q'])) {
                $where[] = 'p.name LIKE :q';
                $args['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']) . '%';
            }
            if (!empty($filters['category'])) {
                $where[] = 'b.category = :cat';
                $args['cat'] = (string) $filters['category'];
            }
            if (!empty($filters['boutique'])) {
                $where[] = 'b.slug = :bs';
                $args['bs'] = (string) $filters['boutique'];
            }
            $order = match ((string) ($filters['sort'] ?? '')) {
                'price_asc'   => 'p.price_cents ASC',
                'price_desc'  => 'p.price_cents DESC',
                'commission'  => 'p.affiliate_rate_bps DESC, p.price_cents DESC',
                default       => 'p.id DESC',
            };
            $sql = "SELECT p.id, p.public_id, p.name, p.price_cents, p.promo_price_cents, p.promo_until, p.affiliate_rate_bps,
                           b.slug AS boutique_slug, b.name AS boutique_name, b.currency AS currency, b.category AS boutique_category
                      FROM products p JOIN boutiques b ON b.id = p.boutique_id
                     WHERE " . implode(' AND ', $where) . " ORDER BY {$order} LIMIT " . max(1, min(60, $limit));
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Réglages d'affiliation d'un produit. @return array{enabled:bool, bps:int} */
    public static function affiliationOf(int $productId): array
    {
        try {
            $st = db()->prepare('SELECT affiliate_enabled, affiliate_rate_bps FROM products WHERE id = :id LIMIT 1');
            $st->execute(['id' => $productId]);
            $r = $st->fetch();
            if ($r === false) {
                return ['enabled' => false, 'bps' => 0];
            }
            return ['enabled' => (bool) (int) $r['affiliate_enabled'], 'bps' => affiliate_clamp_bps((int) $r['affiliate_rate_bps'])];
        } catch (\Throwable) {
            return ['enabled' => false, 'bps' => 0];
        }
    }

    /** Réglages d'affiliation pour un lot de produits (calcul de commission par article).
     *  @return array<int, array{enabled:bool, bps:int}> */
    public static function affiliationMapFor(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($ids === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = db()->prepare("SELECT id, affiliate_enabled, affiliate_rate_bps FROM products WHERE id IN ($in)");
            $st->execute($ids);
            $map = [];
            foreach ($st->fetchAll() ?: [] as $r) {
                $map[(int) $r['id']] = ['enabled' => (bool) (int) $r['affiliate_enabled'], 'bps' => affiliate_clamp_bps((int) $r['affiliate_rate_bps'])];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Active/désactive l'affiliation d'un produit + fixe le taux (bps borné). Appartenance vérifiée. */
    public static function setAffiliation(int $productId, int $ownerUserId, bool $enabled, int $bps): bool
    {
        self::ensureTables();
        try {
            $st = db()->prepare('UPDATE products SET affiliate_enabled = :e, affiliate_rate_bps = :b WHERE id = :id AND user_id = :u');
            $st->execute(['e' => $enabled ? 1 : 0, 'b' => affiliate_clamp_bps($bps), 'id' => $productId, 'u' => $ownerUserId]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Produits d'une boutique + leurs réglages d'affiliation (écran vendeur). @return list<array> */
    public static function forBoutiqueAffiliation(int $boutiqueId): array
    {
        self::ensureTables();
        try {
            $st = db()->prepare('SELECT id, public_id, name, price_cents, status, affiliate_enabled, affiliate_rate_bps
                                   FROM products WHERE boutique_id = :b ORDER BY position ASC, id DESC');
            $st->execute(['b' => $boutiqueId]);
            return $st->fetchAll() ?: [];
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
    /**
     * Recherche insensible à la CASSE et aux ACCENTS, indépendante de la
     * collation de la base (les colonnes TiDB sont en utf8mb4_bin, où LIKE est
     * sinon sensible casse + accents). On replie les accents sur la lettre de
     * base, des deux côtés : terme (PHP) et colonne (SQL).
     */
    private const SEARCH_FOLD = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'ò' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ñ' => 'n', 'ÿ' => 'y', 'ý' => 'y',
    ];

    /** Normalise un terme côté PHP : minuscules + sans accents. */
    private static function searchFold(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), self::SEARCH_FOLD);
    }

    /** Expression SQL équivalente à searchFold() pour une colonne. */
    private static function foldExpr(string $col): string
    {
        $expr = $col;
        foreach (self::SEARCH_FOLD as $lower => $base) {
            $upper = mb_strtoupper($lower, 'UTF-8');
            $expr  = "REPLACE($expr, '$lower', '$base')";
            $expr  = "REPLACE($expr, '$upper', '$base')";
        }
        return "LOWER($expr)";
    }

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
            // Insensible casse + accents (voir foldExpr). Placeholders distincts
            // (EMULATE_PREPARES=false interdit la réutilisation).
            $where[] = '(' . self::foldExpr('p.name') . ' LIKE :q'
                . ' OR ' . self::foldExpr('p.description') . ' LIKE :q2'
                . ' OR ' . self::foldExpr('b.name') . ' LIKE :q3)';
            $like = '%' . self::searchFold($q) . '%';
            $args['q'] = $like; $args['q2'] = $like; $args['q3'] = $like;
        }
        if ($cat !== '') { $where[] = 'b.category = :cat'; $args['cat'] = $cat; }
        if ($pays !== '') { $where[] = 'b.country_code = :pays'; $args['pays'] = $pays; }
        if ($city !== '') { $where[] = self::foldExpr('b.city') . ' LIKE :city'; $args['city'] = '%' . self::searchFold($city) . '%'; }
        if (!empty($f['in_stock'])) { $where[] = '(p.stock IS NULL OR p.stock > 0)'; }
        if ($min !== null) { $where[] = 'p.price_cents >= :pmin'; $args['pmin'] = $min * 100; }
        if ($max !== null) { $where[] = 'p.price_cents <= :pmax'; $args['pmax'] = $max * 100; }
        $aud = (string) ($f['audience'] ?? '');
        if ($aud !== '') { $where[] = 'p.audience = :aud'; $args['aud'] = $aud; }
        $garment = (string) ($f['garment'] ?? '');
        if ($garment !== '') { $where[] = 'p.garment_category = :garment'; $args['garment'] = $garment; }

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

    /** Cale le stock total du produit (= somme des variantes ; null = illimité). */
    public static function setStock(int $id, ?int $stock): void
    {
        try {
            db()->prepare('UPDATE products SET stock = :s WHERE id = :id')->execute(['s' => $stock, 'id' => $id]);
        } catch (\Throwable) {
        }
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM product_photos WHERE product_id = :id')->execute(['id' => $id]);
        db()->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
    }
}
