<?php
declare(strict_types=1);

/**
 * Publicité « AfrikLink Ads » — forfaits de mise en avant à durée fixe.
 *
 * Un vendeur achète un FORFAIT (un emplacement × une durée) pour mettre une de
 * ses offres en avant. Le prix est exprimé dans la devise de référence
 * (base_currency) puis converti à l'affichage et au règlement dans la devise du
 * vendeur (ExchangeRates).
 *
 * Le règlement passe par la même abstraction de paiement que le reste de la
 * plateforme : tant qu'aucun PSP n'est branché, le mode 'simulation' active la
 * campagne en bac à sable (sans argent réel) — exactement comme les autres
 * paiements du site. En 'wallet', le forfait est débité du porte-monnaie
 * vendeur. 'stripe' viendra avec les clés API.
 */
return [
    // 'simulation' (défaut, bac à sable) | 'wallet' (débite le solde vendeur) | 'stripe' (à venir)
    'billing' => (string) env('ADS_BILLING', 'simulation'),

    // Devise de référence des tarifs.
    'base_currency' => 'EUR',

    // Emplacements vendables. Chaque emplacement définit :
    //  - slots    : nombre de créneaux affichés EN ROTATION (l'accueil ne devient
    //               jamais un mur de pub) ;
    //  - packages : durée (jours) => prix en centimes de la devise de référence.
    'placements' => [
        'home' => [
            'slots'    => 8,
            'packages' => [
                7  => 499,
                15 => 899,
                30 => 1499,
            ],
        ],
    ],

    // Objets sponsorisables. MVP : produit. (boutique & annonce : Phase 2.)
    'objects' => ['product'],
];
