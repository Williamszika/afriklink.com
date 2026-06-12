<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Payment;
use App\Request;
use App\Services\AuditLog;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentProviders;
use App\Services\Payment\PaymentRequest;
use App\Services\Payment\PaymentResult;

/**
 * Encaissement en ligne — ossature. Le parcours est identique quel que soit
 * le fournisseur : créer un paiement → rediriger le client vers la page de
 * paiement → vérifier au retour. Tant que CinetPay/Stripe n'ont pas leurs
 * clés, le fournisseur « simulation » permet de tester tout le flux.
 */
final class PaymentController
{
    /** Page vendeur : statut des fournisseurs + bouton de test. */
    public function tester(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        view('paiement/tester', [
            'active'    => 'gains',
            'boutique'  => $boutique,
            'providers' => PaymentProviders::all(),
            'chosen'    => (string) ($boutique['payment_provider'] ?? config('payment.default', 'simulation')),
        ] + SellerController::commonData($user));
    }

    /** Démarre un paiement (de test pour l'instant) et redirige vers le fournisseur. */
    public function start(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }

        $cur = (string) $boutique['currency'];
        $amount = parse_price_to_cents((string) input_string('amount', '10'), $cur) ?? 1000;
        $amount = max(1, min($amount, 100_000_00)); // garde-fou

        $provider = PaymentProviders::resolve($boutique['payment_provider'] ?? null);

        $ref = Payment::create([
            'boutique_id' => (int) $boutique['id'],
            'user_id'     => (int) $user['id'],
            'provider'    => $provider->key(),
            'amount_cents'=> $amount,
            'currency'    => $cur,
            'description' => t('pay.test_desc', ['shop' => (string) $boutique['name']]),
        ]);

        try {
            $init = $provider->createPayment(new PaymentRequest(
                reference: $ref,
                amountCents: $amount,
                currency: $cur,
                description: t('pay.test_desc', ['shop' => (string) $boutique['name']]),
                returnUrl: url('/paiement/retour/' . $ref),
                customerName: (string) ($user['full_name'] ?? ''),
            ));
        } catch (PaymentException $e) {
            Payment::setStatus($ref, PaymentResult::FAILED);
            flash('error', t('pay.provider_unavailable', ['provider' => $provider->label()]));
            redirect('/paiement/tester');
        }

        if ($init->providerRef !== '') {
            Payment::setStatus($ref, PaymentResult::PENDING, $init->providerRef);
        }
        redirect($init->redirectUrl); // redirect() gère aussi les URL absolues (PSP réel)
    }

    /** Page de paiement « simulée » (tient lieu de la page du vrai PSP). */
    public function simulation(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $payment = $this->ownPayment($request, (int) $user['id']);
        view('paiement/simulation', [
            'payment' => $payment,
            'active'  => 'gains',
        ] + SellerController::commonData($user));
    }

    /** Le client « paie » ou « échoue » sur la page de simulation. */
    public function simulationResult(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $payment = $this->ownPayment($request, (int) $user['id']);
        $outcome = whitelist((string) input_string('outcome', ''), ['pay', 'fail'], 'fail');
        Payment::setStatus(
            (string) $payment['public_id'],
            $outcome === 'pay' ? PaymentResult::PAID : PaymentResult::FAILED,
        );
        redirect('/paiement/retour/' . $payment['public_id']);
    }

    /** Retour après paiement : on vérifie le statut auprès du fournisseur. */
    public function result(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $payment = $this->ownPayment($request, (int) $user['id']);
        $provider = PaymentProviders::resolve((string) $payment['provider']);
        try {
            $res = $provider->verify((string) $payment['public_id']);
        } catch (PaymentException) {
            $res = new PaymentResult((string) $payment['public_id'], PaymentResult::FAILED);
        }
        AuditLog::record((int) $user['id'], 'payment.' . $res->status, 'payment', (int) $payment['id'], [], $request->ipBinary());
        view('paiement/retour', [
            'payment' => Payment::findByReference((string) $payment['public_id']),
            'result'  => $res,
            'active'  => 'gains',
        ] + SellerController::commonData($user));
    }

    /** Récupère un paiement appartenant au vendeur connecté, sinon 404. */
    private function ownPayment(Request $request, int $userId): array
    {
        $p = Payment::findByReference((string) $request->param('ref', ''));
        if ($p === null || (int) $p['user_id'] !== $userId) {
            abort(404);
        }
        return $p;
    }

    private function sellerOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return $user;
    }
}
