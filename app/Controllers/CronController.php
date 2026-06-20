<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AbandonedCart;
use App\Request;
use App\Services\MailService;
use App\Services\Supervision;

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

    /** Agent Supervision : envoie un digest « À surveiller » aux opérateurs si besoin. */
    public function supervision(Request $request): void
    {
        $this->authorize();

        $report = Supervision::report();
        $alerts = $report['alerts'];
        $to     = Supervision::recipients();
        $sent   = 0;

        // On n'envoie que s'il y a quelque chose d'actionnable (pas de bruit).
        if ($alerts !== [] && $to !== []) {
            $html = self::supervisionHtml($report);
            $text = self::supervisionText($report);
            foreach ($to as $email) {
                try {
                    if (MailService::send($email, t('sup.subject', ['n' => count($alerts)]), $html, $text)) {
                        $sent++;
                    }
                } catch (\Throwable) {
                    // best-effort
                }
            }
        }

        json_response(['alerts' => count($alerts), 'recipients' => count($to), 'sent' => $sent]);
    }

    /** @param array{alerts:list<array>,stats:array<string,int>} $report */
    private static function supervisionHtml(array $report): string
    {
        $rows = '';
        foreach ($report['alerts'] as $al) {
            $dot = $al['level'] === 'warn' ? '🟠' : '🔵';
            $rows .= '<tr>'
                . '<td style="padding:9px 0;border-bottom:1px solid #eee">' . $dot . ' ' . e((string) $al['label']) . '</td>'
                . '<td align="right" style="padding:9px 0;border-bottom:1px solid #eee;white-space:nowrap">'
                . '<strong style="color:#103D30">' . (int) $al['count'] . '</strong> · '
                . '<a href="' . e((string) $al['href']) . '" style="color:#B8860B;font-weight:700;text-decoration:none">' . e(t('sup.view')) . '</a></td></tr>';
        }
        $s = $report['stats'];
        $statLine = t('sup.pulse', [
            'orders' => (int) ($s['orders_7d'] ?? 0),
            'shops'  => (int) ($s['boutiques_7d'] ?? 0),
            'subs'   => (int) ($s['subscribers'] ?? 0),
        ]);
        $body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:2px 0 16px;font-size:.97rem">' . $rows . '</table>'
            . '<div class="afk-panel">📊 ' . e($statLine) . '</div>';

        return render_partial('emails/base', [
            'subject'   => t('sup.subject', ['n' => count($report['alerts'])]),
            'preheader' => t('sup.intro'),
            'heading'   => '🛰️ ' . e(t('sup.heading')),
            'intro'     => e(t('sup.intro')),
            'body'      => $body,
            'cta_url'   => url('/admin'),
            'cta_label' => t('sup.cta'),
            'accent'    => 'forest',
        ]);
    }

    /** @param array{alerts:list<array>,stats:array<string,int>} $report */
    private static function supervisionText(array $report): string
    {
        $t = t('sup.heading') . "\n";
        foreach ($report['alerts'] as $al) {
            $t .= '- ' . $al['label'] . ' : ' . (int) $al['count'] . ' (' . $al['href'] . ")\n";
        }
        return $t . url('/admin');
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
