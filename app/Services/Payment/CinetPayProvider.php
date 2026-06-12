<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * CinetPay — Mobile Money (Wave, Orange, MTN, Moov…) + cartes pour l'Afrique
 * de l'Ouest. Ossature prête : l'appel réel à l'API CinetPay (initialisation
 * /v2/payment, vérification, webhook) se branche ici dès que les clés
 * CINETPAY_API_KEY / CINETPAY_SITE_ID sont fournies (Phase 3).
 */
final class CinetPayProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'cinetpay';
    }

    public function label(): string
    {
        return (string) config('payment.providers.cinetpay.label', 'CinetPay');
    }

    public function isConfigured(): bool
    {
        return env('CINETPAY_API_KEY') !== null && env('CINETPAY_SITE_ID') !== null;
    }

    public function regions(): array
    {
        return ['africa'];
    }

    public function createPayment(PaymentRequest $request): PaymentInitiation
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('cinetpay_not_configured');
        }
        // TODO Phase 3 : POST https://api-checkout.cinetpay.com/v2/payment
        //   { apikey, site_id, transaction_id: $request->reference, amount,
        //     currency, description, return_url, notify_url } → payment_url.
        throw new PaymentException('cinetpay_not_implemented');
    }

    public function verify(string $reference, array $payload = []): PaymentResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('cinetpay_not_configured');
        }
        // TODO Phase 3 : POST /v2/payment/check { apikey, site_id, transaction_id }.
        throw new PaymentException('cinetpay_not_implemented');
    }
}
