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
        if ($ref === '') {
            return false;
        }
        // Idempotence ATOMIQUE : on revendique le statut « payé » en base. Si une
        // autre livraison de webhook (CinetPay sans dédup, ou 2 événements Stripe)
        // l'a déjà revendiqué, on s'arrête ICI → un SEUL crédit de portefeuille.
        // (Remplace l'ancienne vérification sur un instantané périmé.)
        if (!Payment::claimPaid($ref, $providerRef)) {
            return false;
        }

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
                    $sellerCredit = $amount; // sans affiliation : le vendeur garde 100 %
                }
                \App\Models\Wallet::credit($sellerId, $sellerCredit, $currency, 'sale', $ref);
            }
        } catch (\Throwable $e) {
            // Échec du crédit APRÈS la revendication « payé » : jamais un
            // double-crédit, mais un éventuel SOUS-crédit du vendeur. On
            // JOURNALISE en critique (réconciliation manuelle) au lieu d'avaler
            // l'erreur en silence.
            if (function_exists('log_message')) {
                log_message('critical', 'PaymentSettlement: crédit portefeuille échoué pour ' . $ref . ' — ' . $e->getMessage());
            }
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

    /**
     * Reprise d'argent (clawback) après REMBOURSEMENT ou ANNULATION d'une commande
     * DÉJÀ PAYÉE en ligne. Point UNIQUE, symétrique de confirm() : appelé par le
     * webhook `charge.refunded` (Stripe), la vérification CinetPay « cancelled »,
     * et l'annulation d'une commande payée (vendeur ou acheteur).
     *
     * Sans lui, un remboursement rend l'argent à l'acheteur mais laisse la part
     * vendeur ET la commission de l'apporteur créditées et retirables (perte sèche).
     *
     * IDEMPOTENT : le passage « payé → remboursé » est revendiqué atomiquement en
     * base (Payment::claimRefunded) ; le corps ne s'exécute donc qu'une seule fois.
     *
     * @return bool true si la reprise a été effectuée MAINTENANT
     */
    public static function reverse(array $payment): bool
    {
        $ref = (string) ($payment['public_id'] ?? '');
        if ($ref === '') {
            return false;
        }
        // Verrou d'idempotence : ne reprend que si le paiement était « payé »
        // (donc potentiellement crédité) et bascule en « remboursé » une seule fois.
        if (!Payment::claimRefunded($ref)) {
            return false;
        }

        $sellerId = (int) ($payment['user_id'] ?? 0);
        $orderId  = (int) ($payment['order_id'] ?? 0);
        $kind     = (string) ($payment['kind'] ?? 'boutique');

        try {
            // 1) Reprise du crédit VENDEUR versé sous la référence du paiement.
            if ($sellerId > 0) {
                \App\Models\Wallet::reverseByRef($sellerId, $ref, 'refund');
            }
            // 2) Reprise de la commission d'AFFILIATION versée pour cette commande.
            if ($orderId > 0 && $kind !== 'restaurant' && ($pub = Order::publicIdById($orderId)) !== null) {
                \App\Models\Affiliate::reverseBoutiqueOrder($orderId, $pub);
            }
            // 3) La commande n'est plus « payée » → blocage de l'expédition, cohérence.
            if ($orderId > 0) {
                if ($kind === 'restaurant') {
                    RestaurantOrder::setPaymentStatus($orderId, 'refunded', $ref);
                } else {
                    Order::setPaymentStatus($orderId, 'refunded', $ref);
                }
            }
        } catch (\Throwable $e) {
            if (function_exists('log_message')) {
                log_message('critical', 'PaymentSettlement::reverse — reprise échouée pour ' . $ref . ' — ' . $e->getMessage());
            }
        }

        // Alerte vendeur (best-effort).
        try {
            if ($sellerId > 0) {
                Notification::push(
                    $sellerId,
                    'order_refunded',
                    t('notify.refunded.title'),
                    t('notify.refunded.body', ['ref' => strtoupper(substr($ref, 0, 6))]),
                    $kind === 'restaurant' ? '/restaurant/commandes' : '/vendeur/commandes',
                );
            }
        } catch (\Throwable) {
        }

        return true;
    }
}
