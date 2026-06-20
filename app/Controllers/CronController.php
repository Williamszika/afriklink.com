<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AbandonedCart;
use App\Request;
use App\Services\MailService;

/**
 * Tâches planifiées (déclenchées par Vercel Cron ou un planificateur externe).
 * Endpoint protégé par CRON_SECRET : tant que la variable n'est pas définie,
 * l'endpoint est désactivé (404) — jamais d'exécution ouverte au public.
 */
final class CronController
{
    /** Relance des paniers abandonnés (acheteurs connectés inactifs). */
    public function abandonedCart(Request $request): void
    {
        $this->authorize();

        $due  = AbandonedCart::due(
            (int) ($_ENV['CART_REMIND_AFTER_MIN'] ?? 120),
            (int) ($_ENV['CART_REMIND_MAX_AGE_H'] ?? 336),
            200
        );
        $sent = 0;
        foreach ($due as $row) {
            $resolved = AbandonedCart::resolveItems((string) $row['items_json']);
            // Tous les produits ont disparu (retirés/épuisés) : on ne relance pas.
            if ($resolved['count'] === 0) {
                AbandonedCart::markReminded((int) $row['id']);
                continue;
            }
            try {
                if (MailService::send(
                    (string) $row['email'],
                    t('cart.remind.subject'),
                    self::recoveryHtml($resolved, (string) $row['token']),
                    self::recoveryText($resolved)
                )) {
                    $sent++;
                }
            } catch (\Throwable) {
                // best-effort
            }
            AbandonedCart::markReminded((int) $row['id']);
        }

        json_response(['checked' => count($due), 'sent' => $sent]);
    }

    private function authorize(): void
    {
        $secret = trim((string) ($_ENV['CRON_SECRET'] ?? ''));
        $key    = (string) (input_string('key', '') ?? '');
        $auth   = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $bearer = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        $given  = $key !== '' ? $key : $bearer;
        if ($secret === '' || $given === '' || !hash_equals($secret, $given)) {
            abort(404);
        }
    }

    /** @param array{lines:list<array>,total_cents:int,currency:string,count:int} $cart */
    private static function recoveryHtml(array $cart, string $token): string
    {
        $rows = '';
        foreach ($cart['lines'] as $l) {
            $rows .= '<tr><td style="padding:7px 0;border-bottom:1px solid #eee">' . (int) $l['qty'] . '× ' . e((string) $l['name'])
                . '</td><td align="right" style="padding:7px 0;border-bottom:1px solid #eee;white-space:nowrap">'
                . e(format_price((int) $l['price_cents'] * (int) $l['qty'], (string) $l['currency'])) . '</td></tr>';
        }
        $body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:2px 0 14px;font-size:.95rem">' . $rows
            . '<tr><td style="padding:8px 0 0;border-top:2px solid #E5A02E;font-weight:800;color:#103D30">' . e(t('rorder.total')) . '</td>'
            . '<td align="right" style="padding:8px 0 0;border-top:2px solid #E5A02E;font-weight:800;color:#103D30">' . e(format_price((int) $cart['total_cents'], (string) $cart['currency'])) . '</td></tr></table>';

        return render_partial('emails/base', [
            'subject'         => t('cart.remind.subject'),
            'preheader'       => t('cart.remind.intro'),
            'heading'         => '🛒 ' . e(t('cart.remind.heading')),
            'intro'           => e(t('cart.remind.intro')),
            'body'            => $body,
            'cta_url'         => url('/panier'),
            'cta_label'       => t('cart.remind.cta'),
            'accent'          => 'gold',
            'unsubscribe_url' => AbandonedCart::optoutUrl($token),
            'unsub_text'      => t('cart.remind.unsub_pre'),
        ]);
    }

    /** @param array{lines:list<array>,total_cents:int,currency:string} $cart */
    private static function recoveryText(array $cart): string
    {
        $t = t('cart.remind.intro') . "\n";
        foreach ($cart['lines'] as $l) {
            $t .= (int) $l['qty'] . '× ' . $l['name'] . ' — ' . format_price((int) $l['price_cents'] * (int) $l['qty'], (string) $l['currency']) . "\n";
        }
        return $t . t('rorder.total') . ' : ' . format_price((int) $cart['total_cents'], (string) $cart['currency']) . "\n" . url('/panier');
    }
}
