<?php
declare(strict_types=1);

/**
 * Taux de change INDICATIFS (affichage uniquement). Le règlement se fait
 * toujours dans la devise de la boutique ; l'équivalent montré à l'acheteur est
 * approximatif (préfixé « ≈ »). Exprimés en **unités de devise pour 1 EUR**.
 *
 * XOF / XAF (franc CFA) sont en **parité fixe** avec l'EUR (1 EUR = 655,957) —
 * exact. Les autres flottent : valeurs par défaut surchargées par l'environnement
 * (RATE_USD_PER_EUR…) ; à terme, rafraîchir depuis une source de taux.
 */
return [
    'base' => 'EUR',
    'per_eur' => [
        'EUR' => 1.0,
        'XOF' => 655.957,   // parité fixe CFA (UEMOA)
        'XAF' => 655.957,   // parité fixe CFA (CEMAC)
        'USD' => (float) env('RATE_USD_PER_EUR', 1.08),
        'GBP' => (float) env('RATE_GBP_PER_EUR', 0.85),
        'NGN' => (float) env('RATE_NGN_PER_EUR', 1750.0),
    ],
];
