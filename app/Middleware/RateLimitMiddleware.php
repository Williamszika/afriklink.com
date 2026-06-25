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

    /**
     * Seaux dont chaque requête COÛTE de l'argent (appel LLM, signature d'envoi
     * Cloudinary) : ils basculent en FAIL-CLOSED — si la base de limitation est
     * injoignable, on REFUSE plutôt que de laisser un attaquant contourner la
     * limite et faire flamber la facture. Les autres restent fail-open
     * (disponibilité prioritaire : login, recherche…).
     */
    private const FAIL_CLOSED = ['agnes', 'sign'];

    public function handle(Request $request): void
    {
        $key      = $this->bucket . ':' . $request->ip();
        $failOpen = !in_array($this->bucket, self::FAIL_CLOSED, true);

        if (!rate_limit_ok($key, $this->max, $this->windowSeconds, $failOpen)) {
            header('Retry-After: ' . $this->windowSeconds);
            abort(429, t('error.too_many_requests'));
        }
    }
}
