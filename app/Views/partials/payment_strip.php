<?php
/**
 * Bandeau « moyens de paiement acceptés » façon grandes plateformes : une rangée
 * de logos de marques reconnaissables (cartes, wallets, mobile money) + un badge
 * « paiement sécurisé » optionnel. SVG inline (pas de requête réseau, OK partout).
 *
 * Variables : $only (list<string> clés à afficher, sinon toutes), $label (bool,
 * afficher l'intitulé), $secure (bool, afficher le badge sécurité), $compact.
 */
$only    = $only    ?? [];          // ex. ['visa','mastercard','paypal']
$label   = $label   ?? true;
$secure  = $secure  ?? false;
$compact = $compact ?? false;

$BRANDS = [
    'visa' => '<svg viewBox="0 0 48 16" aria-hidden="true"><text x="24" y="13" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-style="italic" font-weight="700" font-size="13" letter-spacing="1.5" fill="#1A1F71">VISA</text></svg>',
    'mastercard' => '<svg viewBox="0 0 48 30" aria-hidden="true"><circle cx="19" cy="15" r="11" fill="#EB001B"/><circle cx="29" cy="15" r="11" fill="#F79E1B"/><path d="M24 6.5a11 11 0 0 0 0 17 11 11 0 0 0 0-17z" fill="#FF5F00"/></svg>',
    'amex' => '<svg viewBox="0 0 48 30" aria-hidden="true"><rect width="48" height="30" rx="3" fill="#2E77BC"/><text x="24" y="14" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="8" fill="#fff">AMERICAN</text><text x="24" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="8" fill="#fff">EXPRESS</text></svg>',
    'paypal' => '<svg viewBox="0 0 64 18" aria-hidden="true"><text x="2" y="14" font-family="Arial,sans-serif" font-style="italic" font-weight="800" font-size="14" fill="#003087">Pay</text><text x="31" y="14" font-family="Arial,sans-serif" font-style="italic" font-weight="800" font-size="14" fill="#0079C1">Pal</text></svg>',
    'applepay' => '<svg viewBox="0 0 60 20" aria-hidden="true"><path d="M12.4 6.2c.5-.6.8-1.5.7-2.4-.8 0-1.7.5-2.2 1.1-.5.6-.9 1.5-.8 2.3.9.1 1.7-.4 2.3-1zM13.2 7.4c-1.2-.1-2.2.7-2.8.7-.6 0-1.5-.7-2.4-.6-1.2 0-2.4.7-3 1.8-1.3 2.2-.3 5.5.9 7.3.6.9 1.3 1.9 2.3 1.8.9 0 1.3-.6 2.4-.6s1.4.6 2.4.6c1 0 1.6-.9 2.2-1.8.7-1 1-2 1-2.1-.1 0-1.9-.8-1.9-2.9 0-1.8 1.4-2.6 1.5-2.7-.8-1.2-2.1-1.4-2.6-1.5z" fill="#000"/><text x="22" y="15" font-family="Arial,sans-serif" font-weight="600" font-size="13" fill="#000">Pay</text></svg>',
    'googlepay' => '<svg viewBox="0 0 62 20" aria-hidden="true"><path d="M9.6 10v2.3h3.3c-.1.8-.6 1.5-1.3 1.9l2 1.6c1.2-1.1 1.9-2.7 1.9-4.7 0-.5 0-.9-.1-1.1H9.6z" fill="#4285F4"/><path d="M9.6 16.4c1.7 0 3.1-.6 4.1-1.5l-2-1.6c-.6.4-1.3.6-2.1.6-1.6 0-3-1.1-3.5-2.6l-2.1 1.6c1 2 3.1 3.5 5.6 3.5z" fill="#34A853"/><path d="M6.1 11.3c-.1-.4-.2-.8-.2-1.3s.1-.9.2-1.3L4 7.1C3.6 8 3.4 9 3.4 10s.2 2 .6 2.9l2.1-1.6z" fill="#FBBC04"/><path d="M9.6 6.1c.9 0 1.7.3 2.3.9l1.8-1.8C12.7 4.2 11.3 3.6 9.6 3.6 7.1 3.6 5 5.1 4 7.1l2.1 1.6c.5-1.5 1.9-2.6 3.5-2.6z" fill="#EA4335"/><text x="25" y="15" font-family="Arial,sans-serif" font-weight="600" font-size="13" fill="#5F6368">Pay</text></svg>',
    'orange_money' => '<svg viewBox="0 0 54 30" aria-hidden="true"><rect width="54" height="30" rx="3" fill="#FF7900"/><rect x="6" y="6" width="9" height="9" fill="#000"/><text x="32" y="13" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="8" fill="#000">Orange</text><text x="32" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="8" fill="#fff">Money</text></svg>',
    'mtn' => '<svg viewBox="0 0 48 30" aria-hidden="true"><rect width="48" height="30" rx="15" fill="#FFCC00"/><text x="24" y="14" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="9" fill="#004F9F">MTN</text><text x="24" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="7" fill="#000">MoMo</text></svg>',
    'wave' => '<svg viewBox="0 0 48 30" aria-hidden="true"><rect width="48" height="30" rx="6" fill="#1DC8FF"/><path d="M6 19c4-6 8-6 12 0s8 6 12 0" fill="none" stroke="#0A2A66" stroke-width="2.4"/><text x="24" y="12" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="8" fill="#0A2A66">Wave</text></svg>',
    'moov' => '<svg viewBox="0 0 54 30" aria-hidden="true"><rect width="54" height="30" rx="3" fill="#0070C0"/><text x="27" y="14" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="10" fill="#fff">Moov</text><text x="27" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="7" fill="#FF7900">Money</text></svg>',
];

$labels = [
    'visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'American Express',
    'paypal' => 'PayPal', 'applepay' => 'Apple Pay', 'googlepay' => 'Google Pay',
    'orange_money' => 'Orange Money', 'mtn' => 'MTN MoMo', 'wave' => 'Wave', 'moov' => 'Moov Money',
];

$keys = $only !== [] ? array_values(array_intersect(array_keys($BRANDS), $only)) : array_keys($BRANDS);
if ($keys === []) {
    return;
}
?>
<div class="pay-strip<?= $compact ? ' pay-strip--compact' : '' ?>">
    <?php if ($label): ?><span class="pay-strip__label"><?= e(t('pay.accepted')) ?></span><?php endif; ?>
    <span class="pay-strip__tiles">
        <?php foreach ($keys as $k): ?>
            <span class="pay-tile" title="<?= e($labels[$k] ?? $k) ?>" role="img" aria-label="<?= e($labels[$k] ?? $k) ?>"><?= $BRANDS[$k] ?></span>
        <?php endforeach; ?>
    </span>
    <?php if ($secure): ?>
        <span class="pay-secure"><?= icon('lock', ['size' => 14]) ?> <?= e(t('pay.secure')) ?></span>
    <?php endif; ?>
</div>
