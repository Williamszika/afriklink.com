<?php
declare(strict_types=1);

use App\Controllers\AffiliateController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AdminKycController;
use App\Controllers\BoutiqueController;
use App\Controllers\HomeController;
use App\Controllers\KycController;
use App\Controllers\ListingController;
use App\Controllers\GeoController;
use App\Controllers\MediaController;
use App\Controllers\ProductController;
use App\Controllers\ReportController;
use App\Controllers\RestaurantController;
use App\Controllers\ProfileController;
use App\Controllers\ProRegistrationController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\SellerController;
use App\Controllers\SellerProfileController;

/**
 * Route table: [HTTP method, path, [Controller, action], [middleware...]].
 *
 * Middleware aliases:
 *   guest                  — only for logged-out visitors
 *   auth                   — requires a logged-in user (auth:vendor / auth:admin for a role)
 *   csrf                   — verifies CSRF token on mutating requests
 *   throttle:bucket,max,window — per-IP rate limit
 *
 * LocaleMiddleware runs globally before any route middleware (see Router::dispatch).
 */

return [
    // ---- Public -------------------------------------------------------
    ['GET',  '/',                  [HomeController::class, 'index'],          []],
    ['GET',  '/explorer',          [HomeController::class, 'explore'],        []],
    ['GET',  '/sitemap.xml',       [HomeController::class, 'sitemap'],        ['throttle:sitemap,60,3600']],
    ['GET',  '/robots.txt',        [HomeController::class, 'robots'],         []],
    ['GET',  '/health',            [HomeController::class, 'health'],         []],
    ['GET',  '/lang/{locale}',     [HomeController::class, 'switchLanguage'], []],
    ['GET',  '/r/{code}',          [AffiliateController::class, 'go'],        ['throttle:aff,180,3600']],

    // ---- Authentication (guests) -------------------------------------
    ['GET',  '/register',               [AuthController::class, 'showRegisterChoice'],     ['guest']],
    ['GET',  '/register/particulier',   [AuthController::class, 'showRegisterParticulier'],['guest']],
    ['POST', '/register/particulier',   [AuthController::class, 'registerParticulier'],    ['guest', 'csrf', 'throttle:register,10,3600']],
    ['GET',  '/register/vendeur', [ProRegistrationController::class, 'show'],   ['guest']],
    ['POST', '/register/vendeur', [ProRegistrationController::class, 'submit'], ['guest', 'csrf', 'throttle:register,10,3600']],

    ['GET',  '/login',             [AuthController::class, 'showLogin'],      ['guest']],
    ['POST', '/login',             [AuthController::class, 'login'],          ['guest', 'csrf', 'throttle:login,10,900']],

    ['GET',  '/forgot-password',   [AuthController::class, 'showForgot'],     ['guest']],
    ['POST', '/forgot-password',   [AuthController::class, 'sendReset'],      ['guest', 'csrf', 'throttle:forgot,5,3600']],

    ['GET',  '/reset-password',    [AuthController::class, 'showReset'],      ['guest']],
    ['POST', '/reset-password',    [AuthController::class, 'reset'],          ['guest', 'csrf', 'throttle:reset,10,3600']],

    ['GET',  '/verify-email',      [AuthController::class, 'verifyEmail'],    ['throttle:verify,30,3600']],

    // ---- Authenticated ------------------------------------------------
    ['GET',  '/verify-email/notice',  [AuthController::class, 'verifyNotice'],        ['auth']],
    ['POST', '/verify-email/resend',  [AuthController::class, 'resendVerification'],  ['auth', 'csrf', 'throttle:resend,5,3600']],

    ['POST', '/logout',            [AuthController::class, 'logout'],         ['auth', 'csrf']],
    ['GET',  '/dashboard',         [DashboardController::class, 'index'],     ['auth']],

    // Account self-service
    ['GET',  '/profile',           [ProfileController::class, 'edit'],            ['auth']],
    ['POST', '/profile',           [ProfileController::class, 'update'],          ['auth', 'csrf', 'throttle:profile,30,3600']],
    ['POST', '/profile/password',  [ProfileController::class, 'updatePassword'],  ['auth', 'csrf', 'throttle:pwd,10,3600']],
    ['POST', '/profile/photo',         [ProfileController::class, 'updatePhoto'], ['auth', 'csrf', 'throttle:avatar,10,3600']],
    ['POST', '/profile/photo/delete',  [ProfileController::class, 'deletePhoto'], ['auth', 'csrf']],
    ['POST', '/profile/preferences',   [ProfileController::class, 'updatePreferences'], ['auth', 'csrf', 'throttle:profile,30,3600']],
    ['GET',  '/avatar/{pid}',          [ProfileController::class, 'avatar'],      []],

    // Espace vendeur (tableau de bord à menu latéral). « Vue d'ensemble » = /dashboard.
    ['GET',  '/vendeur/vitrines',  [SellerController::class, 'storefronts'],  ['auth']],
    ['GET',  '/vendeur/commandes',                [OrderController::class, 'index'],     ['auth']],
    ['POST', '/vendeur/commandes',                [OrderController::class, 'store'],     ['auth', 'csrf', 'throttle:order,80,3600']],
    ['POST', '/vendeur/commandes/{oid}/statut',   [OrderController::class, 'setStatus'], ['auth', 'csrf']],
    ['GET',  '/vendeur/messages',  [SellerController::class, 'messages'],     ['auth']],

    // Encaissement en ligne (ossature multi-fournisseurs + simulation testable)
    ['GET',  '/paiement/tester',              [PaymentController::class, 'tester'],           ['auth']],
    ['POST', '/paiement/demarrer',            [PaymentController::class, 'start'],            ['auth', 'csrf', 'throttle:pay,30,3600']],
    ['GET',  '/paiement/simulation/{ref}',    [PaymentController::class, 'simulation'],       ['auth']],
    ['POST', '/paiement/simulation/{ref}',    [PaymentController::class, 'simulationResult'], ['auth', 'csrf']],
    ['GET',  '/paiement/retour/{ref}',        [PaymentController::class, 'result'],           ['auth']],

    ['GET',  '/vendeur/gains',       [SellerController::class, 'earnings'],     ['auth']],
    ['GET',  '/vendeur/publicite',   [SellerController::class, 'advertising'],  ['auth']],
    ['GET',  '/vendeur/affiliation', [SellerController::class, 'affiliation'],  ['auth']],
    ['GET',  '/vendeur/verification',[SellerController::class, 'verification'], ['auth']],
    ['POST', '/vendeur/verification/{level}', [KycController::class, 'submit'], ['auth', 'csrf', 'throttle:kycsub2,200,3600']],
    ['POST', '/api/kyc/sign',        [MediaController::class, 'signKyc'],      ['auth', 'csrf', 'throttle:sign,60,3600']],

    // Espace de modération KYC (admins / modérateurs)
    ['GET',  '/admin/kyc',           [AdminKycController::class, 'index'],     ['staff']],
    ['GET',  '/admin/kyc/doc/{id}',  [AdminKycController::class, 'document'],  ['staff']],
    ['GET',  '/admin/kyc/{id}',      [AdminKycController::class, 'show'],      ['staff']],
    ['POST', '/admin/kyc/{id}/review', [AdminKycController::class, 'review'],  ['staff', 'csrf']],
    ['GET',  '/vendeur/reglages',  [SellerController::class, 'settings'],     ['auth']],
    ['GET',  '/vendeur/profil',    [SellerProfileController::class, 'edit'],   ['auth']],
    ['POST', '/vendeur/profil',    [SellerProfileController::class, 'update'], ['auth', 'csrf', 'throttle:profile,30,3600']],

    // Annonces entre particuliers (« Vendre un article »)
    ['GET',  '/vendre',                  [ListingController::class, 'create'],    ['auth']],
    ['POST', '/vendre',                  [ListingController::class, 'store'],     ['auth', 'csrf', 'throttle:listing,20,3600']],
    ['GET',  '/annonces',                [ListingController::class, 'mine'],      ['auth']],
    ['GET',  '/annonce/{pid}',           [ListingController::class, 'show'],      []],
    ['GET',  '/annonce/{pid}/modifier',  [ListingController::class, 'edit'],      ['auth']],
    ['POST', '/annonce/{pid}/modifier',  [ListingController::class, 'update'],    ['auth', 'csrf', 'throttle:listing,20,3600']],
    ['POST', '/annonce/{pid}/statut',    [ListingController::class, 'setStatus'], ['auth', 'csrf']],

    // Signature des envois médias directs navigateur → Cloudinary
    ['POST', '/api/media/sign',          [MediaController::class, 'sign'],        ['auth', 'csrf', 'throttle:sign,60,3600']],
    ['GET',  '/api/geo/reverse',         [GeoController::class, 'reverse'],       ['throttle:geo,60,3600']],
    ['GET',  '/api/geo/session',         [GeoController::class, 'session'],       ['throttle:geosess,120,3600']],

    // Boutique en ligne (assistant de création + gestion + page publique)
    ['GET',  '/boutique/creer',  [BoutiqueController::class, 'create'],  ['auth']],
    ['POST', '/boutique/creer',  [BoutiqueController::class, 'submit'],  ['auth', 'csrf', 'throttle:shop,40,3600']],
    ['GET',  '/api/boutique/slug', [BoutiqueController::class, 'checkSlug'], ['auth', 'throttle:slug,120,3600']],
    ['GET',  '/boutique/gerer',  [BoutiqueController::class, 'manage'],  ['auth']],
    ['POST', '/boutique/publier', [BoutiqueController::class, 'publish'], ['auth', 'csrf']],
    ['GET',  '/boutique/modifier', [BoutiqueController::class, 'edit'],       ['auth']],
    ['POST', '/boutique/modifier', [BoutiqueController::class, 'updateShop'], ['auth', 'csrf', 'throttle:shop,40,3600']],
    ['GET',  '/boutique/qr',       [BoutiqueController::class, 'qr'],         ['auth']],
    ['GET',  '/boutique/stats',    [BoutiqueController::class, 'stats'],      ['auth']],
    // Produits du catalogue
    ['GET',  '/boutique/produits/nouveau',        [ProductController::class, 'create'],    ['auth']],
    ['POST', '/boutique/produits',                [ProductController::class, 'store'],     ['auth', 'csrf', 'throttle:product,60,3600']],
    ['GET',  '/boutique/produits/{pid}/modifier', [ProductController::class, 'edit'],      ['auth']],
    ['POST', '/boutique/produits/{pid}/modifier', [ProductController::class, 'update'],    ['auth', 'csrf', 'throttle:product,60,3600']],
    ['POST', '/boutique/produits/{pid}/statut',   [ProductController::class, 'setStatus'], ['auth', 'csrf']],
    // Caisse + commande en ligne : panier public (client, éventuellement non connecté)
    ['POST', '/boutique/{slug}/caisse',    [BoutiqueController::class, 'caisseStore'], ['csrf', 'throttle:border,80,3600']],
    ['GET',  '/boutique/{slug}/caisse',    [BoutiqueController::class, 'caisse'],      []],
    ['POST', '/boutique/{slug}/commander', [BoutiqueController::class, 'checkout'],          ['csrf', 'throttle:border,40,3600']],
    ['GET',  '/boutique/commande/{ref}',   [BoutiqueController::class, 'orderConfirmation'], []],
    // Paiement en ligne de la commande (public ; PSP réel ou bac à sable de simulation)
    ['POST', '/boutique/commande/{ref}/payer',  [BoutiqueController::class, 'payStart'],   ['csrf', 'throttle:bpay,30,3600']],
    ['GET',  '/boutique/commande/{ref}/regler', [BoutiqueController::class, 'paySandbox'], []],
    ['POST', '/boutique/commande/{ref}/regler', [BoutiqueController::class, 'paySettle'],  ['csrf', 'throttle:bpay,30,3600']],
    ['GET',  '/boutique/commande/{ref}/retour', [BoutiqueController::class, 'payReturn'],  []],
    // Avis & notes + alerte retour en stock
    ['POST', '/boutique/{slug}/p/{pid}/avis',         [BoutiqueController::class, 'storeReview'],      ['csrf', 'throttle:review,10,3600']],
    ['POST', '/boutique/{slug}/p/{pid}/alerte-stock', [BoutiqueController::class, 'storeStockAlert'],  ['csrf', 'throttle:review,10,3600']],
    ['POST', '/boutique/{slug}/assistant',            [BoutiqueController::class, 'assistant'],        ['csrf', 'throttle:assistant,30,3600']],
    ['POST', '/boutique/avis/{rid}/masquer',  [BoutiqueController::class, 'hideReview'],  ['auth', 'csrf']],
    ['POST', '/boutique/politique',           [BoutiqueController::class, 'updatePolicy'], ['auth', 'csrf']],
    // Vitrine publique
    ['GET',  '/boutique/{slug}/p/{pid}', [BoutiqueController::class, 'product'], []],
    ['GET',  '/boutique/{slug}',         [BoutiqueController::class, 'show'],    []],

    // Restaurant (vitrine + carte/menu + page publique)
    ['GET',  '/restaurant/creer',  [RestaurantController::class, 'create'], ['auth']],
    ['POST', '/restaurant/creer',  [RestaurantController::class, 'store'],  ['auth', 'csrf', 'throttle:shop,40,3600']],
    ['GET',  '/restaurant/gerer',  [RestaurantController::class, 'manage'], ['auth']],
    ['POST', '/restaurant/publier', [RestaurantController::class, 'publish'], ['auth', 'csrf']],
    ['POST', '/restaurant/categorie',            [RestaurantController::class, 'storeCategory'],  ['auth', 'csrf', 'throttle:product,80,3600']],
    ['POST', '/restaurant/categorie/{cid}/suppr', [RestaurantController::class, 'deleteCategory'], ['auth', 'csrf']],
    ['POST', '/restaurant/categorie/{cid}/renommer', [RestaurantController::class, 'renameCategory'], ['auth', 'csrf']],
    ['POST', '/restaurant/plat',                 [RestaurantController::class, 'storeItem'],      ['auth', 'csrf', 'throttle:product,120,3600']],
    ['POST', '/restaurant/plat/{mid}/statut',    [RestaurantController::class, 'setItemStatus'],  ['auth', 'csrf']],
    ['POST', '/restaurant/plat/{mid}/contenance', [RestaurantController::class, 'setVariantStatus'], ['auth', 'csrf']],
    ['POST', '/restaurant/paiement',             [RestaurantController::class, 'updatePayment'],   ['auth', 'csrf']],
    // Commandes restaurant : panier public + suivi côté restaurateur
    ['GET',  '/restaurant/commandes',            [RestaurantController::class, 'orders'],          ['auth']],
    ['POST', '/restaurant/commandes/{ref}/statut', [RestaurantController::class, 'setOrderStatus'], ['auth', 'csrf']],
    ['POST', '/restaurant/{slug}/caisse',         [RestaurantController::class, 'caisseStore'],     ['csrf', 'throttle:rorder,80,3600']],
    ['GET',  '/restaurant/{slug}/caisse',         [RestaurantController::class, 'caisse'],          []],
    ['POST', '/restaurant/{slug}/commander',      [RestaurantController::class, 'checkout'],        ['csrf', 'throttle:rorder,40,3600']],
    ['GET',  '/restaurant/commande/{ref}',        [RestaurantController::class, 'orderConfirmation'], []],
    ['POST', '/restaurant/commande/{ref}/payer',  [RestaurantController::class, 'payStart'],        ['csrf', 'throttle:rorder,30,3600']],
    ['GET',  '/restaurant/commande/{ref}/regler', [RestaurantController::class, 'paySandbox'],      []],
    ['POST', '/restaurant/commande/{ref}/regler', [RestaurantController::class, 'paySettle'],       ['csrf', 'throttle:rorder,30,3600']],
    ['GET',  '/restaurant/commande/{ref}/retour', [RestaurantController::class, 'payReturn'],       []],
    ['GET',  '/restaurant/{slug}', [RestaurantController::class, 'show'], []],

    // Signalement « ce n'était pas moi » (lien reçu par e-mail, sans connexion)
    ['GET',  '/signaler-vitrine', [ReportController::class, 'storefront'], ['throttle:report,20,3600']],

    // Roadmap interstitials for not-yet-built dashboard actions
    ['GET',  '/bientot/{feature}', [DashboardController::class, 'comingSoon'],    ['auth']],
];
