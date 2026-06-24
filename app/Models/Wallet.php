<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Portefeuille vendeur — registre (ledger) auditable du solde encaissé par la
 * PLATEFORME pour le compte d'un vendeur, + demandes de retrait.
 *
 * Le solde n'est crédité que pour les paiements RÉELLEMENT collectés par la
 * plateforme (PSP en ligne → PaymentSettlement::confirm), de la part vendeur
 * (montant − commission). Les règlements DIRECTS (espèces, Mobile Money en
 * direct) ne passent pas par ici : le vendeur est déjà payé.
 *
 * Solde = Σ(crédits) − Σ(débits). Retrait possible dès l'équivalent de
 * 20 000 XOF ; la demande débite (réserve) le solde et est versée à la main par
 * un admin (sécurité/conformité au démarrage). Un refus recrédite.
 */
final class Wallet
{
    /** Seuil de retrait : 20 000 XOF (en centimes). */
    private const THRESHOLD_XOF_CENTS = 2000000;

    public static function ensureTables(): void
    {
        // Mémoïsé : une seule fois par requête. Crucial — un CREATE TABLE (DDL)
        // déclenche un COMMIT implicite, ce qui casserait la transaction de
        // requestWithdrawal si ensureTables y était rappelé.
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS wallet_entries (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id      BIGINT UNSIGNED NOT NULL,
                type         VARCHAR(8) NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                currency     CHAR(3) NOT NULL DEFAULT 'XOF',
                source       VARCHAR(16) NOT NULL,
                ref          VARCHAR(40) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_wallet_user (user_id, id)
            )"
        );
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS wallet_withdrawals (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id    CHAR(36) NOT NULL UNIQUE,
                user_id      BIGINT UNSIGNED NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                currency     CHAR(3) NOT NULL DEFAULT 'XOF',
                method       VARCHAR(16) NOT NULL,
                destination  VARCHAR(160) NOT NULL,
                status       VARCHAR(12) NOT NULL DEFAULT 'pending',
                processed_by BIGINT UNSIGNED NULL,
                processed_at DATETIME NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_wd_status (status, id),
                KEY idx_wd_user (user_id, id)
            )"
        );
    }

    public static function credit(int $userId, int $amountCents, string $currency, string $source, ?string $ref): void
    {
        self::entry($userId, 'credit', $amountCents, $currency, $source, $ref);
    }

    public static function debit(int $userId, int $amountCents, string $currency, string $source, ?string $ref): void
    {
        self::entry($userId, 'debit', $amountCents, $currency, $source, $ref);
    }

    private static function entry(int $userId, string $type, int $amountCents, string $currency, string $source, ?string $ref): void
    {
        if ($amountCents <= 0) {
            return;
        }
        self::ensureTables();
        db()->prepare(
            'INSERT INTO wallet_entries (user_id, type, amount_cents, currency, source, ref)
             VALUES (:u, :t, :a, :c, :s, :r)'
        )->execute([
            'u' => $userId, 't' => $type, 'a' => $amountCents,
            'c' => strtoupper(substr($currency, 0, 3)), 's' => $source, 'r' => $ref,
        ]);
    }

    /**
     * Solde courant en centimes (Σ crédits − Σ débits). Si $currency est fourni,
     * compte UNIQUEMENT cette devise (jamais d'addition inter-devises : XOF et
     * EUR ne s'additionnent pas — sinon un solde serait gonflé au taux de change).
     */
    public static function balanceCents(int $userId, ?string $currency = null): int
    {
        try {
            self::ensureTables();
            $sql  = "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount_cents ELSE -amount_cents END), 0)
                       FROM wallet_entries WHERE user_id = :u";
            $args = ['u' => $userId];
            if ($currency !== null) {
                $sql .= ' AND currency = :c';
                $args['c'] = strtoupper(substr($currency, 0, 3));
            }
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Devise PRINCIPALE du portefeuille = celle qui détient le plus gros solde
     * (et non la dernière écriture). Sert de devise de retrait/comparaison —
     * jamais une somme mélangée de plusieurs devises.
     */
    public static function currencyFor(int $userId, string $fallback = 'XOF'): string
    {
        try {
            self::ensureTables();
            $stmt = db()->prepare(
                "SELECT currency,
                        SUM(CASE WHEN type='credit' THEN amount_cents ELSE -amount_cents END) AS bal
                   FROM wallet_entries WHERE user_id = :u
                  GROUP BY currency ORDER BY bal DESC LIMIT 1"
            );
            $stmt->execute(['u' => $userId]);
            $c = $stmt->fetchColumn();
            return $c ? strtoupper((string) $c) : strtoupper($fallback);
        } catch (\Throwable) {
            return strtoupper($fallback);
        }
    }

    /** @return list<array> dernières écritures du registre */
    public static function entries(int $userId, int $limit = 20): array
    {
        try {
            self::ensureTables();
            $limit = max(1, min(100, $limit));
            $stmt = db()->prepare("SELECT type, amount_cents, currency, source, ref, created_at
                                   FROM wallet_entries WHERE user_id = :u ORDER BY id DESC LIMIT {$limit}");
            $stmt->execute(['u' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Seuil de retrait converti dans la devise donnée (équivalent 20 000 XOF). */
    public static function thresholdCents(string $currency): int
    {
        if (strtoupper($currency) === 'XOF') {
            return self::THRESHOLD_XOF_CENTS;
        }
        $conv = \App\Services\ExchangeRates::convert(self::THRESHOLD_XOF_CENTS, 'XOF', $currency);
        return $conv ?? self::THRESHOLD_XOF_CENTS;
    }

    public static function canWithdraw(int $userId): bool
    {
        $cur = self::currencyFor($userId);
        return self::balanceCents($userId, $cur) >= self::thresholdCents($cur);
    }

    /**
     * Demande de retrait de TOUT le solde disponible. Vérifie le seuil, débite
     * (réserve) le solde et crée la demande (en attente). Renvoie le public_id,
     * ou null si solde insuffisant.
     */
    public static function requestWithdrawal(int $userId, string $method, string $destination): ?string
    {
        self::ensureTables();
        $method = in_array($method, ['mobile_money', 'bank'], true) ? $method : 'mobile_money';
        $pdo  = db();
        $lock = 'afk_wallet_' . $userId;
        // Verrou applicatif PAR UTILISATEUR : sérialise les demandes de retrait
        // concurrentes. Sans lui, deux requêtes simultanées lisent le même solde
        // et le retirent chacune → double versement. Le solde est ensuite
        // RECALCULÉ sous le verrou (source de vérité au moment du débit).
        try {
            $lk = $pdo->prepare('SELECT GET_LOCK(:k, 5)');
            $lk->execute(['k' => $lock]);
            $got = (int) $lk->fetchColumn();
        } catch (\Throwable) {
            $got = 0;
        }
        if ($got !== 1) {
            return null; // impossible de sérialiser → on refuse plutôt que risquer un double retrait
        }
        try {
            $cur     = self::currencyFor($userId);
            $balance = self::balanceCents($userId, $cur); // SOUS le verrou, DEVISE principale uniquement
            if ($balance <= 0 || $balance < self::thresholdCents($cur)) {
                return null;
            }
            $pid = uuid();
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO wallet_withdrawals (public_id, user_id, amount_cents, currency, method, destination)
                     VALUES (:p, :u, :a, :c, :m, :d)'
                )->execute(['p' => $pid, 'u' => $userId, 'a' => $balance, 'c' => $cur, 'm' => $method, 'd' => mb_substr($destination, 0, 160)]);
                self::debit($userId, $balance, $cur, 'withdrawal', $pid);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                return null;
            }
            return $pid;
        } finally {
            try {
                $rl = $pdo->prepare('SELECT RELEASE_LOCK(:k)');
                $rl->execute(['k' => $lock]);
            } catch (\Throwable) {
            }
        }
    }

    /** @return list<array> demandes de retrait d'un vendeur */
    public static function withdrawalsFor(int $userId): array
    {
        try {
            self::ensureTables();
            $stmt = db()->prepare('SELECT * FROM wallet_withdrawals WHERE user_id = :u ORDER BY id DESC LIMIT 50');
            $stmt->execute(['u' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array> retraits en attente (back-office admin), avec le vendeur. */
    public static function pendingWithdrawals(): array
    {
        try {
            self::ensureTables();
            return db()->query("SELECT w.*, u.full_name AS seller_name, u.email AS seller_email
                                FROM wallet_withdrawals w LEFT JOIN users u ON u.id = w.user_id
                                WHERE w.status = 'pending' ORDER BY w.id ASC")->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function pendingCount(): int
    {
        try {
            self::ensureTables();
            return (int) db()->query("SELECT COUNT(*) FROM wallet_withdrawals WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function findWithdrawal(int $id): ?array
    {
        self::ensureTables();
        $stmt = db()->prepare('SELECT * FROM wallet_withdrawals WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Traitement admin d'un retrait : 'paid' (versé, le débit reste) ou 'reject'
     * (recrédite le solde). Idempotent (ne retraite pas un retrait déjà traité).
     */
    public static function processWithdrawal(int $id, int $adminId, string $action): bool
    {
        self::ensureTables();
        $w = self::findWithdrawal($id);
        if ($w === null || ($w['status'] ?? '') !== 'pending') {
            return false;
        }
        $status = $action === 'paid' ? 'paid' : 'rejected';
        db()->prepare('UPDATE wallet_withdrawals SET status = :s, processed_by = :by, processed_at = NOW() WHERE id = :id')
            ->execute(['s' => $status, 'by' => $adminId, 'id' => $id]);
        if ($status === 'rejected') {
            // On rend l'argent au vendeur (annulation de la réservation).
            self::credit((int) $w['user_id'], (int) $w['amount_cents'], (string) $w['currency'], 'reversal', (string) $w['public_id']);
        }
        return true;
    }
}
