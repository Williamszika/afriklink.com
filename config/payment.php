<?php
declare(strict_types=1);

/**
 * Encaissement en ligne — ossature multi-fournisseurs. Chaque fournisseur
 * s'active dès que ses variables d'environnement (clés API) sont présentes ;
 * tant qu'elles manquent, il reste « à configurer ». Le fournisseur
 * « simulation » est toujours actif (bac à sable, sans argent réel) pour
 * tester tout le parcours dès maintenant.
 *
 * L'intégration réelle (appels API CinetPay/Stripe/PayPal) se branche dans
 * les classes App\Services\Payment\*Provider quand les comptes existent.
 */
return [
    // Commission marketplace (%) — NON appliquée par défaut : le vendeur garde 100 %
    // sur les ventes SANS affiliation. AfrikaLink se rémunère uniquement via l'affiliation
    // (part fixe ci-dessous). Conservée comme réglage si l'on souhaite réactiver un jour.
    'platform_commission_pct' => (float) env('PLATFORM_COMMISSION_PCT', 5.0),

    // Affiliation PAR PRODUIT : le vendeur fixe un taux R % (ce qu'il veut, dans les
    // bornes). Sur une vente via apporteur, R % est retranché du vendeur, dont la
    // plateforme garde ce pourcentage FIXE, et l'apporteur touche le reste (R − keep).
    'affiliate_platform_keep_pct' => (float) env('AFFILIATE_PLATFORM_KEEP_PCT', 1.5),
    // Taux d'affiliation MAX qu'un vendeur peut fixer par produit (garde-fou).
    'affiliate_max_rate_pct' => (float) env('AFFILIATE_MAX_RATE_PCT', 50.0),

    // Fournisseur par défaut proposé au vendeur.
    'default' => 'simulation',

    'providers' => [
        'simulation' => [
            'label'   => 'Simulation (test)',
            'desc'    => 'Bac à sable sans argent réel, pour tester le parcours de paiement.',
            'regions' => ['africa', 'europe'],
            'methods' => ['cash', 'mobile_money', 'card', 'paypal', 'apple_pay', 'google_pay'],
            'always'  => true,
        ],
        'cinetpay' => [
            'label'   => 'CinetPay',
            'desc'    => 'Mobile Money (Wave, Orange, MTN, Moov…) + cartes — Afrique de l’Ouest.',
            'regions' => ['africa'],
            'methods' => ['mobile_money', 'card'],
            'env'     => ['CINETPAY_API_KEY', 'CINETPAY_SITE_ID'],
            'docs'    => 'https://docs.cinetpay.com',
        ],
        'stripe' => [
            'label'   => 'Stripe',
            'desc'    => 'Cartes, Apple Pay, Google Pay — international (Europe).',
            'regions' => ['europe'],
            'methods' => ['card', 'apple_pay', 'google_pay'],
            'env'     => ['STRIPE_SECRET_KEY'],
            'docs'    => 'https://stripe.com/docs',
        ],
        'paypal' => [
            'label'   => 'PayPal',
            'desc'    => 'Compte PayPal et cartes via PayPal.',
            'regions' => ['africa', 'europe'],
            'methods' => ['paypal', 'card'],
            'env'     => ['PAYPAL_CLIENT_ID', 'PAYPAL_SECRET'],
            'docs'    => 'https://developer.paypal.com',
        ],
    ],
];
