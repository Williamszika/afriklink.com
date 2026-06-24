<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Blocage entre membres pour la messagerie : un membre peut bloquer un autre,
 * ce qui empêche TOUT échange dans les deux sens (ni l'un ni l'autre ne peut
 * écrire à celui qui l'a bloqué). Anti-harcèlement. Table auto-créée.
 */
final class UserBlock
{
    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS user_blocks (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                blocker_id  BIGINT UNSIGNED NOT NULL,
                blocked_id  BIGINT UNSIGNED NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_block (blocker_id, blocked_id),
                KEY idx_block_blocked (blocked_id)
            )'
        );
    }

    public static function block(int $blockerId, int $blockedId): void
    {
        if ($blockerId <= 0 || $blockedId <= 0 || $blockerId === $blockedId) {
            return;
        }
        self::ensureTable();
        try {
            // INSERT IGNORE : idempotent (la clé unique évite les doublons).
            db()->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (:a, :b)')
                ->execute(['a' => $blockerId, 'b' => $blockedId]);
        } catch (\Throwable) {
        }
    }

    public static function unblock(int $blockerId, int $blockedId): void
    {
        self::ensureTable();
        try {
            db()->prepare('DELETE FROM user_blocks WHERE blocker_id = :a AND blocked_id = :b')
                ->execute(['a' => $blockerId, 'b' => $blockedId]);
        } catch (\Throwable) {
        }
    }

    /** $a a-t-il bloqué $b ? (sens unique) */
    public static function has(int $a, int $b): bool
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = :a AND blocked_id = :b LIMIT 1');
            $stmt->execute(['a' => $a, 'b' => $b]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Existe-t-il un blocage entre $a et $b, dans un sens OU l'autre ? */
    public static function between(int $a, int $b): bool
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM user_blocks
                  WHERE (blocker_id = :a AND blocked_id = :b)
                     OR (blocker_id = :b2 AND blocked_id = :a2)
                  LIMIT 1'
            );
            $stmt->execute(['a' => $a, 'b' => $b, 'b2' => $b, 'a2' => $a]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
