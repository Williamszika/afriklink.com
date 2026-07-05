<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Abonnements aux boutiques — « Suivre une boutique ». Relation persistante
 * (utilisateur ↔ boutique), réservée aux comptes connectés : elle porte le
 * nombre d'abonnés (preuve sociale) et pourra alimenter des notifications de
 * nouveautés. Best-effort : toute panne SQL est silencieuse (ne casse jamais
 * l'affichage de la vitrine).
 */
final class Follow
{
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS boutique_follows (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id     BIGINT UNSIGNED NOT NULL,
                boutique_id BIGINT UNSIGNED NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_follow (user_id, boutique_id),
                KEY idx_boutique (boutique_id)
            )'
        );
        self::$ready = true;
    }

    public static function isFollowing(int $userId, int $boutiqueId): bool
    {
        if ($userId <= 0 || $boutiqueId <= 0) {
            return false;
        }
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT 1 FROM boutique_follows WHERE user_id = :u AND boutique_id = :b LIMIT 1');
            $stmt->execute(['u' => $userId, 'b' => $boutiqueId]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Bascule l'abonnement ; renvoie true si l'utilisateur suit désormais la boutique. */
    public static function toggle(int $userId, int $boutiqueId): bool
    {
        if ($userId <= 0 || $boutiqueId <= 0) {
            return false;
        }
        self::ensureTable();
        try {
            if (self::isFollowing($userId, $boutiqueId)) {
                $stmt = db()->prepare('DELETE FROM boutique_follows WHERE user_id = :u AND boutique_id = :b');
                $stmt->execute(['u' => $userId, 'b' => $boutiqueId]);
                return false;
            }
            $stmt = db()->prepare('INSERT IGNORE INTO boutique_follows (user_id, boutique_id) VALUES (:u, :b)');
            $stmt->execute(['u' => $userId, 'b' => $boutiqueId]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function countFor(int $boutiqueId): int
    {
        if ($boutiqueId <= 0) {
            return 0;
        }
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM boutique_follows WHERE boutique_id = :b');
            $stmt->execute(['b' => $boutiqueId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
