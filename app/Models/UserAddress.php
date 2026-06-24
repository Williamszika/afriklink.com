<?php
declare(strict_types=1);

namespace App\Models;

/**
 * user_addresses — carnet d'adresses de l'acheteur. Permet de pré-remplir la
 * caisse et d'éviter de ressaisir l'adresse à chaque commande. Table déjà
 * présente au schéma ; création best-effort pour le dev.
 */
final class UserAddress
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS user_addresses (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id        BIGINT UNSIGNED NOT NULL,
                label          VARCHAR(64) NULL,
                recipient_name VARCHAR(128) NOT NULL,
                line1          VARCHAR(191) NOT NULL,
                line2          VARCHAR(191) NULL,
                city           VARCHAR(128) NOT NULL,
                region         VARCHAR(128) NULL,
                postal_code    VARCHAR(32) NULL,
                country_code   CHAR(2) NOT NULL,
                phone          VARCHAR(32) NULL,
                is_default     TINYINT(1) NOT NULL DEFAULT 0,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_addr_user (user_id, is_default)
            )'
        );
    }

    /** @return list<array> adresses de l'utilisateur (la défaut d'abord). */
    public static function forUser(int $userId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC');
            $stmt->execute(['u' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function defaultFor(int $userId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC LIMIT 1');
            $stmt->execute(['u' => $userId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function find(int $id, int $userId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM user_addresses WHERE id = :id AND user_id = :u LIMIT 1');
            $stmt->execute(['id' => $id, 'u' => $userId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function create(int $userId, array $d): void
    {
        self::ensureTable();
        // Première adresse de l'utilisateur => défaut automatique.
        $isFirst = self::forUser($userId) === [];
        db()->prepare(
            'INSERT INTO user_addresses (user_id, label, recipient_name, line1, line2, city, region, postal_code, country_code, phone, is_default)
             VALUES (:u, :label, :rn, :l1, :l2, :city, :region, :pc, :cc, :phone, :def)'
        )->execute([
            'u'      => $userId,
            'label'  => self::clip($d['label'] ?? null, 64),
            'rn'     => self::clip((string) ($d['recipient_name'] ?? ''), 128),
            'l1'     => self::clip((string) ($d['line1'] ?? ''), 191),
            'l2'     => self::clip($d['line2'] ?? null, 191),
            'city'   => self::clip((string) ($d['city'] ?? ''), 128),
            'region' => self::clip($d['region'] ?? null, 128),
            'pc'     => self::clip($d['postal_code'] ?? null, 32),
            'cc'     => strtoupper(substr((string) ($d['country_code'] ?? ''), 0, 2)),
            'phone'  => self::clip($d['phone'] ?? null, 32),
            'def'    => $isFirst || !empty($d['is_default']) ? 1 : 0,
        ]);
        if (!empty($d['is_default']) && !$isFirst) {
            self::setDefault((int) db()->lastInsertId(), $userId);
        }
    }

    public static function setDefault(int $id, int $userId): void
    {
        $pdo = db();
        try {
            // ATOMIQUE : on retire l'ancien défaut PUIS on pose le nouveau dans la
            // même transaction — sans cela, un échec entre les deux laisserait le
            // compte SANS adresse par défaut.
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u')->execute(['u' => $userId]);
            $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE id = :id AND user_id = :u')->execute(['id' => $id, 'u' => $userId]);
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    public static function delete(int $id, int $userId): void
    {
        try {
            db()->prepare('DELETE FROM user_addresses WHERE id = :id AND user_id = :u')->execute(['id' => $id, 'u' => $userId]);
        } catch (\Throwable) {
        }
    }

    /** Adresse formatée sur une ligne (pour pré-remplir la caisse). */
    public static function oneLine(array $a): string
    {
        $parts = array_filter([
            (string) ($a['recipient_name'] ?? ''),
            (string) ($a['line1'] ?? ''),
            (string) ($a['line2'] ?? ''),
            trim(((string) ($a['postal_code'] ?? '')) . ' ' . ((string) ($a['city'] ?? ''))),
            (string) ($a['country_code'] ?? ''),
        ], static fn (string $s): bool => trim($s) !== '');
        return mb_substr(implode(', ', $parts), 0, 220);
    }

    private static function clip(mixed $v, int $max): ?string
    {
        $v = trim((string) ($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
