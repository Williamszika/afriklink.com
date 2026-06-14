<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;

/**
 * CinetPay — Mobile Money (Wave, Orange, MTN, Moov…) + cartes pour l'Afrique de
 * l'Ouest/Centrale. L'acheteur choisit son moyen (Orange Money en CI, etc.) sur
 * la page hébergée CinetPay selon son pays.
 *
 * Parcours : createPayment() initialise un paiement (/v2/payment) → on redirige
 * l'acheteur vers payment_url ; la VÉRITÉ arrive par webhook (notify_url) où l'on
 * RE-VÉRIFIE via /v2/payment/check. S'active dès que CINETPAY_API_KEY /
 * CINETPAY_SITE_ID sont fournis (sinon repli automatique sur la simulation).
 */
final class CinetPayProvider implements PaymentProvider
{
    private const API = 'https://api-checkout.cinetpay.com/v2/';

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
        $amount = self::toCinetpayAmount($request->amountCents, $request->currency);
        if ($amount < 100) { // CinetPay : minimum 100 (XOF…)
            throw new PaymentException('cinetpay_amount_invalid');
        }
        $body = [
            'apikey'         => (string) env('CINETPAY_API_KEY'),
            'site_id'        => (string) env('CINETPAY_SITE_ID'),
            'transaction_id' => $request->reference,             // notre public_id = la référence
            'amount'         => $amount,
            'currency'       => strtoupper($request->currency),
            'description'    => mb_substr($request->description !== '' ? $request->description : 'Afriklink', 0, 250),
            'notify_url'     => url('/webhooks/cinetpay'),       // webhook = vérité
            'return_url'     => $request->returnUrl,
            'channels'       => 'ALL',                            // Mobile Money + carte selon le pays
            'lang'           => 'fr',
        ];
        if ($request->customerName !== '') {
            $body['customer_name'] = mb_substr($request->customerName, 0, 100);
        }
        if ($request->customerPhone !== '') {
            $body['customer_phone_number'] = $request->customerPhone;
        }

        $resp = $this->api('payment', $body);
        $url  = (string) ($resp['data']['payment_url'] ?? '');
        if ((string) ($resp['code'] ?? '') !== '201' || $url === '') {
            log_message('error', 'CinetPay init error', ['code' => $resp['code'] ?? '', 'message' => $resp['message'] ?? '']);
            throw new PaymentException('cinetpay_init_failed');
        }
        return new PaymentInitiation($request->reference, $url, PaymentResult::PENDING, (string) ($resp['data']['payment_token'] ?? ''));
    }

    /** Statut RÉEL via /v2/payment/check (appelé par le webhook = vérité). */
    public function verify(string $reference, array $payload = []): PaymentResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('cinetpay_not_configured');
        }
        $resp = $this->api('payment/check', [
            'apikey'         => (string) env('CINETPAY_API_KEY'),
            'site_id'        => (string) env('CINETPAY_SITE_ID'),
            'transaction_id' => $reference,
        ]);
        $data = (array) ($resp['data'] ?? []);
        $cp   = strtoupper((string) ($data['status'] ?? ''));
        $status = match (true) {
            (string) ($resp['code'] ?? '') === '00' && $cp === 'ACCEPTED' => PaymentResult::PAID,
            $cp === 'REFUSED' => PaymentResult::FAILED,
            default           => PaymentResult::PENDING,
        };
        $cur    = strtoupper((string) ($data['currency'] ?? ($payload['currency'] ?? 'XOF')));
        $amount = isset($data['amount']) ? self::fromCinetpayAmount((int) round((float) $data['amount']), $cur) : 0;
        return new PaymentResult($reference, $status, $amount, $cur, (string) ($data['payment_method'] ?? ''));
    }

    /* ---- Montants : CinetPay attend des UNITÉS entières (pas des centimes) -- */

    /** Centimes internes → montant CinetPay ; XOF/XAF… arrondis au multiple de 5. */
    public static function toCinetpayAmount(int $cents, string $currency): int
    {
        $units = intdiv($cents, 100);
        if (in_array(strtoupper($currency), ['XOF', 'XAF', 'CDF', 'GNF'], true)) {
            $units = (int) (ceil($units / 5) * 5); // CinetPay impose des multiples de 5 pour le CFA
        }
        return $units;
    }

    /** Montant CinetPay (unités) → centimes internes. */
    public static function fromCinetpayAmount(int $units, string $currency): int
    {
        return $units * 100;
    }

    /* ---- Appel HTTP JSON à l'API CinetPay ----------------------------- */

    /** @param array<string,scalar> $body @return array<string,mixed> */
    private function api(string $path, array $body): array
    {
        $url     = self::API . ltrim($path, '/');
        $json    = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        $resp = null;
        $code = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 25,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $json,
                'timeout'       => 25,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = 200;
        }

        $data = is_string($resp) ? json_decode($resp, true) : null;
        if (!is_array($data) || $code >= 500) {
            log_message('error', 'CinetPay API error', ['path' => $path, 'code' => $code]);
            throw new PaymentException('cinetpay_api_error');
        }
        return $data;
    }
}
