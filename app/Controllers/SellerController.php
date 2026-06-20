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

        $state = [
            'stage'       => $stage,
            'has_shop'    => $hasShop,
            'boutique'    => $boutique,
            'product_n'   => $productN,
            'order_n'     => $orderN,
            'pending'     => $pending,
            'views'       => $views,
            'aff_enabled' => $boutique !== null && \App\Models\Boutique::affiliationOf((int) $boutique['id'])['enabled'],
            'next'        => self::nextBestAction($stage, $boutique, $productN, $pending),
        ];

        // Cockpit chiffré : seulement pour un vendeur ACTIF (≥ 1 commande), pour ne
        // pas surcharger un débutant de graphiques vides ni de requêtes inutiles.
        if ($stage === 'C') {
            $summary = \App\Services\SellerAnalytics::summary($uid);
            $state += [
                'currency'       => \App\Services\SellerAnalytics::currency($uid),
                'revenue_month'  => (int) ($summary['month_cents'] ?? 0),
                'revenue_total'  => (int) ($summary['total_cents'] ?? 0),
                'revenue_by_day' => \App\Services\SellerAnalytics::revenueByDay($uid, 14),
                'top_products'   => \App\Services\SellerAnalytics::topProducts($uid, 5, 30),
                'low_stock'      => \App\Services\SellerAnalytics::lowStock($uid, 5),
                'recent_orders'  => $boutique !== null
                    ? array_slice(\App\Models\Order::forBoutique((int) $boutique['id']), 0, 5)
                    : [],
                'conversion'     => $views > 0 ? round($orderN / $views * 100, 1) : null,
                'revenue_prev_month' => \App\Services\SellerAnalytics::revenuePrevMonth($uid),
                'status_breakdown'   => \App\Services\SellerAnalytics::statusBreakdown($uid),
                'orders_by_day'      => \App\Services\SellerAnalytics::ordersByDay($uid, 14),
                'products_by_day'    => \App\Services\SellerAnalytics::productsByDay($uid, 14),
                'views_by_day'       => $boutique !== null ? \App\Models\ShopView::daily((int) $boutique['id'], 14) : [],
            ];
        }

        return $state;
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
            'boutiques'  => \App\Models\Boutique::allForUser((int) ($user['id'] ?? 0)),
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
        $user = current_user() ?? [];
        view('vendeur/reglages', [
            'active' => 'reglages',
            'prefs'  => ProProfile::sellerPrefs((int) ($user['id'] ?? 0)),
        ] + self::commonData($user));
    }

    /** Enregistre les préférences vendeur : notifications + retrait par défaut. */
    public function updateSettings(Request $request): void
    {
        $user   = current_user() ?? [];
        $method = whitelist((string) input_string('payout_method', ''), ['mobile_money', 'bank'], null);
        ProProfile::setSellerPrefs((int) ($user['id'] ?? 0), [
            'notify_email'       => (string) input_string('notify_email', '') !== '',
            'notify_sms'         => (string) input_string('notify_sms', '') !== '',
            'payout_method'      => $method,
            'payout_destination' => trim((string) input_string('payout_destination', '')),
        ]);
        flash('success', t('settings.prefs_saved'));
        redirect('/vendeur/reglages');
    }

    /** Ancien lien « Gains & retraits » — fusionné dans le Portefeuille. */
    public function earnings(Request $request): void
    {
        redirect('/vendeur/portefeuille');
    }

    public function advertising(Request $request): void
    {
        $user      = current_user() ?? [];
        $boutique  = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
        $products  = $boutique !== null ? \App\Models\Product::forBoutique((int) $boutique['id']) : [];
        $cur       = (string) ($boutique['currency'] ?? config('ads.base_currency', 'EUR'));
        $placement = 'home';

        // Grille des forfaits (durée → prix dans la devise du vendeur).
        $packages = [];
        foreach (\App\Models\AdCampaign::durations($placement) as $d) {
            $packages[$d] = \App\Models\AdCampaign::priceIn($placement, $d, $cur);
        }

        view('vendeur/publicite', [
            'active'       => 'publicite',
            'boutique'     => $boutique,
            'products'     => $products,
            'mains'        => \App\Models\Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'campaigns'    => \App\Models\AdCampaign::activeMap('product', array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'placement'    => $placement,
            'packages'     => $packages,
            'currency'     => $cur,
            'billing'      => (string) config('ads.billing', 'simulation'),
            'wallet_cents' => \App\Models\Wallet::balanceCents((int) ($user['id'] ?? 0)),
        ] + self::commonData($user));
    }

    /** Achète un forfait de mise en avant pour un produit, ou arrête une campagne. */
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

        if ($action === 'stop') {
            $current = \App\Models\AdCampaign::activeFor('product', (int) $product['id']);
            if ($current !== null) {
                \App\Models\AdCampaign::stop((string) $current['public_id'], (int) $user['id']);
            }
            flash('success', t('ads.stopped_flash'));
            redirect('/vendeur/publicite');
        }

        $placement = \App\Models\AdCampaign::validPlacement((string) input_string('placement', 'home'));
        $days      = \App\Models\AdCampaign::validDays($placement, (int) input_string('days', '7'));
        $res       = \App\Models\AdCampaign::purchaseProduct($user, $product, $boutique, $placement, $days);
        if ($res['ok']) {
            flash('success', t('ads.promoted_flash'));
        } else {
            flash('error', t($res['code'] === 'insufficient' ? 'ads.err_balance' : 'ads.err_package'));
        }
        redirect('/vendeur/publicite');
    }

    public function affiliation(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        // Espace vendeur : AUCUN lien d'apporteur ici — uniquement la configuration
        // du programme d'affiliation de SA boutique (le « gagner » est aux particuliers).
        $shop    = \App\Models\Boutique::findByUserId($uid);
        $program = null;
        if ($shop !== null) {
            $aff = \App\Models\Boutique::affiliationOf((int) $shop['id']);
            $program = [
                'boutique' => $shop, 'enabled' => $aff['enabled'], 'rate' => $aff['rate'],
                'stats'    => \App\Models\Affiliate::programStats((int) $shop['id']),
                'recent'   => \App\Models\Affiliate::programRecent((int) $shop['id'], 8),
                'series'   => \App\Models\Affiliate::programSeries((int) $shop['id'], 14),
                'top'      => \App\Models\Affiliate::topReferrersForBoutique((int) $shop['id'], 5),
            ];
        }
        view('vendeur/affiliation', [
            'active'       => 'affiliation',
            'can_earn'     => false,
            'code'         => '',
            'link'         => '',
            'rate'         => \App\Models\Affiliate::RATE_PCT,
            'stats'        => ['clicks' => 0, 'conversions' => 0, 'earnings' => []],
            'recent'       => [],
            'directory'    => [],
            'dir_products' => [],
            'dir_mains'    => [],
            'program'      => $program,
            'wallet'       => null,
        ] + self::commonData($user));
    }

    /** Avis clients : liste des avis reçus + réponse publique du vendeur. */
    public function reviews(Request $request): void
    {
        $user     = current_user() ?? [];
        $boutique = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
        view('vendeur/avis', [
            'active'   => 'avis',
            'boutique' => $boutique,
            'reviews'  => $boutique !== null ? \App\Models\Review::forBoutique((int) $boutique['id'], 100) : [],
            'summary'  => $boutique !== null
                ? \App\Models\Review::summaryForBoutique((int) $boutique['id'])
                : ['avg' => 0.0, 'count' => 0],
        ] + self::commonData($user));
    }

    /** Enregistre (ou retire) la réponse du vendeur à un avis le concernant. */
    public function reviewReply(Request $request): void
    {
        $user     = current_user() ?? [];
        $boutique = \App\Models\Boutique::findByUserId((int) ($user['id'] ?? 0));
        $review   = \App\Models\Review::findByPublicId((string) $request->param('rid', ''));
        if ($boutique === null || $review === null || (int) $review['boutique_id'] !== (int) $boutique['id']) {
            abort(404);
        }
        $reply = trim((string) input_string('reply', ''));
        \App\Models\Review::setReply((int) $review['id'], $reply !== '' ? $reply : null);
        flash('success', t($reply !== '' ? 'reviews.reply_saved' : 'reviews.reply_removed'));
        redirect('/vendeur/avis');
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
}
