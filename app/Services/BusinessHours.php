<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Horaires d'ouverture structurés de la boutique (un créneau par jour, V1).
 *
 * Le JSON stocké en base ressemble à :
 *   {"mon":{"o":"09:00","c":"18:00"}, "sat":{"o":"10:00","c":"14:00"}}
 * Un jour PRÉSENT = ouvert sur ce créneau ; un jour ABSENT = fermé.
 *
 * Le fuseau dépend du pays de la boutique (ISO-3166 → IANA via PER_COUNTRY),
 * pour qu'« ouvert maintenant » soit calculé à l'heure locale du commerçant et
 * non à l'heure du serveur. Service pur : aucune dépendance base de données.
 */
final class BusinessHours
{
    /** Ordre canonique des jours (lundi en premier, comme en Europe/Afrique). */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Fuseau IANA pour un code pays ISO-3166 (ex. SN → Africa/Dakar).
     * Replie sur 'UTC' si le code est invalide ou inconnu.
     */
    public static function timezoneFor(?string $cc): string
    {
        $cc = strtoupper(trim((string) $cc));
        if (preg_match('/^[A-Z]{2}$/', $cc) !== 1) {
            return 'UTC';
        }
        try {
            $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $cc);
        } catch (\Throwable) {
            return 'UTC';
        }
        return $zones[0] ?? 'UTC';
    }

    /**
     * Décode le JSON des horaires en tableau propre et validé.
     * @return array<string,array{o:string,c:string}> jour => créneau
     */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $raw = json_decode($json, true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (self::DAYS as $day) {
            $slot = $raw[$day] ?? null;
            if (!is_array($slot)) {
                continue;
            }
            $o = (string) ($slot['o'] ?? '');
            $c = (string) ($slot['c'] ?? '');
            if (self::validTime($o) && self::validTime($c) && $o !== $c) {
                $out[$day] = ['o' => $o, 'c' => $c];
            }
        }
        return $out;
    }

    /**
     * La boutique est-elle ouverte à l'instant présent (heure locale du pays) ?
     * Renvoie null si aucun horaire n'est défini (badge masqué, jamais bloquant).
     * Gère les créneaux qui passent minuit (ex. 22:00 → 02:00).
     *
     * @param array<string,array{o:string,c:string}> $hours
     */
    public static function isOpenNow(array $hours, string $tz): ?bool
    {
        if ($hours === []) {
            return null;
        }
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone($tz));
        } catch (\Throwable) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
        // 'N' : 1 (lundi) … 7 (dimanche) → index dans self::DAYS.
        $idx     = (int) $now->format('N') - 1;
        $today   = self::DAYS[$idx];
        $yester  = self::DAYS[($idx + 6) % 7];

        // Créneau du jour : soit normal (c > o), soit la partie « soirée » d'un
        // créneau de nuit (c < o), ouverte de l'ouverture jusqu'à minuit.
        if (isset($hours[$today])) {
            $o = self::toMin($hours[$today]['o']);
            $c = self::toMin($hours[$today]['c']);
            if ($c > $o && $nowMin >= $o && $nowMin < $c) {
                return true;
            }
            if ($c < $o && $nowMin >= $o) {
                return true;
            }
        }
        // Débordement d'un créneau de nuit de la veille (partie « matin »).
        if (isset($hours[$yester])) {
            $o = self::toMin($hours[$yester]['o']);
            $c = self::toMin($hours[$yester]['c']);
            if ($c < $o && $nowMin < $c) {
                return true;
            }
        }
        return false;
    }

    /**
     * Minutes restantes avant la fermeture si la boutique est ouverte MAINTENANT
     * (gère les créneaux de nuit). null si fermée ou sans horaires.
     *
     * @param array<string,array{o:string,c:string}> $hours
     */
    public static function minutesUntilClose(array $hours, string $tz): ?int
    {
        if ($hours === []) {
            return null;
        }
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone($tz));
        } catch (\Throwable) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
        $idx    = (int) $now->format('N') - 1;
        $today  = self::DAYS[$idx];
        $yester = self::DAYS[($idx + 6) % 7];

        if (isset($hours[$today])) {
            $o = self::toMin($hours[$today]['o']);
            $c = self::toMin($hours[$today]['c']);
            if ($c > $o && $nowMin >= $o && $nowMin < $c) {
                return $c - $nowMin;                 // créneau normal
            }
            if ($c < $o && $nowMin >= $o) {
                return (1440 - $nowMin) + $c;        // créneau de nuit, partie soirée
            }
        }
        if (isset($hours[$yester])) {
            $o = self::toMin($hours[$yester]['o']);
            $c = self::toMin($hours[$yester]['c']);
            if ($c < $o && $nowMin < $c) {
                return $c - $nowMin;                 // créneau de nuit, partie matin
            }
        }
        return null;
    }

    /**
     * Construit le JSON des horaires depuis les champs du formulaire.
     * Pour chaque jour : case « h_{day} » cochée + heures « h_{day}_o / _c »
     * valides et distinctes. Renvoie null si aucun jour n'est ouvert.
     *
     * @param array<string,mixed> $post
     */
    public static function parseForm(array $post): ?string
    {
        $out = [];
        foreach (self::DAYS as $day) {
            if (($post['h_' . $day] ?? '') !== '1') {
                continue;
            }
            $o = trim((string) ($post['h_' . $day . '_o'] ?? ''));
            $c = trim((string) ($post['h_' . $day . '_c'] ?? ''));
            if (self::validTime($o) && self::validTime($c) && $o !== $c) {
                $out[$day] = ['o' => $o, 'c' => $c];
            }
        }
        if ($out === []) {
            return null;
        }
        $json = json_encode($out, JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : null;
    }

    /** Clé du jour courant ('mon'…'sun') dans le fuseau donné. */
    public static function todayKey(string $tz): string
    {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone($tz));
        } catch (\Throwable) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        return self::DAYS[(int) $now->format('N') - 1];
    }

    /** Minutes depuis minuit pour une heure « HH:MM » (validée en amont). */
    private static function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return $h * 60 + $m;
    }

    /** Valide une heure au format 24 h « HH:MM » (00:00 … 23:59). */
    private static function validTime(string $s): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) === 1;
    }
}
