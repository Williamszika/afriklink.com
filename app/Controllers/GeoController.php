<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\GeoService;

/**
 * /api/geo/reverse — le navigateur envoie la position (Geolocation API,
 * acceptée par l'utilisateur) ; on renvoie ville, pays et continent.
 * Passe par notre serveur : pas de service tiers dans la CSP, et un
 * rate-limit nous protège de l'abus.
 */
final class GeoController
{
    public function reverse(Request $request): void
    {
        $lat = filter_var(input_string('lat', ''), FILTER_VALIDATE_FLOAT);
        $lng = filter_var(input_string('lng', ''), FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            json_response(['error' => 'invalid_coordinates'], 422);
        }

        $geo = GeoService::reverse(round($lat, 6), round($lng, 6), current_locale());
        if ($geo === null) {
            json_response(['error' => 'lookup_failed'], 503);
        }

        $continentLabel = $geo['continent'] !== null ? t('geo.continent.' . $geo['continent']) : null;
        json_response([
            'city'            => $geo['city'],
            'country'         => $geo['country'],
            'country_code'    => $geo['country_code'],
            'continent'       => $geo['continent'],
            'continent_label' => $continentLabel,
            'formatted'       => $geo['formatted'],
            'label'           => trim(implode(', ', array_filter([$geo['city'], $geo['country']])))
                . ($continentLabel !== null ? ' (' . $continentLabel . ')' : ''),
        ]);
    }
}
