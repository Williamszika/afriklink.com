<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * PayPal — compte PayPal et cartes via PayPal. Ossature prête : Orders API v2
 * (create order → approve → capture) se branche ici dès que
 * PAYPAL_CLIENT_ID / PAYPAL_SECRET sont fournis (Phase 3).
 */
final class PayPalProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return (string) config('payment.providers.paypal.label', 'PayPal');
    }

    public function isConfigured(): bool
    {
        return env('PAYPAL_CLIENT_ID') !== null && env('PAYPAL_SECRET') !== null;
    }

    public function regions(): array
    {
        return ['africa', 'europe'];
    }

    public function createPayment(PaymentRequest $request): PaymentInitiation
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('paypal_not_configured');
        }
        throw new PaymentException('paypal_not_implemented');
    }

    public function verify(string $reference, array $payload = []): PaymentResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('paypal_not_configured');
        }
        throw new PaymentException('paypal_not_implemented');
    }
}
