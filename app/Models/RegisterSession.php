<?php
declare(strict_types=1);

namespace App\Models;

/**
 * register_sessions — une session de caisse : ouverture (fond de caisse) →
 * fermeture (comptage du tiroir → écart over/under). Cœur du POS (plan §2).
 * Une seule session OUVERTE par caisse à la fois. Montants en centimes.
 *
 * Théorique attendu à la clôture = fond de caisse
 *   + entrées d'espèces (paid_in) − sorties d'espèces (paid_out)
 *   + ventes réglées en espèces de la session (ajoutées en Phase C avec order_tenders).
 */
final class RegisterSession
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS register_sessions (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id           CHAR(36) NOT NULL UNIQUE,
                register_id         BIGINT UNSIGNED NOT NULL,
                boutique_id         BIGINT UNSIGNED NOT NULL,
                cashier_user_id     BIGINT UNSIGNED NOT NULL,
                opened_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                closed_at           DATETIME NULL,
                opening_float_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                expected_cash_cents BIGINT NULL,
                counted_cash_cents  BIGINT NULL,
                variance_cents      BIGINT NULL,
                currency            CHAR(3) NOT NULL DEFAULT \'EUR\',
                status              VARCHAR(8) NOT NULL DEFAULT \'open\',
                KEY idx_sessions_register (register_id, status),
                KEY idx_sessions_boutique (boutique_id, status)
            )'
        );
    }

    /** La session ouverte d'une caisse, ou null. */
    public static function findOpen(int $registerId): ?array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare("SELECT * FROM register_sessions WHERE register_id = :r AND status = 'open' ORDER BY id DESC LIMIT 1");
            $stmt->execute(['r' => $registerId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM register_sessions WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Ouvre une session avec son fond de caisse (refuse si déjà ouverte). */
    public static function open(int $registerId, int $boutiqueId, int $cashierUserId, int $openingFloatCents, string $currency): ?string
    {
        self::ensureTable();
        if (self::findOpen($registerId) !== null) {
            return null; // déjà une session ouverte sur cette caisse
        }
        $publicId = uuid();
        try {
            db()->prepare(
                'INSERT INTO register_sessions (public_id, register_id, boutique_id, cashier_user_id, opening_float_cents, currency, status)
                 VALUES (:p, :r, :b, :u, :f, :c, \'open\')'
            )->execute([
                'p' => $publicId, 'r' => $registerId, 'b' => $boutiqueId, 'u' => $cashierUserId,
                'f' => max(0, $openingFloatCents), 'c' => $currency,
            ]);
            return $publicId;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Espèces théoriques attendues (fond + ventes espèces + apports − sorties). */
    public static function expectedCash(array $session): int
    {
        $sums = CashMovement::sums((int) $session['id']);
        $cashSales = Order::posCashSalesForSession((int) $session['id']);
        return (int) $session['opening_float_cents'] + $cashSales + (int) $sums['paid_in'] - (int) $sums['paid_out'];
    }

    /** Clôture : enregistre le comptage, calcule le théorique et l'écart (over/under). */
    public static function close(int $sessionId, int $countedCents): void
    {
        try {
            $stmt = db()->prepare("SELECT * FROM register_sessions WHERE id = :id AND status = 'open' LIMIT 1");
            $stmt->execute(['id' => $sessionId]);
            $session = $stmt->fetch();
            if ($session === false) {
                return;
            }
            $expected = self::expectedCash($session);
            db()->prepare(
                "UPDATE register_sessions SET status = 'closed', closed_at = NOW(),
                    expected_cash_cents = :e, counted_cash_cents = :c, variance_cents = :v WHERE id = :id"
            )->execute(['e' => $expected, 'c' => $countedCents, 'v' => $countedCents - $expected, 'id' => $sessionId]);
        } catch (\Throwable) {
        }
    }

    /** @return list<array> sessions d'une caisse (récentes d'abord) */
    public static function forRegister(int $registerId, int $limit = 50): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM register_sessions WHERE register_id = :r ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute(['r' => $registerId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
