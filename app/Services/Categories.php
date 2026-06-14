<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Catégories « vivantes » : au lieu d'une liste figée, elles se construisent
 * depuis le contenu RÉELLEMENT publié par les utilisateurs —
 *   • les annonces (listings) en ligne,
 *   • les produits mis en ligne dans les boutiques publiées (la catégorie d'un
 *     produit = celle de sa boutique).
 * Classées par volume décroissant (les plus fournies d'abord) ; seules les
 * catégories ayant du contenu sont retenues. Repli sur la liste curatée si la
 * marketplace est encore vide, pour ne jamais afficher une section vide.
 *
 * Lecture seule, agrégats bornés à la taxonomie de config('listings.categories')
 * — une catégorie inconnue en base est ignorée (taxonomie propre).
 */
final class Categories
{
    /** @return list<array{key:string,count:int}> ordonné (volume décroissant) */
    public static function live(int $limit = 12): array
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

        $live = array_filter($counts, static fn (int $n): bool => $n > 0);

        // Marketplace encore vide : repli sur la liste curatée (sans compteur).
        if ($live === []) {
            $out = [];
            foreach (array_slice($allowed, 0, max(1, $limit)) as $key) {
                $out[] = ['key' => (string) $key, 'count' => 0];
            }
            return $out;
        }

        // Tri par volume décroissant ; à égalité, on garde l'ordre curaté.
        $rank = array_flip($allowed);
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
