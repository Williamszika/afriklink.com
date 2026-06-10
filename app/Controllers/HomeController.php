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
        if ($configured) {
            try {
                db()->query('SELECT 1');
                $db = 'ok';
            } catch (\Throwable $e) {
                $db = 'error';
                $detail = $e->getMessage();
                log_message('error', 'health db check failed: ' . $detail);
            }
        }

        $payload = [
            'app'            => 'ok',
            'db'             => $db,
            'session_driver' => config('app.session_driver'),
        ];
        if ($detail !== null && config('app.debug', false)) {
            $payload['db_error'] = $detail; // only with APP_DEBUG=true
        }
        json_response($payload);
    }

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
