<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Catégories « vivantes » : construites depuis le contenu RÉELLEMENT publié —
 *   • les annonces (listings) en ligne,
 *   • les produits des boutiques publiées (catégorie d'un produit = celle de sa boutique).
 *
 * Classement « tendance » : volume total + bonus de récence (le contenu des 30
 * derniers jours pèse davantage), pour que les catégories qui bougent remontent.
 *   live()    → accueil : catégories non vides, classées, avec compteur réel.
 *   ordered() → filtres : TOUTES sélectionnables, mais classées par tendance.
 *
 * Agrégats mis en cache (fichier temp, TTL court) + mémoïsés par requête.
 * Lecture seule, bornée à config('listings.categories') ; best-effort (aucune
 * erreur DB / cache ne casse la page).
 */
final class Categories
{
    private const RECENT_DAYS = 30;
    private const RECENT_WEIGHT = 2;   // bonus appliqué au contenu récent
    private const CACHE_TTL = 120;     // secondes

    /** @return list<array{key:string,count:int}> classé par tendance, vides exclues */
    public static function live(int $limit = 12): array
    {
        $counts = self::counts();
        if ($counts === []) {
            return [];
        }
        $live = array_filter($counts, static fn (array $v): bool => $v['total'] > 0);

        // Marketplace encore vide : repli sur la liste curatée (sans compteur).
        if ($live === []) {
            $out = [];
            foreach (array_slice(array_keys($counts), 0, max(1, $limit)) as $key) {
                $out[] = ['key' => (string) $key, 'count' => 0];
            }
            return $out;
        }

        $rank = array_flip(array_keys($counts));
        uksort($live, static fn (string $a, string $b): int =>
            (self::score($live[$b]) <=> self::score($live[$a])) ?: (($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX)));

        $out = [];
        foreach ($live as $key => $v) {
            $out[] = ['key' => (string) $key, 'count' => (int) $v['total']];
            if (count($out) >= max(1, $limit)) {
                break;
            }
        }
        return $out;
    }

    /**
     * TOUTES les catégories, ordonnées par tendance (vides en fin, ordre curaté).
     * @return list<string>
     */
    public static function ordered(): array
    {
        $counts = self::counts();
        if ($counts === []) {
            return [];
        }
        $keys = array_keys($counts);
        $rank = array_flip($keys);
        usort($keys, static fn (string $a, string $b): int =>
            (self::score($counts[$b]) <=> self::score($counts[$a])) ?: (($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX)));
        return $keys;
    }

    /** Score de tendance : volume + bonus de récence. */
    private static function score(array $v): int
    {
        return (int) ($v['total'] ?? 0) + self::RECENT_WEIGHT * (int) ($v['recent'] ?? 0);
    }

    /**
     * Comptes par catégorie (total + récent), mémoïsés par requête et cachés
     * sur disque (TTL court). Toujours normalisés à la taxonomie courante.
     * @return array<string,array{total:int,recent:int}>
     */
    private static function counts(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $allowed = config('listings.categories', []);
        if ($allowed === []) {
            return $memo = [];
        }
        $raw = self::cacheGet();
        if ($raw === null) {
            $raw = self::compute($allowed);
            self::cachePut($raw);
        }
        // Normalise : exactement la taxonomie courante (immunise contre un cache périmé).
        $norm = [];
        foreach ($allowed as $key) {
            $v = $raw[$key] ?? null;
            $norm[$key] = ['total' => (int) ($v['total'] ?? 0), 'recent' => (int) ($v['recent'] ?? 0)];
        }
        return $memo = $norm;
    }

    /** @param list<string> $allowed @return array<string,array{total:int,recent:int}> */
    private static function compute(array $allowed): array
    {
        $base = [];
        foreach ($allowed as $key) {
            $base[$key] = ['total' => 0, 'recent' => 0];
        }
        $since = self::RECENT_DAYS;
        // Annonces.
        self::accumulate($base, 'total',  "SELECT category, COUNT(*) AS n FROM listings WHERE status = 'active' GROUP BY category");
        self::accumulate($base, 'recent', "SELECT category, COUNT(*) AS n FROM listings WHERE status = 'active' AND created_at >= (NOW() - INTERVAL {$since} DAY) GROUP BY category");
        // Produits des boutiques publiées (catégorie = celle de la boutique).
        self::accumulate($base, 'total',  "SELECT b.category AS category, COUNT(*) AS n FROM products p JOIN boutiques b ON b.id = p.boutique_id WHERE p.status = 'active' AND b.status = 'published' GROUP BY b.category");
        self::accumulate($base, 'recent', "SELECT b.category AS category, COUNT(*) AS n FROM products p JOIN boutiques b ON b.id = p.boutique_id WHERE p.status = 'active' AND b.status = 'published' AND p.created_at >= (NOW() - INTERVAL {$since} DAY) GROUP BY b.category");
        return $base;
    }

    /**
     * @param array<string,array{total:int,recent:int}> $base
     */
    private static function accumulate(array &$base, string $field, string $sql): void
    {
        try {
            $stmt = db()->query($sql);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $cat = (string) ($row['category'] ?? '');
                if (isset($base[$cat])) {
                    $base[$cat][$field] += (int) ($row['n'] ?? 0);
                }
            }
        } catch (\Throwable) {
            // table absente / DB indisponible : on garde les comptes courants
        }
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir() . '/afriklink-cat-counts.json';
    }

    /** @return array<string,array{total:int,recent:int}>|null */
    private static function cacheGet(): ?array
    {
        try {
            $f = self::cacheFile();
            if (!is_file($f) || (time() - (int) @filemtime($f)) > self::CACHE_TTL) {
                return null;
            }
            $raw = @file_get_contents($f);
            $data = $raw !== false ? json_decode($raw, true) : null;
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,array{total:int,recent:int}> $counts */
    private static function cachePut(array $counts): void
    {
        try {
            @file_put_contents(self::cacheFile(), json_encode($counts), LOCK_EX);
        } catch (\Throwable) {
            // cache best-effort : un échec d'écriture n'est jamais bloquant
        }
    }
}
