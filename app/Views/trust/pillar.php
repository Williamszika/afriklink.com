<?php
/** @var string $pillar  Page « pilier de confiance » : explique le système + renvoie à la fonctionnalité. */
$pillar = $pillar ?? 'payments';
$en = current_locale() === 'en';
$meta = [
    'payments' => ['ic' => '🔒', 'href' => '/paiements-securises'],
    'verified' => ['ic' => '🛡️', 'href' => '/vendeurs-verifies'],
    'intl'     => ['ic' => '🌍', 'href' => '/local-international'],
    'support'  => ['ic' => '💬', 'href' => '/assistance'],
];
$C = [
    'payments' => [
        'fr' => ['kicker' => 'Confiance', 'title' => 'Paiements sécurisés',
            'lead' => 'Achetez et vendez l’esprit tranquille : vos paiements sont protégés à chaque étape.',
            'points' => [
                ['t' => 'Vos données ne transitent jamais par nous', 'd' => 'Les paiements par carte sont traités par des prestataires certifiés (PCI-DSS, Stripe/PSP). Afriklink ne stocke aucune donnée bancaire.'],
                ['t' => 'Plusieurs moyens de paiement', 'd' => 'Carte bancaire, mobile money et paiement à la livraison — selon ce que propose chaque vendeur, en plusieurs devises.'],
                ['t' => 'Reçu et suivi pour chaque commande', 'd' => 'Chaque achat crée une commande traçable (confirmée → expédiée → livrée), avec sa facture.'],
            ], 'cta' => 'Parcourir en confiance', 'cta_href' => '/explorer'],
        'en' => ['kicker' => 'Trust', 'title' => 'Secure payments',
            'lead' => 'Buy and sell with peace of mind: your payments are protected at every step.',
            'points' => [
                ['t' => 'Your data never passes through us', 'd' => 'Card payments are handled by certified processors (PCI-DSS, Stripe/PSP). Afriklink stores no banking data.'],
                ['t' => 'Several payment methods', 'd' => 'Card, mobile money and cash on delivery — depending on each seller, in multiple currencies.'],
                ['t' => 'Receipt and tracking for every order', 'd' => 'Every purchase creates a traceable order (confirmed → shipped → delivered), with its invoice.'],
            ], 'cta' => 'Browse with confidence', 'cta_href' => '/explorer'],
    ],
    'verified' => [
        'fr' => ['kicker' => 'Confiance', 'title' => 'Vendeurs vérifiés',
            'lead' => 'Des professionnels dont l’identité est réellement contrôlée.',
            'points' => [
                ['t' => 'Vérification d’identité (KYC)', 'd' => 'Avant d’obtenir le badge, le vendeur fournit pièce d’identité, selfie et justificatif — vérifiés par notre équipe.'],
                ['t' => 'Le badge ✓ Vendeur vérifié', 'd' => 'Sur une vitrine, ce badge signifie que l’identité du professionnel a été confirmée par Afriklink.'],
                ['t' => 'Avis & achats vérifiés', 'd' => 'Un avis ne peut être laissé qu’après réception du produit, avec la mention « achat vérifié » : la réputation est fiable.'],
            ], 'cta' => 'Devenir vendeur vérifié', 'cta_href' => '/register/vendeur'],
        'en' => ['kicker' => 'Trust', 'title' => 'Verified sellers',
            'lead' => 'Professionals whose identity is genuinely checked.',
            'points' => [
                ['t' => 'Identity verification (KYC)', 'd' => 'Before getting the badge, the seller provides ID, a selfie and proof of address — reviewed by our team.'],
                ['t' => 'The ✓ Verified seller badge', 'd' => 'On a storefront, this badge means the professional’s identity has been confirmed by Afriklink.'],
                ['t' => 'Verified reviews & purchases', 'd' => 'A review can only be left after receiving the product, marked “verified purchase” — reputation you can trust.'],
            ], 'cta' => 'Become a verified seller', 'cta_href' => '/register/vendeur'],
    ],
    'intl' => [
        'fr' => ['kicker' => 'Sans frontières', 'title' => 'Local & international',
            'lead' => 'Vendez près de chez vous comme à l’étranger, dans la langue et la monnaie de chacun.',
            'points' => [
                ['t' => 'Multi-devises', 'd' => 'Les prix s’affichent dans la devise du visiteur (F CFA, €, £…) ; le règlement se fait dans la devise de la boutique.'],
                ['t' => 'Multi-langues', 'd' => 'L’interface est disponible en plusieurs langues (français, anglais…), pour acheter et vendre sans barrière.'],
                ['t' => 'Livraison locale et internationale', 'd' => 'Chaque vendeur définit ses zones et frais : retrait sur place, livraison nationale et à l’international.'],
            ], 'cta' => 'Explorer le catalogue', 'cta_href' => '/explorer'],
        'en' => ['kicker' => 'Borderless', 'title' => 'Local & international',
            'lead' => 'Sell next door or abroad, in everyone’s language and currency.',
            'points' => [
                ['t' => 'Multi-currency', 'd' => 'Prices show in the visitor’s currency (CFA, €, £…); payment is made in the shop’s currency.'],
                ['t' => 'Multi-language', 'd' => 'The interface is available in several languages (French, English…), to buy and sell without barriers.'],
                ['t' => 'Local and international delivery', 'd' => 'Each seller sets their zones and fees: local pickup, national and international shipping.'],
            ], 'cta' => 'Explore the catalogue', 'cta_href' => '/explorer'],
    ],
    'support' => [
        'fr' => ['kicker' => 'Accompagnement', 'title' => 'Assistance intégrée',
            'lead' => 'On vous accompagne, acheteurs comme vendeurs, à chaque étape.',
            'points' => [
                ['t' => 'Messagerie acheteur-vendeur', 'd' => 'Posez vos questions au vendeur avant et après l’achat, directement depuis la boutique.'],
                ['t' => 'Assistant d’achat', 'd' => 'Un assistant répond aux questions fréquentes (livraison, paiement, retours) sur chaque vitrine.'],
                ['t' => 'Notifications à chaque étape', 'd' => 'Vous êtes informé à la confirmation, l’expédition et la livraison de votre commande.'],
            ], 'cta' => 'Rejoindre Afriklink', 'cta_href' => '/register/vendeur'],
        'en' => ['kicker' => 'Support', 'title' => 'Built-in support',
            'lead' => 'We support you, buyers and sellers alike, at every step.',
            'points' => [
                ['t' => 'Buyer–seller messaging', 'd' => 'Ask the seller your questions before and after buying, right from the shop.'],
                ['t' => 'Shopping assistant', 'd' => 'An assistant answers common questions (delivery, payment, returns) on each storefront.'],
                ['t' => 'Notifications at every step', 'd' => 'You’re notified on confirmation, shipment and delivery of your order.'],
            ], 'cta' => 'Join Afriklink', 'cta_href' => '/register/vendeur'],
    ],
];
$p = $C[$pillar][$en ? 'en' : 'fr'] ?? $C['payments'][$en ? 'en' : 'fr'];
$others = array_values(array_filter(array_keys($meta), static fn (string $k): bool => $k !== $pillar));
?>
<section class="trust-page">
    <p class="muted"><a href="<?= e(url('/')) ?>">← <?= e($en ? 'Home' : 'Accueil') ?></a></p>

    <header class="trust-hero">
        <span class="trust-hero__ic" aria-hidden="true"><?= $meta[$pillar]['ic'] ?></span>
        <span class="afk-eyebrow">◆ <?= e($p['kicker']) ?></span>
        <h1 class="afk-h1"><?= e($p['title']) ?></h1>
        <p class="trust-lead"><?= e($p['lead']) ?></p>
    </header>

    <div class="trust-points">
        <?php foreach ($p['points'] as $i => $pt): ?>
            <div class="trust-point">
                <span class="trust-point__n"><?= (int) $i + 1 ?></span>
                <div><strong><?= e($pt['t']) ?></strong><p><?= e($pt['d']) ?></p></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="trust-cta">
        <a class="afk-btn afk-btn--gold afk-btn--lg" href="<?= e(url($p['cta_href'])) ?>"><?= e($p['cta']) ?></a>
    </div>

    <div class="trust-more">
        <h2 class="afk-h2"><?= e($en ? 'Our other guarantees' : 'Nos autres garanties') ?></h2>
        <div class="trust-more__grid">
            <?php foreach ($others as $o): ?>
                <a class="trust-more__item" href="<?= e(url($meta[$o]['href'])) ?>">
                    <span class="trust-more__ic" aria-hidden="true"><?= $meta[$o]['ic'] ?></span>
                    <span><?= e($C[$o][$en ? 'en' : 'fr']['title']) ?></span> →
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
