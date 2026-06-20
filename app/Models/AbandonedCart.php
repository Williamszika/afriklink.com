<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Paniers abandonnés — miroir interrogeable du panier (qui vit en session) pour
 * les acheteurs CONNECTÉS, afin de pouvoir relancer ceux laissés en plan.
 *
 * Une ligne par utilisateur (upsert). À chaque modif du panier, on rafraîchit
 * l'instantané et on réarme le rappel (reminded_at = NULL). À l'achat, la ligne
 * passe « converted » (jamais de « vous avez oublié votre panier » à un acheteur
 * qui vient de payer). Relance « best-effort », une fois par période d'inactivité.
 */
final class AbandonedCart
{
    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS abandoned_carts (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id     BIGINT UNSIGNED NOT NULL UNIQUE,
                email       VARCHAR(191) NOT NULL,
                token       CHAR(36) NOT NULL UNIQUE,
                items_json  MEDIUMTEXT NOT NULL,
                item_count  INT UNSIGNED NOT NULL DEFAULT 0,
                status      VARCHAR(12) NOT NULL DEFAULT 'active',
                reminded_at DATETIME NULL,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_due (status, reminded_at, updated_at)
            )"
        );
    }

    /** @param array<int,array<string,int>> $rawCart  $_SESSION['cart'] : [bid][pid] => qty */
    public static function capture(int $userId, string $email, array $rawCart): void
    {
        if ($userId <= 0) {
            return;
        }
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        self::ensureTable();
        $count = 0;
        foreach ($rawCart as $items) {
            foreach ((array) $items as $q) {
                $count += max(0, (int) $q);
            }
        }
        // Panier vidé : on considère la ligne comme close (ne pas relancer un panier vide).
        if ($count === 0) {
            self::convert($userId);
            return;
        }
        try {
            db()->prepare(
                "INSERT INTO abandoned_carts (user_id, email, token, items_json, item_count, status, updated_at)
                 VALUES (:u, :e, :t, :j, :c, 'active', NOW())
                 ON DUPLICATE KEY UPDATE email=VALUES(email), items_json=VALUES(items_json),
                    item_count=VALUES(item_count), status='active', reminded_at=NULL, updated_at=NOW()"
            )->execute([
                'u' => $userId, 'e' => $email, 't' => uuid(),
                'j' => json_encode($rawCart, JSON_UNESCAPED_UNICODE), 'c' => $count,
            ]);
        } catch (\Throwable) {
        }
    }

    /** L'acheteur a payé (ou vidé son panier) : on clôt la relance. */
    public static function convert(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        self::ensureTable();
        try {
            db()->prepare("UPDATE abandoned_carts SET status='converted', reminded_at=NULL WHERE user_id=:u")
                ->execute(['u' => $userId]);
        } catch (\Throwable) {
        }
    }

    /** Désinscription des rappels (lien dans l'e-mail). Renvoie l'e-mail, ou null. */
    public static function optout(string $token): ?string
    {
        self::ensureTable();
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        try {
            $stmt = db()->prepare('SELECT id, email FROM abandoned_carts WHERE token=:t LIMIT 1');
            $stmt->execute(['t' => $token]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            db()->prepare("UPDATE abandoned_carts SET status='optout' WHERE id=:id")->execute(['id' => (int) $row['id']]);
            return (string) $row['email'];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function optoutUrl(string $token): string
    {
        return url('/paniers/stop/' . $token);
    }

    /**
     * Paniers à relancer : actifs, non encore relancés, inactifs depuis au moins
     * $staleMinutes mais pas plus vieux que $maxAgeHours (on ne harcèle pas les
     * paniers fossiles). @return list<array>
     */
    public static function due(int $staleMinutes = 120, int $maxAgeHours = 336, int $limit = 200): array
    {
        self::ensureTable();
        $staleMinutes = max(5, $staleMinutes);
        $maxAgeHours  = max(1, $maxAgeHours);
        $limit        = max(1, min(1000, $limit));
        try {
            $stmt = db()->prepare(
                "SELECT * FROM abandoned_carts
                  WHERE status='active' AND reminded_at IS NULL AND item_count > 0
                    AND updated_at <= (NOW() - INTERVAL :stale MINUTE)
                    AND updated_at >= (NOW() - INTERVAL :maxh HOUR)
                  ORDER BY updated_at ASC LIMIT {$limit}"
            );
            $stmt->execute(['stale' => $staleMinutes, 'maxh' => $maxAgeHours]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function markReminded(int $id): void
    {
        try {
            db()->prepare('UPDATE abandoned_carts SET reminded_at=NOW() WHERE id=:id')->execute(['id' => $id]);
        } catch (\Throwable) {
        }
    }

    /**
     * Résout les lignes d'un panier (items_json) en produits affichables.
     * @return array{lines:list<array{name:string,qty:int,price_cents:int,currency:string}>, total_cents:int, currency:string, count:int}
     */
    public static function resolveItems(string $itemsJson): array
    {
        $raw = json_decode($itemsJson, true);
        $out = ['lines' => [], 'total_cents' => 0, 'currency' => 'EUR', 'count' => 0];
        if (!is_array($raw)) {
            return $out;
        }
        $pids = [];
        foreach ($raw as $items) {
            foreach (array_keys((array) $items) as $pid) {
                $pids[] = (string) $pid;
            }
        }
        if ($pids === []) {
            return $out;
        }
        try {
            $in   = implode(',', array_fill(0, count($pids), '?'));
            $stmt = db()->prepare(
                "SELECT p.public_id, p.name, p.price_cents, b.currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.public_id IN ($in)"
            );
            $stmt->execute($pids);
            $byPid = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $byPid[(string) $r['public_id']] = $r;
            }
            foreach ($raw as $items) {
                foreach ((array) $items as $pid => $qty) {
                    $p = $byPid[(string) $pid] ?? null;
                    $qty = max(1, (int) $qty);
                    if ($p === null) {
                        continue;
                    }
                    $out['lines'][] = [
                        'name'        => (string) $p['name'],
                        'qty'         => $qty,
                        'price_cents' => (int) $p['price_cents'],
                        'currency'    => (string) $p['currency'],
                    ];
                    $out['currency']     = (string) $p['currency'];
                    $out['total_cents'] += (int) $p['price_cents'] * $qty;
                    $out['count']       += $qty;
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }
}
