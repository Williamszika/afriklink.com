<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

final class HomeController
{
    public function index(Request $request): void
    {
        view('home');
    }

    /**
     * One-click diagnostics (no secrets exposed): is the app up, is the database
     * reachable, which session driver is active. Lets a dashboard-only operator
     * verify the TiDB wiring right after setting the Vercel env vars.
     */
    public function health(Request $request): void
    {
        $configured = !empty($_ENV['DB_HOST']) && !empty($_ENV['DB_NAME']) && !empty($_ENV['DB_USER']);
        $db = 'unconfigured';
        $detail = null;
        $hint = null;
        if ($configured) {
            try {
                db()->query('SELECT 1');
                $db = 'ok';
            } catch (\Throwable $e) {
                $db = 'error';
                $detail = $e->getMessage();
                $hint = self::classifyDbError($detail);
                log_message('error', 'health db check failed: ' . $detail);
            }
        }

        $payload = [
            'app'            => 'ok',
            'db'             => $db,
            'session_driver' => config('app.session_driver'),
        ];
        if ($hint !== null) {
            $payload['db_hint'] = $hint; // safe category, no secrets
        }
        if ($detail !== null && config('app.debug', false)) {
            $payload['db_error'] = $detail; // raw message only with APP_DEBUG=true
        }
        json_response($payload);
    }

    /** Map a DB connection error to a safe category (no secrets leaked). */
    private static function classifyDbError(string $message): string
    {
        $m = strtolower($message);
        return match (true) {
            str_contains($m, '1045') || str_contains($m, 'access denied')        => 'bad_credentials',
            str_contains($m, '1049') || str_contains($m, 'unknown database')     => 'unknown_database',
            str_contains($m, 'ssl') || str_contains($m, 'certificate') || str_contains($m, 'tls') => 'tls_problem',
            str_contains($m, '2002') || str_contains($m, '2005') || str_contains($m, 'getaddrinfo')
                || str_contains($m, 'refused') || str_contains($m, 'timed out')  => 'cannot_reach_host',
            default                                                               => 'other',
        };

    /** Switch the interface language and remember it in a cookie. */
    public function switchLanguage(Request $request): void
    {
        $locale = (string) $request->param('locale', '');
        $allowed = config('app.locales', ['fr', 'en']);

        if (in_array($locale, $allowed, true)) {
            setcookie('locale', $locale, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'secure'   => request_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        back('/');
    }
}
