<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Bandeau d'actualités défilant (« ticker ») du bas de page : agrège des
 * informations importantes et CLIQUABLES de la place de marché —
 *   • nouvelles boutiques publiées (catégorie · ville · pays · ouvert/fermé) ;
 *   • nouveaux produits en vente ;
 *   • stock faible (≤ 3 restants) ;
 *   • boutiques qui ferment dans l'heure (horaires structurés).
 *
 * Les données BRUTES sont mises en cache (fichier temp, TTL court) ; le texte
 * est résolu à l'AFFICHAGE — i18n correct (FR/EN) et minutes « ferme dans … »
 * toujours fraîches. Tolérant aux pannes : toute erreur DB renvoie une section
 * vide (jamais bloquant pour la page).
 */
final class NewsTicker
{
    private const CACHE_TTL = 60;  // secondes
    private const MAX       = 14;

    /** @return list<array{kind:string,icon:string,text:string,href:string}> */
    public static function items(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $out = [];
        foreach (self::cached() as $r) {
            $text = self::render($r);
            if ($text !== '') {
                $out[] = ['kind' => (string) $r['kind'], 'icon' => (string) $r['icon'], 'text' => $text, 'href' => (string) $r['href']];
            }
        }
        return $memo = $out;
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir() . '/afriklink-ticker.json';
    }

    /** Invalide le cache : à appeler quand une annonce staff change (création/validation). */
    public static function bustCache(): void
    {
        try {
            @unlink(self::cacheFile());
        } catch (\Throwable) {
        }
    }

    /** @return list<array<string,mixed>> données brutes, cache fichier TTL court */
    private static function cached(): array
    {
        $file = self::cacheFile();
        try {
            if (is_file($file) && (time() - (int) @filemtime($file)) <= self::CACHE_TTL) {
                $d = json_decode((string) @file_get_contents($file), true);
                if (is_array($d)) {
                    return $d;
                }
            }
        } catch (\Throwable) {
            // cache illisible : on reconstruit
        }
        $raw = self::build();
        try {
            @file_put_contents($file, json_encode($raw, JSON_UNESCAPED_UNICODE), LOCK_EX);
        } catch (\Throwable) {
            // écriture best-effort
        }
        return $raw;
    }

    /** @return list<array<string,mixed>> */
    private static function build(): array
    {
        $items = [];

        // 0. Annonces éditoriales du staff (EN ROUGE) — approuvées seulement,
        //    prioritaires, cliquables vers la page d'article.
        foreach (\App\Models\Announcement::liveForTicker(6) as $a) {
            $items[] = ['kind' => 'admin', 'icon' => '📢', 'href' => url('/info/' . $a['public_id']),
                'title' => (string) $a['title']];
        }

        // 1. Ferme bientôt (≤ 60 min) — le plus urgent en tête.
        try {
            $rows = db()->query("SELECT slug, name, hours_json, country_code FROM boutiques
                                 WHERE status = 'published' AND hours_json IS NOT NULL")->fetchAll() ?: [];
            foreach ($rows as $b) {
                $mins = BusinessHours::minutesUntilClose(
                    BusinessHours::decode((string) $b['hours_json']),
                    BusinessHours::timezoneFor($b['country_code'] ?? null)
                );
                if ($mins !== null && $mins > 0 && $mins <= 60) {
                    $items[] = ['kind' => 'closing', 'icon' => '🕐', 'href' => url('/boutique/' . $b['slug']),
                        'name' => (string) $b['name'], 'min' => $mins];
                }
            }
        } catch (\Throwable) {
        }

        // 2. Stock faible (1 à 3 restants).
        try {
            $rows = db()->query("SELECT p.public_id, p.name, p.stock, b.slug
                                 FROM products p JOIN boutiques b ON b.id = p.boutique_id
                                 WHERE p.status = 'active' AND b.status = 'published'
                                   AND p.stock IS NOT NULL AND p.stock BETWEEN 1 AND 3
                                 ORDER BY p.stock ASC, p.id DESC LIMIT 8")->fetchAll() ?: [];
            foreach ($rows as $p) {
                $items[] = ['kind' => 'stock', 'icon' => '⚠️', 'href' => url('/boutique/' . $p['slug'] . '/p/' . $p['public_id']),
                    'name' => (string) $p['name'], 'n' => (int) $p['stock']];
            }
        } catch (\Throwable) {
        }

        // 3. Nouveaux produits en vente.
        try {
            $rows = db()->query("SELECT p.public_id, p.name, p.price_cents, b.slug, b.name AS shop, b.currency
                                 FROM products p JOIN boutiques b ON b.id = p.boutique_id
                                 WHERE p.status = 'active' AND b.status = 'published'
                                 ORDER BY p.id DESC LIMIT 6")->fetchAll() ?: [];
            foreach ($rows as $p) {
                $items[] = ['kind' => 'product', 'icon' => '🛍️', 'href' => url('/boutique/' . $p['slug'] . '/p/' . $p['public_id']),
                    'name' => (string) $p['name'], 'shop' => (string) $p['shop'],
                    'price_cents' => (int) $p['price_cents'], 'cur' => (string) $p['currency']];
            }
        } catch (\Throwable) {
        }

        // 4. Nouvelles boutiques : catégorie · ville · pays · ouvert/fermé.
        try {
            $rows = db()->query("SELECT slug, name, category, city, country_code, hours_json
                                 FROM boutiques WHERE status = 'published'
                                 ORDER BY id DESC LIMIT 6")->fetchAll() ?: [];
            foreach ($rows as $b) {
                $open = null;
                try {
                    $h = BusinessHours::decode($b['hours_json'] ?? null);
                    if ($h !== []) {
                        $open = BusinessHours::isOpenNow($h, BusinessHours::timezoneFor($b['country_code'] ?? null));
                    }
                } catch (\Throwable) {
                }
                $items[] = ['kind' => 'shop', 'icon' => '🆕', 'href' => url('/boutique/' . $b['slug']),
                    'name' => (string) $b['name'], 'cat' => (string) ($b['category'] ?? ''),
                    'city' => (string) ($b['city'] ?? ''), 'cc' => (string) ($b['country_code'] ?? ''),
                    'open' => $open];
            }
        } catch (\Throwable) {
        }

        return array_slice($items, 0, self::MAX);
    }

    /** Texte d'affichage (i18n + heure résolus maintenant, jamais depuis le cache). */
    private static function render(array $r): string
    {
        switch ((string) ($r['kind'] ?? '')) {
            case 'admin':
                return (string) ($r['title'] ?? '');
            case 'closing':
                return t('ticker.closing', ['name' => (string) $r['name'], 'min' => (int) $r['min']]);
            case 'stock':
                return t('ticker.low_stock', ['name' => (string) $r['name'], 'n' => (int) $r['n']]);
            case 'product':
                return t('ticker.new_product', [
                    'name'  => (string) $r['name'],
                    'shop'  => (string) $r['shop'],
                    'price' => format_price((int) $r['price_cents'], (string) $r['cur']),
                ]);
            case 'shop':
                $bits = [];
                if (($r['cat'] ?? '') !== '') {
                    $bits[] = t('listing.cat.' . $r['cat']);
                }
                $place = place_label($r['city'] ?? null, $r['cc'] ?? null);
                if ($place !== '') {
                    $bits[] = $place;
                }
                if (($r['open'] ?? null) !== null) {
                    $bits[] = $r['open'] ? t('shop.open_now') : t('shop.closed_now');
                }
                return t('ticker.new_shop', ['name' => (string) $r['name']])
                    . ($bits !== [] ? ' — ' . implode(' · ', $bits) : '');
            default:
                return '';
        }
    }
}
