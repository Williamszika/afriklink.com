<?php
/**
 * Conditions générales (CGU/CGV) — adaptées au pays détecté.
 *  EU/DE → rétractation 14 j + médiation ; CI → loi 2016-412 + transactions
 *  électroniques. Droit applicable = siège de l'éditeur. À faire valider.
 */
$en = current_locale() !== 'fr'; // de/es/it : repli sur l'anglais
$L  = legal_ctx($forced_cc ?? null);
$op = $L['operator'];
$rg = $L['data'];
$seat     = country_name($op['country_code'] ?? 'DE');
$consumer = $en ? ($rg['consumer_en'] ?? '') : ($rg['consumer_fr'] ?? '');
$dispute  = $en ? ($rg['dispute_en'] ?? '') : ($rg['dispute_fr'] ?? '');
$currency = $en ? ($rg['currency_en'] ?? '') : ($rg['currency_fr'] ?? '');
?>
<section class="legal-page">
    <h1><?= e(t('legal.terms.title')) ?><?php if ($L['is_eu']): ?> <span class="legal-aka">/ AGB</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/cgv']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <h2>1. <?= $en ? 'Purpose' : 'Objet' ?></h2>
    <p><?= $en ? 'These terms govern the use of the Afriklink marketplace and the sales concluded through it.' : "Les présentes conditions régissent l'utilisation de la marketplace Afriklink et les ventes qui y sont conclues." ?></p>

    <h2>2. <?= $en ? 'Role of Afriklink' : "Rôle d'Afriklink" ?></h2>
    <p><?= $en
        ? 'Afriklink is an intermediary. The sales contract is formed directly between the seller and the buyer. The seller is responsible for their products, prices, descriptions, stock, delivery and after-sales service.'
        : "Afriklink est un intermédiaire. Le contrat de vente est formé directement entre le vendeur et l'acheteur. Le vendeur est responsable de ses produits, prix, descriptions, stocks, livraison et service après-vente." ?></p>

    <h2>3. <?= $en ? 'Account' : 'Compte' ?></h2>
    <p><?= $en ? 'You must provide accurate information and keep your credentials confidential. Professional sellers complete an identity verification (KYC).' : "Vous devez fournir des informations exactes et garder vos identifiants confidentiels. Les vendeurs professionnels réalisent une vérification d'identité (KYC)." ?></p>

    <h2>4. <?= $en ? 'Professional sellers (P2B)' : 'Vendeurs professionnels (P2B)' ?></h2>
    <p><?= $en
        ? 'In accordance with Regulation (EU) 2019/1150 (P2B), the main ranking parameters of listings (relevance, availability, seller reputation, sponsorship) are disclosed, access to a seller\'s own data is provided, and account-suspension decisions are stated with reasons and an internal complaint channel.'
        : "Conformément au règlement (UE) 2019/1150 (P2B), les principaux paramètres de classement des annonces (pertinence, disponibilité, réputation du vendeur, sponsoring) sont communiqués, l'accès du vendeur à ses propres données est assuré, et les décisions de suspension de compte sont motivées avec une voie de réclamation interne." ?></p>

    <h2>5. <?= $en ? 'Orders, prices & payment' : 'Commandes, prix & paiement' ?></h2>
    <p><?= $en
        ? 'Prices are shown in the displayed currency, inclusive of taxes where applicable, with delivery costs indicated before the order. An order is firm once confirmed. Payment terms and methods are those offered by each seller.'
        : "Les prix sont affichés dans la devise indiquée, toutes taxes comprises le cas échéant, les frais de livraison étant indiqués avant la commande. La commande est ferme une fois confirmée. Les conditions et moyens de paiement sont ceux proposés par chaque vendeur." ?></p>
    <?php if ($currency !== ''): ?><p class="muted"><?= $en ? 'Currency for your region:' : 'Devise pour votre région :' ?> <?= e($currency) ?>.</p><?php endif; ?>

    <h2>6. <?= $en ? 'Delivery' : 'Livraison' ?></h2>
    <p><?= $en ? 'Delivery times and fees are indicated by the seller per zone (local / international). Risks transfer upon delivery.' : "Les délais et frais de livraison sont indiqués par le vendeur selon la zone (locale / internationale). Les risques sont transférés à la livraison." ?></p>

    <h2>7. <?= $en ? 'Right of withdrawal / returns' : 'Droit de rétractation / retours' ?></h2>
    <?php if ($rg['withdrawal'] ?? false): ?>
        <p><?= $en
            ? 'As a consumer you have 14 days from receipt to withdraw from an eligible distance purchase, without giving a reason, save for legal exceptions (perishables, made-to-order items, unsealed hygiene products, etc.).'
            : "En tant que consommateur, vous disposez de 14 jours à compter de la réception pour vous rétracter d'un achat à distance éligible, sans motif, sous réserve des exceptions légales (denrées périssables, produits sur mesure, articles d'hygiène descellés, etc.)." ?>
            <a href="<?= e(url('/retractation')) ?>"><?= $en ? 'See the withdrawal policy and model form' : 'Voir le détail et le formulaire-type de rétractation' ?></a>.</p>
    <?php else: ?>
        <p><?= $en
            ? 'Returns and refunds follow each seller\'s published policy and the consumer law applicable in your country. Contact the seller first; Afriklink helps in good faith.'
            : "Les retours et remboursements suivent la politique publiée par chaque vendeur et le droit de la consommation applicable dans votre pays. Contactez d'abord le vendeur ; Afriklink facilite la résolution de bonne foi." ?></p>
    <?php endif; ?>

    <h2>8. <?= $en ? 'Legal guarantees' : 'Garanties légales' ?></h2>
    <p><?= $en ? 'Consumers benefit from the legal guarantees of conformity and against hidden defects provided by ' : "Les consommateurs bénéficient des garanties légales de conformité et contre les vices cachés prévues par " ?><?= e($consumer) ?>.</p>

    <h2>9. <?= $en ? 'Complaints & dispute resolution' : 'Réclamations & résolution des litiges' ?></h2>
    <p><?= e($dispute) ?> <?= $en ? 'Contact:' : 'Contact :' ?> <strong><?= e($op['email'] ?? '') ?></strong>.</p>

    <h2>10. <?= $en ? 'Liability' : 'Responsabilité' ?></h2>
    <p><?= $en ? 'As an intermediary, Afriklink is not a party to the sale and is not liable for the sellers\' performance, within the limits permitted by law.' : "En tant qu'intermédiaire, Afriklink n'est pas partie à la vente et n'est pas responsable de l'exécution par les vendeurs, dans les limites permises par la loi." ?></p>

    <h2>11. <?= $en ? 'Governing law' : 'Droit applicable' ?></h2>
    <p><?= $en
        ? 'These terms are governed by the law of the publisher\'s place of establishment (' . e($seat) . '). Consumers also retain the mandatory protections of the law of their country of residence. Competent courts are determined by applicable law.'
        : "Les présentes conditions sont régies par le droit du lieu d'établissement de l'éditeur (" . e($seat) . "). Les consommateurs conservent en outre les protections impératives du droit de leur pays de résidence. Les juridictions compétentes sont déterminées par la loi applicable." ?></p>
</section>
