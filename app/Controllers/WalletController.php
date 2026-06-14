<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Wallet;
use App\Request;
use App\Services\AuditLog;
use App\Services\SellerAnalytics;

/**
 * Portefeuille vendeur : solde encaissé par la plateforme, historique, et
 * demande de retrait (dès l'équivalent de 20 000 XOF). Le versement est traité
 * à la main par un admin (back-office /admin/retraits).
 */
final class WalletController
{
    /** Page portefeuille du vendeur. */
    public function index(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $uid  = (int) $user['id'];
        $cur  = Wallet::currencyFor($uid, $this->shopCurrency($uid));
        view('vendeur/wallet', [
            'active'          => 'portefeuille',
            'balance_cents'   => Wallet::balanceCents($uid),
            'currency'        => $cur,
            'threshold_cents' => Wallet::thresholdCents($cur),
            'can_withdraw'    => Wallet::canWithdraw($uid),
            'entries'         => Wallet::entries($uid, 20),
            'withdrawals'     => Wallet::withdrawalsFor($uid),
            // Retrait par défaut (Réglages) pour pré-remplir le formulaire.
            'payout'          => \App\Models\ProProfile::sellerPrefs($uid),
            // Tableau de bord des gains (chiffre d'affaires) par vitrine.
            'gains_currency'  => SellerAnalytics::currency($uid),
            'gains_summary'   => SellerAnalytics::summary($uid),
            'gains_by_day'    => SellerAnalytics::revenueByDay($uid, 14),
            'gains_by_shop'   => SellerAnalytics::byStorefront($uid),
            'page_title'      => t('wallet.title'),
        ] + SellerController::commonData($user));
    }

    /** Demande de retrait de tout le solde disponible. */
    public function withdraw(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        $uid  = (int) $user['id'];
        $method = whitelist((string) input_string('method', 'mobile_money'), ['mobile_money', 'bank'], 'mobile_money');
        $dest   = trim((string) input_string('destination', ''));
        if (mb_strlen($dest) < 4) {
            flash('error', t('wallet.err_destination'));
            redirect('/vendeur/portefeuille');
        }
        $pid = Wallet::requestWithdrawal($uid, $method, $dest);
        if ($pid === null) {
            $cur = Wallet::currencyFor($uid, $this->shopCurrency($uid));
            flash('error', t('wallet.err_threshold', ['min' => format_price(Wallet::thresholdCents($cur), $cur)]));
        } else {
            AuditLog::record($uid, 'wallet.withdrawal_requested', 'withdrawal', null, [], $request->ipBinary());
            flash('success', t('wallet.requested'));
        }
        redirect('/vendeur/portefeuille');
    }

    /** Back-office (staff) : retraits en attente de versement. */
    public function adminIndex(Request $request): void
    {
        view('admin/withdrawals', [
            'list'       => Wallet::pendingWithdrawals(),
            'page_title' => t('wallet.admin_title'),
        ]);
    }

    /** Marque un retrait « versé » (paid) ou « rejeté » (recrédite le solde). */
    public function adminProcess(Request $request): void
    {
        $id     = (int) $request->param('id', '0');
        $action = whitelist((string) input_string('action', ''), ['paid', 'reject'], null);
        if ($action === null || Wallet::findWithdrawal($id) === null) {
            abort(404);
        }
        Wallet::processWithdrawal($id, (int) current_user_id(), $action);
        AuditLog::record((int) current_user_id(), 'wallet.withdrawal_' . $action, 'withdrawal', $id, [], $request->ipBinary());
        flash('success', t($action === 'paid' ? 'wallet.marked_paid' : 'wallet.marked_rejected'));
        redirect('/admin/retraits');
    }

    private function sellerOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return $user;
    }

    private function shopCurrency(int $uid): string
    {
        $b = Boutique::findByUserId($uid);
        return (string) ($b['currency'] ?? 'XOF');
    }
}
