<?php
declare(strict_types=1);

namespace App\Models;

/**
 * registers — une caisse (point de vente) appartenant à une boutique. Le POS est
 * un OUTIL du vendeur : l'argent encaissé en caisse ne transite jamais par la
 * plateforme (cf. plan §3.1 / §7). Plusieurs caisses possibles par boutique.
 * Conventions de l'app : public_id, BIGINT, pas de FK, ensureTable idempotent.
 */
final class Register
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS registers (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                boutique_id BIGINT UNSIGNED NOT NULL,
                name        VARCHAR(80) NOT NULL,
                status      VARCHAR(12) NOT NULL DEFAULT \'active\',
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_registers_boutique (boutique_id, status)
            )'
        );
    }

    public static function create(int $boutiqueId, string $name): string
    {
        self::ensureTable();
        $publicId = uuid();
        db()->prepare('INSERT INTO registers (public_id, boutique_id, name, status) VALUES (:p, :b, :n, \'active\')')
            ->execute(['p' => $publicId, 'b' => $boutiqueId, 'n' => mb_substr(trim($name), 0, 80) ?: 'Caisse']);
        return $publicId;
    }

    /** @return list<array> caisses d'une boutique */
    public static function forBoutique(int $boutiqueId): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM registers WHERE boutique_id = :b ORDER BY id');
            $stmt->execute(['b' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Caisse de la boutique par identifiant public (None si pas la sienne). */
    public static function findForBoutique(string $publicId, int $boutiqueId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM registers WHERE public_id = :p AND boutique_id = :b LIMIT 1');
            $stmt->execute(['p' => $publicId, 'b' => $boutiqueId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setStatus(int $id, int $boutiqueId, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            return;
        }
        try {
            db()->prepare('UPDATE registers SET status = :s WHERE id = :id AND boutique_id = :b')
                ->execute(['s' => $status, 'id' => $id, 'b' => $boutiqueId]);
        } catch (\Throwable) {
        }
    }
}
