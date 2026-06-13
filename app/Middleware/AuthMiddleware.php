<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Requires an authenticated session, and optionally a role.
 * Role check enforces least privilege; 'admin' is allowed everywhere.
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly ?string $role = null)
    {
    }

    public function handle(Request $request): void
    {
        if (!auth_check()) {
            // Mémorise la page demandée (GET interne) pour y revenir après connexion.
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $to = (string) ($_SERVER['REQUEST_URI'] ?? '');
                if ($to !== '' && $to[0] === '/' && !str_starts_with($to, '//') && !preg_match('/[\x00-\x1f]/', $to)) {
                    $_SESSION['intended'] = mb_substr($to, 0, 300);
                }
            }
            flash('error', t('auth.login_required'));
            redirect('/login');
        }

        if ($this->role !== null) {
            $role = current_user()['role'] ?? null;
            if ($role !== $this->role && $role !== 'admin') {
                abort(403);
            }
        }
    }
}
