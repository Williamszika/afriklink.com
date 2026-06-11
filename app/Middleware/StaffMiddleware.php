<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Réserve une route aux relecteurs (modérateurs/admins). Renvoie 404 plutôt
 * que 403 pour ne pas révéler l'existence de l'espace de modération.
 */
final class StaffMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (!auth_check()) {
            flash('error', t('auth.login_required'));
            redirect('/login');
        }
        if (!is_staff()) {
            abort(404);
        }
    }
}
