<?php
declare(strict_types=1);

namespace App\Models;

/**
 * shipping_zones — livraison par ZONES (groupes de pays) d'une boutique, le vrai
 * besoin transfrontalier : un Dakar→Paris facture différemment le Sénégal, la
 * CEDEAO, l'Europe, le reste du monde. Chaque zone = des pays + un tarif + un
 * franco (gratuit au-dessus de X) + un délai.
 *
 * Une zone aux `countries` VIDES sert de **catch-all** (« reste du monde »).
 * Rétro-compatible : tant qu'aucune zone n'existe, le checkout garde les frais à
 * plat hérités (delivery_fee_cents / delivery_intl_cents). Montants en centimes.
 * Table auto-créée.
 */
final class ShippingZone
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS shipping_zones (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id        CHAR(36) NOT NULL UNIQUE,
                boutique_id      BIGINT UNSIGNED NOT NULL,
                name             VARCHAR(60) NOT NULL,
                countries        VARCHAR(400) NULL,
                fee_cents        BIGINT UNSIGNED NOT NULL DEFAULT 0,
                free_above_cents BIGINT UNSIGNED NULL,
                delay            VARCHAR(16) NULL,
                position         INT NOT NULL DEFAULT 0,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_zones_boutique (boutique_id, position)
            )'
        );
    }

    /** @return list<array> zones d'une boutique, ordonnées */
    public static function forBoutique(int $boutiqueId): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM shipping_zones WHERE boutique_id = :b ORDER BY position, id');
            $stmt->execute(['b' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function count(int $boutiqueId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM shipping_zones WHERE boutique_id = :b');
            $stmt->execute(['b' => $boutiqueId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param array{name:string,countries:?string,fee_cents:int,free_above_cents:?int,delay:?string,position?:int} $d */
    public static function create(int $boutiqueId, array $d): string
    {
        self::ensureTable();
        $pub = uuid();
        db()->prepare(
            'INSERT INTO shipping_zones (public_id, boutique_id, name, countries, fee_cents, free_above_cents, delay, position)
             VALUES (:p, :b, :n, :c, :fee, :free, :delay, :pos)'
        )->execute([
            'p' => $pub, 'b' => $boutiqueId,
            'n' => mb_substr(trim($d['name']), 0, 60) ?: 'Zone',
            'c' => $d['countries'] ?? null,
            'fee' => max(0, (int) $d['fee_cents']),
            'free' => isset($d['free_above_cents']) && $d['free_above_cents'] !== null ? max(0, (int) $d['free_above_cents']) : null,
            'delay' => $d['delay'] ?? null,
            'pos' => (int) ($d['position'] ?? 0),
        ]);
        return $pub;
    }

    public static function delete(string $publicId, int $boutiqueId): void
    {
        try {
            db()->prepare('DELETE FROM shipping_zones WHERE public_id = :p AND boutique_id = :b')
                ->execute(['p' => $publicId, 'b' => $boutiqueId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Tarif de livraison vers un pays, pour un sous-total donné.
     *   null                     → aucune zone définie (le checkout garde les frais à plat hérités)
     *   ['deliverable'=>false]   → des zones existent mais ce pays n'est pas couvert (et pas de catch-all)
     *   ['deliverable'=>true, …] → frais (franco appliqué), délai, nom de zone
     *
     * @return array{deliverable:bool,fee_cents?:int,base_fee_cents?:int,free?:bool,free_above_cents?:int,delay?:string,zone?:string}|null
     */
    public static function rateFor(int $boutiqueId, string $countryCode, int $subtotalCents): ?array
    {
        $zones = self::forBoutique($boutiqueId);
        if ($zones === []) {
            return null;
        }
        $cc = strtoupper(trim($countryCode));
        $match = null;
        $catch = null;
        foreach ($zones as $z) {
            $list = array_filter(array_map('trim', explode(',', strtoupper((string) ($z['countries'] ?? '')))));
            if ($list === []) {
                $catch ??= $z;
                continue;
            }
            if ($cc !== '' && in_array($cc, $list, true)) {
                $match = $z;
                break;
            }
        }
        $z = $match ?? $catch;
        if ($z === null) {
            return ['deliverable' => false];
        }
        $fee       = (int) ($z['fee_cents'] ?? 0);
        $freeAbove = (int) ($z['free_above_cents'] ?? 0);
        $free      = $freeAbove > 0 && $subtotalCents >= $freeAbove;
        return [
            'deliverable'      => true,
            'fee_cents'        => $free ? 0 : $fee,
            'base_fee_cents'   => $fee,
            'free'             => $free,
            'free_above_cents' => $freeAbove,
            'delay'            => (string) ($z['delay'] ?? ''),
            'zone'             => (string) ($z['name'] ?? ''),
        ];
    }
}
