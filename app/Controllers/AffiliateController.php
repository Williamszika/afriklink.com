<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Affiliate;
use App\Request;

/**
 * Lien intelligent d'affiliation « /r/{code} » : enregistre le clic, pose le
 * cookie de parrainage (30 j) et redirige vers une cible interne (?to=…).
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
}
