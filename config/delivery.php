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
 * NIVEAU 1 (choix par le client au paiement) : chaque transporteur porte un ou
 * plusieurs `scopes` qui disent OÙ il est pertinent :
 *   - 'intl' : international (UE ↔ Côte d'Ivoire / monde).
 *   - 'eu'   : livraison locale dans un pays de l'Union européenne.
 *   - 'ci'   : livraison locale en Côte d'Ivoire.
 * Le vendeur active les transporteurs qu'il accepte et fixe un tarif pour
 * chacun ; au paiement, le client ne voit que ceux qui correspondent à sa
 * situation (local UE, local CI, ou international) et choisit le sien.
 *
 * DEMAIN (« on connectera les API des livraisons ») : pour un transporteur dont
 * `api => true`, une classe App\Services\Delivery\*Provider créera l'expédition
 * et récupérera le statut/tarif en direct dès que ses clés (env) seront
 * présentes — même schéma que les fournisseurs de paiement. Tant que les clés
 * manquent, on reste en mode manuel : rien ne casse.
 */
return [
    // Transporteur proposé par défaut dans le sélecteur vendeur.
    'default' => 'other',

    // {tracking} est remplacé par le numéro de suivi (URL-encodé). url=null => pas
    // de lien automatique (le client a quand même le transporteur + le numéro).
    'carriers' => [
        // ---- International (UE ↔ Côte d'Ivoire / monde), souvent local UE aussi ----
        'dhl' => [
            'label'  => 'DHL Express',
            'url'    => 'https://www.dhl.com/global-en/home/tracking/tracking-express.html?submit=1&tracking-id={tracking}',
            'scopes' => ['intl', 'eu', 'ci'], // DHL dessert aussi Abidjan
            'api'    => false, // brancher DHLProvider + DHL_API_KEY plus tard
        ],
        'fedex' => [
            'label'  => 'FedEx',
            'url'    => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
            'scopes' => ['intl', 'eu'],
            'api'    => false,
        ],
        'ups' => [
            'label'  => 'UPS',
            'url'    => 'https://www.ups.com/track?tracknum={tracking}',
            'scopes' => ['intl', 'eu'],
            'api'    => false,
        ],
        'chronopost' => [
            'label'  => 'Chronopost',
            'url'    => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}',
            'scopes' => ['intl', 'eu'], // Chronopost International expédie vers la CI
            'api'    => false,
        ],
        'colissimo' => [
            'label'  => 'Colissimo / La Poste',
            'url'    => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
            'scopes' => ['intl', 'eu'], // Colissimo International
            'api'    => false,
        ],
        'aramex' => [
            'label'  => 'Aramex',
            'url'    => 'https://www.aramex.com/track/results?ShipmentNumber={tracking}',
            'scopes' => ['intl'],
            'api'    => false,
        ],

        // ---- Local Union européenne ----
        'dpd' => [
            'label'  => 'DPD',
            'url'    => 'https://tracking.dpd.de/status/en_US/parcel/{tracking}',
            'scopes' => ['eu'],
            'api'    => false,
        ],
        'gls' => [
            'label'  => 'GLS',
            'url'    => 'https://gls-group.com/EU/en/parcel-tracking?match={tracking}',
            'scopes' => ['eu'],
            'api'    => false,
        ],
        'mondial_relay' => [
            'label'  => 'Mondial Relay',
            'url'    => 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition={tracking}',
            'scopes' => ['eu'],
            'api'    => false,
        ],

        // ---- Local Côte d'Ivoire ----
        // Pas de suivi en ligne fiable côté national → url=null (le client garde
        // le transporteur + le numéro ; lien de suivi non automatique).
        'laposte_ci' => [
            'label'  => 'La Poste (Côte d’Ivoire)',
            'url'    => null,
            'scopes' => ['ci'],
            'api'    => false,
        ],

        // Coursier / livraison à domicile (Abidjan & villes, ou point à point en
        // UE) : libellé i18n « Autre / coursier local », sans lien de suivi.
        'other' => [
            'label'  => null, // libellé i18n: order.carrier.other
            'url'    => null,
            'scopes' => ['intl', 'eu', 'ci'],
            'api'    => false,
        ],
    ],
];
