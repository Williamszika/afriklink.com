<?php
declare(strict_types=1);

namespace App\Models;

/**
 * orders — commandes d'une boutique. Première brique du circuit de vente :
 * aujourd'hui le vendeur enregistre les commandes reçues (WhatsApp, téléphone,
 * sur place) et suit leur traitement ; le panier en ligne (checkout public)
 * viendra insérer dans la même table avec source = 'online'. Montants en
 * centimes + devise (jamais de flottants). Table auto-créée.
 */
final class Order
{
    /** Statuts et transitions autorisées (action => [depuis => vers]). */
    public const STATUSES = ['new', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    private const TRANSITIONS = [
        'confirm' => ['new' => 'confirmed'],
        'ship'    => ['confirmed' => 'shipped'],
        'deliver' => ['shipped' => 'delivered'],
        'cancel'  => ['new' => 'cancelled', 'confirmed' => 'cancelled'],
    ];

    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS orders (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id        CHAR(36) NOT NULL UNIQUE,
                boutique_id      BIGINT UNSIGNED NOT NULL,
                user_id          BIGINT UNSIGNED NOT NULL,
                product_id       BIGINT UNSIGNED NULL,
                product_name     VARCHAR(150) NOT NULL,
                unit_price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                qty              INT UNSIGNED NOT NULL DEFAULT 1,
                total_cents      BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency         CHAR(3) NOT NULL DEFAULT \'EUR\',
                client_name      VARCHAR(80) NOT NULL,
                client_phone     VARCHAR(24) NULL,
                note             VARCHAR(500) NULL,
                source           VARCHAR(12) NOT NULL DEFAULT \'manual\',
                status           VARCHAR(12) NOT NULL DEFAULT \'new\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_orders_boutique (boutique_id, status, id),
                KEY idx_orders_user (user_id, status)
            )'
        );
    }

    public static function create(array $d): string
    {
        self::ensureTable();
        $publicId = uuid();
        $stmt = db()->prepare(
            'INSERT INTO orders (public_id, boutique_id, user_id, product_id, product_name,
                unit_price_cents, qty, total_cents, currency, client_name, client_phone, note, source, status)
             VALUES (:pid, :bid, :uid, :prod, :pname, :unit, :qty, :total, :cur, :cname, :cphone, :note, :source, \'new\')'
        );
        $stmt->execute([
            'pid' => $publicId, 'bid' => $d['boutique_id'], 'uid' => $d['user_id'],
            'prod' => $d['product_id'], 'pname' => $d['product_name'],
            'unit' => $d['unit_price_cents'], 'qty' => $d['qty'], 'total' => $d['total_cents'],
            'cur' => $d['currency'], 'cname' => $d['client_name'], 'cphone' => $d['client_phone'],
            'note' => $d['note'], 'source' => $d['source'] ?? 'manual',
        ]);
        return $publicId;
    }

    /** @return list<array> commandes de la boutique (les plus récentes d'abord) */
    public static function forBoutique(int $boutiqueId, ?string $status = null): array
    {
        try {
            $sql = 'SELECT * FROM orders WHERE boutique_id = :bid';
            $args = ['bid' => $boutiqueId];
            if ($status !== null && in_array($status, self::STATUSES, true)) {
                $sql .= ' AND status = :st';
                $args['st'] = $status;
            }
            $stmt = db()->prepare($sql . ' ORDER BY id DESC LIMIT 200');
            $stmt->execute($args);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Compteurs par statut. @return array<string,int> (+ clé total) */
    public static function countFor(int $boutiqueId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0) + ['total' => 0];
        try {
            $stmt = db()->prepare('SELECT status, COUNT(*) AS n FROM orders WHERE boutique_id = :bid GROUP BY status');
            $stmt->execute(['bid' => $boutiqueId]);
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $counts[(string) $r['status']] = (int) $r['n'];
                $counts['total'] += (int) $r['n'];
            }
        } catch (\Throwable) {
            // table absente : tout à zéro
        }
        return $counts;
    }

    /** Commandes « à traiter » d'un vendeur (badge de la barre latérale). */
    public static function pendingForUser(int $userId): int
    {
        try {
            $stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'new'");
            $stmt->execute(['uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM orders WHERE public_id = :pid LIMIT 1');
            $stmt->execute(['pid' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Applique une action de traitement ; renvoie le nouveau statut ou null si interdite. */
    public static function applyAction(int $id, string $current, string $action): ?string
    {
        $to = self::TRANSITIONS[$action][$current] ?? null;
        if ($to === null) {
            return null;
        }
        db()->prepare('UPDATE orders SET status = :s WHERE id = :id')->execute(['s' => $to, 'id' => $id]);
        return $to;
    }
}
