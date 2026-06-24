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
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS payments (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id     CHAR(36) NOT NULL UNIQUE,
                kind          VARCHAR(12) NOT NULL DEFAULT \'boutique\',
                boutique_id   BIGINT UNSIGNED NULL,
                restaurant_id BIGINT UNSIGNED NULL,
                order_id      BIGINT UNSIGNED NULL,
                user_id       BIGINT UNSIGNED NOT NULL,
                provider      VARCHAR(20) NOT NULL,
                provider_ref  VARCHAR(100) NULL,
                amount_cents  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency      CHAR(3) NOT NULL DEFAULT \'EUR\',
                description   VARCHAR(255) NULL,
                status        VARCHAR(12) NOT NULL DEFAULT \'pending\',
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_payments_boutique (boutique_id, status),
                KEY idx_payments_user (user_id)
            )'
        );
        self::migrate();
    }

    /** Ajoute kind + restaurant_id sur une table déjà créée (commandes restaurant). */
    private static function migrate(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            'kind'          => "ADD COLUMN kind VARCHAR(12) NOT NULL DEFAULT 'boutique' AFTER public_id",
            'restaurant_id' => "ADD COLUMN restaurant_id BIGINT UNSIGNED NULL AFTER boutique_id",
        ] as $col => $ddl) {
            try {
                db()->query("SELECT {$col} FROM payments LIMIT 1");
            } catch (\Throwable) {
                try {
                    db()->exec("ALTER TABLE payments {$ddl}");
                } catch (\Throwable) {
                }
            }
        }
    }

    /** Crée une intention de paiement et renvoie sa référence (public_id). */
    public static function create(array $d): string
    {
        self::ensureTable();
        $ref = uuid();
        $stmt = db()->prepare(
            'INSERT INTO payments (public_id, kind, boutique_id, restaurant_id, order_id, user_id, provider,
                amount_cents, currency, description, status)
             VALUES (:pid, :kind, :bid, :rid, :oid, :uid, :provider, :amount, :cur, :desc, \'pending\')'
        );
        $stmt->execute([
            'pid' => $ref,
            'kind' => $d['kind'] ?? 'boutique',
            'bid' => $d['boutique_id'] ?? null,
            'rid' => $d['restaurant_id'] ?? null,
            'oid' => $d['order_id'] ?? null,
            'uid' => $d['user_id'],
            'provider' => $d['provider'],
            'amount' => $d['amount_cents'],
            'cur' => $d['currency'],
            'desc' => $d['description'] ?? null,
        ]);
        return $ref;
    }

    /** Paiement le plus récent rattaché à une commande d'un type donné (ou null). */
    public static function latestForOrder(int $orderId, string $kind = 'boutique'): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM payments WHERE order_id = :o AND kind = :k ORDER BY id DESC LIMIT 1');
            $stmt->execute(['o' => $orderId, 'k' => $kind]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Revendication ATOMIQUE du statut « payé ». Passe la ligne à 'paid'
     * UNIQUEMENT si elle ne l'était pas déjà, et renvoie true seulement pour
     * l'appel qui a EFFECTIVEMENT opéré la transition. Garde d'idempotence : si
     * plusieurs livraisons de webhook (CinetPay sans déduplication, ou deux
     * événements Stripe pour le même paiement) arrivent en parallèle, une seule
     * obtient true → un seul crédit de portefeuille.
     */
    public static function claimPaid(string $ref, string $providerRef = ''): bool
    {
        if ($ref === '') {
            return false;
        }
        $stmt = db()->prepare(
            'UPDATE payments SET status = :s' . ($providerRef !== '' ? ', provider_ref = :pr' : '') . "
              WHERE public_id = :r AND status <> 'paid'"
        );
        $args = ['s' => 'paid', 'r' => $ref];
        if ($providerRef !== '') {
            $args['pr'] = $providerRef;
        }
        $stmt->execute($args);
        return $stmt->rowCount() === 1;
    }
}
