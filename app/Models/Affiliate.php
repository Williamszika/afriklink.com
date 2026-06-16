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
    private const COOKIE_TGT = 'aff_tgt'; // cible (produit/boutique) du lien cliqué

    public static function ensureTables(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS affiliate_codes (
                user_id    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                code       VARCHAR(16) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS affiliate_clicks (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                affiliate_id BIGINT UNSIGNED NOT NULL,
                target       VARCHAR(300) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_aff_clicks (affiliate_id, id)
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS affiliate_conversions (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                affiliate_id     BIGINT UNSIGNED NOT NULL,
                order_public_id  CHAR(36) NOT NULL UNIQUE,
                boutique_id      BIGINT UNSIGNED NOT NULL,
                amount_cents     BIGINT UNSIGNED NOT NULL DEFAULT 0,
                commission_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency         CHAR(3) NOT NULL DEFAULT \'EUR\',
                paid_out_at      DATETIME NULL,
                target           VARCHAR(300) NULL,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_aff_conv (affiliate_id, id)
            )'
        );
        self::migrate();
    }

    /** Ajoute paid_out_at / target aux tables existantes. Mémoïsé. */
    private static function migrate(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (['paid_out_at' => 'DATETIME NULL', 'target' => 'VARCHAR(300) NULL'] as $col => $type) {
            try {
                db()->query("SELECT {$col} FROM affiliate_conversions LIMIT 1");
            } catch (\Throwable) {
                try {
                    db()->exec("ALTER TABLE affiliate_conversions ADD COLUMN {$col} {$type}");
                } catch (\Throwable) {
                    // course entre instances : une autre a déjà migré
                }
            }
        }
    }

    /* ---- Logique pure (testable hors base) ------------------------------- */

    /** Commission (centimes) sur un sous-total donné, au taux indiqué (défaut RATE_PCT). */
    public static function commissionFor(int $subtotalCents, int $ratePct = self::RATE_PCT): int
    {
        $ratePct = max(0, min(100, $ratePct));
        return $subtotalCents > 0 ? intdiv($subtotalCents * $ratePct, 100) : 0;
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

    /** Pose le cookie de parrainage (30 jours) + mémorise la cible du lien cliqué. */
    public static function setRefCookie(string $code, string $target = ''): void
    {
        self::cookie($code, time() + 2592000);
        self::cookie(mb_substr($target, 0, 300), time() + 2592000, self::COOKIE_TGT);
    }

    /**
     * Attribue une commande à l'apporteur s'il y a un cookie de parrainage valide.
     * One-shot : le cookie est consommé. Pas d'auto-parrainage (apporteur ≠ vendeur).
     * Opt-in : seules les boutiques ayant activé leur programme reversent une commission,
     * au taux qu'elles ont fixé.
     */
    public static function attribute(string $orderPublicId, int $boutiqueId, int $sellerUserId, array $lines, int $subtotalCents, string $currency): void
    {
        $code   = trim((string) ($_COOKIE[self::COOKIE] ?? ''));
        $target = trim((string) ($_COOKIE[self::COOKIE_TGT] ?? ''));
        if ($code === '') {
            return;
        }
        self::cookie('', time() - 3600);                   // consomme le cookie code
        self::cookie('', time() - 3600, self::COOKIE_TGT); // consomme le cookie cible
        $affiliateId = self::userIdForCode($code);
        if ($affiliateId === null || $affiliateId === $sellerUserId) {
            return; // lien inconnu ou auto-parrainage
        }
        // L'affiliation est réservée aux particuliers : un vendeur (professionnel)
        // n'apporte pas de commission — les gains sont distribués par particulier.
        $affiliate = User::findById($affiliateId);
        if (($affiliate['account_type'] ?? '') === 'professionnel') {
            return;
        }
        // Commission PAR PRODUIT : somme des commissions des articles affiliés (taux
        // fixé par le vendeur, borné). Prélevée sur la commission plateforme — donc
        // plafonnée à celle-ci (le vendeur ne paie jamais en plus, AfrikaLink garde ≥ son minimum).
        $commission = self::commissionForLines($lines);
        if ($commission <= 0) {
            return; // aucun article affilié dans la commande
        }
        $commission = min($commission, platform_commission_cents($subtotalCents));
        self::recordConversion($affiliateId, $orderPublicId, $boutiqueId, $subtotalCents, $commission, $currency, $target);
    }

    /** Somme des commissions d'affiliation des articles d'une commande (par produit). */
    private static function commissionForLines(array $lines): int
    {
        $pids = [];
        foreach ($lines as $l) {
            $pid = (int) ($l['product_id'] ?? 0);
            if ($pid > 0) {
                $pids[] = $pid;
            }
        }
        $map = \App\Models\Product::affiliationMapFor($pids);
        $total = 0;
        foreach ($lines as $l) {
            $aff = $map[(int) ($l['product_id'] ?? 0)] ?? null;
            if ($aff === null || !$aff['enabled'] || $aff['bps'] <= 0) {
                continue;
            }
            $lineTotal = (int) ($l['line_total_cents'] ?? ((int) ($l['qty'] ?? 0) * (int) ($l['unit_price_cents'] ?? 0)));
            $total += affiliate_line_commission_cents($lineTotal, (int) $aff['bps']);
        }
        return $total;
    }

    public static function recordConversion(int $affiliateId, string $orderPublicId, int $boutiqueId, int $amountCents, int $commissionCents, string $currency, string $target = ''): void
    {
        self::ensureTables();
        try {
            db()->prepare(
                'INSERT INTO affiliate_conversions (affiliate_id, order_public_id, boutique_id, amount_cents, commission_cents, currency, target)
                 VALUES (:a, :o, :b, :amt, :com, :cur, :tgt)'
            )->execute([
                'a' => $affiliateId, 'o' => $orderPublicId, 'b' => $boutiqueId,
                'amt' => max(0, $amountCents), 'com' => max(0, $commissionCents), 'cur' => mb_substr($currency, 0, 3),
                'tgt' => $target !== '' ? mb_substr($target, 0, 300) : null,
            ]);
        } catch (\Throwable) {
            // doublon (commande déjà créditée) : idempotent, on ignore.
        }
    }

    /**
     * Verse la commission d'une commande au PORTEFEUILLE de l'apporteur, une seule
     * fois, lorsque la commande est réellement payée (appelé par PaymentSettlement).
     * Idempotent : un verrou atomique (paid_out_at) empêche tout double crédit.
     */
    public static function payoutForOrder(string $orderPublicId, int $platformFeeCents = 0): void
    {
        $orderPublicId = trim($orderPublicId);
        if ($orderPublicId === '') {
            return;
        }
        self::ensureTables();
        try {
            $stmt = db()->prepare(
                'SELECT id, affiliate_id, commission_cents, currency, paid_out_at
                   FROM affiliate_conversions WHERE order_public_id = :o LIMIT 1'
            );
            $stmt->execute(['o' => $orderPublicId]);
            $row = $stmt->fetch();
            if ($row === false || $row['paid_out_at'] !== null) {
                return; // pas d'apporteur, ou déjà versé
            }
            // Commission = celle calculée à l'attribution (somme par produit), plafonnée
            // à la commission plateforme RÉELLE si connue (jamais plus — sécurité argent).
            $commission = (int) $row['commission_cents'];
            if ($platformFeeCents > 0) {
                $commission = min($commission, $platformFeeCents);
            }
            if ($commission <= 0) {
                return;
            }
            // Verrou : seul le premier à poser paid_out_at crédite (anti double-paiement).
            $lock = db()->prepare('UPDATE affiliate_conversions SET paid_out_at = NOW(), commission_cents = :com WHERE id = :id AND paid_out_at IS NULL');
            $lock->execute(['com' => $commission, 'id' => (int) $row['id']]);
            if ($lock->rowCount() < 1) {
                return; // un autre traitement a déjà versé
            }
            Wallet::credit((int) $row['affiliate_id'], $commission, (string) $row['currency'], 'affiliate', $orderPublicId);
        } catch (\Throwable) {
            // best-effort : ne bloque jamais la confirmation de paiement
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

    /* ---- Suivi PAR LIEN (apporteur particulier) -------------------------- */

    /**
     * Statistiques par lien partagé (par cible) : clics, ventes, gains par devise.
     * @return list<array{target:string, clicks:int, sales:int, earnings:array<string,int>}>
     */
    public static function linkStats(int $affiliateId, int $limit = 100): array
    {
        self::ensureTables();
        if ($affiliateId <= 0) {
            return [];
        }
        $rows = [];
        try {
            $c = db()->prepare('SELECT target AS t, COUNT(*) AS n FROM affiliate_clicks WHERE affiliate_id = :a GROUP BY target');
            $c->execute(['a' => $affiliateId]);
            foreach ($c->fetchAll() ?: [] as $r) {
                $t = (string) ($r['t'] ?? '');
                $rows[$t] ??= ['target' => $t, 'clicks' => 0, 'sales' => 0, 'earnings' => []];
                $rows[$t]['clicks'] = (int) $r['n'];
            }
            $v = db()->prepare('SELECT target AS t, currency, COUNT(*) AS n, COALESCE(SUM(commission_cents),0) AS s
                                  FROM affiliate_conversions WHERE affiliate_id = :a GROUP BY target, currency');
            $v->execute(['a' => $affiliateId]);
            foreach ($v->fetchAll() ?: [] as $r) {
                $t = (string) ($r['t'] ?? '');
                $rows[$t] ??= ['target' => $t, 'clicks' => 0, 'sales' => 0, 'earnings' => []];
                $rows[$t]['sales'] += (int) $r['n'];
                $rows[$t]['earnings'][(string) $r['currency']] = (int) $r['s'];
            }
        } catch (\Throwable) {
        }
        $out = array_values($rows);
        usort($out, static fn (array $a, array $b): int => ($b['sales'] <=> $a['sales']) ?: ($b['clicks'] <=> $a['clicks']));
        return array_slice($out, 0, max(1, $limit));
    }

    /** Libellé lisible d'une cible de lien (nom du produit ou de la boutique), best-effort. */
    public static function labelForTarget(?string $target): string
    {
        $t = trim((string) $target);
        if ($t === '' || $t === '/') {
            return '';
        }
        try {
            if (preg_match('#^/boutique/[^/]+/p/([^/?#]+)#', $t, $m)) {
                $st = db()->prepare('SELECT name FROM products WHERE public_id = :p LIMIT 1');
                $st->execute(['p' => $m[1]]);
                $name = $st->fetchColumn();
                if ($name !== false) {
                    return (string) $name;
                }
            }
            if (preg_match('#^/boutique/([^/?#]+)#', $t, $m)) {
                $st = db()->prepare('SELECT name FROM boutiques WHERE slug = :s LIMIT 1');
                $st->execute(['s' => $m[1]]);
                $name = $st->fetchColumn();
                if ($name !== false) {
                    return (string) $name;
                }
            }
        } catch (\Throwable) {
        }
        return $t;
    }

    /* ---- Performance d'un programme (côté vendeur, par boutique) ---------- */

    /**
     * Statistiques d'affiliation d'UNE boutique (pour le vendeur qui l'offre) :
     * nb d'apporteurs distincts, nb de ventes, montant et commissions par devise.
     * @return array{affiliates:int, sales:int, sales_amount:array<string,int>, commissions:array<string,int>, paid:array<string,int>}
     */
    public static function programStats(int $boutiqueId): array
    {
        self::ensureTables();
        $out = ['affiliates' => 0, 'sales' => 0, 'sales_amount' => [], 'commissions' => [], 'paid' => []];
        if ($boutiqueId <= 0) {
            return $out;
        }
        try {
            $stmt = db()->prepare(
                'SELECT currency,
                        COUNT(*) AS n,
                        COALESCE(SUM(amount_cents), 0) AS amt,
                        COALESCE(SUM(commission_cents), 0) AS com,
                        COALESCE(SUM(CASE WHEN paid_out_at IS NOT NULL THEN commission_cents ELSE 0 END), 0) AS paid
                   FROM affiliate_conversions WHERE boutique_id = :b GROUP BY currency'
            );
            $stmt->execute(['b' => $boutiqueId]);
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $cur = (string) $r['currency'];
                $out['sales'] += (int) $r['n'];
                $out['sales_amount'][$cur] = (int) $r['amt'];
                $out['commissions'][$cur] = (int) $r['com'];
                $out['paid'][$cur] = (int) $r['paid'];
            }
            // Apporteurs DISTINCTS (global, toutes devises confondues).
            $a = db()->prepare('SELECT COUNT(DISTINCT affiliate_id) FROM affiliate_conversions WHERE boutique_id = :b');
            $a->execute(['b' => $boutiqueId]);
            $out['affiliates'] = (int) $a->fetchColumn();
        } catch (\Throwable) {
        }
        return $out;
    }

    /**
     * Évolution journalière (N derniers jours) des indicateurs d'affiliation d'une
     * boutique, pour les graphiques : apporteurs actifs, ventes, commissions (centimes).
     * @return list<array{date:string,label:string,affiliates:int,sales:int,commission:int}>
     */
    public static function programSeries(int $boutiqueId, int $days = 14): array
    {
        self::ensureTables();
        $days = max(7, min(60, $days));
        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $buckets[$d] = ['date' => $d, 'label' => date('d/m', strtotime($d)), 'affiliates' => 0, 'sales' => 0, 'commission' => 0];
        }
        if ($boutiqueId > 0) {
            try {
                $stmt = db()->prepare(
                    'SELECT DATE(created_at) d, COUNT(*) n, COUNT(DISTINCT affiliate_id) a, COALESCE(SUM(commission_cents),0) c
                       FROM affiliate_conversions
                      WHERE boutique_id = :b AND created_at >= :since
                      GROUP BY DATE(created_at)'
                );
                $stmt->execute(['b' => $boutiqueId, 'since' => date('Y-m-d', strtotime('-' . ($days - 1) . ' day')) . ' 00:00:00']);
                foreach ($stmt->fetchAll() ?: [] as $r) {
                    $d = (string) $r['d'];
                    if (isset($buckets[$d])) {
                        $buckets[$d]['sales']      = (int) $r['n'];
                        $buckets[$d]['affiliates'] = (int) $r['a'];
                        $buckets[$d]['commission'] = (int) $r['c'];
                    }
                }
            } catch (\Throwable) {
            }
        }
        return array_values($buckets);
    }

    /** Dernières ventes attribuées à des apporteurs pour CETTE boutique. @return list<array> */
    public static function programRecent(int $boutiqueId, int $limit = 8): array
    {
        self::ensureTables();
        if ($boutiqueId <= 0) {
            return [];
        }
        try {
            $stmt = db()->prepare(
                'SELECT order_public_id, amount_cents, commission_cents, currency, paid_out_at, created_at
                   FROM affiliate_conversions WHERE boutique_id = :b ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
            );
            $stmt->execute(['b' => $boutiqueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function cookie(string $value, int $expires, string $name = self::COOKIE): void
    {
        @setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($value === '') {
            unset($_COOKIE[$name]);
        } else {
            $_COOKIE[$name] = $value;
        }
    }
}
