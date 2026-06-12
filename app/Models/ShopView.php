<?php
declare(strict_types=1);

namespace App\Models;

/**
 * shop_views — compteurs de vues des pages publiques d'une boutique,
 * agrégés par jour (une ligne par boutique × produit × jour, incrémentée
 * atomiquement). product_id = 0 désigne la page vitrine elle-même.
 * Léger anti-gonflage côté contrôleur : robots exclus, propriétaire exclu,
 * une vue par visiteur et par jour (session). Table auto-créée.
 */
final class ShopView
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS shop_views (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                boutique_id BIGINT UNSIGNED NOT NULL,
                product_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                day         DATE NOT NULL,
                views       INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY uq_views (boutique_id, product_id, day)
            )'
        );
    }

    /** Incrémente la vue du jour (vitrine si $productId = 0). N'échoue jamais. */
    public static function record(int $boutiqueId, int $productId = 0): void
    {
        try {
            self::ensureTable();
            db()->prepare(
                'INSERT INTO shop_views (boutique_id, product_id, day, views)
                 VALUES (:b, :p, CURRENT_DATE, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1'
            )->execute(['b' => $boutiqueId, 'p' => $productId]);
        } catch (\Throwable) {
            // le comptage ne doit jamais casser une page publique
        }
    }

    /** Totaux : tout, 7 jours, 30 jours. @return array{total:int,d7:int,d30:int} */
    public static function totals(int $boutiqueId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT COALESCE(SUM(views), 0) AS total,
                        COALESCE(SUM(CASE WHEN day >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY)  THEN views END), 0) AS d7,
                        COALESCE(SUM(CASE WHEN day >= DATE_SUB(CURRENT_DATE, INTERVAL 29 DAY) THEN views END), 0) AS d30
                   FROM shop_views WHERE boutique_id = :b'
            );
            $stmt->execute(['b' => $boutiqueId]);
            $r = $stmt->fetch() ?: [];
            return ['total' => (int) ($r['total'] ?? 0), 'd7' => (int) ($r['d7'] ?? 0), 'd30' => (int) ($r['d30'] ?? 0)];
        } catch (\Throwable) {
            return ['total' => 0, 'd7' => 0, 'd30' => 0];
        }
    }

    /** Vues par jour sur N jours (jours sans visite = 0). @return list<array{day:string,views:int}> */
    public static function daily(int $boutiqueId, int $days = 14): array
    {
        $map = [];
        try {
            $stmt = db()->prepare(
                'SELECT day, SUM(views) AS n FROM shop_views
                  WHERE boutique_id = :b AND day >= DATE_SUB(CURRENT_DATE, INTERVAL :d DAY)
                  GROUP BY day'
            );
            $stmt->bindValue('b', $boutiqueId, \PDO::PARAM_INT);
            $stmt->bindValue('d', $days - 1, \PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $map[(string) $r['day']] = (int) $r['n'];
            }
        } catch (\Throwable) {
            // table absente : série à zéro
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} day"));
            $out[] = ['day' => $d, 'views' => $map[$d] ?? 0];
        }
        return $out;
    }

    /** Vues 30 jours par page (0 = vitrine). @return array<int,int> product_id => vues */
    public static function byProduct(int $boutiqueId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT product_id, SUM(views) AS n FROM shop_views
                  WHERE boutique_id = :b AND day >= DATE_SUB(CURRENT_DATE, INTERVAL 29 DAY)
                  GROUP BY product_id ORDER BY n DESC'
            );
            $stmt->execute(['b' => $boutiqueId]);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(int) $r['product_id']] = (int) $r['n'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Total des vues de la boutique d'un vendeur (carte de la vue d'ensemble). */
    public static function totalForUser(int $userId): int
    {
        try {
            $stmt = db()->prepare(
                'SELECT COALESCE(SUM(v.views), 0) FROM shop_views v
                   JOIN boutiques b ON b.id = v.boutique_id WHERE b.user_id = :uid'
            );
            $stmt->execute(['uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
