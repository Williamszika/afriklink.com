<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Notifications utilisateur (cloche de l'en-tête) : nouveaux messages, commandes,
 * avis… Persistées, avec état lu/non-lu. Le lien pointe vers une page interne.
 * Table auto-créée.
 */
final class Notification
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id    BIGINT UNSIGNED NOT NULL,
                type       VARCHAR(24) NOT NULL DEFAULT \'info\',
                title      VARCHAR(160) NOT NULL,
                body       VARCHAR(300) NULL,
                link       VARCHAR(255) NULL,
                read_at    DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notif_user (user_id, id)
            )'
        );
    }

    /** Crée une notification (best-effort, ne bloque jamais l'action source). */
    public static function push(int $userId, string $type, string $title, string $body, string $link): void
    {
        if ($userId <= 0) {
            return;
        }
        self::ensureTable();
        try {
            db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u, :t, :ti, :b, :l)')
                ->execute([
                    'u'  => $userId,
                    't'  => mb_substr($type, 0, 24),
                    'ti' => mb_substr($title, 0, 160),
                    'b'  => $body !== '' ? mb_substr($body, 0, 300) : null,
                    'l'  => $link !== '' ? mb_substr($link, 0, 255) : null,
                ]);
        } catch (\Throwable) {
        }
    }

    /** @return list<array> notifications récentes d'un membre */
    public static function forUser(int $userId, int $limit = 40): array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
            $stmt->execute(['u' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function unreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
            $stmt->execute(['u' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Marque une notification comme lue et renvoie sa ligne (ou null). */
    public static function markRead(int $id, int $userId): ?array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :u LIMIT 1');
            $stmt->execute(['id' => $id, 'u' => $userId]);
            $row = $stmt->fetch();
            if ($row === false) {
                return null;
            }
            db()->prepare('UPDATE notifications SET read_at = NOW() WHERE id = :id AND read_at IS NULL')->execute(['id' => $id]);
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function markAllRead(int $userId): void
    {
        self::ensureTable();
        try {
            db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL')->execute(['u' => $userId]);
        } catch (\Throwable) {
        }
    }
}
