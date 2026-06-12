<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;

/**
 * Fournisseur bac à sable : aucun argent réel. Il envoie le client vers une
 * page interne où l'on simule « payé » ou « échec », ce qui permet de tester
 * tout le parcours (commande → paiement → confirmation) avant d'avoir les
 * comptes CinetPay/Stripe. Le vrai statut est porté par le modèle Payment.
 */
final class SimulationProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'simulation';
    }

    public function label(): string
    {
        return (string) config('payment.providers.simulation.label', 'Simulation');
    }

    public function isConfigured(): bool
    {
        return true; // toujours disponible
    }

    public function regions(): array
    {
        return ['africa', 'europe'];
    }

    public function createPayment(PaymentRequest $request): PaymentInitiation
    {
        return new PaymentInitiation(
            $request->reference,
            url('/paiement/simulation/' . $request->reference),
            PaymentResult::PENDING,
            'SIM-' . strtoupper(substr($request->reference, 0, 8)),
        );
    }

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
}
