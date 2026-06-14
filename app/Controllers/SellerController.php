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
        $hasStorefront = \App\Models\Boutique::findByUserId($userId) !== null
            || \App\Models\Restaurant::findByUserId($userId) !== null;
        $steps         = self::onboardingSteps($user, $profile, $avatarVersion !== null, $hasStorefront);

        return [
            'user'             => $user,
            'profile'          => $profile,
            'avatar_version'   => $avatarVersion,
            'avatar_url'       => avatar_url($user, $avatarVersion),
            'completion'       => self::completion($steps),
            'onboarding_steps' => $steps,
            'has_storefront'   => $hasStorefront,
        ];
    }

    /**
     * Étapes d'onboarding — SOURCE UNIQUE de la barre de complétion ET de la
     * checklist (elles ne peuvent plus diverger). 5 étapes réelles : e-mail
     * vérifié, logo, description, n° d'enregistrement, première vitrine.
     * @return list<array{done:bool,label:string,href:?string}>
     */
    public static function onboardingSteps(array $user, array $profile, bool $hasLogo, bool $hasStorefront): array
    {
        $emailOk = !empty($user['email_verified_at']);
        $hasDesc = !empty($profile['description']);
        $hasReg  = !empty($profile['reg_number']);
        return [
            ['done' => $emailOk,       'label' => t('pro.dash.check_email'),      'href' => $emailOk ? null : url('/verify-email/notice')],
            ['done' => $hasLogo,       'label' => t('seller.check_logo'),         'href' => $hasLogo ? null : url('/vendeur/profil')],
            ['done' => $hasDesc,       'label' => t('seller.check_description'),   'href' => $hasDesc ? null : url('/vendeur/profil')],
            ['done' => $hasReg,        'label' => t('pro.dash.check_reg'),         'href' => $hasReg ? null : url('/vendeur/profil')],
            ['done' => $hasStorefront, 'label' => t('pro.dash.check_storefront'),  'href' => $hasStorefront ? null : url('/vendeur/vitrines')],
        ];
    }

    /** % de complétion = étapes faites / total (recalculé depuis l'état réel). */
    public static function completion(array $steps): int
    {
        if ($steps === []) {
            return 0;
        }
        $done = 0;
        foreach ($steps as $s) {
            if (!empty($s['done'])) {
                $done++;
            }
        }
        return (int) round($done * 100 / count($steps));
    }

    /**
     * État adaptatif du tableau de bord : calcule le STADE depuis l'état réel du
     * compte (vitrine / produits / commandes) et la « prochaine meilleure action ».
     *   A = mise en route (pas de vitrine) · B = prêt à vendre (vitrine, 0 vente)
     *   · C = vendeur actif (≥ 1 commande).
     * @return array<string,mixed>
     */
    public static function dashboardState(array $user): array
    {
        $uid      = (int) ($user['id'] ?? 0);
        $boutique = \App\Models\Boutique::findByUserId($uid);
        $hasShop  = $boutique !== null || \App\Models\Restaurant::findByUserId($uid) !== null;
        $productN = $boutique !== null ? count(\App\Models\Product::forBoutique((int) $boutique['id'])) : 0;
        $orderN   = $boutique !== null ? (int) (\App\Models\Order::countFor((int) $boutique['id'])['total'] ?? 0) : 0;
        $pending  = \App\Models\Order::pendingForUser($uid);
        $views    = \App\Models\ShopView::totalForUser($uid);
        $stage    = !$hasShop ? 'A' : ($orderN === 0 ? 'B' : 'C');

        return [
            'stage'     => $stage,
            'has_shop'  => $hasShop,
            'boutique'  => $boutique,
            'product_n' => $productN,
            'order_n'   => $orderN,
            'pending'   => $pending,
            'views'     => $views,
            'next'      => self::nextBestAction($stage, $boutique, $productN, $pending),
        ];
    }

    /**
     * La SEULE action la plus utile selon l'avancement : créer la vitrine →
     * ajouter des produits → traiter les commandes → partager la boutique.
     * @return array{icon:string,title:string,desc:string,cta:string,href:string}
     */
    public static function nextBestAction(string $stage, ?array $boutique, int $productN, int $pending): array
    {
        if ($stage === 'A' || $boutique === null) {
            return ['icon' => 'store', 'title' => t('nba.create_shop_t'), 'desc' => t('nba.create_shop_d'),
                'cta' => t('nba.create_shop_c'), 'href' => url('/vendeur/vitrines')];
        }
        if ($productN === 0) {
            return ['icon' => 'package', 'title' => t('nba.add_products_t'), 'desc' => t('nba.add_products_d'),
                'cta' => t('nba.add_products_c'), 'href' => url('/boutique/produits/nouveau')];
        }
        if ($pending > 0) {
            return ['icon' => 'bell', 'title' => t('nba.process_orders_t', ['n' => $pending]), 'desc' => t('nba.process_orders_d'),
                'cta' => t('nba.process_orders_c'), 'href' => url('/vendeur/commandes?filtre=a_traiter')];
        }
        return ['icon' => 'link', 'title' => t('nba.share_shop_t'), 'desc' => t('nba.share_shop_d'),
            'cta' => t('nba.share_shop_c'), 'href' => url('/boutique/' . (string) $boutique['slug'])];
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
        // La messagerie est unifiée (acheteur ↔ vendeur) sur /messages.
        redirect('/messages');
    }

    public function settings(Request $request): void
    {
        view('vendeur/reglages', ['active' => 'reglages'] + self::commonData(current_user() ?? []));
    }

    /* ---- Sections « bientôt » (KYC, publicité, affiliation, gains) ---- */

    public function earnings(Request $request): void
    {
        $this->soon('gains', 'wallet', 'seller.earnings');
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
