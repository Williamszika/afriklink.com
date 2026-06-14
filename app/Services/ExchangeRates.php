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
        $rates = (array) config('currencies.per_eur', []);
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
        $rates = (array) config('currencies.per_eur', []);
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
        $rates = (array) config('currencies.per_eur', []);
        return ($rates[strtoupper(trim($from))] ?? 0) > 0 && ($rates[strtoupper(trim($to))] ?? 0) > 0;
    }
}
