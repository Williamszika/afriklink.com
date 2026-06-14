<?php
declare(strict_types=1);

/**
 * Transporteurs (livraison) — ossature prête à recevoir les API.
 *
 * Aujourd'hui le suivi est MANUEL : à l'expédition, le vendeur choisit un
 * transporteur et colle le numéro de suivi ; on en déduit un lien cliquable
 * via le gabarit d'URL ci-dessous ({tracking} = le numéro). Le client voit le
 * transporteur, le numéro et le bouton « Suivre le colis » — sans aucune API.
 *
 * DEMAIN (« on connectera les API des livraisons ») : pour un transporteur dont
 * `api => true`, une classe App\Services\Delivery\*Provider créera l'expédition
 * et récupérera le statut en direct dès que ses clés (env) seront présentes —
 * exactement le même schéma que les fournisseurs de paiement. Tant que les clés
 * manquent, on reste en mode manuel : rien ne casse.
 */
return [
    // Transporteur proposé par défaut dans le sélecteur vendeur.
    'default' => 'other',

    // {tracking} est remplacé par le numéro de suivi (URL-encodé). url=null => pas
    // de lien automatique (le client a quand même le transporteur + le numéro).
    'carriers' => [
        'dhl' => [
            'label' => 'DHL Express',
            'url'   => 'https://www.dhl.com/global-en/home/tracking/tracking-express.html?submit=1&tracking-id={tracking}',
            'api'   => false, // brancher DHLProvider + DHL_API_KEY plus tard
        ],
        'chronopost' => [
            'label' => 'Chronopost',
            'url'   => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}',
            'api'   => false,
        ],
        'colissimo' => [
            'label' => 'Colissimo / La Poste',
            'url'   => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
            'api'   => false,
        ],
        'ups' => [
            'label' => 'UPS',
            'url'   => 'https://www.ups.com/track?tracknum={tracking}',
            'api'   => false,
        ],
        'fedex' => [
            'label' => 'FedEx',
            'url'   => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
            'api'   => false,
        ],
        'aramex' => [
            'label' => 'Aramex',
            'url'   => 'https://www.aramex.com/track/results?ShipmentNumber={tracking}',
            'api'   => false,
        ],
        'dpd' => [
            'label' => 'DPD',
            'url'   => 'https://tracking.dpd.de/status/en_US/parcel/{tracking}',
            'api'   => false,
        ],
        // Coursier local, livraison en main propre, transporteur non listé… : pas
        // de lien de suivi automatique, mais le numéro reste affiché au client.
        'other' => [
            'label' => null, // libellé i18n: order.carrier.other
            'url'   => null,
            'api'   => false,
        ],
    ],
];
