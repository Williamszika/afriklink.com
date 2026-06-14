<?php
declare(strict_types=1);

namespace App\Models;

/**
 * cash_movements — mouvements d'espèces hors vente d'une session de caisse :
 * apport (paid_in) ou sortie (paid_out, ex. paiement fournisseur, prélèvement).
 * Toute sortie doit être saisie AVANT de retirer le cash, avec motif, sinon la
 * caisse paraît voleuse à tort (plan §2). Montants en centimes.
 */
final class CashMovement
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS cash_movements (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                session_id  BIGINT UNSIGNED NOT NULL,
                type        VARCHAR(10) NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                reason      VARCHAR(160) NOT NULL,
                created_by  BIGINT UNSIGNED NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_movements_session (session_id, id)
            )'
        );
    }

    public static function add(int $sessionId, string $type, int $amountCents, string $reason, int $createdBy): void
    {
        if (!in_array($type, ['paid_in', 'paid_out'], true) || $amountCents <= 0) {
            return;
        }
        self::ensureTable();
        try {
            db()->prepare(
                'INSERT INTO cash_movements (session_id, type, amount_cents, reason, created_by)
                 VALUES (:s, :t, :a, :r, :u)'
            )->execute([
                's' => $sessionId, 't' => $type, 'a' => $amountCents,
                'r' => mb_substr(trim($reason), 0, 160) ?: '—', 'u' => $createdBy,
            ]);
        } catch (\Throwable) {
        }
    }

    /** @return list<array> mouvements d'une session (chronologiques) */
    public static function forSession(int $sessionId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM cash_movements WHERE session_id = :s ORDER BY id');
            $stmt->execute(['s' => $sessionId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Totaux apports / sorties d'une session. @return array{paid_in:int,paid_out:int} */
    public static function sums(int $sessionId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN type = 'paid_in'  THEN amount_cents ELSE 0 END), 0) AS paid_in,
                    COALESCE(SUM(CASE WHEN type = 'paid_out' THEN amount_cents ELSE 0 END), 0) AS paid_out
                 FROM cash_movements WHERE session_id = :s"
            );
            $stmt->execute(['s' => $sessionId]);
            $r = $stmt->fetch() ?: [];
            return ['paid_in' => (int) ($r['paid_in'] ?? 0), 'paid_out' => (int) ($r['paid_out'] ?? 0)];
        } catch (\Throwable) {
            return ['paid_in' => 0, 'paid_out' => 0];
        }
    }
}
