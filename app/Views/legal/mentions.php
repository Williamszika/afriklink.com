<?php
/**
 * Mentions légales / Impressum — adaptées au pays détecté du visiteur.
 *  DE → format §5 DDG + responsable §18 MStV ; CI → Loi 2013-546 + RCCM/ARTCI ;
 *  sinon mentions génériques. Coordonnées réelles dans config/legal.php (.env).
 *  Modèle à faire valider juridiquement.
 */
$en   = current_locale() === 'en';
$L    = legal_ctx($forced_cc ?? null);
$op   = $L['operator'];
$rg   = $L['data'];
$host = config('legal.host');
$ph   = '<em class="legal-todo">' . ($en ? '[to be completed]' : '[à compléter]') . '</em>';
$F    = static fn (string $v): string => trim($v) !== '' ? e(trim($v)) : $ph;
$identity = trim(($op['name'] ?? '') . ($op['legal_form'] !== '' ? ' — ' . $op['legal_form'] : ''));
$addr1 = trim((string) ($op['address'] ?? ''));
$addr2 = trim(($op['postal_code'] ?? '') . ' ' . ($op['city'] ?? '') . ' (' . country_name($op['country_code'] ?? 'DE') . ')');
?>
<section class="legal-page">
    <h1><?= e(t('legal.notice.title')) ?><?php if ($rg['impressum'] ?? false): ?> <span class="legal-aka">/ Impressum</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/mentions-legales']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <h2>1. <?= $en ? 'Site publisher' : 'Éditeur du site' ?><?php if ($rg['impressum'] ?? false): ?> <span class="muted">(Angaben gemäß § 5 DDG)</span><?php endif; ?></h2>
    <p>
        <strong><?= $identity !== '' ? e($identity) : $ph ?></strong><br>
        <?= $addr1 !== '' ? e($addr1) : $ph ?><br>
        <?= e($addr2) ?>
    </p>
    <p>
        <?= $en ? 'Email' : 'Courriel' ?> : <?= $F((string) ($op['email'] ?? '')) ?><br>
        <?php if (trim((string) ($op['phone'] ?? '')) !== ''): ?><?= $en ? 'Phone' : 'Téléphone' ?> : <?= e($op['phone']) ?><br><?php endif; ?>
        <?= e($rg['register_label'] ?? 'Registre') ?> : <?= $F((string) ($op['register'] ?? '')) ?><?php if (trim((string) ($op['register_court'] ?? '')) !== ''): ?> — <?= e($op['register_court']) ?><?php endif; ?><br>
        <?= e($rg['vat_label'] ?? 'TVA') ?> : <?= $F((string) ($op['vat'] ?? '')) ?>
    </p>

    <h2>2. <?php if ($rg['impressum'] ?? false): ?>Vertretungsberechtigte / <?php endif; ?><?= $en ? 'Legal representative' : 'Représentant légal' ?></h2>
    <p><?= $F((string) ($op['representative'] ?? '')) ?><?php if ($rg['impressum'] ?? false): ?> — <span class="muted"><?= $en ? 'also responsible for content under § 18 (2) MStV' : 'également responsable du contenu au sens du § 18 al. 2 MStV' ?></span><?php endif; ?></p>

    <h2>3. <?= $en ? 'Hosting' : 'Hébergement' ?></h2>
    <p><?= $en ? 'The platform is hosted by' : 'La plateforme est hébergée par' ?> <strong><?= e($host['name']) ?></strong>, <?= e($host['address']) ?> — <?= e($host['url']) ?>.</p>

    <h2>4. <?= $en ? 'Role of the platform' : 'Rôle de la plateforme' ?></h2>
    <p>
        <strong><?= e($op['name'] ?? 'Afriklink') ?></strong> <?= $en
            ? 'is an online marketplace that connects independent sellers (shops, restaurants, salons, services) with buyers in Africa and Europe. It acts as an intermediary: sales contracts are concluded directly between the seller and the buyer. Each professional seller is identified on their storefront.'
            : "est une place de marché en ligne qui met en relation des vendeurs indépendants (boutiques, restaurants, salons, services) avec des acheteurs, en Afrique et en Europe. Elle agit en qualité d'intermédiaire : les contrats de vente sont conclus directement entre le vendeur et l'acheteur. Chaque vendeur professionnel est identifié sur sa vitrine." ?>
    </p>
    <?php if ($L['is_ci']): ?>
        <p class="muted"><?= $en
            ? "Electronic transactions are governed by Law No. 2013-546 of 30 July 2013; each seller must be identifiable (name, contact, RCCM where applicable)."
            : "Les transactions électroniques sont régies par la loi n° 2013-546 du 30 juillet 2013 ; chaque vendeur doit être identifiable (dénomination, contact, RCCM le cas échéant)." ?></p>
    <?php endif; ?>

    <h2>5. <?= $en ? 'Contact point & reporting (DSA)' : 'Point de contact & signalement (DSA)' ?></h2>
    <p>
        <?= $en
            ? 'Single point of contact for authorities and users:'
            : 'Point de contact unique pour les autorités et les utilisateurs :' ?>
        <strong><?= $F((string) ($op['email'] ?? '')) ?></strong> (<?= $en ? 'languages: French, English' : 'langues : français, anglais' ?>).
        <?= $en
            ? 'To report illegal content or a non-compliant listing, use the'
            : 'Pour signaler un contenu illicite ou une vitrine non conforme, utilisez le' ?>
        <a href="<?= e(url('/signaler-vitrine')) ?>"><?= $en ? 'reporting form' : 'formulaire de signalement' ?></a>.
        <?= $en
            ? 'We process notices under a notice-and-action procedure and reply to the reporter.'
            : "Nous traitons les signalements selon une procédure « notification et action » et répondons à l'auteur du signalement." ?>
    </p>

    <h2>6. <?= $en ? 'Intellectual property' : 'Propriété intellectuelle' ?></h2>
    <p><?= $en
        ? 'The Afriklink brand, logo and interface are protected. Product content (texts, images) is published under the responsibility of each seller.'
        : "La marque, le logo et l'interface d'Afriklink sont protégés. Les contenus produits (textes, images) sont publiés sous la responsabilité de chaque vendeur." ?></p>

    <h2>7. <?= $en ? 'Data protection' : 'Protection des données' ?></h2>
    <p>
        <?= $en ? 'Personal-data processing is described in our' : 'Le traitement des données personnelles est décrit dans notre' ?>
        <a href="<?= e(url('/confidentialite')) ?>"><?= $en ? 'privacy policy' : 'politique de confidentialité' ?></a>,
        <?= $en ? 'in accordance with' : 'conformément à' ?> <?= e($en ? ($rg['data_law_en'] ?? '') : ($rg['data_law_fr'] ?? '')) ?>.
    </p>
</section>
