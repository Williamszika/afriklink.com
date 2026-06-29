<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Conversion INDICATIVE entre devises pour l'affichage (« ≈ équivalent »). Le
 * règlement reste dans la devise de la boutique — ceci ne sert qu'à aider
 * l'acheteur à se repérer (« 12 000 F CFA ≈ 18 € »). Taux dans
 * config/currencies.php. Montants en centimes internes (valeur × 100), uniformes
 * pour toutes les devises (y compris XOF/JPY : on stocke aussi × 100 en interne).
 */
final class ExchangeRates
{
    /**
     * Convertit des centimes internes de $from vers $to. null si une devise est
     * inconnue (→ on n'affiche alors pas d'équivalent).
     */
    public static function convert(int $cents, string $from, string $to): ?int
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));
        if ($from === $to) {
            return $cents;
        }
        $rates = self::ratesMap();
        $rf = isset($rates[$from]) ? (float) $rates[$from] : 0.0;
        $rt = isset($rates[$to]) ? (float) $rates[$to] : 0.0;
        if ($rf <= 0.0 || $rt <= 0.0) {
            return null;
        }
        // valeur (unités) = cents / 100 ; conversion via l'EUR ; re-passage en centimes.
        $amountTo = ($cents / 100.0) * ($rt / $rf);
        return (int) round($amountTo * 100);
    }

    /**
     * Taux multiplicateur PRÉCIS de `from` vers `to` (centime cible par centime
     * source). À passer tel quel au JS pour qu'il calcule exactement comme
     * convert() : cible = round(source × taux). Évite la perte de précision d'un
     * taux dérivé d'un convert() déjà arrondi.
     */
    public static function rate(string $from, string $to): ?float
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));
        if ($from === $to) {
            return 1.0;
        }
        $rates = self::ratesMap();
        $rf = isset($rates[$from]) ? (float) $rates[$from] : 0.0;
        $rt = isset($rates[$to]) ? (float) $rates[$to] : 0.0;
        if ($rf <= 0.0 || $rt <= 0.0) {
            return null;
        }
        return $rt / $rf;
    }

    /** Un taux existe-t-il pour cette paire ? */
    public static function available(string $from, string $to): bool
    {
        $rates = self::ratesMap();
        return ($rates[strtoupper(trim($from))] ?? 0) > 0 && ($rates[strtoupper(trim($to))] ?? 0) > 0;
    }

    /**
     * Carte des taux (devise → unités par EUR) : valeurs par défaut de
     * config/currencies.php, SURCHARGÉES par les taux rafraîchis en base
     * (currency_rates) quand ils existent. Repli silencieux sur la config si la
     * table est absente / la base injoignable. Mémoïsé par requête. La base
     * (EUR = 1) est toujours réaffirmée ; les parités fixes XOF/XAF ne sont
     * jamais écrites par le rafraîchissement, donc conservées telles quelles.
     */
    private static function ratesMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $out = [];
        foreach ((array) config('currencies.per_eur', []) as $k => $v) {
            $out[strtoupper((string) $k)] = (float) $v;
        }
        try {
            foreach (db()->query('SELECT code, per_eur FROM currency_rates')->fetchAll() ?: [] as $r) {
                $c   = strtoupper((string) ($r['code'] ?? ''));
                $val = (float) ($r['per_eur'] ?? 0);
                if ($c !== '' && $val > 0) {
                    $out[$c] = $val;
                }
            }
        } catch (\Throwable) {
            // table absente / base injoignable : on garde les taux de config.
        }
        // Parités FIXES réaffirmées APRÈS la surcouche base : ni une ligne en base
        // (insérée à la main), ni un bug futur ne peuvent fausser l'EUR (base) ni
        // le franc CFA (XOF/XAF, légalement pegés à 655,957). Repris de la config.
        $cfg = (array) config('currencies.per_eur', []);
        $out['EUR'] = 1.0;
        $out['XOF'] = isset($cfg['XOF']) ? (float) $cfg['XOF'] : 655.957;
        $out['XAF'] = isset($cfg['XAF']) ? (float) $cfg['XAF'] : 655.957;
        return $map = $out;
    }
}
