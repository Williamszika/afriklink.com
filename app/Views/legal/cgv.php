<?php
/** Conditions générales (CGU/CGV) — modèle à faire valider juridiquement. */
$en = current_locale() === 'en';
?>
<section class="legal-page">
    <h1><?= e(t('legal.terms.title')) ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '13/06/2026'])) ?></p>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <?php if ($en): ?>
        <h2>1. Purpose</h2>
        <p>These terms govern the use of the Afriklink marketplace and the sales concluded through it.</p>
        <h2>2. Role of Afriklink</h2>
        <p>Afriklink is an <strong>intermediary</strong>. The sales contract is formed directly between the <strong>seller</strong> and the <strong>buyer</strong>. The seller is responsible for their products, prices, descriptions, stock, delivery and after-sales service.</p>
        <h2>3. Account</h2>
        <p>You must provide accurate information and keep your credentials confidential. Professional sellers complete an identity verification (KYC).</p>
        <h2>4. Orders, prices &amp; payment</h2>
        <p>Prices are shown in the displayed currency, taxes where applicable. An order is firm once confirmed. Payment terms (deposit, on delivery, before delivery) and methods are those offered by each seller.</p>
        <h2>5. Delivery</h2>
        <p>Delivery times and fees are indicated by the seller per zone (local / international). Risks transfer upon delivery.</p>
        <h2>6. Right of withdrawal (EU)</h2>
        <p>For consumers in the EU, you have <strong>14 days</strong> from receipt to withdraw from an eligible distance purchase, without giving a reason, save for legal exceptions (e.g. perishable goods, made-to-order items, opened hygiene products). To exercise it, contact the seller; the goods are returned and refunded under the conditions of law.</p>
        <h2>7. Legal guarantees</h2>
        <p>Consumers benefit from the legal guarantees of conformity and against hidden defects provided by applicable law.</p>
        <h2>8. Returns &amp; refunds</h2>
        <p>Each seller publishes their return policy on their storefront. Refunds are processed using the original payment method.</p>
        <h2>9. Complaints &amp; dispute resolution</h2>
        <p>First contact the seller. If unresolved, contact Afriklink at [email]; we help mediate in good faith. EU consumers may also use the European ODR platform. This does not affect your right to go to court.</p>
        <h2>10. Liability</h2>
        <p>As an intermediary, Afriklink is not a party to the sale and is not liable for the sellers' performance, within the limits permitted by law.</p>
        <h2>11. Governing law</h2>
        <p>[Governing law and competent courts to complete according to your establishment and target markets].</p>
    <?php else: ?>
        <h2>1. Objet</h2>
        <p>Les présentes conditions régissent l'utilisation de la marketplace Afriklink et les ventes qui y sont conclues.</p>
        <h2>2. Rôle d'Afriklink</h2>
        <p>Afriklink est un <strong>intermédiaire</strong>. Le contrat de vente est formé directement entre le <strong>vendeur</strong> et l'<strong>acheteur</strong>. Le vendeur est responsable de ses produits, prix, descriptions, stocks, livraison et service après-vente.</p>
        <h2>3. Compte</h2>
        <p>Vous devez fournir des informations exactes et garder vos identifiants confidentiels. Les vendeurs professionnels réalisent une vérification d'identité (KYC).</p>
        <h2>4. Commandes, prix &amp; paiement</h2>
        <p>Les prix sont affichés dans la devise indiquée, taxes le cas échéant. La commande est ferme une fois confirmée. Les conditions de paiement (acompte, à la livraison, avant livraison) et les moyens proposés sont ceux de chaque vendeur.</p>
        <h2>5. Livraison</h2>
        <p>Les délais et frais de livraison sont indiqués par le vendeur selon la zone (locale / internationale). Les risques sont transférés à la livraison.</p>
        <h2>6. Droit de rétractation (UE)</h2>
        <p>Pour les consommateurs dans l'UE, vous disposez de <strong>14 jours</strong> à compter de la réception pour vous rétracter d'un achat à distance éligible, sans motif, sous réserve des exceptions légales (ex. denrées périssables, produits sur mesure, articles d'hygiène descellés). Pour l'exercer, contactez le vendeur ; le bien est retourné et remboursé dans les conditions prévues par la loi.</p>
        <h2>7. Garanties légales</h2>
        <p>Les consommateurs bénéficient des garanties légales de conformité et contre les vices cachés prévues par le droit applicable.</p>
        <h2>8. Retours &amp; remboursements</h2>
        <p>Chaque vendeur publie sa politique de retour sur sa vitrine. Les remboursements sont effectués via le moyen de paiement d'origine.</p>
        <h2>9. Réclamations &amp; résolution des litiges</h2>
        <p>Contactez d'abord le vendeur. À défaut de solution, contactez Afriklink à [courriel] ; nous facilitons une médiation de bonne foi. Les consommateurs de l'UE peuvent aussi recourir à la plateforme européenne RLL (ODR). Cela ne prive pas de votre droit de saisir les tribunaux.</p>
        <h2>10. Responsabilité</h2>
        <p>En tant qu'intermédiaire, Afriklink n'est pas partie à la vente et n'est pas responsable de l'exécution par les vendeurs, dans les limites permises par la loi.</p>
        <h2>11. Droit applicable</h2>
        <p>[Droit applicable et juridictions compétentes à compléter selon votre établissement et vos marchés cibles].</p>
    <?php endif; ?>
</section>
