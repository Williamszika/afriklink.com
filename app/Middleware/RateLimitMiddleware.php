<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Per-IP rate limiting on sensitive routes (login, register, reset, payment...).
 * Backed by rate_limit_ok() (app/Support/rate_limit.php) and the `rate_limits` table.
 * Complement with Cloudflare edge rules in production.
 */
final class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private readonly string $bucket,
        private readonly int $max,
        private readonly int $windowSeconds,
    ) {
    }

    public function handle(Request $request): void
    {
        $key = $this->bucket . ':' . $request->ip();

        if (!rate_limit_ok($key, $this->max, $this->windowSeconds)) {
            header('Retry-After: ' . $this->windowSeconds);
            abort(429, t('error.too_many_requests'));
        }
    }
}
