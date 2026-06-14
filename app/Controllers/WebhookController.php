<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Request;
use App\Services\AuditLog;
use App\Services\Payment\StripeProvider;
use App\Services\PaymentSettlement;

/**
 * Webhooks PSP — la **source de vérité** de l'encaissement. Un paiement n'est
 * jamais confirmé sur le retour navigateur (le client peut fermer l'onglet ou
 * falsifier l'URL), seulement ici, après :
 *   1. vérification de la **signature** (le PSP, pas un tiers, est l'auteur),
 *   2. **idempotence** par identifiant d'événement (pas de double-confirmation),
 *   3. **contrôle du montant** (anti-falsification),
 *   4. confirmation via PaymentSettlement (Payment + commande payés + notif).
 *
 * Route publique SANS csrf ni auth : c'est la signature qui authentifie.
 */
final class WebhookController
{
    /** Endpoint Stripe : corps brut + en-tête de signature. */
    public function stripe(Request $request): void
    {
        $payload = (string) file_get_contents('php://input');
        $sig     = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $status  = self::processStripe($payload, $sig);

        http_response_code($status);
        header('Content-Type: application/json');
        echo $status === 200 ? '{"received":true}' : '{"error":"invalid"}';
    }

    /**
     * Cœur testable du traitement Stripe. Renvoie le code HTTP :
     *   400 = signature/charge invalide (rejet) · 200 = traité ou ignoré.
     * (Toujours 200 une fois la signature validée → Stripe ne boucle pas.)
     */
    public static function processStripe(string $payload, string $sig): int
    {
        // 1. Signature : seul Stripe peut produire un HMAC valide avec notre secret.
        if (!StripeProvider::verifySignature($payload, $sig)) {
            return 400;
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            return 400;
        }
        $eventId = (string) $event['id'];
        $type    = (string) $event['type'];

        // 2. Idempotence : enregistré AVANT traitement → un rejeu est ignoré.
        if (!PaymentEvent::firstTime('stripe', $eventId, $type)) {
            return 200;
        }

        $obj = $event['data']['object'] ?? [];
        $ref = (string) ($obj['client_reference_id'] ?? ($obj['metadata']['reference'] ?? ''));
        if ($ref === '') {
            return 200; // pas de référence interne → rien à faire
        }
        $payment = Payment::findByReference($ref);
        if ($payment === null) {
            return 200;
        }

        try {
            switch ($type) {
                case 'checkout.session.completed':
                case 'payment_intent.succeeded':
                    $paidFlag = (string) ($obj['payment_status'] ?? ($obj['status'] ?? ''));
                    if (!in_array($paidFlag, ['paid', 'succeeded'], true)) {
                        break; // réglé de façon asynchrone / pas encore payé
                    }
                    // 3. Contrôle du montant (anti-falsification).
                    $stripeAmt = (int) ($obj['amount_total'] ?? ($obj['amount_received'] ?? ($obj['amount'] ?? 0)));
                    $cur  = strtoupper((string) ($obj['currency'] ?? $payment['currency']));
                    $ours = StripeProvider::fromStripeAmount($stripeAmt, $cur);
                    if ($stripeAmt > 0 && $ours !== (int) $payment['amount_cents']) {
                        AuditLog::record((int) ($payment['user_id'] ?? 0), 'payment.amount_mismatch', 'payment',
                            (int) $payment['id'], ['expected' => (int) $payment['amount_cents'], 'got' => $ours], null);
                        break; // accepté (200) pour ne pas boucler, mais NON confirmé
                    }
                    // 4. Confirmation (vérité).
                    PaymentSettlement::confirm($payment, (string) ($obj['payment_intent'] ?? ($obj['id'] ?? '')));
                    AuditLog::record((int) ($payment['user_id'] ?? 0), 'payment.paid.webhook', 'payment',
                        (int) $payment['id'], ['event' => $eventId], null);
                    break;

                case 'checkout.session.expired':
                case 'payment_intent.payment_failed':
                    PaymentSettlement::fail($payment, 'failed');
                    break;

                case 'charge.refunded':
                    Payment::setStatus($ref, 'cancelled');
                    break;
            }
        } catch (\Throwable $e) {
            log_message('error', 'stripe webhook handler', ['err' => $e->getMessage()]);
        }

        return 200;
    }
}
