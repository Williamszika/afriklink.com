<?php
declare(strict_types=1);

namespace App\Models;

/**
 * delivery_areas — zones de livraison LOCALES d'un restaurant (par quartier). La
 * livraison de repas est locale : pas de zones-pays comme la boutique, mais des
 * secteurs nommés (« Plateau », « Almadies »…) chacun avec son tarif, son franco
 * et son délai. L'acheteur choisit son secteur ; le restaurateur connaît le coût.
 * Montants en centimes. Table auto-créée.
 */
final class DeliveryArea
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS delivery_areas (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id        CHAR(36) NOT NULL UNIQUE,
                restaurant_id    BIGINT UNSIGNED NOT NULL,
                name             VARCHAR(60) NOT NULL,
                fee_cents        BIGINT UNSIGNED NOT NULL DEFAULT 0,
                free_above_cents BIGINT UNSIGNED NULL,
                delay            VARCHAR(16) NULL,
                position         INT NOT NULL DEFAULT 0,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_areas_restaurant (restaurant_id, position)
            )'
        );
    }

    /** @return list<array> zones d'un restaurant, ordonnées */
    public static function forRestaurant(int $restaurantId): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM delivery_areas WHERE restaurant_id = :r ORDER BY position, id');
            $stmt->execute(['r' => $restaurantId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function count(int $restaurantId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM delivery_areas WHERE restaurant_id = :r');
            $stmt->execute(['r' => $restaurantId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param array{name:string,fee_cents:int,free_above_cents:?int,delay:?string,position?:int} $d */
    public static function create(int $restaurantId, array $d): string
    {
        self::ensureTable();
        $pub = uuid();
        db()->prepare(
            'INSERT INTO delivery_areas (public_id, restaurant_id, name, fee_cents, free_above_cents, delay, position)
             VALUES (:p, :r, :n, :fee, :free, :delay, :pos)'
        )->execute([
            'p' => $pub, 'r' => $restaurantId,
            'n' => mb_substr(trim($d['name']), 0, 60) ?: 'Zone',
            'fee' => max(0, (int) $d['fee_cents']),
            'free' => isset($d['free_above_cents']) && $d['free_above_cents'] !== null ? max(0, (int) $d['free_above_cents']) : null,
            'delay' => $d['delay'] ?? null,
            'pos' => (int) ($d['position'] ?? 0),
        ]);
        return $pub;
    }

    public static function delete(string $publicId, int $restaurantId): void
    {
        try {
            db()->prepare('DELETE FROM delivery_areas WHERE public_id = :p AND restaurant_id = :r')
                ->execute(['p' => $publicId, 'r' => $restaurantId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Frais d'une zone choisie (par public_id), franco appliqué. null si la zone
     * n'appartient pas au restaurant. @return array{fee_cents:int,free:bool,delay:string,name:string}|null
     */
    public static function feeFor(int $restaurantId, string $areaPublicId, int $subtotalCents): ?array
    {
        if ($areaPublicId === '') {
            return null;
        }
        try {
            $stmt = db()->prepare('SELECT * FROM delivery_areas WHERE public_id = :p AND restaurant_id = :r LIMIT 1');
            $stmt->execute(['p' => $areaPublicId, 'r' => $restaurantId]);
            $z = $stmt->fetch();
        } catch (\Throwable) {
            $z = false;
        }
        if ($z === false) {
            return null;
        }
        $fee       = (int) ($z['fee_cents'] ?? 0);
        $freeAbove = (int) ($z['free_above_cents'] ?? 0);
        $free      = $freeAbove > 0 && $subtotalCents >= $freeAbove;
        return [
            'fee_cents' => $free ? 0 : $fee,
            'free'      => $free,
            'delay'     => (string) ($z['delay'] ?? ''),
            'name'      => (string) ($z['name'] ?? ''),
        ];
    }
}
