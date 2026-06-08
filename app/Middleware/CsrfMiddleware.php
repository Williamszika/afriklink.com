<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Verifies the CSRF token on every state-changing request (POST/PUT/PATCH/DELETE).
 * Delegates to csrf_check() (app/Support/csrf.php), which compares in constant time.
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if ($request->isMutating()) {
            csrf_check();
        }
    }
}
