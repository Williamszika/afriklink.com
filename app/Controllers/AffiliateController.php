<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Boutique;
use App\Models\Product;
use App\Models\Wallet;
use App\Request;

/**
 * Affiliation : lien intelligent « /r/{code} » (clic + cookie 30 j + redirection
 * interne), hub universel ouvert à TOUT membre (lien perso, gains, annuaire des
 * boutiques participantes) et configuration du programme côté vendeur (opt-in).
 */
final class AffiliateController
{
    public function go(Request $request): void
    {
        $code   = (string) $request->param('code', '');
        $target = Affiliate::safeTarget((string) input_string('to', '/'));

        $affiliateId = Affiliate::userIdForCode($code);
        if ($affiliateId !== null) {
            Affiliate::recordClick($affiliateId, $target);
            Affiliate::setRefCookie($code);
        }
        redirect($target);
    }

    /** Hub d'affiliation accessible à tout membre connecté (acheteur comme vendeur). */
    public function hub(Request $request): void
    {
        $user    = current_user() ?? [];
        $uid     = (int) ($user['id'] ?? 0);
        // Le lien d'apporteur (et les gains) est réservé aux PARTICULIERS.
        $canEarn = ($user['account_type'] ?? '') !== 'professionnel';

        // Si le membre possède une boutique, on lui propose de configurer son programme.
        $shop    = Boutique::findByUserId($uid);
        $program = null;
        if ($shop !== null) {
            $aff = Boutique::affiliationOf((int) $shop['id']);
            $program = [
                'boutique' => $shop, 'enabled' => $aff['enabled'], 'rate' => $aff['rate'],
                'stats'    => Affiliate::programStats((int) $shop['id']),
                'recent'   => Affiliate::programRecent((int) $shop['id'], 8),
                'series'   => Affiliate::programSeries((int) $shop['id'], 14),
            ];
        }

        // Lien PERSONNEL unique : généré uniquement pour les particuliers.
        $code = $canEarn ? Affiliate::codeFor($uid) : '';
        $data = [
            'user'         => $user,
            'can_earn'     => $canEarn,
            'code'         => $code,
            'link'         => $code !== '' ? url('/r/' . $code) : '',
            'rate'         => Affiliate::RATE_PCT,
            'program'      => $program,
            'stats'        => ['clicks' => 0, 'conversions' => 0, 'earnings' => []],
            'recent'       => [],
            'directory'    => [],
            'dir_products' => [],
            'dir_mains'    => [],
            'wallet'       => null,
            'page_title'   => t('aff.title'),
        ];
        if ($canEarn) {
            $products = Product::participating(12);
            $cur      = Wallet::currencyFor($uid, 'EUR');
            $data['stats']        = Affiliate::statsFor($uid);
            $data['recent']       = Affiliate::recentFor($uid, 10);
            $data['directory']    = Boutique::participating(60);
            $data['dir_products'] = $products;
            $data['dir_mains']    = Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));
            $data['wallet']       = [
                'balance'     => Wallet::balanceCents($uid),
                'currency'    => $cur,
                'threshold'   => Wallet::thresholdCents($cur),
                'can'         => Wallet::canWithdraw($uid),
                'withdrawals' => Wallet::withdrawalsFor($uid),
            ];
        }
        view('affiliation/hub', $data);
    }

    /** Demande de retrait du solde (commissions d'affiliation) — ouvert à tout membre. */
    public function withdraw(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            redirect('/login');
        }
        $method = whitelist((string) input_string('method', 'mobile_money'), ['mobile_money', 'bank'], 'mobile_money');
        $dest   = trim((string) input_string('destination', ''));
        if (mb_strlen($dest) < 4) {
            flash('error', t('wallet.err_destination'));
            redirect('/affiliation');
        }
        $pid = Wallet::requestWithdrawal($uid, $method, $dest);
        if ($pid === null) {
            $cur = Wallet::currencyFor($uid, 'EUR');
            flash('error', t('wallet.err_threshold', ['min' => format_price(Wallet::thresholdCents($cur), $cur)]));
        } else {
            AuditLog::record($uid, 'wallet.withdrawal_requested', 'withdrawal', null, [], $request->ipBinary());
            flash('success', t('wallet.requested'));
        }
        redirect('/affiliation');
    }

    /** Active/désactive le programme d'affiliation de SA boutique et fixe le taux. */
    public function saveProgram(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        $shop = Boutique::findByUserId($uid);
        if ($shop === null) {
            redirect('/affiliation'); // pas de boutique : rien à configurer
        }
        $enabled = input_string('enabled', '') === '1';
        $rate    = (int) input_string('rate', '5');
        Boutique::setAffiliation((int) $shop['id'], $uid, $enabled, $rate);
        flash('success', t('aff.program_saved'));
        redirect('/affiliation');
    }
}
