<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Avatar;
use App\Models\ProProfile;
use App\Request;
use App\Services\CloudinaryService;

/**
 * Espace vendeur — tableau de bord à menu latéral. Chaque entrée du menu est
 * une vraie URL (navigation serveur, fonctionne sans JavaScript) ; la vue de
 * section rend la barre latérale partagée + son contenu à droite.
 */
final class SellerController
{
    /**
     * Données communes à toutes les sections (identité, logo, complétion).
     * @return array<string,mixed>
     */
    public static function commonData(array $user): array
    {
        $userId        = (int) ($user['id'] ?? 0);
        $profile       = ProProfile::findByUserId($userId) ?? [];
        $avatarVersion = Avatar::versionFor($userId);

        return [
            'user'           => $user,
            'profile'        => $profile,
            'avatar_version' => $avatarVersion,
            'avatar_url'     => avatar_url($user, $avatarVersion),
            'completion'     => self::completion($profile, $avatarVersion !== null),
        ];
    }

    /** % de complétion : logo + 6 champs entreprise (sert barre + checklist). */
    public static function completion(array $profile, bool $hasLogo): int
    {
        $done = $hasLogo ? 1 : 0;
        foreach (['description', 'legal_form', 'reg_number', 'address', 'website', 'languages'] as $f) {
            if (!empty($profile[$f])) {
                $done++;
            }
        }
        return (int) round($done * 100 / 7);
    }

    public function storefronts(Request $request): void
    {
        $user = current_user() ?? [];
        view('vendeur/vitrines', [
            'active'     => 'vitrines',
            'boutique'   => \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0)),
            'restaurant' => \App\Models\Restaurant::findByUserId((int) ($user['id'] ?? 0)),
        ] + self::commonData($user));
    }

    public function messages(Request $request): void
    {
        view('vendeur/messages', ['active' => 'messages'] + self::commonData(current_user() ?? []));
    }

    public function settings(Request $request): void
    {
        view('vendeur/reglages', ['active' => 'reglages'] + self::commonData(current_user() ?? []));
    }

    /* ---- Sections « bientôt » (KYC, publicité, affiliation, gains) ---- */

    public function earnings(Request $request): void
    {
        $this->soon('gains', '💸', 'seller.earnings');
    }

    public function advertising(Request $request): void
    {
        $user     = current_user() ?? [];
        $boutique = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
        $products = $boutique !== null ? \App\Models\Product::forBoutique((int) $boutique['id']) : [];
        view('vendeur/publicite', [
            'active'     => 'publicite',
            'boutique'   => $boutique,
            'products'   => $products,
            'mains'      => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'promo_days' => 7,
        ] + self::commonData($user));
    }

    /** Active/retire la mise en avant « sponsorisé » d'un de ses produits. */
    public function promote(Request $request): void
    {
        $user     = current_user() ?? [];
        $boutique = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
        $product  = \App\Models\Product::findByPublicId((string) $request->param('pid', ''));
        if ($boutique === null || $product === null || (int) $product['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $action = whitelist((string) input_string('action', ''), ['promote', 'stop'], null);
        if ($action === null) {
            abort(404);
        }
        \App\Models\Product::setPromoted((int) $product['id'], $action === 'promote' ? 7 : null);
        flash('success', t($action === 'promote' ? 'ads.promoted_flash' : 'ads.stopped_flash'));
        redirect('/vendeur/publicite');
    }

    public function affiliation(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        $code = \App\Models\Affiliate::codeFor($uid);
        view('vendeur/affiliation', [
            'active' => 'affiliation',
            'code'   => $code,
            'link'   => $code !== '' ? url('/r/' . $code) : '',
            'rate'   => \App\Models\Affiliate::RATE_PCT,
            'stats'  => \App\Models\Affiliate::statsFor($uid),
            'recent' => \App\Models\Affiliate::recentFor($uid, 10),
        ] + self::commonData($user));
    }

    public function verification(Request $request): void
    {
        $userId = (int) current_user_id();
        \App\Models\Kyc::ensureTables();
        view('vendeur/verification', [
            'active'        => 'verification',
            'submissions'   => \App\Models\Kyc::submissionsByLevel($userId),
            'approvedLevel' => \App\Models\Kyc::approvedLevel($userId),
            'mediaReady'    => CloudinaryService::configured(),
        ] + self::commonData(current_user() ?? []));
    }

    /** Rend la vue de section générique avec ses libellés. */
    private function soon(string $active, string $icon, string $prefix): void
    {
        view('vendeur/section_soon', [
            'active' => $active,
            'icon'   => $icon,
            'prefix' => $prefix,
        ] + self::commonData(current_user() ?? []));
    }
}
