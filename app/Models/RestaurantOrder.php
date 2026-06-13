<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Commandes de restaurant (panier multi-lignes). restaurant_orders = l'en-tête
 * (client, service, total, statut) ; restaurant_order_items = les lignes
 * (plat ou contenance de boisson, prix figé au moment de la commande). Montants
 * en centimes. Tables auto-créées (TiDB).
 */
final class RestaurantOrder
{
    public const STATUSES = ['new', 'confirmed', 'ready', 'delivered', 'cancelled'];
    public const SERVICES = ['dine_in', 'takeaway', 'delivery'];

    private const TRANSITIONS = [
        'confirm' => ['new' => 'confirmed'],
        'ready'   => ['confirmed' => 'ready'],
        'deliver' => ['ready' => 'delivered', 'confirmed' => 'delivered'],
        'cancel'  => ['new' => 'cancelled', 'confirmed' => 'cancelled', 'ready' => 'cancelled'],
    ];

    public static function ensureTables(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS restaurant_orders (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id     CHAR(36) NOT NULL UNIQUE,
                restaurant_id BIGINT UNSIGNED NOT NULL,
                seller_id     BIGINT UNSIGNED NOT NULL,
                client_name   VARCHAR(80) NOT NULL,
                client_phone  VARCHAR(24) NULL,
                service       VARCHAR(12) NOT NULL DEFAULT \'takeaway\',
                note          VARCHAR(500) NULL,
                subtotal_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency      CHAR(3) NOT NULL DEFAULT \'XOF\',
                status        VARCHAR(12) NOT NULL DEFAULT \'new\',
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_rorders_resto (restaurant_id, status, id),
                KEY idx_rorders_seller (seller_id, status)
            )'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS restaurant_order_items (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id      BIGINT UNSIGNED NOT NULL,
                title         VARCHAR(140) NOT NULL,
                qty           INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                line_total_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_roitems_order (order_id)
            )'
        );
    }

    /**
     * Crée une commande à partir de lignes déjà validées côté serveur.
     * @param list<array{title:string,qty:int,unit_price_cents:int}> $lines
     */
    public static function create(array $header, array $lines): string
    {
        self::ensureTables();
        $pdo = db();
        $publicId = uuid();
        $subtotal = 0;
        foreach ($lines as $l) {
            $subtotal += $l['unit_price_cents'] * $l['qty'];
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO restaurant_orders
                    (public_id, restaurant_id, seller_id, client_name, client_phone, service, note, subtotal_cents, currency, status)
                 VALUES (:pid, :rid, :sid, :cname, :cphone, :service, :note, :sub, :cur, \'new\')'
            );
            $stmt->execute([
                'pid' => $publicId, 'rid' => $header['restaurant_id'], 'sid' => $header['seller_id'],
                'cname' => $header['client_name'], 'cphone' => $header['client_phone'],
                'service' => $header['service'], 'note' => $header['note'],
                'sub' => $subtotal, 'cur' => $header['currency'],
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare(
                'INSERT INTO restaurant_order_items (order_id, title, qty, unit_price_cents, line_total_cents)
                 VALUES (:o, :t, :q, :u, :lt)'
            );
            foreach ($lines as $l) {
                $ins->execute([
                    'o' => $orderId, 't' => $l['title'], 'q' => $l['qty'],
                    'u' => $l['unit_price_cents'], 'lt' => $l['unit_price_cents'] * $l['qty'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $publicId;
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM restaurant_orders WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array> lignes d'une commande */
    public static function items(int $orderId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM restaurant_order_items WHERE order_id = :o ORDER BY id');
            $stmt->execute(['o' => $orderId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array> commandes d'un restaurant (récentes d'abord) */
    public static function forRestaurant(int $restaurantId, ?string $status = null): array
    {
        try {
            $sql = 'SELECT * FROM restaurant_orders WHERE restaurant_id = :r';
            $args = ['r' => $restaurantId];
            if ($status !== null && in_array($status, self::STATUSES, true)) {
                $sql .= ' AND status = :s';
                $args['s'] = $status;
            }
            $stmt = db()->prepare($sql . ' ORDER BY id DESC LIMIT 200');
            $stmt->execute($args);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function pendingForUser(int $sellerId): int
    {
        try {
            $stmt = db()->prepare("SELECT COUNT(*) FROM restaurant_orders WHERE seller_id = :s AND status = 'new'");
            $stmt->execute(['s' => $sellerId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function applyAction(int $id, string $current, string $action): ?string
    {
        $to = self::TRANSITIONS[$action][$current] ?? null;
        if ($to === null) {
            return null;
        }
        db()->prepare('UPDATE restaurant_orders SET status = :s WHERE id = :id')->execute(['s' => $to, 'id' => $id]);
        return $to;
    }
}
