<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Order;
use App\Models\Product;
use App\Request;
use App\Services\AuditLog;
use App\Services\OrderNotifier;

/**
 * Commandes côté vendeur : liste filtrée par statut, enregistrement manuel
 * (ventes WhatsApp / téléphone / sur place) et traitement (confirmer →
 * expédier → livrée, ou annuler). Le checkout public (panier) viendra créer
 * des commandes source = 'online' dans le même circuit.
 */
final class OrderController
{
    /** Filtres d'URL (français) → statut en base. */
    private const FILTERS = [
        'a_traiter'  => 'new',
        'confirmees' => 'confirmed',
        'expediees'  => 'shipped',
        'livrees'    => 'delivered',
        'annulees'   => 'cancelled',
        'toutes'     => null,
    ];

    public function index(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $common = ['active' => 'commandes'] + SellerController::commonData($user);

        if ($boutique === null) {
            view('vendeur/commandes', $common + [
                'boutique' => null, 'orders' => [], 'counts' => [],
                'filter' => 'a_traiter', 'products' => [], 'items_by_order' => [],
            ]);
            return;
        }

        $filter = whitelist((string) input_string('filtre', 'a_traiter'), array_keys(self::FILTERS), 'a_traiter');
        $orders = Order::forBoutique((int) $boutique['id'], self::FILTERS[$filter]);
        // Lignes détaillées des commandes en ligne (panier multi-produits).
        $itemsByOrder = [];
        foreach ($orders as $o) {
            if (($o['source'] ?? '') === 'online') {
                $itemsByOrder[(int) $o['id']] = Order::items((int) $o['id']);
            }
        }
        view('vendeur/commandes', $common + [
            'boutique'       => $boutique,
            'orders'         => $orders,
            'counts'         => Order::countFor((int) $boutique['id']),
            'filter'         => $filter,
            'products'       => Product::forBoutique((int) $boutique['id']),
            'items_by_order' => $itemsByOrder,
        ]);
    }

    /** Enregistrement manuel d'une commande. */
    public function store(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        if ($boutique === null) {
            redirect('/boutique/creer');
        }

        $errors = [];

        // Produit : doit appartenir à la boutique.
        $product = Product::findByPublicId((string) input_string('product', ''));
        if ($product === null || (int) $product['boutique_id'] !== (int) $boutique['id']) {
            $errors['product'] = t('validation.required');
        }

        $qty = (int) (input_string('qty', '1') ?? '1');
        if ($qty < 1 || $qty > 999) {
            $errors['qty'] = t('order.err_qty');
        }

        $clientName = trim((string) input_string('client_name', ''));
        if (mb_strlen($clientName) < 2 || mb_strlen($clientName) > 80) {
            $errors['client_name'] = t('order.err_client');
        }

        $clientPhone = trim((string) input_string('client_phone', ''));
        if ($clientPhone !== '' && !preg_match('/^\+?[0-9 .\-]{6,22}$/', $clientPhone)) {
            $errors['client_phone'] = t('order.err_phone');
        }

        $note = trim((string) input_string('note', ''));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        // Total : prix catalogue × quantité, ou montant négocié si renseigné.
        $currency = (string) $boutique['currency'];
        $totalCents = null;
        $negotiated = trim((string) input_string('total', ''));
        if ($negotiated !== '') {
            $totalCents = parse_price_to_cents($negotiated, $currency);
            if ($totalCents === null) {
                $errors['total'] = t('validation.price_invalid');
            }
        }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/vendeur/commandes');
        }

        $unit = (int) $product['price_cents'];
        Order::create([
            'boutique_id'      => (int) $boutique['id'],
            'user_id'          => (int) $user['id'],
            'product_id'       => (int) $product['id'],
            'product_name'     => (string) $product['name'],
            'unit_price_cents' => $unit,
            'qty'              => $qty,
            'total_cents'      => $totalCents ?? $unit * $qty,
            'currency'         => $currency,
            'client_name'      => $clientName,
            'client_phone'     => $clientPhone !== '' ? $clientPhone : null,
            'note'             => $note !== '' ? $note : null,
            'source'           => 'manual',
        ]);
        AuditLog::record((int) $user['id'], 'order.recorded', 'boutique', (int) $boutique['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('order.recorded_flash'));
        redirect('/vendeur/commandes?filtre=a_traiter');
    }

    /** Traitement : confirmer / expédier / livrer / annuler. */
    public function setStatus(Request $request): void
    {
        [$user, $boutique] = $this->sellerShop();
        $order = Order::findByPublicId((string) $request->param('oid', ''));
        if ($boutique === null || $order === null || (int) $order['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $action = whitelist((string) input_string('action', ''), ['confirm', 'ship', 'deliver', 'cancel'], null);
        if ($action === null) {
            abort(404);
        }
        // Intégrité « payer avant livraison » : on n'EXPÉDIE ni ne LIVRE pas une
        // commande EN LIGNE dont le règlement en ligne DÛ (totalité pour
        // before_delivery, acompte pour deposit) n'a pas été encaissé. Sans ce
        // garde-fou, la condition de paiement serait contournable côté vendeur.
        // Les commandes « à la livraison » (montant en ligne dû = 0) ne sont pas
        // concernées et restent expédiables normalement.
        if (in_array($action, ['ship', 'deliver'], true)
            && (string) ($order['source'] ?? '') === 'online'
            && Order::amountDue($order) > 0
            && (string) ($order['payment_status'] ?? 'unpaid') !== 'paid') {
            flash('error', t('order.must_be_paid'));
            $back = whitelist((string) input_string('retour', 'a_traiter'), array_keys(self::FILTERS), 'a_traiter');
            redirect('/vendeur/commandes?filtre=' . $back);
        }
        $to = Order::applyAction((int) $order['id'], (string) $order['status'], $action);
        if ($to === null) {
            flash('error', t('order.bad_transition'));
        } else {
            AuditLog::record((int) $user['id'], 'order.' . $action, 'order', (int) $order['id'], [], $request->ipBinary());
            flash('success', t('order.status_flash', ['status' => t('order.status.' . $to)]));
            // Expédition : le vendeur peut joindre un transporteur + un numéro de
            // suivi (facultatifs). On en déduit le lien de suivi cliquable, puis on
            // recharge la commande pour que la notification client le porte.
            if ($action === 'ship') {
                $carriers = array_keys((array) config('delivery.carriers', []));
                $carrier  = whitelist((string) input_string('carrier', ''), $carriers, null);
                $tracking = trim(mb_substr((string) preg_replace('/[^A-Za-z0-9\- ]/', '', (string) input_string('tracking_number', '')), 0, 64));
                Order::setShipment(
                    (int) $order['id'],
                    $carrier,
                    $tracking !== '' ? $tracking : null,
                    carrier_tracking_url($carrier, $tracking),
                );
                $order = Order::findByPublicId((string) $order['public_id']) ?? $order;
            }
            // Commande EN LIGNE : on tient le client informé à chaque étape
            // (confirmée → expédiée → livrée), par e-mail + SMS/WhatsApp, et en
            // in-app s'il a un compte. Best-effort : ne bloque jamais le vendeur.
            if ((string) $order['source'] === 'online') {
                $clientUrl = url('/boutique/commande/' . $order['public_id']);
                $shopName  = (string) $boutique['name'];
                try {
                    if ($action === 'confirm' && (string) ($order['payment_status'] ?? 'unpaid') !== 'paid') {
                        OrderNotifier::clientOrderConfirmed($order, $shopName, $clientUrl);
                    } elseif ($action === 'ship') {
                        OrderNotifier::clientOrderShipped($order, $shopName, $clientUrl);
                    } elseif ($action === 'deliver') {
                        OrderNotifier::clientOrderDelivered($order, $shopName, $clientUrl);
                    }
                } catch (\Throwable) {
                    // notification best-effort
                }
                // Livraison : on incite l'acheteur (s'il a un compte) à partager un
                // avis accompagné d'une photo, depuis sa page de commande.
                if ($action === 'deliver' && (int) ($order['user_id'] ?? 0) > 0) {
                    try {
                        \App\Models\Notification::push(
                            (int) $order['user_id'],
                            'review_invite',
                            t('notif.review_invite_title'),
                            t('notif.review_invite_body', ['shop' => $shopName]),
                            $clientUrl
                        );
                    } catch (\Throwable) {
                        // best-effort
                    }
                }
            }
        }
        $back = whitelist((string) input_string('retour', 'a_traiter'), array_keys(self::FILTERS), 'a_traiter');
        redirect('/vendeur/commandes?filtre=' . $back);
    }

    /** @return array{0: array, 1: ?array} vendeur connecté + sa boutique (ou null) */
    private function sellerShop(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return [$user, Boutique::findByUserId((int) $user['id'])];
    }
}
