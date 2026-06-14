<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Prévient le vendeur à chaque nouvelle commande : e-mail récapitulatif +
 * message court SMS / WhatsApp (selon NOTIFY_DRIVER). Générique : la boutique
 * comme le restaurant l'appellent avec leurs lignes. L'envoi ne doit JAMAIS
 * faire échouer la commande — chaque canal est isolé et l'appelant enveloppe
 * malgré tout dans un try/catch.
 *
 * @phpstan-type Line array{qty:int,title:string,line_total_cents:int}
 */
final class OrderNotifier
{
    /**
     * @param array $seller  ligne utilisateur du vendeur (email, phone, full_name)
     * @param list<array{qty:int,title:string,line_total_cents:int}> $lines
     */
    public static function sellerNewOrder(
        array $seller,
        string $vitrineName,
        string $orderRef,
        array $lines,
        int $totalCents,
        string $currency,
        string $clientName,
        string $clientPhone,
        string $manageUrl,
    ): void {
        $itemCount = array_sum(array_map(static fn ($l): int => (int) $l['qty'], $lines));
        $totalLabel = format_price($totalCents, $currency);

        // ---- E-mail détaillé ----
        $email = trim((string) ($seller['email'] ?? ''));
        if ($email !== '') {
            try {
                self::email($email, $vitrineName, $orderRef, $lines, $currency, $totalLabel, $clientName, $clientPhone, $manageUrl);
            } catch (\Throwable) {
                // un échec d'e-mail ne bloque rien
            }
        }

        // ---- SMS / WhatsApp court ----
        $phone = Notifier::normalize((string) ($seller['phone'] ?? ''));
        if ($phone !== '') {
            try {
                $text = t('notify.order.sms', [
                    'ref'   => $orderRef,
                    'n'     => $itemCount,
                    'total' => $totalLabel,
                    'shop'  => $vitrineName,
                ]) . ' ' . $manageUrl;
                Notifier::send($phone, $text);
            } catch (\Throwable) {
                // idem côté SMS/WhatsApp
            }
        }
    }

    /**
     * @param list<array{qty:int,title:string,line_total_cents:int}> $lines
     */
    private static function email(
        string $to,
        string $vitrineName,
        string $orderRef,
        array $lines,
        string $currency,
        string $totalLabel,
        string $clientName,
        string $clientPhone,
        string $manageUrl,
    ): void {
        $rows = '';
        foreach ($lines as $l) {
            $rows .= '<tr>'
                . '<td style="padding:4px 10px 4px 0">' . (int) $l['qty'] . '× ' . e((string) $l['title']) . '</td>'
                . '<td style="padding:4px 0;text-align:right;white-space:nowrap"><strong>'
                . e(format_price((int) $l['line_total_cents'], $currency)) . '</strong></td></tr>';
        }
        $client = e($clientName) . ($clientPhone !== '' ? ' · ' . e($clientPhone) : '');

        $html = '<p>' . e(t('notify.order.mail_intro', ['shop' => $vitrineName, 'ref' => $orderRef])) . '</p>'
            . '<table style="border-collapse:collapse;margin:6px 0 10px">' . $rows
            . '<tr><td style="padding:8px 10px 0 0;border-top:1px solid #e5e7eb"><strong>' . e(t('rorder.total')) . '</strong></td>'
            . '<td style="padding:8px 0 0;text-align:right;border-top:1px solid #e5e7eb"><strong>' . e($totalLabel) . '</strong></td></tr>'
            . '</table>'
            . '<p>' . e(t('notify.order.mail_client')) . ' : ' . $client . '</p>'
            . '<p><a href="' . e($manageUrl) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">'
            . e(t('notify.order.mail_cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($manageUrl) . '</p>';

        $text = t('notify.order.mail_intro', ['shop' => $vitrineName, 'ref' => $orderRef]) . "\n";
        foreach ($lines as $l) {
            $text .= (int) $l['qty'] . '× ' . $l['title'] . "\n";
        }
        $text .= t('rorder.total') . ' : ' . $totalLabel . "\n"
            . t('notify.order.mail_client') . ' : ' . $clientName . ($clientPhone !== '' ? ' · ' . $clientPhone : '') . "\n"
            . $manageUrl;

        MailService::send($to, t('notify.order.mail_subject', ['shop' => $vitrineName, 'ref' => $orderRef]), $html, $text);
    }

    /* ---- Côté client -------------------------------------------------- */

    /**
     * Reçu au client : commande bien reçue (en attente de validation du vendeur),
     * avec le DÉTAIL chiffré (lignes, sous-total, livraison, remise, total) + un
     * lien pour suivre l'avancement de la livraison.
     * @param list<array{qty:int,title:string,line_total_cents:int}> $lines
     */
    public static function clientOrderPlaced(
        string $email,
        string $phone,
        string $shopName,
        string $ref,
        array $lines,
        int $subtotalCents,
        int $shippingCents,
        int $discountCents,
        int $totalCents,
        string $currency,
        ?string $term,
        string $url,
    ): void {
        $total = format_price($totalCents, $currency);

        // ---- Détail des articles (tableau HTML) ----
        $rows = '';
        foreach ($lines as $l) {
            $rows .= '<tr>'
                . '<td style="padding:4px 10px 4px 0">' . (int) $l['qty'] . '× ' . e((string) $l['title']) . '</td>'
                . '<td style="padding:4px 0;text-align:right;white-space:nowrap">'
                . e(format_price((int) $l['line_total_cents'], $currency)) . '</td></tr>';
        }
        $sub = static function (string $label, int $cents) use ($currency): string {
            return '<tr><td style="padding:2px 10px 2px 0;color:#555">' . e($label) . '</td>'
                . '<td style="padding:2px 0;text-align:right;color:#555">' . e(format_price($cents, $currency)) . '</td></tr>';
        };
        $breakdown = $sub(t('caisse.subtotal'), $subtotalCents);
        if ($shippingCents > 0) {
            $breakdown .= $sub(t('caisse.shipping'), $shippingCents);
        }
        if ($discountCents > 0) {
            $breakdown .= '<tr><td style="padding:2px 10px 2px 0;color:#0b7a4b">' . e(t('order.receipt.discount'))
                . '</td><td style="padding:2px 0;text-align:right;color:#0b7a4b">−' . e(format_price($discountCents, $currency)) . '</td></tr>';
        }

        $termLine = $term ? '<p>' . e(t('shop.f.payment_terms')) . ' : <strong>' . e(t('shop.payterm.' . $term)) . '</strong></p>' : '';

        $html = '<p>' . e(t('notify.client.receipt_intro', ['shop' => $shopName, 'ref' => $ref])) . '</p>'
            . '<table style="border-collapse:collapse;margin:6px 0 4px;min-width:260px">' . $rows
            . '<tr><td colspan="2" style="border-top:1px solid #e5e7eb;padding-top:6px"></td></tr>'
            . $breakdown
            . '<tr><td style="padding:6px 10px 0 0;border-top:1px solid #e5e7eb"><strong>' . e(t('rorder.total')) . '</strong></td>'
            . '<td style="padding:6px 0 0;text-align:right;border-top:1px solid #e5e7eb"><strong>' . e($total) . '</strong></td></tr>'
            . '</table>'
            . $termLine
            . '<p><a href="' . e($url) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">'
            . e(t('notify.client.track_order_cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($url) . '</p>';

        $text = t('notify.client.receipt_intro', ['shop' => $shopName, 'ref' => $ref]) . "\n";
        foreach ($lines as $l) {
            $text .= (int) $l['qty'] . '× ' . $l['title'] . ' — ' . format_price((int) $l['line_total_cents'], $currency) . "\n";
        }
        $text .= t('rorder.total') . ' : ' . $total . "\n" . $url;
        $sms = t('notify.client.placed_sms', ['ref' => $ref, 'shop' => $shopName]) . ' ' . $url;
        self::client($email, $phone, t('notify.client.receipt_subject', ['ref' => $ref]), $html, $text, $sms);
    }

    /**
     * Le vendeur a confirmé : on invite le client à régler (paiement avant / acompte)
     * ou on lui rappelle que ce sera à la livraison. @param array $order ligne commande
     */
    public static function clientOrderConfirmed(array $order, string $shopName, string $url): void
    {
        $email = trim((string) ($order['client_email'] ?? ''));
        $phone = (string) ($order['client_phone'] ?? '');
        $ref   = strtoupper(substr((string) ($order['public_id'] ?? ''), 0, 6));
        $cur   = (string) ($order['currency'] ?? 'EUR');
        $due   = \App\Models\Order::amountDue($order);

        if ($due > 0) {
            $isDeposit = (string) ($order['payment_term'] ?? '') === 'deposit';
            $amount = format_price($due, $cur);
            $payLine = $isDeposit
                ? t('notify.client.pay_deposit_line', ['amount' => $amount, 'rest' => format_price(\App\Models\Order::restDue($order), $cur)])
                : t('notify.client.pay_line', ['amount' => $amount]);
            $html = '<p>' . e(t('notify.client.confirmed_intro', ['shop' => $shopName, 'ref' => $ref])) . '</p>'
                . '<p>' . e($payLine) . '</p>'
                . '<p><a href="' . e($url) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">'
                . e(t('pay.pay_now')) . '</a></p>'
                . '<p style="color:#666;font-size:13px">' . e($url) . '</p>';
            $text = t('notify.client.confirmed_intro', ['shop' => $shopName, 'ref' => $ref]) . "\n" . $payLine . "\n" . $url;
            $sms = t('notify.client.pay_sms', ['ref' => $ref, 'amount' => $amount]) . ' ' . $url;
            self::client($email, $phone, t('notify.client.pay_subject', ['ref' => $ref]), $html, $text, $sms);
        } else {
            $html = '<p>' . e(t('notify.client.confirmed_intro', ['shop' => $shopName, 'ref' => $ref])) . '</p>'
                . '<p>' . e(t('notify.client.cod_line')) . '</p>'
                . '<p style="color:#666;font-size:13px">' . e($url) . '</p>';
            $text = t('notify.client.confirmed_intro', ['shop' => $shopName, 'ref' => $ref]) . "\n" . t('notify.client.cod_line') . "\n" . $url;
            $sms = t('notify.client.cod_sms', ['ref' => $ref, 'shop' => $shopName]) . ' ' . $url;
            self::client($email, $phone, t('notify.client.confirmed_subject', ['ref' => $ref]), $html, $text, $sms);
        }
    }

    /** Le vendeur a EXPÉDIÉ la commande : on invite le client à la suivre. */
    public static function clientOrderShipped(array $order, string $shopName, string $url): void
    {
        $ref = self::ref($order);
        // Suivi transporteur (facultatif) : transporteur + n° + lien cliquable.
        $carrier  = trim((string) ($order['carrier'] ?? ''));
        $tracking = trim((string) ($order['tracking_number'] ?? ''));
        $trackUrl = trim((string) ($order['tracking_url'] ?? ''));
        $trackLine = '';
        if ($tracking !== '') {
            $cl = $carrier !== '' ? carrier_label($carrier) : '';
            $trackLine = '<p>' . e(t('order.track.tracking')) . ' : <strong>' . e($tracking) . '</strong>'
                . ($cl !== '' ? ' · ' . e($cl) : '') . '</p>';
        }
        // Le bouton « Suivre le colis » pointe vers le transporteur si on a un
        // lien, sinon vers la page de suivi de la commande.
        $cta = $trackUrl !== '' ? $trackUrl : $url;
        $html = '<p>' . e(t('notify.client.shipped_intro', ['shop' => $shopName, 'ref' => $ref])) . '</p>'
            . $trackLine
            . '<p><a href="' . e($cta) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">'
            . e(t('notify.client.track_cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($url) . '</p>';
        $text = t('notify.client.shipped_intro', ['shop' => $shopName, 'ref' => $ref]) . "\n"
            . ($tracking !== '' ? t('order.track.tracking') . ' : ' . $tracking . "\n" : '')
            . ($trackUrl !== '' ? $trackUrl . "\n" : '') . $url;
        $sms  = t('notify.client.shipped_sms', ['ref' => $ref, 'shop' => $shopName]) . ' ' . $cta;
        self::client(
            trim((string) ($order['client_email'] ?? '')),
            (string) ($order['client_phone'] ?? ''),
            t('notify.client.shipped_subject', ['ref' => $ref]),
            $html,
            $text,
            $sms,
        );
        self::clientInApp($order, t('notify.client.shipped_subject', ['ref' => $ref]), $shopName);
    }

    /** Le vendeur a LIVRÉ la commande : remerciement + invitation à laisser un avis. */
    public static function clientOrderDelivered(array $order, string $shopName, string $url): void
    {
        $ref = self::ref($order);
        $html = '<p>' . e(t('notify.client.delivered_intro', ['shop' => $shopName, 'ref' => $ref])) . '</p>'
            . '<p>' . e(t('notify.client.delivered_review')) . '</p>'
            . '<p style="color:#666;font-size:13px">' . e($url) . '</p>';
        $text = t('notify.client.delivered_intro', ['shop' => $shopName, 'ref' => $ref]) . "\n"
            . t('notify.client.delivered_review') . "\n" . $url;
        $sms  = t('notify.client.delivered_sms', ['ref' => $ref]) . ' ' . $url;
        self::client(
            trim((string) ($order['client_email'] ?? '')),
            (string) ($order['client_phone'] ?? ''),
            t('notify.client.delivered_subject', ['ref' => $ref]),
            $html,
            $text,
            $sms,
        );
        self::clientInApp($order, t('notify.client.delivered_subject', ['ref' => $ref]), $shopName);
    }

    /** Réf. courte et lisible d'une commande (les 6 premiers du public_id). */
    private static function ref(array $order): string
    {
        return strtoupper(substr((string) ($order['public_id'] ?? ''), 0, 6));
    }

    /**
     * Notification IN-APP côté client — seulement si l'acheteur a un compte
     * (e-mail de la commande reconnu). Les invités ne reçoivent que e-mail/SMS.
     * Best-effort : ne lève jamais.
     */
    private static function clientInApp(array $order, string $title, string $shopName): void
    {
        try {
            $email = trim((string) ($order['client_email'] ?? ''));
            if ($email === '') {
                return;
            }
            $buyer = \App\Models\User::findByEmail($email);
            if ($buyer === null) {
                return;
            }
            \App\Models\Notification::push(
                (int) $buyer['id'],
                'order_status',
                $title,
                $shopName,
                '/boutique/commande/' . (string) ($order['public_id'] ?? ''),
            );
        } catch (\Throwable) {
        }
    }

    /** Envoi au client : e-mail (si fourni) + SMS/WhatsApp (si téléphone). Best-effort. */
    private static function client(string $email, string $phone, string $subject, string $html, string $text, string $sms): void
    {
        if ($email !== '') {
            try {
                MailService::send($email, $subject, $html, $text);
            } catch (\Throwable) {
            }
        }
        $p = Notifier::normalize($phone);
        if ($p !== '') {
            try {
                Notifier::send($p, $sms);
            } catch (\Throwable) {
            }
        }
    }
}
