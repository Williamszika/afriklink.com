<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantOrder;

/**
 * Confirmation d'un paiement — point UNIQUE qui marque un paiement (et sa
 * commande) « payé » et notifie le vendeur. Appelé par le **webhook signé**
 * (source de vérité) et réutilisable par tout autre canal. **Idempotent** : ne
 * refait rien si le paiement est déjà confirmé (les PSP renvoient les
 * événements plusieurs fois).
 */
final class PaymentSettlement
{
    /**
     * Confirme un paiement : Payment + commande → payés, notif vendeur.
     * @param array $payment ligne `payments`
     * @return bool true si confirmé MAINTENANT, false si déjà payé / invalide
     */
    public static function confirm(array $payment, string $providerRef = ''): bool
    {
        $ref = (string) ($payment['public_id'] ?? '');
        if ($ref === '' || ($payment['status'] ?? '') === 'paid') {
            return false; // idempotent : déjà confirmé (ou référence absente)
        }

        Payment::setStatus($ref, 'paid', $providerRef);

        $orderId = (int) ($payment['order_id'] ?? 0);
        $kind    = (string) ($payment['kind'] ?? 'boutique');
        if ($orderId > 0) {
            if ($kind === 'restaurant') {
                RestaurantOrder::setPaymentStatus($orderId, 'paid', $ref);
            } else {
                Order::setPaymentStatus($orderId, 'paid', $ref);
            }
        }

        $sellerId = (int) ($payment['user_id'] ?? 0);
        $amount   = (int) ($payment['amount_cents'] ?? 0);
        $currency = (string) ($payment['currency'] ?? 'EUR');

        // Crédite le portefeuille du vendeur de SA PART. Pour une commande boutique, le
        // règlement gère l'affiliation PAR PRODUIT (R % retranché du vendeur sur les
        // articles affiliés vendus via un apporteur ; il crédite l'apporteur de R − part
        // plateforme) et renvoie la part vendeur. Sinon : commission plateforme normale.
        try {
            if ($sellerId > 0 && $amount > 0) {
                if ($orderId > 0 && $kind !== 'restaurant' && ($pub = Order::publicIdById($orderId)) !== null) {
                    $sellerCredit = \App\Models\Affiliate::settleBoutiqueOrder($orderId, $pub, $amount, $currency);
                } else {
                    $sellerCredit = $amount - platform_commission_cents($amount);
                }
                \App\Models\Wallet::credit($sellerId, $sellerCredit, $currency, 'sale', $ref);
            }
        } catch (\Throwable) {
        }

        // Alerte vendeur (in-app) — best-effort, ne bloque jamais.
        try {
            $sellerId = (int) ($payment['user_id'] ?? 0);
            if ($sellerId > 0) {
                $shortRef = strtoupper(substr($ref, 0, 6));
                $amount   = format_price((int) ($payment['amount_cents'] ?? 0), (string) ($payment['currency'] ?? 'EUR'));
                Notification::push(
                    $sellerId,
                    'order_paid',
                    t('notify.paid.title'),
                    t('notify.paid.body', ['ref' => $shortRef, 'amount' => $amount]),
                    $kind === 'restaurant' ? '/restaurant/commandes' : '/vendeur/commandes',
                );
            }
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * Marque un paiement échoué / annulé. Ne « défait » JAMAIS un paiement déjà
     * confirmé (sécurité). Best-effort.
     */
    public static function fail(array $payment, string $status = 'failed'): void
    {
        $ref = (string) ($payment['public_id'] ?? '');
        if ($ref === '' || ($payment['status'] ?? '') === 'paid') {
            return;
        }
        $status = in_array($status, ['failed', 'cancelled'], true) ? $status : 'failed';
        Payment::setStatus($ref, $status);

        $orderId = (int) ($payment['order_id'] ?? 0);
        $kind    = (string) ($payment['kind'] ?? 'boutique');
        if ($orderId > 0 && $kind !== 'restaurant') {
            Order::setPaymentStatus($orderId, 'failed');
        }
    }
}
