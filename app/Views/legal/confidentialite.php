<?php
/**
 * Politique de confidentialité — adaptée au pays détecté.
 *  DE/EU → RGPD + autorité nationale ; CI → Loi 2013-450 + ARTCI ; sinon loi
 *  locale. Sous-traitants listés depuis config/legal.php. À faire valider.
 */
$en   = current_locale() !== 'fr'; // de/es/it : repli sur l'anglais
$L    = legal_ctx($forced_cc ?? null);
$op   = $L['operator'];
$rg   = $L['data'];
$proc = config('legal.processors', []);
$dataLaw   = $en ? ($rg['data_law_en'] ?? '') : ($rg['data_law_fr'] ?? '');
$authority = $en ? ($rg['authority_en'] ?? '') : ($rg['authority_fr'] ?? '');
$contact   = trim((string) ($op['dpo_email'] ?? '')) !== '' ? $op['dpo_email'] : ($op['email'] ?? '');
?>
<section class="legal-page">
    <h1><?= e(t('legal.privacy.title')) ?><?php if ($L['is_eu']): ?> <span class="legal-aka">/ Datenschutzerklärung</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/confidentialite']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <p class="legal-lead"><?= $en
        ? 'This processing complies with ' . e($dataLaw) . '.'
        : 'Les traitements décrits respectent ' . e($dataLaw) . '.' ?></p>

    <h2>1. <?= $en ? 'Data controller' : 'Responsable du traitement' ?></h2>
    <p><?= e($op['name'] ?? 'Afriklink') ?><?php if (trim((string) ($op['city'] ?? '')) !== ''): ?>, <?= e(trim(($op['postal_code'] ?? '') . ' ' . ($op['city'] ?? ''))) ?> (<?= e(country_name($op['country_code'] ?? 'DE')) ?>)<?php endif; ?>. <?= $en ? 'Contact:' : 'Contact :' ?> <strong><?= e($contact) ?></strong>.</p>

    <h2>2. <?= $en ? 'What we collect' : 'Données collectées' ?></h2>
    <ul>
        <li><strong><?= $en ? 'Account' : 'Compte' ?></strong> : <?= $en ? 'name, nickname, email and/or phone, country, language, hashed password.' : 'nom, pseudo, e-mail et/ou téléphone, pays, langue, mot de passe (haché).' ?></li>
        <li><strong><?= $en ? 'Sellers' : 'Vendeurs' ?></strong> : <?= $en ? 'business details and KYC documents for identity verification.' : "informations d'entreprise et pièces KYC pour la vérification d'identité." ?></li>
        <li><strong><?= $en ? 'Orders' : 'Commandes' ?></strong> : <?= $en ? 'items, amounts, delivery contact and address, optional GPS location you share.' : 'articles, montants, coordonnées et adresse de livraison, position GPS si vous la partagez.' ?></li>
        <li><strong><?= $en ? 'Technical' : 'Technique' ?></strong> : <?= $en ? 'session and security cookies; functional and audience-measurement cookies only with your consent.' : "cookies de session et de sécurité ; cookies fonctionnels et de mesure d'audience uniquement avec votre consentement." ?></li>
    </ul>

    <h2>3. <?= $en ? 'Purposes & legal bases' : 'Finalités & bases légales' ?></h2>
    <ul>
        <li><?= $en ? 'Run the marketplace and process orders' : 'Faire fonctionner la marketplace et traiter les commandes' ?> — <em><?= $en ? 'performance of a contract' : 'exécution du contrat' ?></em>.</li>
        <li><?= $en ? 'Account security, fraud and abuse prevention' : 'Sécurité des comptes, prévention de la fraude et des abus' ?> — <em><?= $en ? 'legitimate interest' : 'intérêt légitime' ?></em>.</li>
        <li><?= $en ? 'Transactional emails' : 'E-mails transactionnels' ?> — <em><?= $en ? 'contract' : 'contrat' ?></em> ; <?= $en ? 'newsletter & audience measurement' : "newsletter & mesure d'audience" ?> — <em><?= $en ? 'consent' : 'consentement' ?></em>.</li>
        <li><?= $en ? 'Legal, tax and accounting obligations' : 'Obligations légales, fiscales et comptables' ?> — <em><?= $en ? 'legal obligation' : 'obligation légale' ?></em>.</li>
    </ul>

    <h2>4. <?= $en ? 'Recipients & processors' : 'Destinataires & sous-traitants' ?></h2>
    <p><?= $en
        ? 'Your order data is shared with the seller concerned (to fulfil the order). We never sell your data. We rely on the following processors:'
        : "Les données de commande sont transmises au vendeur concerné (pour exécuter la commande). Nous ne vendons jamais vos données. Nous recourons aux sous-traitants suivants :" ?></p>
    <ul>
        <?php foreach ($proc as $p): ?>
            <li><strong><?= e($p['name']) ?></strong> — <?= e($en ? $p['role_en'] : $p['role_fr']) ?> (<?= e($p['loc']) ?>)</li>
        <?php endforeach; ?>
    </ul>

    <h2>5. <?= $en ? 'International transfers' : 'Transferts internationaux' ?></h2>
    <p><?= $en
        ? 'Afriklink connects Africa and Europe: some data is transferred between these regions and to providers located outside the EU/EEA. Such transfers rely on appropriate safeguards (e.g. EU standard contractual clauses) and data minimisation.'
        : "Afriklink relie l'Afrique et l'Europe : certaines données sont transférées entre ces régions et vers des prestataires situés hors UE/EEE. Ces transferts reposent sur des garanties appropriées (clauses contractuelles types de l'UE, par exemple) et la minimisation des données." ?></p>

    <h2>6. <?= $en ? 'Retention' : 'Durée de conservation' ?></h2>
    <p><?= $en
        ? 'Account data is kept while your account is active; order and invoicing data for the legal retention period; then deleted or anonymised.'
        : 'Les données de compte sont conservées tant que le compte est actif ; les données de commande et de facturation pendant la durée légale ; puis supprimées ou anonymisées.' ?></p>

    <h2>7. <?= $en ? 'Your rights' : 'Vos droits' ?></h2>
    <p><?= $en
        ? 'You may request access, rectification, erasure, portability, restriction or object to processing, and lodge a complaint with '
        : "Vous disposez des droits d'accès, de rectification, d'effacement, de portabilité, de limitation et d'opposition, et pouvez introduire une réclamation auprès de " ?><?= e($authority) ?>. <?= $en ? 'Contact:' : 'Contact :' ?> <strong><?= e($contact) ?></strong>.</p>
    <?php if ($L['is_eu']): ?>
        <p class="muted"><?= $en ? 'In the event of a personal-data breach, we notify the competent authority within 72 hours where required.' : "En cas de violation de données à caractère personnel, nous notifions l'autorité compétente sous 72 heures lorsque la loi l'exige." ?></p>
    <?php endif; ?>

    <h2 id="cookies">8. <?= $en ? 'Cookies' : 'Cookies' ?></h2>
    <p><?= $en ? 'We use three categories of cookies:' : 'Nous utilisons trois catégories de cookies :' ?></p>
    <ul>
        <li><strong><?= $en ? 'Essential' : 'Essentiels' ?></strong> (<?= $en ? 'always on' : 'toujours actifs' ?>) — <?= $en ? 'session, security/CSRF, language, cart, consent choice.' : 'session, sécurité/CSRF, langue, panier, choix de consentement.' ?></li>
        <li><strong><?= $en ? 'Functional' : 'Fonctionnels' ?></strong> — <?= $en ? 'e.g. recently-viewed products; set only with your consent.' : 'ex. produits vus récemment ; déposés uniquement avec votre consentement.' ?></li>
        <li><strong><?= $en ? 'Audience measurement' : "Mesure d'audience" ?></strong> — <?= $en ? 'anonymous traffic statistics; set only with your consent.' : "statistiques de fréquentation anonymes ; déposés uniquement avec votre consentement." ?></li>
    </ul>
    <p><?= $en ? 'You can change your choice at any time via the cookie banner.' : 'Vous pouvez modifier votre choix à tout moment via le bandeau cookies.' ?>
        <a href="<?= e(url('/consentement/refuser?to=' . rawurlencode('/confidentialite#cookies'))) ?>"><?= $en ? 'Reset my choice' : 'Réinitialiser mon choix' ?></a>.</p>
</section>
