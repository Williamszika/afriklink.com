<?php
declare(strict_types=1);

namespace App;

/**
 * Immutable-ish snapshot of the incoming HTTP request.
 */
final class Request
{
    /** @var array<string,string> route parameters extracted by the router */
    public array $params = [];

    private function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Allow method override for HTML forms (hidden _method) and JS clients.
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '');
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);
        // Normalise: strip trailing slash except for root.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return new self($method, $path);
    }

    public function isMutating(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /** Route parameter (e.g. {slug}). */
    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    /** Raw request input (POST then GET). Validate via input_* helpers, do not trust directly. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Best-effort client IP (Cloudflare-aware), for rate limiting / audit. */
    public function ip(): string
    {
        // Behind Cloudflare the origin should only accept CF IPs; CF-Connecting-IP is the real client.
        $candidate = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        return is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '0.0.0.0';
    }

    /** Packed binary IP for VARBINARY(16) columns, or null. */
    public function ipBinary(): ?string
    {
        $packed = @inet_pton($this->ip());
        return $packed === false ? null : $packed;
    }
}
