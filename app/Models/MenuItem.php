<?php
declare(strict_types=1);

namespace App\Models;

/**
 * menu_categories / menu_items — la carte d'un restaurant. Une catégorie
 * regroupe des plats ordonnés. Prix en centimes + devise du restaurant.
 * Tables auto-créées (TiDB).
 */
final class MenuItem
{
    public static function ensureTables(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS menu_categories (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id     CHAR(36) NOT NULL UNIQUE,
                restaurant_id BIGINT UNSIGNED NOT NULL,
                name          VARCHAR(60) NOT NULL,
                kind          VARCHAR(10) NOT NULL DEFAULT \'dish\',
                position      INT NOT NULL DEFAULT 0,
                KEY idx_mcat_restaurant (restaurant_id, position)
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS menu_items (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id     CHAR(36) NOT NULL UNIQUE,
                restaurant_id BIGINT UNSIGNED NOT NULL,
                category_id   BIGINT UNSIGNED NOT NULL,
                name          VARCHAR(80) NOT NULL,
                description   VARCHAR(400) NULL,
                price_cents   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                variants      VARCHAR(255) NULL,
                photo_public_id VARCHAR(255) NULL,
                diets         VARCHAR(120) NULL,
                is_available  TINYINT(1) NOT NULL DEFAULT 1,
                position      INT NOT NULL DEFAULT 0,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_mitem_restaurant (restaurant_id, category_id, position)
            )'
        );
        // Migrations douces (colonnes ajoutées aux tables déjà créées).
        try {
            db()->query('SELECT kind FROM menu_categories LIMIT 1');
        } catch (\Throwable) {
            try { db()->exec('ALTER TABLE menu_categories ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT \'dish\''); } catch (\Throwable) {}
        }
        try {
            db()->query('SELECT variants FROM menu_items LIMIT 1');
        } catch (\Throwable) {
            try { db()->exec('ALTER TABLE menu_items ADD COLUMN variants VARCHAR(255) NULL'); } catch (\Throwable) {}
        }
    }

    /* ---- Catégories ------------------------------------------------- */

    public static function createCategory(int $restaurantId, string $name, string $kind = 'dish'): string
    {
        self::ensureTables();
        $pid = uuid();
        db()->prepare('INSERT INTO menu_categories (public_id, restaurant_id, name, kind, position) VALUES (:p, :r, :n, :k, :pos)')
            ->execute(['p' => $pid, 'r' => $restaurantId, 'n' => $name, 'k' => $kind, 'pos' => time() % 100000000]);
        return $pid;
    }

    /** @return list<array> catégories d'un restaurant, ordonnées */
    public static function categories(int $restaurantId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM menu_categories WHERE restaurant_id = :r ORDER BY position, id');
            $stmt->execute(['r' => $restaurantId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findCategory(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM menu_categories WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function renameCategory(int $id, string $name): void
    {
        db()->prepare('UPDATE menu_categories SET name = :n WHERE id = :id')
            ->execute(['n' => mb_substr($name, 0, 60), 'id' => $id]);
    }

    /** Une catégorie du même nom existe-t-elle déjà (insensible à la casse) ? */
    public static function categoryNameExists(int $restaurantId, string $name, ?int $exceptId = null): bool
    {
        $needle = mb_strtolower(trim($name));
        foreach (self::categories($restaurantId) as $c) {
            if ((int) $c['id'] !== (int) $exceptId && mb_strtolower((string) $c['name']) === $needle) {
                return true;
            }
        }
        return false;
    }

    public static function deleteCategory(int $id): void
    {
        db()->prepare('DELETE FROM menu_items WHERE category_id = :id')->execute(['id' => $id]);
        db()->prepare('DELETE FROM menu_categories WHERE id = :id')->execute(['id' => $id]);
    }

    /* ---- Plats ------------------------------------------------------ */

    public static function createItem(array $d): string
    {
        self::ensureTables();
        $pid = uuid();
        $stmt = db()->prepare(
            'INSERT INTO menu_items (public_id, restaurant_id, category_id, name, description,
                price_cents, variants, photo_public_id, diets, is_available, position)
             VALUES (:p, :r, :c, :n, :desc, :price, :variants, :photo, :diets, :avail, :pos)'
        );
        $stmt->execute([
            'p' => $pid, 'r' => $d['restaurant_id'], 'c' => $d['category_id'], 'n' => $d['name'],
            'desc' => $d['description'] ?? null, 'price' => $d['price_cents'],
            'variants' => $d['variants'] ?? null,
            'photo' => $d['photo_public_id'] ?? null, 'diets' => $d['diets'] ?? null,
            'avail' => !empty($d['is_available']) ? 1 : 0, 'pos' => time() % 100000000,
        ]);
        return $pid;
    }

    public static function updateItem(int $id, array $d): void
    {
        $stmt = db()->prepare(
            'UPDATE menu_items SET category_id = :c, name = :n, description = :desc,
                price_cents = :price, variants = :variants, photo_public_id = :photo, diets = :diets, is_available = :avail
             WHERE id = :id'
        );
        $stmt->execute([
            'c' => $d['category_id'], 'n' => $d['name'], 'desc' => $d['description'] ?? null,
            'price' => $d['price_cents'], 'variants' => $d['variants'] ?? null, 'photo' => $d['photo_public_id'] ?? null,
            'diets' => $d['diets'] ?? null, 'avail' => !empty($d['is_available']) ? 1 : 0, 'id' => $id,
        ]);
    }

    /** Décode les contenances d'une boisson. @return list<array{v:string,p:int,out:bool}> */
    public static function variants(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $out = [];
        foreach (json_decode($raw, true) ?: [] as $row) {
            if (isset($row['v'], $row['p'])) {
                $out[] = ['v' => (string) $row['v'], 'p' => (int) $row['p'], 'out' => !empty($row['out'])];
            }
        }
        return $out;
    }

    /**
     * Marque une contenance épuisée (ou de retour) et recalcule le prix de
     * base = la moins chère des contenances encore disponibles (sinon de
     * toutes). Vrai si la contenance existait.
     */
    public static function setVariantOut(int $itemId, string $vol, bool $isOut): bool
    {
        try {
            $stmt = db()->prepare('SELECT variants FROM menu_items WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $itemId]);
            $raw = $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        $vars = self::variants(is_string($raw) ? $raw : null);
        $found = false;
        foreach ($vars as &$r) {
            if ($r['v'] === $vol) {
                $r['out'] = $isOut;
                $found = true;
            }
        }
        unset($r);
        if (!$found) {
            return false;
        }
        $available = array_values(array_filter($vars, static fn (array $r): bool => !$r['out']));
        $minSource = $available !== [] ? $available : $vars;
        $min = min(array_column($minSource, 'p'));
        $rows = array_map(static fn (array $r): array => ['v' => $r['v'], 'p' => $r['p'], 'out' => $r['out'] ? 1 : 0], $vars);
        db()->prepare('UPDATE menu_items SET variants = :v, price_cents = :p WHERE id = :id')
            ->execute(['v' => json_encode($rows, JSON_UNESCAPED_UNICODE), 'p' => $min, 'id' => $itemId]);
        return true;
    }

    /** @return list<array> plats d'un restaurant (option : seulement disponibles) */
    public static function forRestaurant(int $restaurantId, bool $availableOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM menu_items WHERE restaurant_id = :r';
            if ($availableOnly) { $sql .= ' AND is_available = 1'; }
            $sql .= ' ORDER BY position, id';
            $stmt = db()->prepare($sql);
            $stmt->execute(['r' => $restaurantId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findItem(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM menu_items WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setAvailable(int $id, bool $available): void
    {
        db()->prepare('UPDATE menu_items SET is_available = :a WHERE id = :id')
            ->execute(['a' => $available ? 1 : 0, 'id' => $id]);
    }

    public static function deleteItem(int $id): void
    {
        db()->prepare('DELETE FROM menu_items WHERE id = :id')->execute(['id' => $id]);
    }

    public static function countFor(int $restaurantId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT COALESCE(SUM(is_available),0) AS available, COUNT(*) AS total
                   FROM menu_items WHERE restaurant_id = :r"
            );
            $stmt->execute(['r' => $restaurantId]);
            $r = $stmt->fetch() ?: [];
            return ['available' => (int) ($r['available'] ?? 0), 'total' => (int) ($r['total'] ?? 0)];
        } catch (\Throwable) {
            return ['available' => 0, 'total' => 0];
        }
    }
}
