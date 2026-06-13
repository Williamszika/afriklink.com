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
                client_email     VARCHAR(120) NULL,
                client_address   VARCHAR(220) NULL,
                note             VARCHAR(500) NULL,
                fulfillment      VARCHAR(16) NULL,
                source           VARCHAR(12) NOT NULL DEFAULT \'manual\',
                payment_status   VARCHAR(12) NOT NULL DEFAULT \'unpaid\',
                payment_ref      CHAR(36) NULL,
                payment_term     VARCHAR(16) NULL,
                payment_method   VARCHAR(16) NULL,
                status           VARCHAR(12) NOT NULL DEFAULT \'new\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_orders_boutique (boutique_id, status, id),
                KEY idx_orders_user (user_id, status)
            )'
        );
        self::migrate();
    }

    /** Lignes d'une commande passée en ligne (panier multi-produits). */
    public static function ensureItemsTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS order_items (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id         BIGINT UNSIGNED NOT NULL,
                product_id       BIGINT UNSIGNED NULL,
                title            VARCHAR(150) NOT NULL,
                qty              INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                line_total_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_oitems_order (order_id)
            )'
        );
    }

    /** Ajoute les colonnes ajoutées après coup sur une table déjà créée. */
    private static function migrate(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $columns = [
            'fulfillment'    => "ADD COLUMN fulfillment VARCHAR(16) NULL AFTER note",
            'payment_status' => "ADD COLUMN payment_status VARCHAR(12) NOT NULL DEFAULT 'unpaid' AFTER source",
            'payment_ref'    => "ADD COLUMN payment_ref CHAR(36) NULL AFTER payment_status",
            'payment_term'   => "ADD COLUMN payment_term VARCHAR(16) NULL AFTER payment_ref",
            'payment_method' => "ADD COLUMN payment_method VARCHAR(16) NULL AFTER payment_term",
            'client_email'   => "ADD COLUMN client_email VARCHAR(120) NULL AFTER client_phone",
            'client_address' => "ADD COLUMN client_address VARCHAR(220) NULL AFTER client_email",
        ];
        foreach ($columns as $col => $ddl) {
            try {
                db()->query("SELECT {$col} FROM orders LIMIT 1");
            } catch (\Throwable) {
                try {
                    db()->exec("ALTER TABLE orders {$ddl}");
                } catch (\Throwable) {
                    // colonne déjà présente ou ALTER indisponible : on ignore
                }
            }
        }
    }

    /** Met à jour le statut de paiement d'une commande (unpaid|pending|paid|failed). */
    public static function setPaymentStatus(int $id, string $status, ?string $ref = null): void
    {
        self::migrate();
        if (!in_array($status, ['unpaid', 'pending', 'paid', 'failed'], true)) {
            return;
        }
        $sql = 'UPDATE orders SET payment_status = :s' . ($ref !== null ? ', payment_ref = :r' : '') . ' WHERE id = :id';
        $args = ['s' => $status, 'id' => $id];
        if ($ref !== null) {
            $args['r'] = $ref;
        }
        db()->prepare($sql)->execute($args);
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

    /**
     * Commande passée en ligne (panier multi-produits). L'en-tête garde le
     * total et un résumé ; chaque ligne (produit + prix figé) va dans
     * order_items. Lignes déjà revalidées côté serveur.
     * @param list<array{product_id:?int,title:string,qty:int,unit_price_cents:int}> $lines
     */
    public static function createCart(array $header, array $lines): string
    {
        self::ensureTable();
        self::ensureItemsTable();
        $pdo = db();
        $publicId = uuid();
        $count = 0;
        $subtotal = 0;
        foreach ($lines as $l) {
            $count += $l['qty'];
            $subtotal += $l['qty'] * $l['unit_price_cents'];
        }
        // Étiquette de repli (l'affichage détaillé se fait via order_items).
        $first = (string) ($lines[0]['title'] ?? '');
        $summary = count($lines) > 1 ? $first . ' +' . (count($lines) - 1) : $first;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (public_id, boutique_id, user_id, product_id, product_name,
                    unit_price_cents, qty, total_cents, currency, client_name, client_phone, client_email, client_address,
                    note, fulfillment, payment_term, payment_method, source, status)
                 VALUES (:pid, :bid, :uid, NULL, :pname, 0, :qty, :total, :cur, :cname, :cphone, :cemail, :caddr,
                    :note, :ful, :term, :method, \'online\', \'new\')'
            );
            $stmt->execute([
                'pid' => $publicId, 'bid' => $header['boutique_id'], 'uid' => $header['user_id'],
                'pname' => mb_substr($summary, 0, 150), 'qty' => $count, 'total' => $subtotal,
                'cur' => $header['currency'], 'cname' => $header['client_name'],
                'cphone' => $header['client_phone'], 'cemail' => $header['client_email'] ?? null,
                'caddr' => $header['client_address'] ?? null,
                'note' => $header['note'], 'ful' => $header['fulfillment'], 'term' => $header['payment_term'] ?? null,
                'method' => $header['payment_method'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, title, qty, unit_price_cents, line_total_cents)
                 VALUES (:o, :p, :t, :q, :u, :lt)'
            );
            // Décompte du stock : atomique et borné (jamais négatif). Les produits
            // à stock illimité (stock NULL) ne sont pas touchés.
            $dec = $pdo->prepare(
                'UPDATE products SET stock = stock - :qty WHERE id = :pid AND stock IS NOT NULL AND stock >= :qmin'
            );
            foreach ($lines as $l) {
                $ins->execute([
                    'o' => $orderId, 'p' => $l['product_id'], 't' => mb_substr($l['title'], 0, 150),
                    'q' => $l['qty'], 'u' => $l['unit_price_cents'], 'lt' => $l['unit_price_cents'] * $l['qty'],
                ]);
                if (!empty($l['product_id'])) {
                    $dec->execute(['qty' => $l['qty'], 'qmin' => $l['qty'], 'pid' => (int) $l['product_id']]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $publicId;
    }

    /**
     * Montant à régler EN LIGNE selon la condition de paiement choisie :
     *   before_delivery → la totalité ; deposit → un acompte (config shop.deposit_pct) ;
     *   on_delivery / non défini → 0 (réglé à la réception, pas de paiement en ligne).
     */
    public static function amountDue(array $order): int
    {
        $total = (int) ($order['total_cents'] ?? 0);
        return match ((string) ($order['payment_term'] ?? '')) {
            'before_delivery' => $total,
            'deposit'         => (int) round($total * (int) config('shop.deposit_pct', 50) / 100),
            default           => 0,
        };
    }

    /** Reste à régler à la livraison (cas de l'acompte). */
    public static function restDue(array $order): int
    {
        return max(0, (int) ($order['total_cents'] ?? 0) - self::amountDue($order));
    }

    /** @return list<array> lignes d'une commande en ligne (vide pour les commandes manuelles) */
    public static function items(int $orderId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :o ORDER BY id');
            $stmt->execute(['o' => $orderId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
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
