<?php
declare(strict_types=1);

namespace App\Models;

/**
 * payments — journal des paiements (intentions + statut). Une ligne par
 * tentative d'encaissement, rattachée à une boutique (et plus tard à une
 * commande). Montants en centimes + devise. Sert de source de vérité quel
 * que soit le fournisseur. Table auto-créée.
 */
final class Payment
{
    public const STATUSES = ['pending', 'paid', 'failed', 'cancelled'];

    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS payments (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id    CHAR(36) NOT NULL UNIQUE,
                boutique_id  BIGINT UNSIGNED NULL,
                order_id     BIGINT UNSIGNED NULL,
                user_id      BIGINT UNSIGNED NOT NULL,
                provider     VARCHAR(20) NOT NULL,
                provider_ref VARCHAR(100) NULL,
                amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency     CHAR(3) NOT NULL DEFAULT \'EUR\',
                description  VARCHAR(255) NULL,
                status       VARCHAR(12) NOT NULL DEFAULT \'pending\',
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_payments_boutique (boutique_id, status),
                KEY idx_payments_user (user_id)
            )'
        );
    }

    /** Crée une intention de paiement et renvoie sa référence (public_id). */
    public static function create(array $d): string
    {
        self::ensureTable();
        $ref = uuid();
        $stmt = db()->prepare(
            'INSERT INTO payments (public_id, boutique_id, order_id, user_id, provider,
                amount_cents, currency, description, status)
             VALUES (:pid, :bid, :oid, :uid, :provider, :amount, :cur, :desc, \'pending\')'
        );
        $stmt->execute([
            'pid' => $ref,
            'bid' => $d['boutique_id'] ?? null,
            'oid' => $d['order_id'] ?? null,
            'uid' => $d['user_id'],
            'provider' => $d['provider'],
            'amount' => $d['amount_cents'],
            'cur' => $d['currency'],
            'desc' => $d['description'] ?? null,
        ]);
        return $ref;
    }

    public static function findByReference(string $ref): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM payments WHERE public_id = :r LIMIT 1');
            $stmt->execute(['r' => $ref]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setStatus(string $ref, string $status, string $providerRef = ''): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $stmt = db()->prepare(
            'UPDATE payments SET status = :s' . ($providerRef !== '' ? ', provider_ref = :pr' : '') . ' WHERE public_id = :r'
        );
        $args = ['s' => $status, 'r' => $ref];
        if ($providerRef !== '') {
            $args['pr'] = $providerRef;
        }
        $stmt->execute($args);
    }
}
