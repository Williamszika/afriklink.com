<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;

/**
 * Stripe — cartes / Apple Pay / Google Pay (Europe / international).
 *
 * Parcours : createPayment() ouvre une **Checkout Session** (la carte ne touche
 * jamais notre serveur — PCI) ; le client paie chez Stripe ; la **vérité** arrive
 * par **webhook signé** (WebhookController), jamais par le retour navigateur.
 * verify() lit donc NOTRE enregistrement, mis à jour par le webhook.
 *
 * Reversement vendeur (Stripe Connect : application_fee_amount + transfer_data)
 * = étape suivante — nécessite un identifiant de compte connecté par boutique.
 */
final class StripeProvider implements PaymentProvider
{
    private const API = 'https://api.stripe.com/v1/';

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return (string) config('payment.providers.stripe.label', 'Stripe');
    }

    /** Utilisable seulement si la clé secrète ET le secret de webhook sont fournis. */
    public function isConfigured(): bool
    {
        return env('STRIPE_SECRET_KEY') !== null && env('STRIPE_WEBHOOK_SECRET') !== null;
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
        $amount = self::toStripeAmount($request->amountCents, $request->currency);
        if ($amount < 1) {
            throw new PaymentException('stripe_amount_invalid');
        }
        $sep = str_contains($request->returnUrl, '?') ? '&' : '?';
        $params = [
            'mode'                => 'payment',
            'success_url'         => $request->returnUrl . $sep . 'pp=stripe',
            'cancel_url'          => $request->returnUrl . $sep . 'pp=stripe&cancel=1',
            'client_reference_id' => $request->reference,
            'line_items[0][quantity]'                          => 1,
            'line_items[0][price_data][currency]'              => strtolower($request->currency),
            'line_items[0][price_data][unit_amount]'           => $amount,
            'line_items[0][price_data][product_data][name]'    => mb_substr($request->description ?: 'Afriklink', 0, 250),
            'metadata[reference]'                              => $request->reference,
            'payment_intent_data[metadata][reference]'         => $request->reference,
        ];
        // Clé d'idempotence = notre référence : rejouer createPayment ne crée pas
        // deux sessions (anti double-paiement à la création).
        $session = $this->api('checkout/sessions', $params, $request->reference);
        $url = (string) ($session['url'] ?? '');
        if ($url === '') {
            throw new PaymentException('stripe_no_url');
        }
        return new PaymentInitiation($request->reference, $url, PaymentResult::PENDING, (string) ($session['id'] ?? ''));
    }

    /** Statut autoritatif = NOTRE enregistrement (écrit par le webhook signé). */
    public function verify(string $reference, array $payload = []): PaymentResult
    {
        $p = Payment::findByReference($reference);
        if ($p === null) {
            return new PaymentResult($reference, PaymentResult::FAILED);
        }
        return new PaymentResult(
            $reference,
            (string) $p['status'],
            (int) $p['amount_cents'],
            (string) $p['currency'],
            (string) ($p['provider_ref'] ?? ''),
        );
    }

    /* ---- Webhook : la signature fait foi ------------------------------ */

    /**
     * Vérifie la signature d'un webhook Stripe (en-tête `Stripe-Signature`).
     * Schéma officiel : `t=<ts>,v1=<hmac>` ; signature = HMAC-SHA256 de
     * `"{t}.{payload}"` avec le secret de webhook ; comparaison en temps
     * constant ; tolérance temporelle (anti-rejeu) de 5 min par défaut.
     */
    public static function verifySignature(string $payload, string $sigHeader, ?string $secret = null, int $tolerance = 300): bool
    {
        $secret = $secret ?? (string) (env('STRIPE_WEBHOOK_SECRET', '') ?? '');
        if ($secret === '' || $sigHeader === '') {
            return false;
        }
        $ts = null;
        $v1 = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $ts = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $v1[] = $kv[1];
            }
        }
        if ($ts === null || !ctype_digit($ts) || $v1 === []) {
            return false;
        }
        if (abs(time() - (int) $ts) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        foreach ($v1 as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /* ---- Montants : Stripe attend l'unité « zéro décimale » telle quelle - */

    /** Centimes internes → montant Stripe (XOF/JPY… = pas de ×100). */
    public static function toStripeAmount(int $cents, string $currency): int
    {
        return currency_is_integer($currency) ? intdiv($cents, 100) : $cents;
    }

    /** Montant Stripe → centimes internes (inverse). */
    public static function fromStripeAmount(int $amount, string $currency): int
    {
        return currency_is_integer($currency) ? $amount * 100 : $amount;
    }

    /* ---- Appel HTTP Stripe (form-encoded) ----------------------------- */

    /** @param array<string,scalar> $params @return array<string,mixed> */
    private function api(string $path, array $params, string $idempotencyKey = ''): array
    {
        $secret = (string) env('STRIPE_SECRET_KEY');
        $url    = self::API . ltrim($path, '/');
        $body   = http_build_query($params);
        $headers = [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $resp = null;
        $code = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 20,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = 200;
        }

        $data = is_string($resp) ? json_decode($resp, true) : null;
        if (!is_array($data) || $code >= 400) {
            log_message('error', 'Stripe API error', ['path' => $path, 'code' => $code]);
            throw new PaymentException('stripe_api_error');
        }
        return $data;
    }
}
