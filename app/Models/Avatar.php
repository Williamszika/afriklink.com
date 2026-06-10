<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * user_avatars — photos de profil stockées dans la base (TiDB), une ligne par
 * utilisateur. Le disque de Vercel est en lecture seule, donc le BLOB vit avec
 * le reste des données ; les images sont re-encodées en petit carré avant
 * stockage (~30 Ko par ligne). La table se crée toute seule au premier envoi —
 * aucune migration manuelle.
 */
final class Avatar
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS user_avatars (
                user_id    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                mime       VARCHAR(32) NOT NULL,
                data       MEDIUMBLOB NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )'
        );
    }

    public static function save(int $userId, string $mime, string $blob): void
    {
        self::ensureTable();
        $stmt = db()->prepare(
            'INSERT INTO user_avatars (user_id, mime, data) VALUES (:id, :mime, :data)
             ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)'
        );
        $stmt->bindValue('id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('mime', $mime);
        $stmt->bindValue('data', $blob, PDO::PARAM_LOB);
        $stmt->execute();
    }

    public static function delete(int $userId): void
    {
        try {
            $stmt = db()->prepare('DELETE FROM user_avatars WHERE user_id = :id');
            $stmt->execute(['id' => $userId]);
        } catch (\Throwable) {
            // table pas encore créée → rien à supprimer
        }
    }

    /** Horodatage de l'avatar de l'utilisateur, ou null s'il n'en a pas. */
    public static function versionFor(int $userId): ?string
    {
        try {
            $stmt = db()->prepare('SELECT updated_at FROM user_avatars WHERE user_id = :id');
            $stmt->execute(['id' => $userId]);
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string) $v;
        } catch (\Throwable) {
            return null; // table absente tant que personne n'a envoyé de photo
        }
    }

    /** @return array{mime:string,data:string,updated_at:string}|null */
    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT a.mime, a.data, a.updated_at
                   FROM user_avatars a
                   JOIN users u ON u.id = a.user_id
                  WHERE u.public_id = :pid AND u.deleted_at IS NULL
                  LIMIT 1'
            );
            $stmt->execute(['pid' => $publicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
