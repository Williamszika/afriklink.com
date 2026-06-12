<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Géocodage inverse : latitude/longitude → ville, pays, continent.
 * Le navigateur fournit la position (Geolocation API, avec permission) ;
 * le serveur fait la conversion — deux fournisseurs gratuits, l'un en
 * secours de l'autre : Nominatim (OpenStreetMap) puis BigDataCloud.
 * Le continent est déduit du code pays (table locale, jamais d'appel réseau).
 */
final class GeoService
{
    /** Continents par code ISO 3166-1 alpha-2. */
    private const CONTINENTS = [
        'africa' => ['DZ','AO','BJ','BW','BF','BI','CV','CM','CF','TD','KM','CG','CD','CI','DJ','EG','GQ','ER','SZ','ET','GA','GM','GH','GN','GW','KE','LS','LR','LY','MG','MW','ML','MR','MU','YT','MA','MZ','NA','NE','NG','RE','RW','SH','ST','SN','SC','SL','SO','ZA','SS','SD','TZ','TG','TN','UG','EH','ZM','ZW'],
        'europe' => ['AL','AD','AT','BY','BE','BA','BG','HR','CY','CZ','DK','EE','FO','FI','FR','DE','GI','GR','GG','HU','IS','IE','IM','IT','JE','XK','LV','LI','LT','LU','MT','MD','MC','ME','NL','MK','NO','PL','PT','RO','RU','SM','RS','SK','SI','ES','SJ','SE','CH','UA','GB','VA','AX'],
        'asia' => ['AF','AM','AZ','BH','BD','BT','BN','KH','CN','GE','HK','IN','ID','IR','IQ','IL','JP','JO','KZ','KW','KG','LA','LB','MO','MY','MV','MN','MM','NP','KP','OM','PK','PS','PH','QA','SA','SG','KR','LK','SY','TW','TJ','TH','TL','TR','TM','AE','UZ','VN','YE'],
        'north_america' => ['AI','AG','AW','BS','BB','BZ','BM','CA','KY','CR','CU','CW','DM','DO','SV','GD','GP','GT','HT','HN','JM','MQ','MX','MS','NI','PA','PR','BL','KN','LC','MF','PM','VC','SX','TT','TC','US','VG','VI'],
        'south_america' => ['AR','BO','BR','CL','CO','EC','FK','GF','GY','PY','PE','SR','UY','VE'],
        'oceania' => ['AS','AU','CK','FJ','PF','GU','KI','MH','FM','NR','NC','NZ','NU','NF','MP','PW','PG','PN','WS','SB','TK','TO','TV','VU','WF'],
        'antarctica' => ['AQ','BV','GS','HM','TF'],
    ];

    /** Continent (clé i18n) d'un code pays, ou null si inconnu. */
    public static function continentOf(?string $countryCode): ?string
    {
        if ($countryCode === null || $countryCode === '') {
            return null;
        }
        $cc = strtoupper($countryCode);
        foreach (self::CONTINENTS as $continent => $codes) {
            if (in_array($cc, $codes, true)) {
                return $continent;
            }
        }
        return null;
    }

    /**
     * Ville / pays / continent depuis des coordonnées.
     * @return ?array{city:?string,country:?string,country_code:?string,continent:?string,formatted:?string}
     */
    public static function reverse(float $lat, float $lng, string $locale = 'fr'): ?array
    {
        return self::fromNominatim($lat, $lng, $locale) ?? self::fromBigDataCloud($lat, $lng, $locale);
    }

    /** Nominatim (OpenStreetMap) — User-Agent identifiant requis par leur politique. */
    private static function fromNominatim(float $lat, float $lng, string $locale): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=14'
            . '&lat=' . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lng)
            . '&accept-language=' . rawurlencode($locale);
        $data = self::getJson($url);
        $addr = $data['address'] ?? null;
        if (!is_array($addr)) {
            return null;
        }
        $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? null;
        $cc   = isset($addr['country_code']) ? strtoupper((string) $addr['country_code']) : null;
        $road = trim(implode(' ', array_filter([$addr['house_number'] ?? null, $addr['road'] ?? null])));
        return [
            'city'         => $city !== null ? (string) $city : null,
            'country'      => isset($addr['country']) ? (string) $addr['country'] : null,
            'country_code' => $cc,
            'continent'    => self::continentOf($cc),
            'formatted'    => trim(implode(', ', array_filter([$road !== '' ? $road : null, $city, $addr['country'] ?? null]))) ?: null,
        ];
    }

    /** BigDataCloud (secours) — point d'accès client gratuit, sans clé. */
    private static function fromBigDataCloud(float $lat, float $lng, string $locale): ?array
    {
        $url = 'https://api-bdc.net/data/reverse-geocode-client?latitude=' . rawurlencode((string) $lat)
            . '&longitude=' . rawurlencode((string) $lng) . '&localityLanguage=' . rawurlencode($locale);
        $data = self::getJson($url);
        if ($data === null || !isset($data['countryCode'])) {
            return null;
        }
        $cc   = strtoupper((string) $data['countryCode']);
        $city = $data['city'] ?? $data['locality'] ?? null;
        if ($cc === '') {
            return null;
        }
        return [
            'city'         => $city !== null && $city !== '' ? (string) $city : null,
            'country'      => isset($data['countryName']) ? (string) $data['countryName'] : null,
            'country_code' => $cc,
            'continent'    => self::continentOf($cc),
            'formatted'    => trim(implode(', ', array_filter([$city, $data['countryName'] ?? null]))) ?: null,
        ];
    }

    /** GET JSON avec délai court ; null en cas d'échec. @return ?array<string,mixed> */
    private static function getJson(string $url): ?array
    {
        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'Afriklink/1.0 (marketplace; ' . (config('app.url') ?: 'https://afriklink.com') . ')',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if (!is_string($body) || $code !== 200) {
                return null;
            }
            $json = json_decode($body, true);
            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
