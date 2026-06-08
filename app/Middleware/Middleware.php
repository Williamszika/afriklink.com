<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Middleware contract. A middleware either lets the request through (returns void)
 * or halts it (redirect / abort / exit) — it never returns a value.
 */
interface Middleware
{
    public function handle(Request $request): void;
}
