<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Vérification d'identité (KYC) à 3 niveaux successifs.
 * - kyc_submissions : une ligne par (user, niveau), avec son statut.
 * - kyc_documents   : les pièces (identifiants Cloudinary privés) d'une soumission.
 * Tables auto-créées au premier usage. Un niveau N s'ouvre quand N-1 est approuvé.
 */
final class Kyc
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public static function ensureTables(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS kyc_submissions (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id     BIGINT UNSIGNED NOT NULL,
                level       TINYINT UNSIGNED NOT NULL,
                status      VARCHAR(12) NOT NULL DEFAULT \'pending\',
                doc_type    VARCHAR(16) NULL,
                id_first_name VARCHAR(100) NULL,
                id_last_name  VARCHAR(100) NULL,
                review_note VARCHAR(500) NULL,
                reviewer_id BIGINT UNSIGNED NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at  DATETIME NULL,
                UNIQUE KEY uq_user_level (user_id, level),
                KEY idx_status (status, submitted_at)
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS kyc_documents (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                submission_id   BIGINT UNSIGNED NOT NULL,
                slot            VARCHAR(24) NOT NULL,
                cloud_public_id VARCHAR(255) NOT NULL,
                cloud_version   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                cloud_format    VARCHAR(8) NOT NULL DEFAULT \'jpg\',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_doc_submission (submission_id)
            )'
        );
        self::migrate();
    }

    /** Ajoute les colonnes nom/prénom à une table déjà créée (idempotent). */
    private static function migrate(): void
    {
        try {
            db()->query('SELECT id_first_name FROM kyc_submissions LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE kyc_submissions
                    ADD COLUMN id_first_name VARCHAR(100) NULL,
                    ADD COLUMN id_last_name  VARCHAR(100) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre a déjà migré
            }
        }
    }

    /** @return array<int,array> statut par niveau (1..3), indexé par niveau */
    public static function submissionsByLevel(int $userId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM kyc_submissions WHERE user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $out[(int) $row['level']] = $row;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Plus haut niveau APPROUVÉ (0 si aucun). */
    public static function approvedLevel(int $userId): int
    {
        $byLevel = self::submissionsByLevel($userId);
        $n = 0;
        for ($lvl = 1; $lvl <= 3; $lvl++) {
            if (($byLevel[$lvl]['status'] ?? null) === self::APPROVED) {
                $n = $lvl;
            } else {
                break;
            }
        }
        return $n;
    }

    /** Un niveau est-il ouvert à la soumission ? (le précédent doit être approuvé) */
    public static function isUnlocked(int $userId, int $level): bool
    {
        return $level === 1 ? true : self::approvedLevel($userId) >= $level - 1;
    }

    /**
     * Crée/remplace la soumission d'un niveau (repasse en attente) et ses pièces.
     * @param list<array{slot:string,public_id:string,version:int,format:string}> $docs
     */
    public static function submit(int $userId, int $level, ?string $docType, array $docs, ?string $firstName = null, ?string $lastName = null): void
    {
        self::ensureTables();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO kyc_submissions (user_id, level, status, doc_type, id_first_name, id_last_name, submitted_at)
                 VALUES (:uid, :lvl, \'pending\', :dt, :fn, :ln, NOW())
                 ON DUPLICATE KEY UPDATE status = \'pending\', doc_type = :dt2,
                     id_first_name = :fn2, id_last_name = :ln2,
                     review_note = NULL, reviewer_id = NULL, submitted_at = NOW(), reviewed_at = NULL'
            )->execute([
                'uid' => $userId, 'lvl' => $level, 'dt' => $docType, 'dt2' => $docType,
                'fn' => $firstName, 'fn2' => $firstName, 'ln' => $lastName, 'ln2' => $lastName,
            ]);

            $sel = $pdo->prepare('SELECT id FROM kyc_submissions WHERE user_id = :u AND level = :l');
            $sel->execute(['u' => $userId, 'l' => $level]);
            $sid = (int) $sel->fetchColumn();

            // Remplace les pièces de cette soumission.
            $pdo->prepare('DELETE FROM kyc_documents WHERE submission_id = :sid')->execute(['sid' => $sid]);
            $ins = $pdo->prepare(
                'INSERT INTO kyc_documents (submission_id, slot, cloud_public_id, cloud_version, cloud_format)
                 VALUES (:sid, :slot, :pid, :ver, :fmt)'
            );
            foreach ($docs as $d) {
                $ins->execute([
                    'sid'  => $sid,
                    'slot' => $d['slot'],
                    'pid'  => $d['public_id'],
                    'ver'  => $d['version'],
                    'fmt'  => $d['format'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array> soumissions en attente (file de revue), plus anciennes d'abord */
    public static function pendingQueue(int $limit = 100): array
    {
        try {
            $stmt = db()->query(
                'SELECT s.*, u.email, u.full_name, u.country_code
                   FROM kyc_submissions s
                   JOIN users u ON u.id = s.user_id
                  WHERE s.status = \'pending\'
                  ORDER BY s.submitted_at ASC
                  LIMIT ' . max(1, $limit)
            );
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findSubmission(int $id): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT s.*, u.email, u.full_name, u.country_code, u.city
                   FROM kyc_submissions s JOIN users u ON u.id = s.user_id
                  WHERE s.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array> pièces d'une soumission */
    public static function documents(int $submissionId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM kyc_documents WHERE submission_id = :sid ORDER BY id');
            $stmt->execute(['sid' => $submissionId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findDocument(int $id): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM kyc_documents WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function review(int $submissionId, int $reviewerId, bool $approve, ?string $note): void
    {
        $stmt = db()->prepare(
            'UPDATE kyc_submissions
                SET status = :status, reviewer_id = :rid, review_note = :note, reviewed_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            'status' => $approve ? self::APPROVED : self::REJECTED,
            'rid'    => $reviewerId,
            'note'   => $note,
            'id'     => $submissionId,
        ]);
    }
}
