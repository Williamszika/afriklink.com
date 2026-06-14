<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Affiliate;
use App\Models\Boutique;
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
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        $code = Affiliate::codeFor($uid);

        // Si le membre possède une boutique, on lui propose de configurer son programme.
        $shop    = Boutique::findByUserId($uid);
        $program = null;
        if ($shop !== null) {
            $aff = Boutique::affiliationOf((int) $shop['id']);
            $program = ['boutique' => $shop, 'enabled' => $aff['enabled'], 'rate' => $aff['rate']];
        }

        view('affiliation/hub', [
            'user'       => $user,
            'code'       => $code,
            'link'       => $code !== '' ? url('/r/' . $code) : '',
            'rate'       => Affiliate::RATE_PCT,
            'stats'      => Affiliate::statsFor($uid),
            'recent'     => Affiliate::recentFor($uid, 10),
            'directory'  => Boutique::participating(60),
            'program'    => $program,
            'page_title' => t('aff.title'),
        ]);
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
