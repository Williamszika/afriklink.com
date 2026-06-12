<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Résultat de l'initialisation : où envoyer le client. */
final class PaymentInitiation
{
    public function __construct(
        public readonly string $reference,
        public readonly string $redirectUrl,   // page de paiement du fournisseur
        public readonly string $status = 'pending',
        public readonly string $providerRef = '',
    ) {
    }
}
