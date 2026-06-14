<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Boutique;
use App\Models\Restaurant;

/**
 * Agrégats de gains d'un vendeur pour le tableau de bord du portefeuille :
 * synthèse (chiffre d'affaires total / du mois / nb de commandes), revenu par
 * jour (graphique), et répartition PAR VITRINE (boutique, restaurant).
 *
 * « Gains » = chiffre d'affaires des commandes non annulées (≠ solde RETIRABLE
 * du portefeuille, qui ne compte que l'encaissé en ligne). Tolérant aux pannes :
 * toute erreur DB renvoie des valeurs neutres (jamais bloquant).
 */
final class SellerAnalytics
{
    /** Devise d'affichage des gains (celle de la boutique, sinon du restaurant, sinon XOF). */
    public static function currency(int $userId): string
    {
        $b = Boutique::findByUserId($userId);
        if ($b !== null) {
            return (string) ($b['currency'] ?? 'XOF');
        }
        $r = Restaurant::findByUserId($userId);
        return (string) ($r['currency'] ?? 'XOF');
    }

    /** @return array{total_cents:int,month_cents:int,count:int} */
    public static function summary(int $userId): array
    {
        $total = 0;
        $month = 0;
        $count = 0;
        foreach (self::sources($userId) as $src) {
            try {
                $stmt = db()->prepare(
                    "SELECT COUNT(*) AS n, COALESCE(SUM({$src['amount']}),0) AS total,
                            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN {$src['amount']} ELSE 0 END),0) AS month
                       FROM {$src['table']}
                      WHERE {$src['key']} = :id AND status <> 'cancelled'"
                );
                $stmt->execute(['id' => $src['id']]);
                $row = $stmt->fetch() ?: [];
                $total += (int) ($row['total'] ?? 0);
                $month += (int) ($row['month'] ?? 0);
                $count += (int) ($row['n'] ?? 0);
            } catch (\Throwable) {
            }
        }
        return ['total_cents' => $total, 'month_cents' => $month, 'count' => $count];
    }

    /** Revenu par jour sur les $days derniers jours (séries remplies à 0). @return list<array{date:string,cents:int}> */
    public static function revenueByDay(int $userId, int $days = 14): array
    {
        $days = max(7, min(60, $days));
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[date('Y-m-d', strtotime("-{$i} day"))] = 0;
        }
        foreach (self::sources($userId) as $src) {
            try {
                $stmt = db()->prepare(
                    "SELECT DATE(created_at) AS d, COALESCE(SUM({$src['amount']}),0) AS t
                       FROM {$src['table']}
                      WHERE {$src['key']} = :id AND status <> 'cancelled'
                        AND created_at >= (CURDATE() - INTERVAL {$days} DAY)
                      GROUP BY DATE(created_at)"
                );
                $stmt->execute(['id' => $src['id']]);
                foreach ($stmt->fetchAll() ?: [] as $row) {
                    $d = (string) $row['d'];
                    if (isset($series[$d])) {
                        $series[$d] += (int) $row['t'];
                    }
                }
            } catch (\Throwable) {
            }
        }
        $out = [];
        foreach ($series as $date => $cents) {
            $out[] = ['date' => $date, 'cents' => $cents];
        }
        return $out;
    }

    /** Répartition du chiffre d'affaires par vitrine. @return list<array{label:string,kind:string,cents:int}> */
    public static function byStorefront(int $userId): array
    {
        $out = [];
        foreach (self::sources($userId) as $src) {
            try {
                $stmt = db()->prepare("SELECT COALESCE(SUM({$src['amount']}),0) AS t FROM {$src['table']}
                                       WHERE {$src['key']} = :id AND status <> 'cancelled'");
                $stmt->execute(['id' => $src['id']]);
                $out[] = ['label' => $src['label'], 'kind' => $src['kind'], 'cents' => (int) $stmt->fetchColumn()];
            } catch (\Throwable) {
            }
        }
        return $out;
    }

    /**
     * Vitrines du vendeur sous forme de sources d'agrégation homogènes.
     * @return list<array{kind:string,label:string,table:string,key:string,amount:string,id:int}>
     */
    private static function sources(int $userId): array
    {
        $src = [];
        $b = Boutique::findByUserId($userId);
        if ($b !== null) {
            $src[] = ['kind' => 'boutique', 'label' => (string) $b['name'], 'table' => 'orders',
                'key' => 'boutique_id', 'amount' => 'total_cents', 'id' => (int) $b['id']];
        }
        $r = Restaurant::findByUserId($userId);
        if ($r !== null) {
            $src[] = ['kind' => 'restaurant', 'label' => (string) $r['name'], 'table' => 'restaurant_orders',
                'key' => 'restaurant_id', 'amount' => 'subtotal_cents', 'id' => (int) $r['id']];
        }
        return $src;
    }
}
