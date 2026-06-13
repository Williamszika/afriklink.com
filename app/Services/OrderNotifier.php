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
}
