<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Annonces éditoriales du staff (admins & modérateurs) diffusées « À la une »
 * dans le bandeau défilant — affichées EN ROUGE, cliquables vers une page
 * d'article (/info/{public_id}).
 *
 * Modération : une annonce de modérateur naît en `pending` et n'apparaît qu'une
 * fois APPROUVÉE par un admin. Une annonce d'admin est publiée directement.
 * Statuts : pending → approved | rejected.
 */
final class Announcement
{
    public static function ensureTable(): void
    {
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS announcements (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id      CHAR(36) NOT NULL UNIQUE,
                author_user_id BIGINT UNSIGNED NOT NULL,
                title          VARCHAR(160) NOT NULL,
                body           TEXT NULL,
                link           VARCHAR(255) NULL,
                status         VARCHAR(12) NOT NULL DEFAULT 'pending',
                approved_by    BIGINT UNSIGNED NULL,
                approved_at    DATETIME NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_ann_status (status, id)
            )"
        );
    }

    /**
     * Crée une annonce. $autoApprove = true pour un admin (publiée directement) ;
     * false pour un modérateur (en attente de validation). Renvoie le public_id.
     */
    public static function create(int $authorId, string $title, ?string $body, ?string $link, bool $autoApprove): string
    {
        self::ensureTable();
        $pid = uuid();
        $stmt = db()->prepare(
            "INSERT INTO announcements (public_id, author_user_id, title, body, link, status, approved_by, approved_at)
             VALUES (:pid, :uid, :title, :body, :link, :status, :appby, :appat)"
        );
        $stmt->execute([
            'pid'    => $pid,
            'uid'    => $authorId,
            'title'  => mb_substr($title, 0, 160),
            'body'   => ($body !== null && trim($body) !== '') ? $body : null,
            'link'   => ($link !== null && trim($link) !== '') ? mb_substr($link, 0, 255) : null,
            'status' => $autoApprove ? 'approved' : 'pending',
            'appby'  => $autoApprove ? $authorId : null,
            'appat'  => $autoApprove ? date('Y-m-d H:i:s') : null,
        ]);
        return $pid;
    }

    /** Approuve ou rejette une annonce (réservé aux admins, vérifié côté contrôleur). */
    public static function review(int $id, int $adminId, bool $approve): void
    {
        self::ensureTable();
        $stmt = db()->prepare(
            "UPDATE announcements
                SET status = :status, approved_by = :by, approved_at = :at
              WHERE id = :id"
        );
        $stmt->execute([
            'status' => $approve ? 'approved' : 'rejected',
            'by'     => $adminId,
            'at'     => $approve ? date('Y-m-d H:i:s') : null,
            'id'     => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        self::ensureTable();
        db()->prepare('DELETE FROM announcements WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array> annonces APPROUVÉES, plus récentes d'abord (pour le ticker). */
    public static function liveForTicker(int $limit = 6): array
    {
        try {
            self::ensureTable();
            $limit = max(1, min(20, $limit));
            return db()->query("SELECT public_id, title FROM announcements
                                WHERE status = 'approved' ORDER BY id DESC LIMIT {$limit}")->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Annonce approuvée, pour la page publique /info/{public_id}. */
    public static function findPublic(string $publicId): ?array
    {
        try {
            self::ensureTable();
            $stmt = db()->prepare("SELECT * FROM announcements WHERE public_id = :p AND status = 'approved' LIMIT 1");
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste pour le back-office. Un admin voit tout ; un modérateur voit ses
     * propres annonces. @return list<array>
     */
    public static function listFor(?int $authorId): array
    {
        self::ensureTable();
        if ($authorId === null) {
            return db()->query("SELECT a.*, u.full_name AS author_name
                                FROM announcements a LEFT JOIN users u ON u.id = a.author_user_id
                                ORDER BY a.id DESC LIMIT 100")->fetchAll() ?: [];
        }
        $stmt = db()->prepare("SELECT a.*, u.full_name AS author_name
                               FROM announcements a LEFT JOIN users u ON u.id = a.author_user_id
                               WHERE a.author_user_id = :uid ORDER BY a.id DESC LIMIT 100");
        $stmt->execute(['uid' => $authorId]);
        return $stmt->fetchAll() ?: [];
    }

    /** Nombre d'annonces en attente de validation (badge admin). */
    public static function pendingCount(): int
    {
        try {
            self::ensureTable();
            return (int) db()->query("SELECT COUNT(*) FROM announcements WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
