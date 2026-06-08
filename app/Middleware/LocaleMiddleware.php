<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Resolves interface language. Priority: explicit cookie (set by the language
 * switcher) > Accept-Language negotiation > default. Currency is resolved in
 * bootstrap from its own cookie and stays independent of language.
 */
final class LocaleMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (isset($_COOKIE['locale'])) {
            return; // already honoured in bootstrap
        }

        $allowed = config('app.locales', ['fr', 'en']);
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        foreach (explode(',', (string) $accept) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (in_array($code, $allowed, true)) {
                set_locale($code);
                return;
            }
        }
    }
}
