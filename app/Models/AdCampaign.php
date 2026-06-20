<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\ExchangeRates;

/**
 * Campagne publicitaire « AfrikLink Ads » — un FORFAIT de mise en avant acheté
 * par un vendeur pour une de ses offres (produit), sur un emplacement
 * (ex. « À la une » de l'accueil), pour une durée fixe (7 / 15 / 30 jours).
 *
 * Source de vérité des emplacements payants. Pour ne rien casser de l'existant,
 * l'activation d'une campagne PRODUIT pose aussi products.promoted_until = ends_at
 * (le badge « Sponsorisé » et les tris existants continuent de fonctionner) ;
 * l'arrêt / l'expiration le remet à NULL.
 *
 * Équité : on n'affiche que `slots` créneaux par emplacement, EN ROTATION
 * (RAND), pour que toutes les campagnes payées obtiennent des impressions sans
 * transformer l'accueil en mur de pub. Stats par campagne : impressions, clics.
 */
final class AdCampaign
{
    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS ad_campaigns (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id    CHAR(36) NOT NULL UNIQUE,
                user_id      BIGINT UNSIGNED NOT NULL,
                object_type  VARCHAR(12) NOT NULL DEFAULT 'product',
                object_id    BIGINT UNSIGNED NOT NULL,
                placement    VARCHAR(16) NOT NULL DEFAULT 'home',
                days         SMALLINT UNSIGNED NOT NULL DEFAULT 7,
                amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                currency     CHAR(3) NOT NULL DEFAULT 'EUR',
                billing      VARCHAR(12) NOT NULL DEFAULT 'simulation',
                status       VARCHAR(16) NOT NULL DEFAULT 'active',
                starts_at    DATETIME NULL,
                ends_at      DATETIME NULL,
                impressions  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks       BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_place_status (placement, status, ends_at),
                KEY idx_user (user_id, id),
                KEY idx_obj (object_type, object_id, status)
            )"
        );
    }

    // ---- Tarifs (forfaits) -------------------------------------------------

    /** Prix d'un forfait, en centimes de la devise de référence (config). */
    public static function priceCents(string $placement, int $days): ?int
    {
        $pkg = config("ads.placements.$placement.packages", []);
        return isset($pkg[$days]) ? (int) $pkg[$days] : null;
    }

    /** Durées disponibles pour un emplacement. @return list<int> */
    public static function durations(string $placement): array
    {
        $pkg = (array) config("ads.placements.$placement.packages", []);
        $days = array_map('intval', array_keys($pkg));
        sort($days);
        return $days;
    }

    public static function baseCurrency(): string
    {
        return strtoupper((string) config('ads.base_currency', 'EUR'));
    }

    /** Prix converti dans la devise du vendeur (pour l'affichage / le règlement). */
    public static function priceIn(string $placement, int $days, string $currency): ?int
    {
        $base = self::priceCents($placement, $days);
        if ($base === null) {
            return null;
        }
        $currency = strtoupper($currency);
        if ($currency === self::baseCurrency()) {
            return $base;
        }
        return ExchangeRates::convert($base, self::baseCurrency(), $currency) ?? $base;
    }

    // ---- Achat d'un forfait ------------------------------------------------

    /**
     * Achète + active un forfait pour un produit. Gère le règlement selon le
     * mode de facturation (config ads.billing) :
     *   - 'wallet'     : exige un solde suffisant, débite le porte-monnaie ;
     *   - 'simulation' : active en bac à sable (sans argent réel) ;
     *   - 'stripe'     : à venir → retombe en simulation tant que non branché.
     *
     * @return array{ok:bool, code:string} code ∈ {created, insufficient, bad_package}
     */
    public static function purchaseProduct(array $user, array $product, array $boutique, string $placement, int $days): array
    {
        self::ensureTable();
        $placement = self::validPlacement($placement);
        $days      = self::validDays($placement, $days);
        $base      = self::priceCents($placement, $days);
        if ($base === null) {
            return ['ok' => false, 'code' => 'bad_package'];
        }

        $userId  = (int) $user['id'];
        $billing = (string) config('ads.billing', 'simulation');

        if ($billing === 'wallet') {
            $walCur = Wallet::currencyFor($userId, (string) ($boutique['currency'] ?? 'XOF'));
            $price  = ExchangeRates::convert($base, self::baseCurrency(), $walCur) ?? $base;
            if (Wallet::balanceCents($userId) < $price) {
                return ['ok' => false, 'code' => 'insufficient'];
            }
            Wallet::debit($userId, $price, $walCur, 'ad_campaign', null);
        }

        // Montant enregistré = prix du forfait en devise de référence (revenu net
        // de la régie, comparable quel que soit le vendeur).
        $pid = self::insert($userId, 'product', (int) $product['id'], $placement, $days, $base, self::baseCurrency(), $billing);
        self::activate($pid, 'product', (int) $product['id'], $days);
        return ['ok' => true, 'code' => 'created'];
    }

    private static function insert(int $userId, string $type, int $objectId, string $placement, int $days, int $amountCents, string $currency, string $billing): string
    {
        $pid = uuid();
        db()->prepare(
            'INSERT INTO ad_campaigns (public_id, user_id, object_type, object_id, placement, days, amount_cents, currency, billing, status)
             VALUES (:p, :u, :t, :o, :pl, :d, :a, :c, :b, :s)'
        )->execute([
            'p' => $pid, 'u' => $userId, 't' => $type, 'o' => $objectId, 'pl' => $placement,
            'd' => $days, 'a' => $amountCents, 'c' => $currency, 'b' => $billing, 's' => 'active',
        ]);
        return $pid;
    }

    /** Passe la campagne en « active » + pose le pont vers products.promoted_until. */
    private static function activate(string $pid, string $type, int $objectId, int $days): void
    {
        $days = max(1, min(365, $days));
        db()->prepare(
            "UPDATE ad_campaigns
                SET status='active', starts_at=NOW(), ends_at=(NOW() + INTERVAL {$days} DAY)
              WHERE public_id = :p"
        )->execute(['p' => $pid]);
        if ($type === 'product') {
            self::syncProductFlag($objectId);
        }
    }

    /** Aligne products.promoted_until sur la fin de campagne active la plus tardive. */
    private static function syncProductFlag(int $productId): void
    {
        try {
            $stmt = db()->prepare(
                "SELECT MAX(ends_at) FROM ad_campaigns
                  WHERE object_type='product' AND object_id = :o AND status='active' AND ends_at > NOW()"
            );
            $stmt->execute(['o' => $productId]);
            $until = $stmt->fetchColumn();
            db()->prepare('UPDATE products SET promoted_until = :u WHERE id = :id')
                ->execute(['u' => $until ?: null, 'id' => $productId]);
        } catch (\Throwable) {
        }
    }

    // ---- Affichage public (rotation) --------------------------------------

    /**
     * Produits sponsorisés à afficher pour un emplacement, EN ROTATION. Chaque
     * ligne porte les champs produit attendus par les cartes + `campaign_pid`
     * (pour le suivi des clics) et `campaign_id`.
     * @return list<array>
     */
    public static function activeProducts(string $placement = 'home', ?int $limit = null): array
    {
        self::ensureTable();
        self::expireDue();
        $placement = self::validPlacement($placement);
        $limit ??= (int) config("ads.placements.$placement.slots", 8);
        $limit = max(1, min(24, $limit));
        try {
            $stmt = db()->prepare(
                "SELECT p.*, b.slug AS boutique_slug, b.currency AS currency,
                        c.public_id AS campaign_pid, c.id AS campaign_id
                   FROM ad_campaigns c
                   JOIN products  p ON p.id = c.object_id
                   JOIN boutiques b ON b.id = p.boutique_id
                  WHERE c.object_type='product' AND c.placement = :pl AND c.status='active'
                    AND c.ends_at > NOW() AND p.status='active' AND b.status='published'
                  ORDER BY RAND() LIMIT {$limit}"
            );
            $stmt->execute(['pl' => $placement]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** +1 impression pour les campagnes affichées (ids). */
    public static function recordImpressions(array $campaignIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $campaignIds)));
        if ($ids === []) {
            return;
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            db()->prepare("UPDATE ad_campaigns SET impressions = impressions + 1 WHERE id IN ($in)")->execute($ids);
        } catch (\Throwable) {
        }
    }

    /**
     * Enregistre un clic et renvoie l'URL de destination réelle de l'objet
     * sponsorisé (ou null si introuvable / inactif).
     */
    public static function clickThrough(string $campaignPid): ?string
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                "SELECT c.id, c.object_type, p.public_id AS product_pid, b.slug AS boutique_slug
                   FROM ad_campaigns c
                   JOIN products  p ON p.id = c.object_id AND c.object_type='product'
                   JOIN boutiques b ON b.id = p.boutique_id
                  WHERE c.public_id = :p LIMIT 1"
            );
            $stmt->execute(['p' => $campaignPid]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            db()->prepare('UPDATE ad_campaigns SET clicks = clicks + 1 WHERE id = :id')->execute(['id' => (int) $row['id']]);
            return '/boutique/' . $row['boutique_slug'] . '/p/' . $row['product_pid'];
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- Côté vendeur ------------------------------------------------------

    /** Campagne ACTIVE en cours pour un objet donné (ou null). */
    public static function activeFor(string $objectType, int $objectId): ?array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                "SELECT * FROM ad_campaigns
                  WHERE object_type = :t AND object_id = :o AND status='active' AND ends_at > NOW()
                  ORDER BY ends_at DESC LIMIT 1"
            );
            $stmt->execute(['t' => $objectType, 'o' => $objectId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Toutes les campagnes actives d'un objet, indexées par object_id. @return array<int,array> */
    public static function activeMap(string $objectType, array $objectIds): array
    {
        self::ensureTable();
        $ids = array_values(array_filter(array_map('intval', $objectIds)));
        if ($ids === []) {
            return [];
        }
        try {
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $args = array_merge([$objectType], $ids);
            $stmt = db()->prepare(
                "SELECT * FROM ad_campaigns
                  WHERE object_type = ? AND object_id IN ($in) AND status='active' AND ends_at > NOW()"
            );
            $stmt->execute($args);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(int) $r['object_id']] = $r;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Arrêt par le vendeur (ou l'admin) : campagne stoppée + flag produit retiré. */
    public static function stop(string $campaignPid, int $userId): bool
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM ad_campaigns WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $campaignPid]);
            $c = $stmt->fetch();
            if (!$c || (int) $c['user_id'] !== $userId) {
                return false;
            }
            db()->prepare("UPDATE ad_campaigns SET status='stopped', ends_at=NOW() WHERE id = :id")
                ->execute(['id' => (int) $c['id']]);
            if ($c['object_type'] === 'product') {
                self::syncProductFlag((int) $c['object_id']);
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Expire les campagnes échues + nettoie les flags produit. */
    public static function expireDue(): void
    {
        try {
            $due = db()->query("SELECT id, object_type, object_id FROM ad_campaigns WHERE status='active' AND ends_at <= NOW()")->fetchAll();
            if (!$due) {
                return;
            }
            db()->exec("UPDATE ad_campaigns SET status='expired' WHERE status='active' AND ends_at <= NOW()");
            foreach ($due as $c) {
                if ($c['object_type'] === 'product') {
                    self::syncProductFlag((int) $c['object_id']);
                }
            }
        } catch (\Throwable) {
        }
    }

    // ---- Back-office admin -------------------------------------------------

    /** @return list<array> campagnes récentes avec annonceur + nom de l'objet. */
    public static function adminList(int $limit = 100): array
    {
        self::ensureTable();
        self::expireDue();
        $limit = max(1, min(500, $limit));
        try {
            return db()->query(
                "SELECT c.*, u.full_name AS seller_name, u.email AS seller_email, p.name AS object_name
                   FROM ad_campaigns c
                   LEFT JOIN users u ON u.id = c.user_id
                   LEFT JOIN products p ON p.id = c.object_id AND c.object_type='product'
                  ORDER BY c.id DESC LIMIT {$limit}"
            )->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Action admin : 'stop' (suspend) ou 'reject' (refuse) une campagne. */
    public static function adminSetStatus(string $campaignPid, string $status): bool
    {
        self::ensureTable();
        $status = in_array($status, ['stopped', 'rejected'], true) ? $status : 'stopped';
        try {
            $stmt = db()->prepare('SELECT * FROM ad_campaigns WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $campaignPid]);
            $c = $stmt->fetch();
            if (!$c) {
                return false;
            }
            db()->prepare('UPDATE ad_campaigns SET status = :s, ends_at = NOW() WHERE id = :id')
                ->execute(['s' => $status, 'id' => (int) $c['id']]);
            if ($c['object_type'] === 'product') {
                self::syncProductFlag((int) $c['object_id']);
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function activeCount(): int
    {
        self::ensureTable();
        try {
            return (int) db()->query("SELECT COUNT(*) FROM ad_campaigns WHERE status='active' AND ends_at > NOW()")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Revenu pub cumulé (devise de référence) — hors campagnes refusées. */
    public static function revenueCents(): int
    {
        self::ensureTable();
        try {
            return (int) db()->query("SELECT COALESCE(SUM(amount_cents),0) FROM ad_campaigns WHERE status <> 'rejected'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ---- Validation -------------------------------------------------------

    public static function validPlacement(string $placement): string
    {
        $all = array_keys((array) config('ads.placements', []));
        return in_array($placement, $all, true) ? $placement : ($all[0] ?? 'home');
    }

    public static function validDays(string $placement, int $days): int
    {
        $durations = self::durations($placement);
        return in_array($days, $durations, true) ? $days : (int) ($durations[0] ?? 7);
    }
}
