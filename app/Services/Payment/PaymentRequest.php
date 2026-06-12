<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Demande de paiement (montant en centimes, jamais en flottant). */
final class PaymentRequest
{
    public function __construct(
        public readonly string $reference,    // notre référence interne (public_id)
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $returnUrl,    // où revient le client après paiement
        public readonly string $customerName = '',
        public readonly string $customerPhone = '',
    ) {
    }
}
