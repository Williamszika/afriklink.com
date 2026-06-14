<?php
declare(strict_types=1);

namespace App\Models;

/**
 * discounts — promotions d'une boutique. Deux usages :
 *  - code promo saisi par le client en ligne (code non nul) ;
 *  - remise manuelle appliquée en caisse / POS (code nul).
 * type 'percent' : value = pourcentage entier 0–100 ; type 'amount' : value en
 * centimes. Montants en centimes, jamais de flottants. Table auto-créée.
 */
final class Discount
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS discounts (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id       CHAR(36) NOT NULL UNIQUE,
                boutique_id     BIGINT UNSIGNED NOT NULL,
                code            VARCHAR(40) NULL,
                type            VARCHAR(8) NOT NULL DEFAULT \'percent\',
                value           INT NOT NULL DEFAULT 0,
                min_order_cents BIGINT UNSIGNED NULL,
                max_uses        INT NULL,
                uses            INT NOT NULL DEFAULT 0,
                starts_at       DATETIME NULL,
                ends_at         DATETIME NULL,
                status          VARCHAR(12) NOT NULL DEFAULT \'active\',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_discounts_boutique (boutique_id, status),
                KEY idx_discounts_code (code)
            )'
        );
    }

    public static function create(int $boutiqueId, array $d): string
    {
        self::ensureTable();
        $publicId = uuid();
        db()->prepare(
            'INSERT INTO discounts (public_id, boutique_id, code, type, value, min_order_cents, max_uses, starts_at, ends_at, status)
             VALUES (:pub, :b, :code, :type, :value, :min, :max, :starts, :ends, :status)'
        )->execute([
            'pub' => $publicId, 'b' => $boutiqueId,
            'code' => $d['code'] ?? null,
            'type' => in_array($d['type'] ?? '', ['percent', 'amount'], true) ? $d['type'] : 'percent',
            'value' => max(0, (int) ($d['value'] ?? 0)),
            'min' => $d['min_order_cents'] ?? null, 'max' => $d['max_uses'] ?? null,
            'starts' => $d['starts_at'] ?? null, 'ends' => $d['ends_at'] ?? null,
            'status' => $d['status'] ?? 'active',
        ]);
        return $publicId;
    }

    /** @return list<array> promotions d'une boutique (les plus récentes d'abord) */
    public static function forBoutique(int $boutiqueId): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM discounts WHERE boutique_id = :b ORDER BY id DESC LIMIT 200');
            $stmt->execute(['b' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Code promo actif et valide d'une boutique (pour le panier en ligne). */
    public static function findValidCode(int $boutiqueId, string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        try {
            $stmt = db()->prepare(
                "SELECT * FROM discounts
                  WHERE boutique_id = :b AND code = :c AND status = 'active'
                    AND (starts_at IS NULL OR starts_at <= NOW())
                    AND (ends_at IS NULL OR ends_at >= NOW())
                    AND (max_uses IS NULL OR uses < max_uses)
                  LIMIT 1"
            );
            $stmt->execute(['b' => $boutiqueId, 'c' => $code]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Montant de réduction (centimes) appliqué à un sous-total, borné au sous-total. */
    public static function reductionFor(array $discount, int $subtotalCents): int
    {
        $min = (int) ($discount['min_order_cents'] ?? 0);
        if ($min > 0 && $subtotalCents < $min) {
            return 0;
        }
        $type = (string) ($discount['type'] ?? 'percent');
        $value = max(0, (int) ($discount['value'] ?? 0));
        $reduction = $type === 'amount'
            ? $value
            : (int) floor($subtotalCents * min(100, $value) / 100);
        return max(0, min($subtotalCents, $reduction));
    }

    /** Incrémente le compteur d'utilisations (après une commande validée). */
    public static function recordUse(int $id): void
    {
        try {
            db()->prepare('UPDATE discounts SET uses = uses + 1 WHERE id = :id')->execute(['id' => $id]);
        } catch (\Throwable) {
        }
    }

    public static function setStatus(int $id, int $boutiqueId, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            return;
        }
        try {
            db()->prepare('UPDATE discounts SET status = :s WHERE id = :id AND boutique_id = :b')
                ->execute(['s' => $status, 'id' => $id, 'b' => $boutiqueId]);
        } catch (\Throwable) {
        }
    }
}
