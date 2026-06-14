<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Payment\PaymentProviders;

/**
 * Garde-fous de configuration de la boutique. La checklist « préparez votre
 * boutique » vérifie que le MINIMUM est là (logo, produit, livraison…) ; ces
 * garde-fous signalent en plus les **incohérences** : une boutique peut « passer »
 * la checklist tout en se contredisant (ex. « carte » cochée mais aucun
 * encaissement réel configuré). Non bloquant — on guide le vendeur.
 *
 * Lecture seule, sans effet de bord (config + l'enregistrement boutique).
 */
final class ShopReadiness
{
    /** Moyens de paiement qui supposent un encaissement EN LIGNE (pas du cash). */
    private const ONLINE_METHODS = ['card', 'paypal', 'apple_pay', 'google_pay', 'mobile_money'];

    /**
     * @param array $b boutique  @param int $activeProducts produits actifs (-1 = inconnu)
     * @return list<array{level:string,key:string,href:?string}>
     */
    public static function warnings(array $b, int $activeProducts = -1): array
    {
        $methods = self::csv($b['payment_methods'] ?? '');
        $terms   = self::csv($b['payment_terms'] ?? '');
        $zones   = self::csv($b['delivery_zones'] ?? '');
        $dmeth   = self::csv($b['delivery_methods'] ?? '');

        $hasOnlineMethod = array_intersect($methods, self::ONLINE_METHODS) !== [];
        $providerKey = (string) ($b['payment_provider'] ?? '');
        $provider = PaymentProviders::resolve($providerKey !== '' ? $providerKey : null);
        $realPsp  = $provider->key() !== 'simulation' && $provider->isConfigured();

        $w = [];

        // 1. Paiement en ligne affiché, mais aucun encaissement réel (mode démo).
        if ($hasOnlineMethod && !$realPsp) {
            $w[] = ['level' => 'warn', 'key' => 'pay_no_psp', 'href' => '/paiement/tester'];
        }
        // 2. Règlement à l'avance / acompte exigé, mais impossible de payer en ligne.
        if ((in_array('before_delivery', $terms, true) || in_array('deposit', $terms, true)) && !$realPsp) {
            $w[] = ['level' => 'warn', 'key' => 'prepay_no_psp', 'href' => '/paiement/tester'];
        }
        // 3. International activé sans tarif international.
        $intl = in_array('international', $zones, true) || in_array('international', $dmeth, true);
        if ($intl && empty($b['delivery_intl_cents'])) {
            $w[] = ['level' => 'warn', 'key' => 'intl_no_fee', 'href' => '/boutique/modifier'];
        }
        // 4. Publiée alors qu'aucun produit n'est en ligne.
        if ($activeProducts === 0 && (string) ($b['status'] ?? '') === 'published') {
            $w[] = ['level' => 'warn', 'key' => 'published_empty', 'href' => '/boutique/produits/nouveau'];
        }
        // 5. Mode vacances actif (information).
        if (!empty($b['is_vacation'])) {
            $w[] = ['level' => 'info', 'key' => 'vacation', 'href' => '/boutique/modifier'];
        }

        return $w;
    }

    /** @return list<string> CSV → liste nettoyée */
    private static function csv(mixed $v): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $v))));
    }
}
