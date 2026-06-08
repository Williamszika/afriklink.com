<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Opposite of AuthMiddleware: only for visitors who are NOT logged in
 * (login, register, password reset). Authenticated users are sent to their dashboard.
 */
final class GuestMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (auth_check()) {
            redirect('/dashboard');
        }
    }
}
