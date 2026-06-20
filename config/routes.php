<?php
declare(strict_types=1);

use App\Controllers\AffiliateController;
use App\Controllers\LegalController;
use App\Controllers\TrustController;
use App\Controllers\WishlistController;
use App\Controllers\CompareController;
use App\Controllers\CartController;
use App\Controllers\CronController;
use App\Controllers\NotificationController;
use App\Controllers\MessageController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AddressController;
use App\Controllers\NewsletterController;
use App\Controllers\AdminAdsController;
use App\Controllers\AdminDemoController;
use App\Controllers\AdminNewsletterController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminKycController;
use App\Controllers\AdminMailController;
use App\Controllers\AnnouncementController;
use App\Controllers\WalletController;
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
use App\Controllers\PosController;
use App\Controllers\SellerProfileController;
use App\Controllers\WebhookController;

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
    ['GET',  '/mise-en-avant',     [HomeController::class, 'spotlight'],      []],
    ['GET',  '/sp/{pid}',          [HomeController::class, 'sponsoredClick'], ['throttle:spclick,300,3600']],
    ['GET',  '/sitemap.xml',       [HomeController::class, 'sitemap'],        ['throttle:sitemap,60,3600']],
    ['GET',  '/robots.txt',        [HomeController::class, 'robots'],         []],
    ['GET',  '/health',            [HomeController::class, 'health'],         []],
    ['GET',  '/lang/{locale}',     [HomeController::class, 'switchLanguage'], []],
    ['GET',  '/devise/{currency}', [HomeController::class, 'switchCurrency'], ['throttle:cur,60,3600']],
    ['GET',  '/r/{code}',          [AffiliateController::class, 'go'],        ['throttle:aff,180,3600']],
    ['GET',  '/mentions-legales',  [LegalController::class, 'notice'],        []],
    ['GET',  '/confidentialite',   [LegalController::class, 'privacy'],       []],
    ['GET',  '/cgv',               [LegalController::class, 'terms'],         []],
    ['GET',  '/consentement/{choice}', [LegalController::class, 'consent'],   ['throttle:consent,60,3600']],
    // Piliers de confiance (pages publiques expliquant chaque système)
    ['GET',  '/paiements-securises', [TrustController::class, 'payments'], []],
    ['GET',  '/vendeurs-verifies',   [TrustController::class, 'verified'], []],
    ['GET',  '/local-international',  [TrustController::class, 'intl'],     []],
    ['GET',  '/assistance',          [TrustController::class, 'support'],  []],
    ['GET',  '/favoris',                [WishlistController::class, 'index'],  []],
    ['POST', '/favoris/{pid}/basculer', [WishlistController::class, 'toggle'], ['csrf', 'throttle:wish,180,3600']],
    ['GET',  '/comparer',               [CompareController::class, 'index'],   []],
    ['POST', '/comparer/{pid}/basculer', [CompareController::class, 'toggle'], ['csrf', 'throttle:compare,180,3600']],
    ['GET',  '/panier/apercu',          [CartController::class, 'preview'],     ['throttle:preview,600,3600']],
    ['GET',  '/favoris/apercu',         [WishlistController::class, 'preview'], ['throttle:preview,600,3600']],
    ['GET',  '/comparer/apercu',        [CompareController::class, 'preview'],  ['throttle:preview,600,3600']],
    ['GET',  '/notifications/apercu',   [NotificationController::class, 'preview'], ['auth', 'throttle:preview,600,3600']],
    ['GET',  '/panier',                 [CartController::class, 'index'],     []],
    ['POST', '/panier/ajouter',         [CartController::class, 'add'],       ['csrf', 'throttle:cart,400,3600']],
    ['POST', '/panier/modifier',        [CartController::class, 'update'],    ['csrf']],
    ['POST', '/panier/{slug}/caisse',   [CartController::class, 'checkout'],  ['csrf']],

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
    ['GET',  '/vendeur',           [DashboardController::class, 'index'],     ['auth']],
    ['GET',  '/mes-achats',        [DashboardController::class, 'purchases'], ['auth']],
    // Affiliation — hub universel (tout membre) : lien perso, gains, annuaire des boutiques participantes.
    ['GET',  '/affiliation',           [AffiliateController::class, 'hub'],          ['auth']],
    ['GET',  '/affiliation/produits',  [AffiliateController::class, 'products'],     ['auth']],
    ['GET',  '/affiliation/liens',     [AffiliateController::class, 'links'],        ['auth']],
    ['GET',  '/affiliation/mes-produits', [AffiliateController::class, 'vendorProducts'],     ['auth']],
    ['POST', '/affiliation/mes-produits', [AffiliateController::class, 'vendorProductsSave'], ['auth', 'csrf', 'throttle:aff,40,3600']],
    ['POST', '/affiliation/retrait',   [AffiliateController::class, 'withdraw'],     ['auth', 'csrf', 'throttle:wd,20,3600']],
    ['POST', '/newsletter',        [NewsletterController::class, 'subscribe'], ['csrf', 'throttle:news,20,3600']],
    ['POST', '/newsletter/popup',  [NewsletterController::class, 'popup'],     ['csrf', 'throttle:news,20,3600']],
    ['GET',  '/desinscription/{token}', [NewsletterController::class, 'unsubscribe'], ['throttle:unsub,60,3600']],
    ['GET',  '/paniers/stop/{token}',   [CartController::class, 'stopReminders'],     ['throttle:unsub,60,3600']],
    // Tâche planifiée (Vercel Cron / externe) — protégée par CRON_SECRET.
    ['GET',  '/cron/relance-paniers',   [CronController::class, 'abandonedCart'],     ['throttle:cron,120,3600']],
    ['GET',  '/mes-adresses',          [AddressController::class, 'index'],      ['auth']],
    ['POST', '/mes-adresses',          [AddressController::class, 'store'],      ['auth', 'csrf', 'throttle:addr,40,3600']],
    ['POST', '/mes-adresses/{id}/defaut', [AddressController::class, 'setDefault'], ['auth', 'csrf']],
    ['POST', '/mes-adresses/{id}/suppr',  [AddressController::class, 'delete'],     ['auth', 'csrf']],

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
    ['GET',  '/notifications',            [NotificationController::class, 'index'],   ['auth']],
    ['GET',  '/notifications/{id}/ouvrir', [NotificationController::class, 'open'],   ['auth']],
    ['POST', '/notifications/lus',        [NotificationController::class, 'markAll'], ['auth', 'csrf']],
    ['GET',  '/messages',                 [MessageController::class, 'inbox'],  ['auth']],
    ['POST', '/messages/demarrer',        [MessageController::class, 'start'],  ['auth', 'csrf', 'throttle:msg,40,3600']],
    ['GET',  '/messages/{id}',            [MessageController::class, 'thread'], ['auth']],
    ['POST', '/messages/{id}/repondre',   [MessageController::class, 'reply'],  ['auth', 'csrf', 'throttle:msg,60,3600']],

    // Encaissement en ligne (ossature multi-fournisseurs + simulation testable)
    ['GET',  '/paiement/tester',              [PaymentController::class, 'tester'],           ['auth']],
    ['POST', '/paiement/demarrer',            [PaymentController::class, 'start'],            ['auth', 'csrf', 'throttle:pay,30,3600']],
    ['GET',  '/paiement/simulation/{ref}',    [PaymentController::class, 'simulation'],       ['auth']],
    ['POST', '/paiement/simulation/{ref}',    [PaymentController::class, 'simulationResult'], ['auth', 'csrf']],
    ['GET',  '/paiement/retour/{ref}',        [PaymentController::class, 'result'],           ['auth']],

    ['GET',  '/vendeur/gains',       [SellerController::class, 'earnings'],     ['auth']],
    // Portefeuille vendeur : solde encaissé + demande de retrait (≥ 20 000 XOF).
    ['GET',  '/vendeur/portefeuille',         [WalletController::class, 'index'],    ['auth']],
    ['POST', '/vendeur/portefeuille/retrait', [WalletController::class, 'withdraw'], ['auth', 'csrf', 'throttle:wd,20,3600']],
    ['GET',  '/vendeur/point-de-vente',           [PosController::class, 'index'],    ['auth']],
    ['POST', '/vendeur/point-de-vente/ouvrir',    [PosController::class, 'open'],     ['auth', 'csrf', 'throttle:pos,120,3600']],
    ['POST', '/vendeur/point-de-vente/vente',     [PosController::class, 'sale'],     ['auth', 'csrf', 'throttle:possale,600,3600']],
    ['POST', '/vendeur/point-de-vente/mouvement', [PosController::class, 'movement'], ['auth', 'csrf', 'throttle:pos,120,3600']],
    ['POST', '/vendeur/point-de-vente/fermer',    [PosController::class, 'close'],    ['auth', 'csrf', 'throttle:pos,120,3600']],
    ['GET',  '/vendeur/point-de-vente/session/{id}/export', [PosController::class, 'exportSession'], ['auth', 'throttle:posx,120,3600']],
    ['GET',  '/vendeur/publicite',   [SellerController::class, 'advertising'],  ['auth']],
    ['POST', '/vendeur/publicite/{pid}/promouvoir', [SellerController::class, 'promote'], ['auth', 'csrf', 'throttle:product,60,3600']],
    ['GET',  '/vendeur/affiliation', [SellerController::class, 'affiliation'],  ['auth']],
    ['GET',  '/vendeur/avis',                  [SellerController::class, 'reviews'],     ['auth']],
    ['POST', '/vendeur/avis/{rid}/repondre',   [SellerController::class, 'reviewReply'], ['auth', 'csrf', 'throttle:review,60,3600']],
    ['GET',  '/vendeur/verification',[SellerController::class, 'verification'], ['auth']],
    ['POST', '/vendeur/verification/{level}', [KycController::class, 'submit'], ['auth', 'csrf', 'throttle:kycsub2,200,3600']],
    ['POST', '/api/kyc/sign',        [MediaController::class, 'signKyc'],      ['auth', 'csrf', 'throttle:sign,60,3600']],

    // Tableau de bord administrateur (staff) : vue d'ensemble + outils
    ['GET',  '/admin',               [AdminDashboardController::class, 'index'], ['staff']],

    // Espace de modération KYC (admins / modérateurs)
    ['GET',  '/admin/kyc',           [AdminKycController::class, 'index'],     ['staff']],
    ['GET',  '/admin/kyc/doc/{id}',  [AdminKycController::class, 'document'],  ['staff']],
    ['GET',  '/admin/kyc/{id}',      [AdminKycController::class, 'show'],      ['staff']],
    ['POST', '/admin/kyc/{id}/review', [AdminKycController::class, 'review'],  ['staff', 'csrf']],

    // Annonces « À la une » : staff rédige ; admin valide ce que les modérateurs proposent.
    ['GET',  '/admin/annonces',              [AnnouncementController::class, 'index'],   ['staff']],
    ['POST', '/admin/annonces',              [AnnouncementController::class, 'store'],   ['staff', 'csrf', 'throttle:annonce,30,3600']],
    ['POST', '/admin/annonces/{id}/valider', [AnnouncementController::class, 'review'],  ['staff', 'csrf']],
    ['POST', '/admin/annonces/{id}/supprimer', [AnnouncementController::class, 'destroy'], ['staff', 'csrf']],
    // Page publique d'article (annonce approuvée).
    ['GET',  '/info/{slug}',                  [AnnouncementController::class, 'show'],    []],

    // Retraits vendeurs : back-office (staff) — versement manuel.
    ['GET',  '/admin/retraits',               [WalletController::class, 'adminIndex'],   ['staff']],
    ['POST', '/admin/retraits/{id}/traiter',  [WalletController::class, 'adminProcess'], ['staff', 'csrf']],
    // Régie publicitaire : back-office (staff) — campagnes + revenu.
    ['GET',  '/admin/publicite',              [AdminAdsController::class, 'index'],  ['staff']],
    ['POST', '/admin/publicite/{pid}/action', [AdminAdsController::class, 'action'], ['staff', 'csrf']],
    // Régie marketing : composer + diffuser la lettre d'information (staff).
    ['GET',  '/admin/newsletter',  [AdminNewsletterController::class, 'index'], ['staff']],
    ['POST', '/admin/newsletter',  [AdminNewsletterController::class, 'send'],  ['staff', 'csrf', 'throttle:nlsend,10,3600']],
    // Outil de démo TEMPORAIRE (staff) — à retirer après la démo.
    ['GET',  '/admin/demo',         [AdminDemoController::class, 'index'], ['staff']],
    ['POST', '/admin/demo/creer',   [AdminDemoController::class, 'seed'],  ['staff', 'csrf']],
    ['POST', '/admin/demo/retirer', [AdminDemoController::class, 'purge'], ['staff', 'csrf']],
    // Diagnostic e-mail (staff) : configuration effective + envoi d'un test.
    ['GET',  '/admin/email',       [AdminMailController::class, 'index'],    ['staff']],
    ['POST', '/admin/email/test',  [AdminMailController::class, 'sendTest'],  ['staff', 'csrf', 'throttle:mailtest,10,3600']],
    ['GET',  '/vendeur/reglages',  [SellerController::class, 'settings'],     ['auth']],
    ['POST', '/vendeur/reglages',  [SellerController::class, 'updateSettings'], ['auth', 'csrf', 'throttle:profile,30,3600']],
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
    ['POST', '/annonce/{pid}/promouvoir', [ListingController::class, 'promote'],  ['auth', 'csrf', 'throttle:listing,20,3600']],

    // Signature des envois médias directs navigateur → Cloudinary
    ['POST', '/api/media/sign',          [MediaController::class, 'sign'],        ['auth', 'csrf', 'throttle:sign,60,3600']],
    ['GET',  '/api/geo/reverse',         [GeoController::class, 'reverse'],       ['throttle:geo,60,3600']],
    ['GET',  '/api/geo/session',         [GeoController::class, 'session'],       ['throttle:geosess,120,3600']],

    // Boutique en ligne (assistant de création + gestion + page publique)
    ['GET',  '/boutique/creer',  [BoutiqueController::class, 'create'],  ['auth']],
    ['POST', '/boutique/creer',  [BoutiqueController::class, 'submit'],  ['auth', 'csrf', 'throttle:shop,300,3600']],
    ['POST', '/vendeur/boutique-active', [BoutiqueController::class, 'switchBoutique'], ['auth', 'csrf']],
    ['GET',  '/api/boutique/slug', [BoutiqueController::class, 'checkSlug'], ['auth', 'throttle:slug,120,3600']],
    ['GET',  '/boutique/gerer',  [BoutiqueController::class, 'manage'],  ['auth']],
    ['POST', '/boutique/publier', [BoutiqueController::class, 'publish'], ['auth', 'csrf']],
    ['GET',  '/boutique/modifier', [BoutiqueController::class, 'edit'],       ['auth']],
    ['POST', '/boutique/modifier', [BoutiqueController::class, 'updateShop'], ['auth', 'csrf', 'throttle:shop,300,3600']],
    // Suppression de la boutique, confirmée par un code à 6 chiffres envoyé par e-mail.
    ['GET',  '/boutique/supprimer',      [BoutiqueController::class, 'deleteForm'],    ['auth']],
    ['POST', '/boutique/supprimer/code', [BoutiqueController::class, 'requestDelete'], ['auth', 'csrf', 'throttle:shopdel,6,3600']],
    ['POST', '/boutique/supprimer',      [BoutiqueController::class, 'confirmDelete'], ['auth', 'csrf', 'throttle:shopdelc,30,3600']],
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
    ['GET',  '/boutique/commande/{ref}/facture', [BoutiqueController::class, 'invoice'], []],
    ['POST', '/boutique/commande/{ref}/recommander', [BoutiqueController::class, 'reorder'], ['csrf', 'throttle:border,40,3600']],
    ['POST', '/boutique/commande/{ref}/annuler',     [BoutiqueController::class, 'cancelOrder'],   ['csrf', 'throttle:border,40,3600']],
    ['POST', '/boutique/commande/{ref}/retour',      [BoutiqueController::class, 'requestReturn'], ['csrf', 'throttle:border,40,3600']],
    // Paiement en ligne de la commande (public ; PSP réel ou bac à sable de simulation)
    ['POST', '/boutique/commande/{ref}/payer',  [BoutiqueController::class, 'payStart'],   ['csrf', 'throttle:bpay,30,3600']],
    ['GET',  '/boutique/commande/{ref}/regler', [BoutiqueController::class, 'paySandbox'], []],
    ['POST', '/boutique/commande/{ref}/regler', [BoutiqueController::class, 'paySettle'],  ['csrf', 'throttle:bpay,30,3600']],
    ['GET',  '/boutique/commande/{ref}/retour', [BoutiqueController::class, 'payReturn'],  []],
    // Avis & notes + alerte retour en stock
    ['POST', '/boutique/{slug}/p/{pid}/avis',         [BoutiqueController::class, 'storeReview'],      ['auth', 'csrf', 'throttle:review,10,3600']],
    ['POST', '/boutique/{slug}/p/{pid}/alerte-stock', [BoutiqueController::class, 'storeStockAlert'],  ['csrf', 'throttle:review,10,3600']],
    ['POST', '/boutique/{slug}/assistant',            [BoutiqueController::class, 'assistant'],        ['csrf', 'throttle:assistant,30,3600']],
    ['POST', '/boutique/avis/{rid}/masquer',  [BoutiqueController::class, 'hideReview'],  ['auth', 'csrf']],
    ['POST', '/boutique/politique',           [BoutiqueController::class, 'updatePolicy'], ['auth', 'csrf']],
    ['POST', '/boutique/promotions',              [BoutiqueController::class, 'createDiscount'], ['auth', 'csrf']],
    ['POST', '/boutique/promotions/{id}/statut',  [BoutiqueController::class, 'toggleDiscount'], ['auth', 'csrf']],
    ['POST', '/boutique/livraison/zones',             [BoutiqueController::class, 'createShippingZone'], ['auth', 'csrf', 'throttle:shop,300,3600']],
    ['POST', '/boutique/livraison/zones/{zid}/suppr', [BoutiqueController::class, 'deleteShippingZone'], ['auth', 'csrf']],
    // Vitrine publique
    ['GET',  '/boutique/{slug}/p/{pid}', [BoutiqueController::class, 'product'], []],
    ['GET',  '/boutique/{slug}',         [BoutiqueController::class, 'show'],    []],

    // Restaurant (vitrine + carte/menu + page publique)
    ['GET',  '/restaurant/creer',  [RestaurantController::class, 'create'], ['auth']],
    ['POST', '/restaurant/creer',  [RestaurantController::class, 'store'],  ['auth', 'csrf', 'throttle:shop,300,3600']],
    ['GET',  '/restaurant/gerer',  [RestaurantController::class, 'manage'], ['auth']],
    ['POST', '/restaurant/publier', [RestaurantController::class, 'publish'], ['auth', 'csrf']],
    ['POST', '/restaurant/categorie',            [RestaurantController::class, 'storeCategory'],  ['auth', 'csrf', 'throttle:product,80,3600']],
    ['POST', '/restaurant/categorie/{cid}/suppr', [RestaurantController::class, 'deleteCategory'], ['auth', 'csrf']],
    ['POST', '/restaurant/categorie/{cid}/renommer', [RestaurantController::class, 'renameCategory'], ['auth', 'csrf']],
    ['POST', '/restaurant/plat',                 [RestaurantController::class, 'storeItem'],      ['auth', 'csrf', 'throttle:product,120,3600']],
    ['POST', '/restaurant/plat/{mid}/statut',    [RestaurantController::class, 'setItemStatus'],  ['auth', 'csrf']],
    ['POST', '/restaurant/plat/{mid}/contenance', [RestaurantController::class, 'setVariantStatus'], ['auth', 'csrf']],
    ['POST', '/restaurant/paiement',             [RestaurantController::class, 'updatePayment'],   ['auth', 'csrf']],
    ['POST', '/restaurant/livraison/zones',             [RestaurantController::class, 'createDeliveryArea'], ['auth', 'csrf', 'throttle:shop,300,3600']],
    ['POST', '/restaurant/livraison/zones/{zid}/suppr', [RestaurantController::class, 'deleteDeliveryArea'], ['auth', 'csrf']],
    // Commandes restaurant : panier public + suivi côté restaurateur
    ['GET',  '/restaurant/commandes',            [RestaurantController::class, 'orders'],          ['auth']],
    ['POST', '/restaurant/commandes/{ref}/statut', [RestaurantController::class, 'setOrderStatus'], ['auth', 'csrf']],
    ['POST', '/restaurant/{slug}/caisse',         [RestaurantController::class, 'caisseStore'],     ['csrf', 'throttle:rorder,80,3600']],
    ['GET',  '/restaurant/{slug}/caisse',         [RestaurantController::class, 'caisse'],          ['auth']],
    ['POST', '/restaurant/{slug}/commander',      [RestaurantController::class, 'checkout'],        ['auth', 'csrf', 'throttle:rorder,40,3600']],
    ['GET',  '/restaurant/commande/{ref}',        [RestaurantController::class, 'orderConfirmation'], []],
    ['POST', '/restaurant/commande/{ref}/payer',  [RestaurantController::class, 'payStart'],        ['csrf', 'throttle:rorder,30,3600']],
    ['GET',  '/restaurant/commande/{ref}/regler', [RestaurantController::class, 'paySandbox'],      []],
    ['POST', '/restaurant/commande/{ref}/regler', [RestaurantController::class, 'paySettle'],       ['csrf', 'throttle:rorder,30,3600']],
    ['GET',  '/restaurant/commande/{ref}/retour', [RestaurantController::class, 'payReturn'],       []],
    ['GET',  '/restaurant/{slug}', [RestaurantController::class, 'show'], []],

    // Webhooks PSP — SOURCE DE VÉRITÉ de l'encaissement (sans csrf/auth : la
    // signature authentifie). Corps brut lu via php://input.
    ['POST', '/webhooks/stripe',   [WebhookController::class, 'stripe'],   ['throttle:webhook,300,3600']],
    ['POST', '/webhooks/cinetpay', [WebhookController::class, 'cinetpay'], ['throttle:webhook,300,3600']],

    // Signalement « ce n'était pas moi » (lien reçu par e-mail, sans connexion)
    ['GET',  '/signaler-vitrine', [ReportController::class, 'storefront'], ['throttle:report,20,3600']],

    // Roadmap interstitials for not-yet-built dashboard actions
    ['GET',  '/bientot/{feature}', [DashboardController::class, 'comingSoon'],    ['auth']],
];
