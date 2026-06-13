<?php
/** Mentions légales — modèle à faire valider juridiquement.
 *  Corps long en clair (FR/EN) ; titres et avertissement via t(). */
$en = current_locale() === 'en';
?>
<section class="legal-page">
    <h1><?= e(t('legal.notice.title')) ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '13/06/2026'])) ?></p>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <?php if ($en): ?>
        <h2>1. Site publisher</h2>
        <p><strong>Afriklink</strong> — [company name to complete], [legal form], [share capital], registered office: [address]. Trade register: [number]. VAT: [number]. Email: [contact email].</p>
        <h2>2. Publication director</h2>
        <p>[Full name of the legal representative].</p>
        <h2>3. Hosting</h2>
        <p>The platform is hosted by <strong>Vercel Inc.</strong>, 340 S Lemon Ave #4133, Walnut, CA 91789, USA — vercel.com.</p>
        <h2>4. Role of the platform</h2>
        <p>Afriklink is an online <strong>marketplace</strong> that connects independent sellers (shops, restaurants, salons, services) with buyers in Africa and Europe. Afriklink acts as an <strong>intermediary</strong>: sales contracts are concluded directly between the seller and the buyer. Each professional seller is identified on their storefront.</p>
        <h2>5. Intellectual property</h2>
        <p>The Afriklink brand, logo and interface are protected. Product content (texts, images) is published under the responsibility of each seller.</p>
        <h2>6. Contact</h2>
        <p>For any legal request: [contact email].</p>
    <?php else: ?>
        <h2>1. Éditeur du site</h2>
        <p><strong>Afriklink</strong> — [raison sociale à compléter], [forme juridique], [capital social], siège social : [adresse]. Registre du commerce : [numéro]. TVA / NIF : [numéro]. Courriel : [adresse de contact].</p>
        <h2>2. Directeur de la publication</h2>
        <p>[Nom et prénom du représentant légal].</p>
        <h2>3. Hébergement</h2>
        <p>La plateforme est hébergée par <strong>Vercel Inc.</strong>, 340 S Lemon Ave #4133, Walnut, CA 91789, États-Unis — vercel.com.</p>
        <h2>4. Rôle de la plateforme</h2>
        <p>Afriklink est une <strong>place de marché</strong> (marketplace) en ligne qui met en relation des vendeurs indépendants (boutiques, restaurants, salons, services) avec des acheteurs, en Afrique et en Europe. Afriklink agit en qualité d'<strong>intermédiaire</strong> : les contrats de vente sont conclus directement entre le vendeur et l'acheteur. Chaque vendeur professionnel est identifié sur sa vitrine.</p>
        <h2>5. Propriété intellectuelle</h2>
        <p>La marque, le logo et l'interface d'Afriklink sont protégés. Les contenus produits (textes, images) sont publiés sous la responsabilité de chaque vendeur.</p>
        <h2>6. Contact</h2>
        <p>Pour toute demande relative aux présentes mentions : [adresse de contact].</p>
    <?php endif; ?>
</section>
