<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Contrat commun à tous les fournisseurs d'encaissement (CinetPay, Stripe,
 * PayPal, simulation…). Le reste de l'application ne parle qu'à cette
 * interface : brancher un nouveau fournisseur = écrire une classe, rien
 * d'autre à changer.
 */
interface PaymentProvider
{
    /** Identifiant court ('simulation', 'cinetpay', 'stripe', 'paypal'). */
    public function key(): string;

    /** Nom affiché au vendeur. */
    public function label(): string;

    /** Les clés API sont présentes → fournisseur utilisable. */
    public function isConfigured(): bool;

    /** Régions d'usage indicatives. @return list<string> */
    public function regions(): array;

    /**
     * Initialise un paiement et indique où envoyer le client (page du
     * fournisseur, ou page de simulation interne).
     * @throws PaymentException si non configuré ou erreur fournisseur.
     */
    public function createPayment(PaymentRequest $request): PaymentInitiation;

    /**
     * Vérifie le statut réel d'un paiement (retour client / webhook).
     * @param array<string,mixed> $payload données reçues du fournisseur
     * @throws PaymentException
     */
    public function verify(string $reference, array $payload = []): PaymentResult;
}
