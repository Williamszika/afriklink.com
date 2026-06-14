<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\CashMovement;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Request;
use App\Services\AuditLog;

/**
 * Point de vente (POS) — caisse présentiel du vendeur. Phase B : ouverture d'une
 * session avec fond de caisse, mouvements d'espèces (apport/sortie), clôture avec
 * comptage du tiroir → écart over/under. La vente en caisse vient en Phase C.
 * L'argent reste chez le vendeur ; la plateforme ne fournit que l'outil (plan §3.1).
 */
final class PosController
{
    public function index(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        // Une caisse par défaut suffit pour démarrer (multi-caisses possible ensuite).
        $registers = Register::forBoutique((int) $boutique['id']);
        if ($registers === []) {
            Register::create((int) $boutique['id'], t('pos.default_register'));
            $registers = Register::forBoutique((int) $boutique['id']);
        }
        $register = $registers[0];
        $session  = RegisterSession::findOpen((int) $register['id']);
        $movements = $session !== null ? CashMovement::forSession((int) $session['id']) : [];
        view('pos/index', [
            'active'    => 'pos',
            'boutique'  => $boutique,
            'register'  => $register,
            'session'   => $session,
            'movements' => $movements,
            'expected'  => $session !== null ? RegisterSession::expectedCash($session) : 0,
            'sessions'  => RegisterSession::forRegister((int) $register['id'], 8),
        ] + SellerController::commonData($user));
    }

    public function open(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $register = $this->register($boutique);
        $floatCents = (int) (parse_price_to_cents(trim((string) input_string('opening_float', '0')), (string) $boutique['currency']) ?? 0);
        $publicId = RegisterSession::open((int) $register['id'], (int) $boutique['id'], (int) $user['id'], $floatCents, (string) $boutique['currency']);
        if ($publicId === null) {
            flash('error', t('pos.err_already_open'));
        } else {
            AuditLog::record((int) $user['id'], 'pos.session_open', 'register', (int) $register['id'], ['float' => $floatCents], $request->ipBinary());
            flash('success', t('pos.opened'));
        }
        redirect('/vendeur/point-de-vente');
    }

    public function movement(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $register = $this->register($boutique);
        $session  = RegisterSession::findOpen((int) $register['id']);
        if ($session === null) {
            flash('error', t('pos.err_no_session'));
            redirect('/vendeur/point-de-vente');
        }
        $type   = whitelist((string) input_string('type', ''), ['paid_in', 'paid_out'], null);
        $amount = (int) (parse_price_to_cents(trim((string) input_string('amount', '')), (string) $boutique['currency']) ?? 0);
        $reason = trim((string) input_string('reason', ''));
        if ($type === null || $amount <= 0 || $reason === '') {
            flash('error', t('pos.err_movement'));
            redirect('/vendeur/point-de-vente');
        }
        CashMovement::add((int) $session['id'], $type, $amount, $reason, (int) $user['id']);
        AuditLog::record((int) $user['id'], 'pos.cash_' . $type, 'register_session', (int) $session['id'], ['amount' => $amount], $request->ipBinary());
        flash('success', t('pos.movement_saved'));
        redirect('/vendeur/point-de-vente');
    }

    public function close(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $register = $this->register($boutique);
        $session  = RegisterSession::findOpen((int) $register['id']);
        if ($session === null) {
            flash('error', t('pos.err_no_session'));
            redirect('/vendeur/point-de-vente');
        }
        $counted = (int) (parse_price_to_cents(trim((string) input_string('counted_cash', '0')), (string) $boutique['currency']) ?? 0);
        RegisterSession::close((int) $session['id'], $counted);
        AuditLog::record((int) $user['id'], 'pos.session_close', 'register_session', (int) $session['id'], ['counted' => $counted], $request->ipBinary());
        flash('success', t('pos.closed'));
        redirect('/vendeur/point-de-vente');
    }

    /* ---- Helpers ---- */

    /** @return array{0:array,1:array} [user, boutique] ; redirige sinon. */
    private function sellerShop(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        $boutique = Boutique::findByUserId((int) $user['id']);
        if ($boutique === null) {
            redirect('/boutique/creer');
        }
        return [$user, $boutique];
    }

    private function register(array $boutique): array
    {
        $registers = Register::forBoutique((int) $boutique['id']);
        if ($registers === []) {
            Register::create((int) $boutique['id'], t('pos.default_register'));
            $registers = Register::forBoutique((int) $boutique['id']);
        }
        return $registers[0];
    }
}
