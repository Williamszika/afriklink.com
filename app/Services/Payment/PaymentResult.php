<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Statut normalisé d'un paiement, quel que soit le fournisseur. */
final class PaymentResult
{
    public const PENDING   = 'pending';
    public const PAID      = 'paid';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly int $amountCents = 0,
        public readonly string $currency = '',
        public readonly string $providerRef = '',
    ) {
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
