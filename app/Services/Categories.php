<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Catégories « vivantes » : au lieu d'une liste figée, elles se construisent
 * depuis le contenu RÉELLEMENT publié par les utilisateurs —
 *   • les annonces (listings) en ligne,
 *   • les produits mis en ligne dans les boutiques publiées (la catégorie d'un
 *     produit = celle de sa boutique).
 *
 *   live()    → pour l'accueil : seulement les catégories qui ont du contenu,
 *               classées par volume, avec leur compteur (repli curaté si vide).
 *   ordered() → pour les filtres : TOUTES les catégories restent sélectionnables,
 *               mais ordonnées par volume (les plus fournies d'abord).
 *
 * Lecture seule, agrégats bornés à la taxonomie de config('listings.categories')
 * — une catégorie inconnue en base est ignorée (taxonomie propre).
 */
final class Categories
{
    /** @return list<array{key:string,count:int}> classé par volume, vides exclues */
    public static function live(int $limit = 12): array
    {
        $counts = self::counts();
        if ($counts === []) {
            return [];
        }
        $live = array_filter($counts, static fn (int $n): bool => $n > 0);

        // Marketplace encore vide : repli sur la liste curatée (sans compteur).
        if ($live === []) {
            $out = [];
            foreach (array_slice(array_keys($counts), 0, max(1, $limit)) as $key) {
                $out[] = ['key' => (string) $key, 'count' => 0];
            }
            return $out;
        }

        $rank = array_flip(array_keys($counts));
        uksort($live, static function (string $a, string $b) use ($live, $rank): int {
            return ($live[$b] <=> $live[$a]) ?: (($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX));
        });

        $out = [];
        foreach ($live as $key => $n) {
            $out[] = ['key' => (string) $key, 'count' => (int) $n];
            if (count($out) >= max(1, $limit)) {
                break;
            }
        }
        return $out;
    }

    /**
     * TOUTES les catégories de la taxonomie, ordonnées par volume décroissant
     * (les vides en fin, dans l'ordre curaté). Pour les filtres : tout reste
     * sélectionnable, mais le plus fourni d'abord.
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
        usort($keys, static function (string $a, string $b) use ($counts, $rank): int {
            return ($counts[$b] <=> $counts[$a]) ?: (($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX));
        });
        return $keys;
    }

    /**
     * Comptes par catégorie depuis le contenu publié (annonces + produits des
     * boutiques en ligne), bornés à la taxonomie connue.
     * @return array<string,int>  clés = config('listings.categories') (ordre curaté)
     */
    private static function counts(): array
    {
        $allowed = config('listings.categories', []);
        if ($allowed === []) {
            return [];
        }
        $counts = array_fill_keys($allowed, 0);

        // Annonces entre particuliers actuellement en ligne.
        self::accumulate(
            $counts,
            "SELECT category, COUNT(*) AS n FROM listings WHERE status = 'active' GROUP BY category"
        );
        // Produits actifs des boutiques publiées (catégorie = celle de la boutique).
        self::accumulate(
            $counts,
            "SELECT b.category AS category, COUNT(*) AS n
               FROM products p JOIN boutiques b ON b.id = p.boutique_id
              WHERE p.status = 'active' AND b.status = 'published'
              GROUP BY b.category"
        );
        return $counts;
    }

    /**
     * Ajoute les comptes d'une requête d'agrégat (category, n) dans $counts,
     * bornés à la taxonomie connue. Best-effort : ignore toute erreur SQL.
     * @param array<string,int> $counts
     */
    private static function accumulate(array &$counts, string $sql): void
    {
        try {
            $stmt = db()->query($sql);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $cat = (string) ($row['category'] ?? '');
                if (array_key_exists($cat, $counts)) {
                    $counts[$cat] += (int) ($row['n'] ?? 0);
                }
            }
        } catch (\Throwable) {
            // table absente / DB indisponible : on garde les comptes courants
        }
    }
}
