<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Programme d'affiliation (parrainage) — un membre partage un lien « /r/{code} »
 * vers une vitrine/produit ; s'il en résulte une commande, il touche une
 * commission (simulation, créditée en centimes). 100 % interne, sans clé.
 *
 * Tables auto-créées : affiliate_codes (1 code par membre), affiliate_clicks
 * (clics sur les liens), affiliate_conversions (ventes attribuées, idempotentes
 * par commande).
 */
final class Affiliate
{
    /** Taux de commission (%) reversé à l'apporteur sur le sous-total des ventes. */
    public const RATE_PCT = 5;

    private const COOKIE = 'aff_ref';

    public static function ensureTables(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS affiliate_codes (
                user_id    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                code       VARCHAR(16) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS affiliate_clicks (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                affiliate_id BIGINT UNSIGNED NOT NULL,
                target       VARCHAR(300) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_aff_clicks (affiliate_id, id)
            )'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS affiliate_conversions (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                affiliate_id     BIGINT UNSIGNED NOT NULL,
                order_public_id  CHAR(36) NOT NULL UNIQUE,
                boutique_id      BIGINT UNSIGNED NOT NULL,
                amount_cents     BIGINT UNSIGNED NOT NULL DEFAULT 0,
                commission_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency         CHAR(3) NOT NULL DEFAULT \'EUR\',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_aff_conv (affiliate_id, id)
            )'
        );
    }

    /* ---- Logique pure (testable hors base) ------------------------------- */

    /** Commission (centimes) sur un sous-total donné. */
    public static function commissionFor(int $subtotalCents): int
    {
        return $subtotalCents > 0 ? intdiv($subtotalCents * self::RATE_PCT, 100) : 0;
    }

    /** Cible de redirection sûre : chemin interne uniquement (pas d'open-redirect). */
    public static function safeTarget(string $to): string
    {
        $to = trim($to);
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//')) {
            return '/';
        }
        if (preg_match('/[\x00-\x1f]/', $to)) {
            return '/';
        }
        return mb_substr($to, 0, 300);
    }

    /* ---- Codes ----------------------------------------------------------- */

    /** Récupère (ou crée) le code d'affiliation stable d'un membre. */
    public static function codeFor(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        self::ensureTables();
        try {
            $existing = self::lookupCode($userId);
            if ($existing !== null) {
                return $existing;
            }
            for ($i = 0; $i < 5; $i++) {
                $code = strtoupper(bin2hex(random_bytes(4))); // 8 caractères 0-9A-F
                try {
                    db()->prepare('INSERT INTO affiliate_codes (user_id, code) VALUES (:u, :c)')
                        ->execute(['u' => $userId, 'c' => $code]);
                    return $code;
                } catch (\Throwable) {
                    // collision (code) ou course (user_id) : on relit
                    $again = self::lookupCode($userId);
                    if ($again !== null) {
                        return $again;
                    }
                }
            }
        } catch (\Throwable) {
        }
        return '';
    }

    public static function userIdForCode(string $code): ?int
    {
        $code = trim($code);
        if ($code === '' || preg_match('/^[A-Za-z0-9]{4,16}$/', $code) !== 1) {
            return null;
        }
        try {
            $stmt = db()->prepare('SELECT user_id FROM affiliate_codes WHERE code = :c LIMIT 1');
            $stmt->execute(['c' => $code]);
            $u = $stmt->fetchColumn();
            return $u !== false ? (int) $u : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function lookupCode(int $userId): ?string
    {
        $stmt = db()->prepare('SELECT code FROM affiliate_codes WHERE user_id = :u LIMIT 1');
        $stmt->execute(['u' => $userId]);
        $c = $stmt->fetchColumn();
        return $c !== false ? (string) $c : null;
    }

    /* ---- Suivi ----------------------------------------------------------- */

    public static function recordClick(int $affiliateId, string $target): void
    {
        if ($affiliateId <= 0) {
            return;
        }
        self::ensureTables();
        try {
            db()->prepare('INSERT INTO affiliate_clicks (affiliate_id, target) VALUES (:a, :t)')
                ->execute(['a' => $affiliateId, 't' => mb_substr($target, 0, 300)]);
        } catch (\Throwable) {
        }
    }

    /** Pose le cookie de parrainage (30 jours) côté visiteur. */
    public static function setRefCookie(string $code): void
    {
        self::cookie($code, time() + 2592000);
    }

    /**
     * Attribue une commande à l'apporteur s'il y a un cookie de parrainage valide.
     * One-shot : le cookie est consommé. Pas d'auto-parrainage (apporteur ≠ vendeur).
     */
    public static function attribute(string $orderPublicId, int $boutiqueId, int $sellerUserId, int $subtotalCents, string $currency): void
    {
        $code = trim((string) ($_COOKIE[self::COOKIE] ?? ''));
        if ($code === '') {
            return;
        }
        self::cookie('', time() - 3600); // consommer le cookie
        $affiliateId = self::userIdForCode($code);
        if ($affiliateId === null || $affiliateId === $sellerUserId) {
            return;
        }
        self::recordConversion($affiliateId, $orderPublicId, $boutiqueId, $subtotalCents, self::commissionFor($subtotalCents), $currency);
    }

    public static function recordConversion(int $affiliateId, string $orderPublicId, int $boutiqueId, int $amountCents, int $commissionCents, string $currency): void
    {
        self::ensureTables();
        try {
            db()->prepare(
                'INSERT INTO affiliate_conversions (affiliate_id, order_public_id, boutique_id, amount_cents, commission_cents, currency)
                 VALUES (:a, :o, :b, :amt, :com, :cur)'
            )->execute([
                'a' => $affiliateId, 'o' => $orderPublicId, 'b' => $boutiqueId,
                'amt' => max(0, $amountCents), 'com' => max(0, $commissionCents), 'cur' => mb_substr($currency, 0, 3),
            ]);
        } catch (\Throwable) {
            // doublon (commande déjà créditée) : idempotent, on ignore.
        }
    }

    /** @return array{clicks:int,conversions:int,earnings:array<string,int>} */
    public static function statsFor(int $affiliateId): array
    {
        self::ensureTables();
        $out = ['clicks' => 0, 'conversions' => 0, 'earnings' => []];
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a');
            $c->execute(['a' => $affiliateId]);
            $out['clicks'] = (int) $c->fetchColumn();

            $v = db()->prepare(
                'SELECT currency, COUNT(*) AS n, COALESCE(SUM(commission_cents), 0) AS s
                   FROM affiliate_conversions WHERE affiliate_id = :a GROUP BY currency'
            );
            $v->execute(['a' => $affiliateId]);
            foreach ($v->fetchAll() ?: [] as $r) {
                $out['earnings'][(string) $r['currency']] = (int) $r['s'];
                $out['conversions'] += (int) $r['n'];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** @return list<array> conversions récentes */
    public static function recentFor(int $affiliateId, int $limit = 10): array
    {
        self::ensureTables();
        try {
            $stmt = db()->prepare(
                'SELECT order_public_id, amount_cents, commission_cents, currency, created_at
                   FROM affiliate_conversions WHERE affiliate_id = :a ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
            );
            $stmt->execute(['a' => $affiliateId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function cookie(string $value, int $expires): void
    {
        @setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($value === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $value;
        }
    }
}
