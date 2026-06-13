<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

/**
 * Prévision de stock / réassort — entièrement algorithmique, sans clé ni service
 * externe. À partir de la vélocité de vente réelle (unités vendues sur une
 * fenêtre récente), estime le nombre de jours avant rupture et signale les
 * produits à réapprovisionner.
 *
 * Statuts :
 *   unlimited — stock illimité (NULL) : pas de prévision
 *   out       — déjà en rupture (stock ≤ 0)
 *   nodata    — stock fini mais aucune vente récente : impossible d'estimer
 *   critical  — rupture dans ≤ 7 jours
 *   soon      — rupture dans ≤ 21 jours
 *   ok        — au-delà
 */
final class StockForecast
{
    public const WINDOW = 30;

    /**
     * @param list<array> $products  lignes produits (avec id, stock)
     * @return array<int,array{status:string,sold:int,per_day:float,days_left:?int,restock:bool,window:int}>
     */
    public static function forProducts(array $products, int $windowDays = self::WINDOW): array
    {
        $windowDays = max(1, min(365, $windowDays));
        $ids  = array_map(static fn (array $p): int => (int) $p['id'], $products);
        $sold = Order::soldSince($ids, $windowDays);

        $out = [];
        foreach ($products as $p) {
            $pid = (int) $p['id'];
            $out[$pid] = self::classify(
                ($p['stock'] ?? null) === null ? null : (int) $p['stock'],
                (int) ($sold[$pid] ?? 0),
                $windowDays
            );
        }
        return $out;
    }

    /**
     * Logique de prévision pure (testable, sans base) : à partir du stock courant,
     * des unités vendues et de la fenêtre, renvoie le statut + jours restants.
     * @return array{status:string,sold:int,per_day:float,days_left:?int,restock:bool,window:int}
     */
    public static function classify(?int $stock, int $units, int $windowDays = self::WINDOW): array
    {
        $windowDays = max(1, min(365, $windowDays));
        if ($stock === null) {
            return self::row('unlimited', $units, 0.0, null, false, $windowDays);
        }
        $perDay = $units / $windowDays;
        if ($stock <= 0) {
            return self::row('out', $units, $perDay, 0, true, $windowDays);
        }
        if ($perDay <= 0) {
            return self::row('nodata', $units, 0.0, null, false, $windowDays);
        }
        $daysLeft = (int) floor($stock / $perDay);
        $status   = $daysLeft <= 7 ? 'critical' : ($daysLeft <= 21 ? 'soon' : 'ok');
        return self::row($status, $units, $perDay, $daysLeft, $status !== 'ok', $windowDays);
    }

    /** Nombre de produits à réapprovisionner dans une carte de prévisions. */
    public static function restockCount(array $forecasts): int
    {
        $n = 0;
        foreach ($forecasts as $f) {
            if (!empty($f['restock'])) {
                $n++;
            }
        }
        return $n;
    }

    private static function row(string $status, int $sold, float $perDay, ?int $daysLeft, bool $restock, int $window): array
    {
        return [
            'status'    => $status,
            'sold'      => $sold,
            'per_day'   => round($perDay, 2),
            'days_left' => $daysLeft,
            'restock'   => $restock,
            'window'    => $window,
        ];
    }
}
