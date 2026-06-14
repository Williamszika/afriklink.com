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
        ddl_safe(
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
                shipping_cents   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency         CHAR(3) NOT NULL DEFAULT \'EUR\',
                client_name      VARCHAR(80) NOT NULL,
                client_phone     VARCHAR(24) NULL,
                client_email     VARCHAR(120) NULL,
                client_address   VARCHAR(220) NULL,
                geo_lat          DECIMAL(9,6) NULL,
                geo_lng          DECIMAL(9,6) NULL,
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
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS order_items (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id         BIGINT UNSIGNED NOT NULL,
                product_id       BIGINT UNSIGNED NULL,
                variant_id       BIGINT UNSIGNED NULL,
                title            VARCHAR(150) NOT NULL,
                qty              INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                line_total_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_oitems_order (order_id)
            )'
        );
        // Colonne variante (ajoutée après coup sur les tables déjà créées).
        try {
            db()->query('SELECT variant_id FROM order_items LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE order_items ADD COLUMN variant_id BIGINT UNSIGNED NULL AFTER product_id');
            } catch (\Throwable) {
            }
        }
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
            'geo_lat'        => "ADD COLUMN geo_lat DECIMAL(9,6) NULL AFTER client_address",
            'geo_lng'        => "ADD COLUMN geo_lng DECIMAL(9,6) NULL AFTER geo_lat",
            'shipping_cents' => "ADD COLUMN shipping_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER total_cents",
            'discount_cents' => "ADD COLUMN discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_cents",
            'discount_code'  => "ADD COLUMN discount_code VARCHAR(40) NULL AFTER discount_cents",
            'channel'        => "ADD COLUMN channel VARCHAR(8) NOT NULL DEFAULT 'online' AFTER source",
            'register_session_id' => "ADD COLUMN register_session_id BIGINT UNSIGNED NULL AFTER channel",
            'dest_country'   => "ADD COLUMN dest_country CHAR(2) NULL AFTER client_address",
            // Suivi de livraison (transporteur + n° de suivi → lien cliquable).
            'carrier'         => "ADD COLUMN carrier VARCHAR(32) NULL AFTER fulfillment",
            'tracking_number' => "ADD COLUMN tracking_number VARCHAR(64) NULL AFTER carrier",
            'tracking_url'    => "ADD COLUMN tracking_url VARCHAR(300) NULL AFTER tracking_number",
            'shipped_at'      => "ADD COLUMN shipped_at DATETIME NULL AFTER status",
            'delivered_at'    => "ADD COLUMN delivered_at DATETIME NULL AFTER shipped_at",
            // Acheteur connecté (≠ user_id qui pointe le vendeur) : historique d'achat.
            'buyer_user_id'   => "ADD COLUMN buyer_user_id BIGINT UNSIGNED NULL AFTER user_id",
            // Demande de retour par l'acheteur (après livraison).
            'return_requested_at' => "ADD COLUMN return_requested_at DATETIME NULL AFTER delivered_at",
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
        $shipping = max(0, (int) ($header['shipping_cents'] ?? 0));
        $discount = max(0, min($subtotal, (int) ($header['discount_cents'] ?? 0)));
        $grand = max(0, $subtotal + $shipping - $discount);
        // Étiquette de repli (l'affichage détaillé se fait via order_items).
        $first = (string) ($lines[0]['title'] ?? '');
        $summary = count($lines) > 1 ? $first . ' +' . (count($lines) - 1) : $first;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (public_id, boutique_id, user_id, product_id, product_name,
                    unit_price_cents, qty, total_cents, shipping_cents, discount_cents, discount_code, currency, client_name, client_phone, client_email, client_address, dest_country,
                    geo_lat, geo_lng, note, fulfillment, payment_term, payment_method, source, status)
                 VALUES (:pid, :bid, :uid, NULL, :pname, 0, :qty, :total, :ship, :disc, :dcode, :cur, :cname, :cphone, :cemail, :caddr, :destc,
                    :lat, :lng, :note, :ful, :term, :method, \'online\', \'new\')'
            );
            $stmt->execute([
                'pid' => $publicId, 'bid' => $header['boutique_id'], 'uid' => $header['user_id'],
                'pname' => mb_substr($summary, 0, 150), 'qty' => $count, 'total' => $grand, 'ship' => $shipping,
                'disc' => $discount, 'dcode' => $header['discount_code'] ?? null,
                'cur' => $header['currency'], 'cname' => $header['client_name'],
                'cphone' => $header['client_phone'], 'cemail' => $header['client_email'] ?? null,
                'caddr' => $header['client_address'] ?? null,
                'destc' => ($header['dest_country'] ?? null) ?: null,
                'lat' => $header['geo_lat'] ?? null, 'lng' => $header['geo_lng'] ?? null,
                'note' => $header['note'], 'ful' => $header['fulfillment'], 'term' => $header['payment_term'] ?? null,
                'method' => $header['payment_method'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, variant_id, title, qty, unit_price_cents, line_total_cents)
                 VALUES (:o, :p, :v, :t, :q, :u, :lt)'
            );
            // Décompte du stock : atomique et borné (jamais négatif) — variante ET
            // produit (stock partagé online + POS). Le WHERE `stock >= :qmin`
            // sérialise deux ventes concurrentes : la 2ᵉ voit le stock déjà baissé
            // et n'affecte 0 ligne → jamais de survente. Stock NULL = illimité.
            $dec = $pdo->prepare(
                'UPDATE products SET stock = stock - :qty WHERE id = :pid AND stock IS NOT NULL AND stock >= :qmin'
            );
            $decV = $pdo->prepare(
                'UPDATE product_variants SET stock = stock - :qtyv WHERE id = :vid AND stock IS NOT NULL AND stock >= :qminv'
            );
            foreach ($lines as $l) {
                $ins->execute([
                    'o' => $orderId, 'p' => $l['product_id'], 'v' => $l['variant_id'] ?? null,
                    't' => mb_substr($l['title'], 0, 150),
                    'q' => $l['qty'], 'u' => $l['unit_price_cents'], 'lt' => $l['unit_price_cents'] * $l['qty'],
                ]);
                if (!empty($l['variant_id'])) {
                    $decV->execute(['qtyv' => $l['qty'], 'qminv' => $l['qty'], 'vid' => (int) $l['variant_id']]);
                }
                if (!empty($l['product_id'])) {
                    $dec->execute(['qty' => $l['qty'], 'qmin' => $l['qty'], 'pid' => (int) $l['product_id']]);
                }
            }
            $low = self::detectLowStock($pdo, $lines);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::notifyLowStock((int) ($header['user_id'] ?? 0), $low);
        return $publicId;
    }

    /** Lignes de paiement (« tenders ») d'une vente — permet le paiement mixte. */
    public static function ensureTendersTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS order_tenders (
                id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id           BIGINT UNSIGNED NOT NULL,
                method             VARCHAR(16) NOT NULL,
                amount_cents       BIGINT UNSIGNED NOT NULL,
                change_given_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency           CHAR(3) NOT NULL DEFAULT \'EUR\',
                provider_ref       VARCHAR(120) NULL,
                created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_tenders_order (order_id)
            )'
        );
    }

    /**
     * Vente en caisse (POS) : commande channel='pos' réglée immédiatement, qui
     * décrémente LE MÊME stock partagé (variantes/produits) que la boutique en
     * ligne. Décrément atomique + STRICT : si une ligne à stock fini ne peut être
     * honorée (vente concurrente en ligne), toute la vente est annulée (null).
     * @param list<array{product_id:int,variant_id:?int,title:string,qty:int,unit_price_cents:int}> $lines
     * @param array{method:string,amount_cents:int,change_given_cents:int} $tender
     */
    public static function createPosSale(array $header, array $lines, array $tender): ?string
    {
        self::ensureTable();
        self::ensureItemsTable();
        self::ensureTendersTable();
        if ($lines === []) {
            return null;
        }
        $pdo = db();
        $publicId = uuid();
        $count = 0;
        $subtotal = 0;
        foreach ($lines as $l) {
            $count += $l['qty'];
            $subtotal += $l['qty'] * $l['unit_price_cents'];
        }
        $first = (string) ($lines[0]['title'] ?? '');
        $summary = count($lines) > 1 ? $first . ' +' . (count($lines) - 1) : $first;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO orders (public_id, boutique_id, user_id, product_id, product_name,
                    unit_price_cents, qty, total_cents, currency, client_name, source, channel, register_session_id, payment_status, status)
                 VALUES (:pid, :bid, :uid, NULL, :pname, 0, :qty, :total, :cur, :cname, 'pos', 'pos', :rs, 'paid', 'delivered')"
            );
            $stmt->execute([
                'pid' => $publicId, 'bid' => $header['boutique_id'], 'uid' => $header['user_id'],
                'pname' => mb_substr($summary, 0, 150), 'qty' => $count, 'total' => $subtotal,
                'cur' => $header['currency'], 'cname' => mb_substr((string) ($header['client_name'] ?? 'Client comptoir'), 0, 80),
                'rs' => $header['register_session_id'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ins  = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variant_id, title, qty, unit_price_cents, line_total_cents) VALUES (:o, :p, :v, :t, :q, :u, :lt)');
            $decV = $pdo->prepare('UPDATE product_variants SET stock = stock - :q WHERE id = :id AND stock IS NOT NULL AND stock >= :qm');
            $decP = $pdo->prepare('UPDATE products SET stock = stock - :q WHERE id = :id AND stock IS NOT NULL AND stock >= :qm');
            foreach ($lines as $l) {
                $ins->execute(['o' => $orderId, 'p' => $l['product_id'], 'v' => $l['variant_id'] ?? null,
                    't' => mb_substr($l['title'], 0, 150), 'q' => $l['qty'], 'u' => $l['unit_price_cents'], 'lt' => $l['unit_price_cents'] * $l['qty']]);
                if (!empty($l['variant_id'])) {
                    $decV->execute(['q' => $l['qty'], 'qm' => $l['qty'], 'id' => (int) $l['variant_id']]);
                    if ($decV->rowCount() === 0 && !self::isUnlimited('product_variants', (int) $l['variant_id'])) {
                        $pdo->rollBack();
                        return null;
                    }
                }
                if (!empty($l['product_id'])) {
                    $decP->execute(['q' => $l['qty'], 'qm' => $l['qty'], 'id' => (int) $l['product_id']]);
                    if (empty($l['variant_id']) && $decP->rowCount() === 0 && !self::isUnlimited('products', (int) $l['product_id'])) {
                        $pdo->rollBack();
                        return null;
                    }
                }
            }
            $pdo->prepare('INSERT INTO order_tenders (order_id, method, amount_cents, change_given_cents, currency) VALUES (:o, :m, :a, :ch, :cur)')
                ->execute(['o' => $orderId, 'm' => (string) ($tender['method'] ?? 'cash'),
                    'a' => max(0, (int) ($tender['amount_cents'] ?? $subtotal)), 'ch' => max(0, (int) ($tender['change_given_cents'] ?? 0)),
                    'cur' => $header['currency']]);
            $low = self::detectLowStock($pdo, $lines);
            $pdo->commit();
        } catch (\Throwable) {
            $pdo->rollBack();
            return null;
        }
        self::notifyLowStock((int) ($header['user_id'] ?? 0), $low);
        return $publicId;
    }

    /** Stock NULL (illimité) sur une ligne ? (pour le décrément strict du POS). */
    private static function isUnlimited(string $table, int $id): bool
    {
        try {
            $stmt = db()->prepare("SELECT stock FROM {$table} WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetchColumn() === null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Détecte les lignes dont le stock vient de PASSER sous le seuil d'alerte
     * lors de cette vente — franchissement unique (new ≤ seuil < new+qty) pour
     * ne notifier qu'une fois. À appeler DANS la transaction, après les
     * décréments. Stock NULL = illimité (ignoré). Best-effort : ne casse jamais
     * la vente (renvoie [] en cas d'erreur).
     * @return list<array{title:string,stock:int}>
     */
    private static function detectLowStock(\PDO $pdo, array $lines): array
    {
        try {
            $t = (int) config('shop.low_stock_threshold', 3);
            if ($t <= 0) {
                return [];
            }
            $out = [];
            $qV = $pdo->prepare('SELECT stock FROM product_variants WHERE id = :id');
            $qP = $pdo->prepare('SELECT stock FROM products WHERE id = :id');
            foreach ($lines as $l) {
                $qty = (int) ($l['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if (!empty($l['variant_id'])) {
                    $qV->execute(['id' => (int) $l['variant_id']]);
                    $s = $qV->fetchColumn();
                } elseif (!empty($l['product_id'])) {
                    $qP->execute(['id' => (int) $l['product_id']]);
                    $s = $qP->fetchColumn();
                } else {
                    continue;
                }
                if ($s === null || $s === false) {
                    continue; // illimité ou introuvable
                }
                $new = (int) $s;
                $old = $new + $qty; // le décrément a réussi d'exactement qty
                if ($new <= $t && $old > $t) {
                    $out[] = ['title' => (string) ($l['title'] ?? ''), 'stock' => $new];
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Pousse une alerte « stock bas » au vendeur pour chaque franchissement. */
    private static function notifyLowStock(int $userId, array $crossings): void
    {
        foreach ($crossings as $c) {
            Notification::push(
                $userId,
                'low_stock',
                t('notif.low_stock'),
                trim((string) ($c['title'] ?? '')) . ' · ' . t('notif.low_stock_left', ['n' => (int) ($c['stock'] ?? 0)]),
                '/boutique/gerer'
            );
        }
    }

    /**
     * Synthèse des ventes POS d'une session (rapports X/Z) : nombre, total, et
     * répartition nette par moyen de paiement (montant − rendu).
     * @return array{count:int,total:int,tenders:array<string,int>}
     */
    public static function posSummaryForSession(int $sessionId): array
    {
        $out = ['count' => 0, 'total' => 0, 'tenders' => []];
        try {
            $stmt = db()->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(total_cents), 0) AS total FROM orders WHERE register_session_id = :s');
            $stmt->execute(['s' => $sessionId]);
            $r = $stmt->fetch() ?: [];
            $out['count'] = (int) ($r['n'] ?? 0);
            $out['total'] = (int) ($r['total'] ?? 0);
            $t = db()->prepare(
                "SELECT t.method, COALESCE(SUM(t.amount_cents - t.change_given_cents), 0) AS net
                   FROM order_tenders t JOIN orders o ON o.id = t.order_id
                  WHERE o.register_session_id = :s GROUP BY t.method"
            );
            $t->execute(['s' => $sessionId]);
            foreach ($t->fetchAll() ?: [] as $row) {
                $out['tenders'][(string) $row['method']] = (int) $row['net'];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** Total des ventes POS réglées en espèces d'une session (théorique de caisse). */
    public static function posCashSalesForSession(int $sessionId): int
    {
        try {
            $stmt = db()->prepare(
                "SELECT COALESCE(SUM(t.amount_cents - t.change_given_cents), 0)
                   FROM order_tenders t JOIN orders o ON o.id = t.order_id
                  WHERE o.register_session_id = :s AND t.method = 'cash'"
            );
            $stmt->execute(['s' => $sessionId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
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

    /** Existe-t-il une commande (non annulée) de ce produit au nom de cet e-mail / téléphone ? */
    public static function hasPurchase(int $productId, ?string $email, ?string $phone): bool
    {
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        if ($email === '' && $phone === '') {
            return false;
        }
        try {
            // Placeholders non réutilisables (EMULATE_PREPARES=false) : on construit la
            // condition selon ce qui est fourni, chaque nom n'apparaissant qu'une fois.
            $conds = [];
            $args = ['p' => $productId];
            if ($email !== '') {
                $conds[] = 'o.client_email = :e';
                $args['e'] = $email;
            }
            if ($phone !== '') {
                $conds[] = 'o.client_phone = :ph';
                $args['ph'] = $phone;
            }
            $stmt = db()->prepare(
                "SELECT 1 FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = :p AND o.status <> 'cancelled'
                    AND (" . implode(' OR ', $conds) . ")
                  LIMIT 1"
            );
            $stmt->execute($args);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Unités vendues par produit sur les $days derniers jours (commandes non
     * annulées) — base de la prévision de stock / réassort.
     * @param list<int> $productIds
     * @return array<int,int> id produit -> unités vendues
     */
    public static function soldSince(array $productIds, int $days = 30): array
    {
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if ($ids === []) {
            return [];
        }
        $days = max(1, min(365, $days)); // borne sûre, interpolée (jamais liée)
        try {
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare(
                "SELECT oi.product_id AS pid, COALESCE(SUM(oi.qty), 0) AS units
                   FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                  WHERE o.status <> 'cancelled'
                    AND o.created_at >= (NOW() - INTERVAL $days DAY)
                    AND oi.product_id IN ($in)
                  GROUP BY oi.product_id"
            );
            $stmt->execute($ids);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(int) $r['pid']] = (int) $r['units'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
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

    /** L'acheteur demande un retour (après livraison). Best-effort. */
    public static function requestReturn(int $id): void
    {
        try {
            db()->prepare('UPDATE orders SET return_requested_at = COALESCE(return_requested_at, NOW()) WHERE id = :id')
                ->execute(['id' => $id]);
        } catch (\Throwable) {
        }
    }

    /** Rattache une commande à l'acheteur connecté (best-effort : colonne facultative). */
    public static function setBuyer(string $publicId, int $buyerUserId): void
    {
        if ($buyerUserId <= 0) {
            return;
        }
        try {
            db()->prepare('UPDATE orders SET buyer_user_id = :b WHERE public_id = :p')
                ->execute(['b' => $buyerUserId, 'p' => $publicId]);
        } catch (\Throwable) {
            // colonne buyer_user_id non provisionnée : sans gravité
        }
    }

    /** @return list<array> ACHATS d'un acheteur (boutique jointe), récents d'abord. */
    public static function forUser(int $userId, int $limit = 10): array
    {
        try {
            $limit = max(1, min(50, $limit));
            $stmt = db()->prepare(
                "SELECT o.public_id, o.total_cents, o.currency, o.status, o.created_at,
                        b.name AS boutique_name, b.slug AS boutique_slug
                   FROM orders o JOIN boutiques b ON b.id = o.boutique_id
                  WHERE o.buyer_user_id = :uid
                  ORDER BY o.id DESC LIMIT {$limit}"
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Nombre total d'ACHATS d'un acheteur. */
    public static function countForUser(int $userId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE buyer_user_id = :uid');
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
        // Horodatage d'expédition / livraison — best-effort : la colonne peut ne
        // pas encore être provisionnée en prod, ce qui ne doit jamais bloquer le
        // changement de statut (déjà appliqué ci-dessus).
        $col = match ($to) {
            'shipped'   => 'shipped_at',
            'delivered' => 'delivered_at',
            default     => null,
        };
        if ($col !== null) {
            try {
                db()->prepare("UPDATE orders SET {$col} = COALESCE({$col}, NOW()) WHERE id = :id")->execute(['id' => $id]);
            } catch (\Throwable) {
            }
        }
        return $to;
    }

    /**
     * Renseigne le transporteur + le numéro de suivi (+ lien) à l'expédition.
     * Best-effort : si les colonnes de suivi ne sont pas encore provisionnées,
     * l'expédition reste possible (le statut, lui, est déjà passé à « expédiée »).
     */
    public static function setShipment(int $id, ?string $carrier, ?string $tracking, ?string $url): void
    {
        try {
            db()->prepare('UPDATE orders SET carrier = :c, tracking_number = :t, tracking_url = :u WHERE id = :id')
                ->execute([
                    'c'  => ($carrier ?? '') !== '' ? $carrier : null,
                    't'  => ($tracking ?? '') !== '' ? $tracking : null,
                    'u'  => ($url ?? '') !== '' ? $url : null,
                    'id' => $id,
                ]);
        } catch (\Throwable) {
            // colonnes de suivi non provisionnées : sans gravité
        }
    }
}
