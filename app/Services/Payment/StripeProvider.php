<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Stripe — cartes, Apple Pay, Google Pay (international / Europe). Ossature
 * prête : Checkout Session + webhook + Stripe Connect (reversement vendeur,
 * modèle marketplace) se branchent ici dès que STRIPE_SECRET_KEY est fournie.
 */
final class StripeProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return (string) config('payment.providers.stripe.label', 'Stripe');
    }

    public function isConfigured(): bool
    {
        return env('STRIPE_SECRET_KEY') !== null;
    }

    public function regions(): array
    {
        return ['europe'];
    }

    public function createPayment(PaymentRequest $request): PaymentInitiation
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('stripe_not_configured');
        }
        // TODO Phase 3 : Stripe Checkout Session (mode=payment, line_items,
        //   success_url, cancel_url, payment_intent_data avec application_fee
        //   + transfer_data[destination] pour Connect) → session.url.
        throw new PaymentException('stripe_not_implemented');
    }

    public function verify(string $reference, array $payload = []): PaymentResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('stripe_not_configured');
        }
        // TODO Phase 3 : vérifier la signature du webhook + récupérer la session.
        throw new PaymentException('stripe_not_implemented');
    }
}
