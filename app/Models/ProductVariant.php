<?php
declare(strict_types=1);

namespace App\Models;

/**
 * product_variants — déclinaisons d'un produit (taille, couleur…) avec SKU
 * scannable en caisse. Source UNIQUE du stock vendable : online et POS lisent
 * et décrémentent le même `stock` ici (évite la survente entre canaux).
 *
 * Migration douce : tout produit a au moins une variante « par défaut » qui
 * reprend le stock/prix du produit (ensureDefault). Tant que le panier public
 * n'est pas rebranché sur les variantes, products.stock reste la vérité — les
 * variantes vivent en parallèle. Montants en centimes. Table auto-créée.
 */
final class ProductVariant
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS product_variants (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                product_id  BIGINT UNSIGNED NOT NULL,
                boutique_id BIGINT UNSIGNED NOT NULL,
                sku         VARCHAR(64) NULL,
                attributes  JSON NULL,
                label       VARCHAR(120) NULL,
                price_cents BIGINT UNSIGNED NULL,
                stock       INT NULL,
                position    INT NOT NULL DEFAULT 0,
                is_default  TINYINT(1) NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_variants_product (product_id, position),
                KEY idx_variants_boutique (boutique_id),
                KEY idx_variants_sku (sku)
            )'
        );
    }

    /**
     * Garantit la variante par défaut d'un produit (idempotent) : si aucune
     * variante n'existe, en crée une qui reprend le stock/prix du produit.
     * Renvoie l'identifiant de la variante par défaut.
     */
    public static function ensureDefault(int $productId, int $boutiqueId, ?int $stock, int $priceCents): int
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT id FROM product_variants WHERE product_id = :p ORDER BY is_default DESC, position, id LIMIT 1');
            $stmt->execute(['p' => $productId]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
            $publicId = uuid();
            db()->prepare(
                'INSERT INTO product_variants (public_id, product_id, boutique_id, sku, attributes, label, price_cents, stock, position, is_default)
                 VALUES (:pub, :p, :b, :sku, NULL, NULL, :price, :stock, 0, 1)'
            )->execute([
                'pub' => $publicId, 'p' => $productId, 'b' => $boutiqueId,
                'sku' => self::generateSku(), 'price' => $priceCents, 'stock' => $stock,
            ]);
            return (int) db()->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array> variantes d'un produit, ordonnées (défaut/position) */
    public static function forProduct(int $productId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM product_variants WHERE product_id = :p ORDER BY is_default DESC, position, id');
            $stmt->execute(['p' => $productId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM product_variants WHERE public_id = :v LIMIT 1');
            $stmt->execute(['v' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** SKU déjà utilisé dans cette boutique (hors une variante donnée) ? */
    public static function skuTaken(int $boutiqueId, string $sku, ?int $exceptId = null): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }
        try {
            $sql = 'SELECT 1 FROM product_variants WHERE boutique_id = :b AND sku = :s';
            $args = ['b' => $boutiqueId, 's' => $sku];
            if ($exceptId !== null) {
                $sql .= ' AND id <> :x';
                $args['x'] = $exceptId;
            }
            $stmt = db()->prepare($sql . ' LIMIT 1');
            $stmt->execute($args);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array{sku:?string,attributes:?array,label:?string,price_cents:?int,stock:?int,position?:int,is_default?:bool} $d */
    public static function create(int $productId, int $boutiqueId, array $d): string
    {
        self::ensureTable();
        $publicId = uuid();
        db()->prepare(
            'INSERT INTO product_variants (public_id, product_id, boutique_id, sku, attributes, label, price_cents, stock, position, is_default)
             VALUES (:pub, :p, :b, :sku, :attr, :label, :price, :stock, :pos, :def)'
        )->execute([
            'pub' => $publicId, 'p' => $productId, 'b' => $boutiqueId,
            'sku' => $d['sku'] ?? null,
            'attr' => isset($d['attributes']) && $d['attributes'] !== [] ? json_encode($d['attributes']) : null,
            'label' => $d['label'] ?? null, 'price' => $d['price_cents'] ?? null, 'stock' => $d['stock'] ?? null,
            'pos' => (int) ($d['position'] ?? 0), 'def' => !empty($d['is_default']) ? 1 : 0,
        ]);
        return $publicId;
    }

    public static function update(int $id, array $d): void
    {
        try {
            db()->prepare(
                'UPDATE product_variants SET sku = :sku, attributes = :attr, label = :label,
                    price_cents = :price, stock = :stock, position = :pos WHERE id = :id'
            )->execute([
                'sku' => $d['sku'] ?? null,
                'attr' => isset($d['attributes']) && $d['attributes'] !== [] ? json_encode($d['attributes']) : null,
                'label' => $d['label'] ?? null, 'price' => $d['price_cents'] ?? null,
                'stock' => $d['stock'] ?? null, 'pos' => (int) ($d['position'] ?? 0), 'id' => $id,
            ]);
        } catch (\Throwable) {
        }
    }

    public static function delete(int $id): void
    {
        try {
            db()->prepare('DELETE FROM product_variants WHERE id = :id AND is_default = 0')->execute(['id' => $id]);
        } catch (\Throwable) {
        }
    }

    /** Supprime toutes les variantes d'un produit (utilisé pour réécrire la liste). */
    public static function deleteForProduct(int $productId): void
    {
        try {
            db()->prepare('DELETE FROM product_variants WHERE product_id = :p')->execute(['p' => $productId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Décrément de stock atomique et borné (jamais négatif). Stock NULL =
     * illimité (non touché). Renvoie true si décrémenté (ou illimité).
     */
    public static function decrement(int $variantId, int $qty): bool
    {
        try {
            $stmt = db()->prepare(
                'UPDATE product_variants SET stock = stock - :q WHERE id = :id AND stock IS NOT NULL AND stock >= :qmin'
            );
            $stmt->execute(['q' => $qty, 'qmin' => $qty, 'id' => $variantId]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
            // 0 ligne touchée : soit illimité (stock NULL), soit stock insuffisant.
            $check = db()->prepare('SELECT stock FROM product_variants WHERE id = :id');
            $check->execute(['id' => $variantId]);
            $stock = $check->fetchColumn();
            return $stock === null; // illimité = OK ; sinon insuffisant = false
        } catch (\Throwable) {
            return false;
        }
    }

    /** Stock total d'un produit (somme des variantes ; null si une variante est illimitée). */
    public static function totalStock(int $productId): ?int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) AS n, SUM(stock IS NULL) AS unlimited, COALESCE(SUM(stock), 0) AS total FROM product_variants WHERE product_id = :p');
            $stmt->execute(['p' => $productId]);
            $r = $stmt->fetch() ?: [];
            if ((int) ($r['n'] ?? 0) === 0) {
                return 0;
            }
            return (int) ($r['unlimited'] ?? 0) > 0 ? null : (int) ($r['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Référence courte, lisible et scannable, propre à la plateforme. */
    public static function generateSku(): string
    {
        return 'AFK-' . strtoupper(substr(str_replace('-', '', uuid()), 0, 8));
    }
}
