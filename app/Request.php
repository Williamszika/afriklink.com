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

    /**
     * Plages des proxys de confiance par défaut (Cloudflare). On n'honore les
     * en-têtes de transfert d'IP QUE si REMOTE_ADDR appartient à l'une d'elles ;
     * sinon un client direct pourrait usurper son IP et contourner la limitation
     * de débit. Surchargeable par l'env TRUSTED_PROXIES (CIDR séparés par virgules).
     * @var list<string>
     */
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * IP cliente pour la limitation de débit / l'audit. Sûre contre l'usurpation :
     * l'en-tête de transfert (CF-Connecting-IP, puis X-Forwarded-For) n'est pris en
     * compte QUE si la requête provient d'un proxy de confiance ; sinon REMOTE_ADDR.
     */
    public function ip(): string
    {
        $remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($remote !== '' && self::isTrustedProxy($remote)) {
            $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
            if (!is_string($fwd) || filter_var($fwd, FILTER_VALIDATE_IP) === false) {
                $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
                $fwd = $xff !== '' ? trim(explode(',', $xff)[0]) : ''; // 1ʳᵉ = client d'origine
            }
            if (is_string($fwd) && filter_var($fwd, FILTER_VALIDATE_IP)) {
                return $fwd;
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    /** REMOTE_ADDR est-il un proxy de confiance (env TRUSTED_PROXIES, défaut Cloudflare) ? */
    private static function isTrustedProxy(string $ip): bool
    {
        static $cidrs = null;
        if ($cidrs === null) {
            $env   = function_exists('env') ? (string) env('TRUSTED_PROXIES', '') : '';
            $cidrs = $env !== '' ? array_values(array_filter(array_map('trim', explode(',', $env)))) : self::CLOUDFLARE_RANGES;
        }
        foreach ($cidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /** Appartenance d'une IP à un CIDR (IPv4 et IPv6), ou égalité si pas de « / ». */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bitsRaw] = explode('/', $cidr, 2);
        $ipL  = @inet_pton($ip);
        $subL = @inet_pton($subnet);
        if ($ipL === false || $subL === false || strlen($ipL) !== strlen($subL)) {
            return false;
        }
        $bits  = max(0, min(strlen($ipL) * 8, (int) $bitsRaw));
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if ($bytes > 0 && strncmp($ipL, $subL, $bytes) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $rem)) & 0xff);
        return (ord($ipL[$bytes]) & ord($mask)) === (ord($subL[$bytes]) & ord($mask));
    }

    /** Packed binary IP for VARBINARY(16) columns, or null. */
    public function ipBinary(): ?string
    {
        $packed = @inet_pton($this->ip());
        return $packed === false ? null : $packed;
    }
}
