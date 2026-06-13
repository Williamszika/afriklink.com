<?php
/** Politique de confidentialité (RGPD) — modèle à faire valider juridiquement. */
$en = current_locale() === 'en';
?>
<section class="legal-page">
    <h1><?= e(t('legal.privacy.title')) ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '13/06/2026'])) ?></p>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <?php if ($en): ?>
        <h2>1. Data controller</h2>
        <p>Afriklink — [company name], [address], [email]. For West-African users, processing also complies with applicable national data-protection laws and the ECOWAS data-protection framework.</p>
        <h2>2. What we collect</h2>
        <ul>
            <li><strong>Account</strong>: name, nickname, email and/or phone, country, language, password (hashed).</li>
            <li><strong>Sellers</strong>: business details and KYC documents for identity verification.</li>
            <li><strong>Orders</strong>: items, amounts, delivery contact and address, optional GPS location you share.</li>
            <li><strong>Technical</strong>: session and security cookies, and — only with your consent — audience measurement.</li>
        </ul>
        <h2>3. Purposes &amp; legal bases</h2>
        <ul>
            <li>Run the marketplace and process orders — <em>performance of a contract</em>.</li>
            <li>Account security, fraud and abuse prevention — <em>legitimate interest</em>.</li>
            <li>Transactional emails (orders, alerts) — <em>contract</em>; audience measurement — <em>consent</em>.</li>
            <li>Legal, tax and accounting obligations — <em>legal obligation</em>.</li>
        </ul>
        <h2>4. Recipients</h2>
        <p>Your order data is shared with the <strong>seller</strong> concerned (to fulfil the order) and with our technical providers (hosting: Vercel; media: Cloudinary; email: Brevo). We never sell your data.</p>
        <h2>5. Retention</h2>
        <p>Account data is kept while your account is active; order and invoicing data for the legal retention period; then deleted or anonymised.</p>
        <h2>6. Your rights</h2>
        <p>You may request access, rectification, erasure, portability, restriction or object to processing, and lodge a complaint with your supervisory authority. Contact: [email].</p>
        <h2>7. International transfers</h2>
        <p>Some providers are located outside your country/region; transfers rely on appropriate safeguards (e.g. standard contractual clauses).</p>
        <h2 id="cookies">8. Cookies</h2>
        <p><strong>Essential</strong> cookies (session, security/CSRF, language, cart, consent choice) are required and always on. <strong>Functional</strong> cookies (e.g. recently-viewed products) personalise your experience. <strong>Audience measurement</strong> would only be enabled with your consent. You can change your choice at any time via the cookie banner.</p>
    <?php else: ?>
        <h2>1. Responsable du traitement</h2>
        <p>Afriklink — [raison sociale], [adresse], [courriel]. Pour les utilisateurs d'Afrique de l'Ouest, les traitements respectent également les lois nationales applicables et le cadre CEDEAO de protection des données.</p>
        <h2>2. Données collectées</h2>
        <ul>
            <li><strong>Compte</strong> : nom, pseudo, e-mail et/ou téléphone, pays, langue, mot de passe (haché).</li>
            <li><strong>Vendeurs</strong> : informations d'entreprise et pièces KYC pour la vérification d'identité.</li>
            <li><strong>Commandes</strong> : articles, montants, coordonnées et adresse de livraison, position GPS si vous la partagez.</li>
            <li><strong>Technique</strong> : cookies de session et de sécurité ; mesure d'audience uniquement avec votre consentement.</li>
        </ul>
        <h2>3. Finalités &amp; bases légales</h2>
        <ul>
            <li>Faire fonctionner la marketplace et traiter les commandes — <em>exécution du contrat</em>.</li>
            <li>Sécurité des comptes, prévention de la fraude et des abus — <em>intérêt légitime</em>.</li>
            <li>E-mails transactionnels (commandes, alertes) — <em>contrat</em> ; mesure d'audience — <em>consentement</em>.</li>
            <li>Obligations légales, fiscales et comptables — <em>obligation légale</em>.</li>
        </ul>
        <h2>4. Destinataires</h2>
        <p>Les données de commande sont transmises au <strong>vendeur</strong> concerné (pour exécuter la commande) et à nos prestataires techniques (hébergement : Vercel ; médias : Cloudinary ; e-mail : Brevo). Nous ne vendons jamais vos données.</p>
        <h2>5. Durée de conservation</h2>
        <p>Les données de compte sont conservées tant que le compte est actif ; les données de commande et de facturation pendant la durée légale ; puis supprimées ou anonymisées.</p>
        <h2>6. Vos droits</h2>
        <p>Vous disposez des droits d'accès, de rectification, d'effacement, de portabilité, de limitation et d'opposition, et du droit d'introduire une réclamation auprès de votre autorité de contrôle. Contact : [courriel].</p>
        <h2>7. Transferts internationaux</h2>
        <p>Certains prestataires sont situés hors de votre pays/région ; les transferts reposent sur des garanties appropriées (clauses contractuelles types, par exemple).</p>
        <h2 id="cookies">8. Cookies</h2>
        <p>Les cookies <strong>essentiels</strong> (session, sécurité/CSRF, langue, panier, choix de consentement) sont nécessaires et toujours actifs. Les cookies <strong>fonctionnels</strong> (ex. produits vus récemment) personnalisent votre expérience. La <strong>mesure d'audience</strong> ne serait activée qu'avec votre consentement. Vous pouvez modifier votre choix à tout moment via le bandeau cookies.</p>
    <?php endif; ?>
</section>
