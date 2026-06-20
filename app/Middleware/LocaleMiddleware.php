<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Resolves interface language. Priority: explicit cookie (set by the language
 * switcher) > detected country language (geolocation) > Accept-Language
 * negotiation > default. The manual switcher always wins and persists, so a
 * visitor can override the automatic choice. Currency is resolved in bootstrap
 * from its own cookie and stays independent of language.
 */
final class LocaleMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (isset($_COOKIE['locale'])) {
            return; // choix explicite déjà honoré dans bootstrap
        }

        // 1) Langue du pays détecté (automatique selon la géolocalisation) :
        //    Côte d'Ivoire → fr, Nigeria → en, etc.
        $geoLang = language_for_country(detect_country_code());
        if ($geoLang !== null) {
            set_locale($geoLang);
            return;
        }

        // 2) Repli : préférence déclarée par le navigateur.
        $allowed = config('app.locales', ['fr', 'en']);
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (in_array($code, $allowed, true)) {
                set_locale($code);
                return;
            }
        }
        // 3) sinon, défaut déjà appliqué dans bootstrap.
    }
}
