<?php
declare(strict_types=1);

/**
 * Base de connaissances de l'assistant. Chaque sujet :
 *   - screen : nom d'une capture dans public/assets/img/help/<screen>.png (ou null)
 *   - link   : lien d'action profond vers la bonne page (ou null)
 *   - kw     : mots-clés (repli SANS IA : on renvoie le sujet le mieux apparié)
 *   - fr/en  : contenu court qui (1) ancre l'IA et (2) sert de réponse de repli.
 *
 * Le contenu est volontairement concis et factuel : l'IA le reformule dans la
 * langue du visiteur ; le repli l'affiche tel quel (fr/en selon la locale).
 */
return [
    'topics' => [

        'create_account' => [
            'screen' => 'register',
            'link'   => '/register',
            'kw'     => ['compte', 'konto', 'cuenta', 'conto', 'conta', 'account', 'registrieren', 'registrarse', 'aanmaken', 'inscription', 'inscrire', 's inscrire', 'créer un compte', 'register', 'sign up', 'signup', 'account', 'créer compte', 'enregistrer'],
            'fr' => "Pour créer un compte : ouvrez « S'inscrire » puis choisissez votre profil — Particulier (acheter, vendre des annonces, parrainer) ou Vendeur pro (ouvrir une boutique). Renseignez un e-mail OU un téléphone, et un mot de passe d'au moins 12 caractères. Votre pays et votre ville sont détectés automatiquement et verrouillés.",
            'en' => "To create an account: open « Sign up » and pick your profile — Individual (buy, post listings, refer friends) or Pro seller (open a shop). Enter an email OR a phone number, plus a password of at least 12 characters. Your country and city are detected automatically and locked.",
        ],

        'become_seller' => [
            'screen' => 'create_shop',
            'link'   => '/register/vendeur',
            'kw'     => ['vendeur', 'verkaufen', 'verkäufer', 'vender', 'vendedor', 'vendere', 'venditore', 'verkopen', 'vendre', 'professionnel', 'pro', 'seller', 'sell', 'devenir vendeur', 'become seller', 'compte pro', 'business'],
            'fr' => "Pour vendre en pro : inscrivez-vous comme Vendeur via « S'inscrire → Vendeur pro » (/register/vendeur). Vous pourrez ensuite créer une boutique en ligne, un restaurant ou un salon, et publier vos produits. Un badge « Vendeur vérifié » s'obtient en fournissant votre numéro d'enregistrement.",
            'en' => "To sell professionally: register as a Seller via « Sign up → Pro seller » (/register/vendeur). You can then open an online shop, a restaurant or a salon and publish your products. A « Verified seller » badge is granted once you provide your registration number.",
        ],

        'create_shop' => [
            'screen' => 'create_shop',
            'link'   => '/boutique/creer',
            'kw'     => ['boutique', 'shop', 'laden', 'geschäft', 'tienda', 'negozio', 'loja', 'winkel', 'magasin', 'shop', 'créer boutique', 'open shop', 'créer une boutique', 'vitrine', 'storefront', 'store'],
            'fr' => "Créer une boutique : allez sur « Créer ma boutique » (/boutique/creer). L'assistant a 3 étapes — (1) infos de la boutique (nom, logo, catégorie), (2) livraison & paiement (devise pré-remplie selon votre pays, modes de livraison, transporteurs, moyens de paiement), (3) confirmation. Ajoutez ensuite vos produits.",
            'en' => "Create a shop: go to « Create my shop » (/boutique/creer). The wizard has 3 steps — (1) shop info (name, logo, category), (2) delivery & payment (currency pre-filled from your country, delivery methods, carriers, payment methods), (3) confirmation. Then add your products.",
        ],

        'first_steps' => [
            'screen' => 'dashboard',
            'link'   => '/dashboard',
            'kw'     => ['premiers pas', 'erste schritte', 'primeros pasos', 'primi passi', 'primeiros passos', 'eerste stappen', 'schritte', 'pasos', 'commencer', 'démarrer', 'first steps', 'get started', 'onboarding', 'débuter', 'tableau de bord', 'dashboard', 'que faire'],
            'fr' => "Vos premiers pas (vendeur) : 1) complétez votre profil, 2) créez votre boutique, 3) ajoutez un premier produit (photos nettes, prix), 4) réglez la livraison et les moyens de paiement, 5) publiez. Votre tableau de bord (/dashboard) affiche une liste de tâches et vos chiffres une fois actif.",
            'en' => "Your first steps (seller): 1) complete your profile, 2) create your shop, 3) add a first product (clear photos, price), 4) set delivery and payment methods, 5) publish. Your dashboard (/dashboard) shows a checklist and your figures once you're active.",
        ],

        'add_product' => [
            'screen' => 'add_product',
            'link'   => '/boutique/creer',
            'kw'     => ['produit', 'produkt', 'producto', 'prodotto', 'produto', 'artikel', 'hinzufügen', 'añadir', 'aggiungere', 'toevoegen', 'article', 'product', 'ajouter produit', 'add product', 'photo', 'stock', 'prix', 'price', 'variante', 'publier produit'],
            'fr' => "Ajouter un produit : depuis « Gérer ma boutique », ouvrez « Produits → Nouveau ». Mettez un nom clair, jusqu'à 5 photos, le prix (dans la devise de votre boutique), le stock et, si besoin, des variantes (taille, couleur). Vous pouvez aussi publier une simple annonce via « Vendre » (/vendre).",
            'en' => "Add a product: from « Manage my shop », open « Products → New ». Add a clear name, up to 5 photos, the price (in your shop currency), stock and, if needed, variants (size, colour). You can also post a simple listing via « Sell » (/vendre).",
        ],

        'payments' => [
            'screen' => null,
            'link'   => '/boutique/creer',
            'kw'     => ['paiement', 'zahlung', 'bezahlen', 'pago', 'pagar', 'pagamento', 'betaling', 'betalen', 'payer', 'payment', 'pay', 'mobile money', 'carte', 'card', 'paypal', 'espèces', 'cash', 'stripe', 'cinetpay', 'acompte', 'encaisser'],
            'fr' => "Paiements : le vendeur choisit QUAND le client paie (à la livraison, acompte, ou avant livraison) et COMMENT (espèces, Mobile Money, carte, PayPal, Apple/Google Pay). L'encaissement en ligne réel s'active quand les clés du prestataire sont fournies (Stripe pour la carte, CinetPay pour le Mobile Money) ; sinon le paiement se règle directement avec le vendeur.",
            'en' => "Payments: the seller chooses WHEN the customer pays (on delivery, deposit, or before delivery) and HOW (cash, Mobile Money, card, PayPal, Apple/Google Pay). Real online charging turns on once the provider keys are set (Stripe for cards, CinetPay for Mobile Money); otherwise payment is settled directly with the seller.",
        ],

        'delivery' => [
            'screen' => 'carriers',
            'link'   => '/boutique/creer',
            'kw'     => ['livraison', 'lieferung', 'versand', 'envío', 'entrega', 'spedizione', 'consegna', 'levering', 'verzending', 'transporteur', 'expédition', 'delivery', 'shipping', 'carrier', 'dhl', 'colis', 'frais de port', 'zone', 'envoi', 'transport'],
            'fr' => "Livraison : le vendeur définit ses modes (main propre, retrait, livraison) et peut proposer des transporteurs (DHL, Chronopost, La Poste CI, coursier local…) avec un tarif, par contexte (international UE↔CI, local UE, local Côte d'Ivoire). Au paiement, le client choisit le transporteur adapté à sa situation. À l'expédition, le vendeur colle le numéro de suivi et le client reçoit un lien « Suivre le colis ».",
            'en' => "Delivery: the seller sets their methods (hand-to-hand, pickup, delivery) and can offer carriers (DHL, Chronopost, La Poste CI, local courier…) with a price, per context (international EU↔CI, local EU, local Côte d'Ivoire). At checkout the customer picks the right carrier for their situation. When shipping, the seller pastes the tracking number and the customer gets a « Track parcel » link.",
        ],

        'currency_language' => [
            'screen' => null,
            'link'   => null,
            'kw'     => ['devise', 'währung', 'sprache', 'moneda', 'idioma', 'valuta', 'lingua', 'moeda', 'taal', 'monnaie', 'currency', 'langue', 'language', 'fcfa', 'euro', 'naira', 'conversion', 'taux', 'traduction', 'change'],
            'fr' => "Devise & langue : le site s'affiche automatiquement dans la langue et la devise de votre pays (détectés). Un acheteur voit le prix de la boutique ET l'équivalent dans sa monnaie locale ; sur son propre tableau de bord, chacun voit uniquement la devise de son pays. Vous pouvez forcer la langue/devise via les menus en haut.",
            'en' => "Currency & language: the site shows automatically in your country's language and currency (detected). A buyer sees the shop price AND the equivalent in their local currency; on their own dashboard, everyone sees only their country's currency. You can force language/currency via the top menus.",
        ],

        'orders' => [
            'screen' => null,
            'link'   => '/vendeur/commandes',
            'kw'     => ['commande', 'bestellung', 'pedido', 'ordine', 'bestelling', 'orders', 'auftrag', 'commandes', 'order', 'orders', 'suivi', 'tracking', 'statut', 'expédier', 'livrer', 'vente', 'gérer commande'],
            'fr' => "Commandes (vendeur) : retrouvez-les dans « Mes commandes » (/vendeur/commandes). Le flux est : Nouvelle → Confirmée → Expédiée (vous ajoutez le transporteur + numéro de suivi) → Livrée. Le client est prévenu à chaque étape par e-mail et SMS/WhatsApp. Côté acheteur, le suivi est dans « Mes achats ».",
            'en' => "Orders (seller): find them in « My orders » (/vendeur/commandes). The flow is: New → Confirmed → Shipped (you add the carrier + tracking number) → Delivered. The customer is notified at each step by email and SMS/WhatsApp. Buyers track theirs in « My purchases ».",
        ],

        'affiliation' => [
            'screen' => null,
            'link'   => '/affiliation',
            'kw'     => ['affiliation', 'partnerprogramm', 'afiliación', 'affiliazione', 'afiliação', 'provision', 'comisión', 'commissione', 'parrainage', 'commission', 'gagner', 'affiliate', 'referral', 'lien', 'partager', 'apporteur', 'earn'],
            'fr' => "Affiliation : partagez votre lien personnel (/affiliation) ou des liens produits ; vous touchez une commission sur chaque vente attribuée. Vos gains s'accumulent dans votre portefeuille d'affiliation, retirables dès le seuil atteint — affichés dans votre devise locale.",
            'en' => "Affiliation: share your personal link (/affiliation) or product links; you earn a commission on each attributed sale. Your earnings build up in your affiliation wallet, withdrawable once the threshold is reached — shown in your local currency.",
        ],

        'login_problem' => [
            'screen' => null,
            'link'   => '/login',
            'kw'     => ['connexion', 'anmelden', 'anmeldung', 'iniciar sesión', 'accedi', 'entrar', 'inloggen', 'passwort', 'contraseña', 'connecter', 'login', 'log in', 'mot de passe', 'password', 'oublié', 'forgot', 'problème connexion', 'accès', 'se connecter', 'reset'],
            'fr' => "Connexion : utilisez « Se connecter » (/login) avec votre e-mail OU votre téléphone et votre mot de passe. Mot de passe oublié ? Utilisez le lien de réinitialisation sur la page de connexion. Vérifiez aussi la langue/le pays détectés en haut si l'affichage vous semble inhabituel.",
            'en' => "Login: use « Log in » (/login) with your email OR phone and your password. Forgot your password? Use the reset link on the login page. Also check the detected language/country at the top if the display looks unusual.",
        ],

    ],
];
