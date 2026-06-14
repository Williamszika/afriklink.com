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

        // Crédite le portefeuille du vendeur de SA PART (montant − commission
        // plateforme). Best-effort ; ne bloque jamais la confirmation.
        try {
            $sellerId = (int) ($payment['user_id'] ?? 0);
            $amount   = (int) ($payment['amount_cents'] ?? 0);
            if ($sellerId > 0 && $amount > 0) {
                $share = $amount - platform_commission_cents($amount);
                \App\Models\Wallet::credit($sellerId, $share, (string) ($payment['currency'] ?? 'EUR'), 'sale', $ref);
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
