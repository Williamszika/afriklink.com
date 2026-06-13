<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Panier persistant (session), partagé sur tout le site et regroupé par
 * boutique : $_SESSION['cart'][boutiqueId][productPublicId] = quantité.
 * Le storefront s'y synchronise à chaque ajout ; la page /panier permet de
 * modifier. La validation prix/stock reste serveur (caisse).
 */
final class Cart
{
    /** @return array<int,array<string,int>> */
    public static function raw(): array
    {
        return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
    }

    public static function count(): int
    {
        $n = 0;
        foreach (self::raw() as $items) {
            foreach ((array) $items as $q) {
                $n += (int) $q;
            }
        }
        return $n;
    }

    public static function qty(int $boutiqueId, string $pid): int
    {
        return (int) ($_SESSION['cart'][$boutiqueId][$pid] ?? 0);
    }

    /** Fixe la quantité (0 = retire). */
    public static function setQty(int $boutiqueId, string $pid, int $qty): void
    {
        $qty = max(0, min(99, $qty));
        if ($qty === 0) {
            unset($_SESSION['cart'][$boutiqueId][$pid]);
            if (empty($_SESSION['cart'][$boutiqueId])) {
                unset($_SESSION['cart'][$boutiqueId]);
            }
        } else {
            $_SESSION['cart'][$boutiqueId][$pid] = $qty;
        }
    }

    public static function clearBoutique(int $boutiqueId): void
    {
        unset($_SESSION['cart'][$boutiqueId]);
    }

    /** @return list<int> identifiants de boutiques présentes au panier */
    public static function boutiqueIds(): array
    {
        return array_map('intval', array_keys(self::raw()));
    }

    /** @return list<string> tous les identifiants publics de produits au panier */
    public static function allPids(): array
    {
        $out = [];
        foreach (self::raw() as $items) {
            foreach (array_keys((array) $items) as $pid) {
                $out[] = (string) $pid;
            }
        }
        return $out;
    }
}
