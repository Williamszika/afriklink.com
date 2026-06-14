<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'units'     => $session !== null ? $this->sellableUnits((int) $boutique['id']) : [],
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

    /** Vente rapide en caisse réglée en espèces — décrémente le stock PARTAGÉ. */
    public function sale(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $register = $this->register($boutique);
        $session  = RegisterSession::findOpen((int) $register['id']);
        if ($session === null) {
            flash('error', t('pos.err_no_session'));
            redirect('/vendeur/point-de-vente');
        }
        $cur  = (string) $boutique['currency'];
        $qty  = max(1, (int) input_string('qty', '1'));
        $line = $this->resolveLine($boutique, (string) input_string('unit', ''), $qty);
        if ($line === null) {
            flash('error', t('pos.err_unit'));
            redirect('/vendeur/point-de-vente');
        }
        $total    = $line['qty'] * $line['unit_price_cents'];
        $received = (int) (parse_price_to_cents(trim((string) input_string('received', '')), $cur) ?? 0);
        if ($received < $total) {
            flash('error', t('pos.err_received'));
            redirect('/vendeur/point-de-vente');
        }
        $change   = $received - $total;
        $publicId = Order::createPosSale([
            'boutique_id' => (int) $boutique['id'], 'user_id' => (int) $boutique['user_id'],
            'register_session_id' => (int) $session['id'], 'currency' => $cur,
        ], [$line], ['method' => 'cash', 'amount_cents' => $received, 'change_given_cents' => $change]);
        if ($publicId === null) {
            flash('error', t('pos.err_stock'));
            redirect('/vendeur/point-de-vente');
        }
        AuditLog::record((int) $user['id'], 'pos.sale', 'register_session', (int) $session['id'], ['order' => $publicId, 'total' => $total], $request->ipBinary());
        flash('success', t('pos.sale_done', ['change' => format_price($change, $cur)]));
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

    /** Unités vendables : produit simple OU chaque variante réelle (stock partagé). */
    private function sellableUnits(int $boutiqueId): array
    {
        $units = [];
        foreach (Product::forBoutique($boutiqueId, true) as $p) {
            $variants = ProductVariant::forProduct((int) $p['id']);
            $real = array_values(array_filter($variants, static fn (array $v): bool =>
                trim((string) ($v['label'] ?? '')) !== '' || count($variants) > 1));
            if ($real !== []) {
                foreach ($real as $v) {
                    $units[] = ['id' => (string) $v['public_id'], 'label' => (string) $p['name'] . ' — ' . ($v['label'] ?: '—'),
                        'price' => $v['price_cents'] !== null ? (int) $v['price_cents'] : (int) $p['price_cents'], 'stock' => $v['stock']];
                }
            } else {
                $units[] = ['id' => (string) $p['public_id'], 'label' => (string) $p['name'], 'price' => (int) $p['price_cents'], 'stock' => $p['stock']];
            }
        }
        return $units;
    }

    /** Résout un identifiant public (produit ou variante) en ligne de vente vérifiée. */
    private function resolveLine(array $boutique, string $publicId, int $qty): ?array
    {
        $variant = ProductVariant::findByPublicId($publicId);
        $product = $variant !== null ? Product::findById((int) $variant['product_id']) : Product::findByPublicId($publicId);
        if ($product === null || (int) $product['boutique_id'] !== (int) $boutique['id'] || $product['status'] !== 'active') {
            return null;
        }
        $stock = $variant !== null ? $variant['stock'] : $product['stock'];
        $price = ($variant !== null && $variant['price_cents'] !== null) ? (int) $variant['price_cents'] : (int) $product['price_cents'];
        $qty = max(1, min(999, $qty));
        if ($stock !== null) {
            $stock = (int) $stock;
            if ($stock <= 0) { return null; }
            $qty = min($qty, $stock);
        }
        $label = $variant !== null ? trim((string) ($variant['label'] ?? '')) : '';
        return ['product_id' => (int) $product['id'], 'variant_id' => $variant !== null ? (int) $variant['id'] : null,
            'title' => (string) $product['name'] . ($label !== '' ? ' — ' . $label : ''), 'qty' => $qty, 'unit_price_cents' => $price];
    }
}
