<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Boutique;
use App\Models\Product;

/**
 * « Pépites du catalogue » — transforme tes VRAIES données (produits, promos,
 * boutiques) en blocs HTML prêts à coller dans une newsletter de marque :
 * une promo vedette, une boutique à l'honneur, deux nouveautés. Tout est
 * email-safe (tables + styles en ligne) et pointe vers les vraies fiches.
 *
 * C'est la réalisation concrète de « entrée = catalogue + boutiques → sortie =
 * newsletter » : l'opérateur compose un mot, le système y ajoute l'actu réelle.
 */
final class NewsletterContent
{
    /** Bloc HTML complet des pépites, ou '' s'il n'y a rien à montrer. */
    public static function weeklyPicks(): string
    {
        $featured  = self::featured();
        $boutique  = Boutique::recentPublished(1)[0] ?? null;
        $newItems  = self::recent(2, $featured !== null ? (int) $featured['id'] : 0);
        if ($featured === null && $boutique === null && $newItems === []) {
            return '';
        }

        $html = '';
        if ($featured !== null) {
            $html .= self::section(t('newsletter.picks_promo')) . self::productRow($featured, true);
        }
        if ($boutique !== null) {
            $html .= self::section(t('newsletter.picks_boutique')) . self::boutiqueRow($boutique);
        }
        if ($newItems !== []) {
            $html .= self::section(t('newsletter.picks_new')) . self::productGrid($newItems);
        }
        return $html;
    }

    /** Produit vedette : une promo en cours en priorité, sinon le plus récent. */
    private static function featured(): ?array
    {
        try {
            $stmt = db()->query(
                "SELECT p.id, p.public_id, p.name, p.price_cents, p.promo_price_cents, p.promo_until,
                        b.slug AS boutique_slug, b.currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status='active' AND b.status='published'
                  ORDER BY (p.promo_price_cents IS NOT NULL AND p.promo_until > NOW()) DESC, p.id DESC
                  LIMIT 1"
            );
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array> nouveautés récentes (hors produit vedette). */
    private static function recent(int $limit, int $excludeId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT p.id, p.public_id, p.name, p.price_cents, p.promo_price_cents, p.promo_until,
                        b.slug AS boutique_slug, b.currency
                   FROM products p JOIN boutiques b ON b.id = p.boutique_id
                  WHERE p.status='active' AND b.status='published' AND p.id <> :ex
                  ORDER BY p.id DESC LIMIT " . max(1, min(4, $limit))
            );
            $stmt->execute(['ex' => $excludeId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function section(string $title): string
    {
        return '<p style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#B8860B;font-weight:700;margin:18px 0 8px">'
            . e($title) . '</p>';
    }

    /** Prix effectif (promo si active) + prix barré éventuel. @return array{0:int,1:?int} */
    private static function price(array $p): array
    {
        $onPromo = !empty($p['promo_price_cents']) && !empty($p['promo_until']) && strtotime((string) $p['promo_until']) > time();
        return $onPromo
            ? [(int) $p['promo_price_cents'], (int) $p['price_cents']]
            : [(int) $p['price_cents'], null];
    }

    private static function thumb(?string $publicId, string $emoji): string
    {
        if ($publicId !== null && $publicId !== '') {
            $src = CloudinaryService::imageUrl($publicId, 180, 180);
            return '<img src="' . e($src) . '" width="84" height="84" alt="" style="width:84px;height:84px;border-radius:12px;object-fit:cover;display:block">';
        }
        return '<div style="width:84px;height:84px;border-radius:12px;background:#F1E9D6;text-align:center;line-height:84px;font-size:40px">' . $emoji . '</div>';
    }

    /** Grande carte produit (promo vedette). */
    private static function productRow(array $p, bool $highlight = false): string
    {
        [$price, $old] = self::price($p);
        $cur   = (string) ($p['currency'] ?? 'EUR');
        $photo = Product::mainPhotos([(int) $p['id']])[(int) $p['id']] ?? null;
        $url   = url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']);
        $bg    = $highlight ? '#FBF7EF' : '#ffffff';
        $priceHtml = '<span style="font-weight:800;color:#103D30">' . e(format_price($price, $cur)) . '</span>';
        if ($old !== null && $old > $price) {
            $priceHtml .= ' <span style="color:#9aa39d;text-decoration:line-through;font-size:.85em">' . e(format_price($old, $cur)) . '</span>';
            $pct = (int) round(($old - $price) / max(1, $old) * 100);
            $priceHtml .= ' <span style="background:#B21E4B;color:#fff;font-size:.72rem;font-weight:700;padding:2px 7px;border-radius:6px">-' . $pct . '%</span>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:' . $bg . ';border:1px solid rgba(16,36,30,.1);border-radius:14px;margin-bottom:6px">'
            . '<tr><td width="100" valign="middle" style="padding:14px 0 14px 14px">' . self::thumb($photo, '🛍️') . '</td>'
            . '<td valign="middle" style="padding:14px">'
            . '<a href="' . e($url) . '" style="font-weight:700;color:#16241F;text-decoration:none;font-size:1.02rem">' . e((string) $p['name']) . '</a>'
            . '<div style="margin:6px 0 2px">' . $priceHtml . '</div>'
            . '<a href="' . e($url) . '" style="color:#B8860B;font-weight:700;font-size:.88rem;text-decoration:none">' . e(t('newsletter.picks_cta_order')) . ' →</a>'
            . '</td></tr></table>';
    }

    private static function boutiqueRow(array $b): string
    {
        $url  = url('/boutique/' . $b['slug']);
        $logo = self::thumb((string) ($b['logo_public_id'] ?? ''), '🏬');
        $sub  = !empty($b['category']) ? e(t('listing.cat.' . $b['category'])) : '';
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid rgba(16,36,30,.1);border-radius:14px;margin-bottom:6px">'
            . '<tr><td width="100" valign="middle" style="padding:14px 0 14px 14px">' . $logo . '</td>'
            . '<td valign="middle" style="padding:14px">'
            . '<a href="' . e($url) . '" style="font-weight:700;color:#16241F;text-decoration:none;font-size:1.02rem">' . e((string) $b['name']) . '</a>'
            . ($sub !== '' ? '<div style="color:#5B6B62;font-size:.85rem;margin:3px 0 2px">' . $sub . '</div>' : '')
            . '<a href="' . e($url) . '" style="color:#B8860B;font-weight:700;font-size:.88rem;text-decoration:none">' . e(t('newsletter.picks_cta_visit')) . ' →</a>'
            . '</td></tr></table>';
    }

    /** Deux nouveautés côte à côte. @param list<array> $items */
    private static function productGrid(array $items): string
    {
        $cells = '';
        foreach ($items as $p) {
            [$price] = self::price($p);
            $cur   = (string) ($p['currency'] ?? 'EUR');
            $photo = Product::mainPhotos([(int) $p['id']])[(int) $p['id']] ?? null;
            $url   = url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']);
            $cells .= '<td width="50%" valign="top" style="padding:0 6px">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid rgba(16,36,30,.1);border-radius:12px">'
                . '<tr><td style="padding:12px;text-align:center">'
                . '<a href="' . e($url) . '" style="text-decoration:none">'
                . str_replace('width:84px;height:84px', 'width:100%;height:120px', self::thumb($photo, '🛍️'))
                . '<div style="font-weight:700;color:#16241F;font-size:.92rem;margin:9px 0 3px">' . e(mb_strimwidth((string) $p['name'], 0, 34, '…')) . '</div>'
                . '<div style="font-weight:800;color:#103D30;font-size:.92rem">' . e(format_price($price, $cur)) . '</div>'
                . '</a></td></tr></table></td>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 -6px"><tr>' . $cells . '</tr></table>';
    }
}
