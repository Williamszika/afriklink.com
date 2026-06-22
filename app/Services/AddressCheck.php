<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Contrôle de cohérence d'adresse par géocodage (OpenStreetMap / Nominatim).
 * Vérifie que la VILLE saisie se situe bien dans le PAYS choisi. « Best-effort »
 * et fail-open : toute erreur réseau / quota / zone non couverte renvoie
 * « unknown » (aucun avertissement) — on ne bloque jamais le parcours.
 *
 * Mémoïsé par requête. Respecte la politique Nominatim (User-Agent, faible
 * volume : un appel ponctuel à l'enregistrement d'une adresse).
 */
final class AddressCheck
{
    private static array $cache = [];

    public static function enabled(): bool
    {
        return (bool) config('geocode.enabled', false) && function_exists('curl_init');
    }

    /**
     * Cohérence ville ↔ pays.
     * @return array{status:string,resolved_cc:?string} status ∈ ok | mismatch | unknown
     */
    public static function cityCountry(string $city, string $cc): array
    {
        $city = trim($city);
        $cc   = strtoupper(trim($cc));
        if (!self::enabled() || $city === '' || preg_match('/^[A-Z]{2}$/', $cc) !== 1) {
            return ['status' => 'unknown', 'resolved_cc' => null];
        }
        $key = mb_strtolower($city) . '|' . $cc;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        // On interroge la VILLE SEULE (sans le pays, qui biaiserait le résultat),
        // puis on compare le pays renvoyé au pays choisi.
        $resolved = self::lookupCountry($city);
        $out = $resolved === null
            ? ['status' => 'unknown', 'resolved_cc' => null]
            : ['status' => $resolved === $cc ? 'ok' : 'mismatch', 'resolved_cc' => $resolved];
        return self::$cache[$key] = $out;
    }

    /** Interroge le géocodeur et renvoie le code pays ISO du 1er résultat, ou null. */
    private static function lookupCountry(string $q): ?string
    {
        $url = rtrim((string) config('geocode.endpoint', ''), '/') . '?' . http_build_query([
            'q'             => $q,
            'format'        => 'jsonv2',
            'addressdetails' => 1,
            'limit'         => 1,
        ]);
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'User-Agent: ' . (string) config('geocode.user_agent', 'AfrikaLink/1.0'),
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => max(2, (int) config('geocode.timeout', 5)),
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $raw  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($raw) || $code < 200 || $code >= 300) {
                return null;
            }
            $data = json_decode($raw, true);
            $cc   = $data[0]['address']['country_code'] ?? null;
            return is_string($cc) && $cc !== '' ? strtoupper($cc) : null;
        } catch (\Throwable) {
            return null; // fail-open
        }
    }
}
