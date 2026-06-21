<?php
/**
 * Droit de rétractation (UE/EEE) + formulaire-type de rétractation
 * (Muster-Widerrufsformular). Pour les visiteurs hors UE : renvoi à la
 * politique de retour du vendeur. Modèle à faire valider juridiquement.
 */
$en = current_locale() !== 'fr'; // de/es/it : repli sur l'anglais
$L  = legal_ctx($forced_cc ?? null);
$op = $L['operator'];
?>
<section class="legal-page">
    <h1><?= e(t('legal.withdrawal.title')) ?><?php if ($L['is_eu']): ?> <span class="legal-aka">/ Widerrufsrecht</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/retractation']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <?php if ($L['is_eu']): ?>
        <h2>1. <?= $en ? 'Right of withdrawal' : 'Droit de rétractation' ?></h2>
        <p><?= $en
            ? 'As a consumer, you have the right to withdraw from this contract within 14 days without giving any reason. The withdrawal period expires 14 days from the day on which you (or a third party you indicate, other than the carrier) acquires physical possession of the goods.'
            : "En tant que consommateur, vous avez le droit de vous rétracter du présent contrat sans donner de motif dans un délai de 14 jours. Le délai expire 14 jours après le jour où vous-même (ou un tiers que vous désignez, autre que le transporteur) prenez physiquement possession du bien." ?></p>
        <p><?= $en
            ? 'To exercise it, inform the seller (and, if you wish, Afriklink) of your decision by an unambiguous statement (e.g. a letter sent by post or email). You may use the model form below, but it is not mandatory.'
            : "Pour l'exercer, informez le vendeur (et, si vous le souhaitez, Afriklink) de votre décision par une déclaration dénuée d'ambiguïté (courrier postal ou e-mail). Vous pouvez utiliser le formulaire-type ci-dessous, sans obligation." ?></p>

        <h2>2. <?= $en ? 'Effects of withdrawal' : 'Effets de la rétractation' ?></h2>
        <p><?= $en
            ? 'The seller reimburses all payments received, including standard delivery costs, without undue delay and within 14 days of being informed. You must send back the goods without undue delay and within 14 days; the direct cost of return may be borne by you. You are only liable for any diminished value resulting from handling beyond what is necessary.'
            : "Le vendeur rembourse tous les paiements reçus, y compris les frais de livraison standard, sans retard injustifié et dans les 14 jours suivant l'information. Vous devez renvoyer le bien sans retard injustifié et dans les 14 jours ; le coût direct du renvoi peut rester à votre charge. Vous n'êtes responsable que de la dépréciation résultant de manipulations au-delà du nécessaire." ?></p>

        <h2>3. <?= $en ? 'Exceptions' : 'Exceptions' ?></h2>
        <p><?= $en
            ? 'The right of withdrawal does not apply, in particular, to: made-to-order or clearly personalised goods; goods liable to deteriorate or expire rapidly (incl. prepared meals); sealed goods unsealed after delivery for hygiene/health reasons; and services fully performed with your prior agreement.'
            : "Le droit de rétractation ne s'applique pas, notamment, aux : biens sur mesure ou nettement personnalisés ; biens susceptibles de se détériorer ou de périmer rapidement (y compris plats préparés) ; biens scellés descellés après livraison pour des raisons d'hygiène/santé ; et services pleinement exécutés avec votre accord préalable." ?></p>

        <h2>4. <?= $en ? 'Model withdrawal form' : 'Formulaire-type de rétractation' ?></h2>
        <p class="muted"><?= $en ? '(Complete and return this form only if you wish to withdraw from the contract.)' : '(Veuillez compléter et renvoyer ce formulaire uniquement si vous souhaitez vous rétracter du contrat.)' ?></p>
        <div class="legal-form-model">
            <p><?= $en ? 'To: the seller concerned, via Afriklink' : "À l'attention : du vendeur concerné, via Afriklink" ?> — <?= e($op['email'] ?? 'contact@afriklink.com') ?></p>
            <p><?= $en
                ? 'I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*) / for the provision of the following service (*):'
                : "Je/Nous (*) vous notifie/notifions (*) par la présente ma/notre (*) rétractation du contrat portant sur la vente du bien (*) / pour la prestation de service (*) ci-dessous :" ?></p>
            <p>……………………………………………………………………………………</p>
            <ul class="legal-form-fields">
                <li><?= $en ? 'Ordered on (*) / received on (*):' : 'Commandé le (*) / reçu le (*) :' ?> …………………</li>
                <li><?= $en ? 'Order number:' : 'Numéro de commande :' ?> …………………</li>
                <li><?= $en ? 'Name of consumer(s):' : 'Nom du/des consommateur(s) :' ?> …………………</li>
                <li><?= $en ? 'Address of consumer(s):' : 'Adresse du/des consommateur(s) :' ?> …………………</li>
                <li><?= $en ? 'Date:' : 'Date :' ?> …………………</li>
                <li><?= $en ? 'Signature (only if this form is notified on paper):' : 'Signature (uniquement en cas de notification sur papier) :' ?> …………………</li>
            </ul>
            <p class="muted">(*) <?= $en ? 'Delete as appropriate.' : 'Rayez la mention inutile.' ?></p>
        </div>
    <?php else: ?>
        <h2><?= $en ? 'Returns & refunds' : 'Retours & remboursements' ?></h2>
        <p><?= $en
            ? 'The 14-day EU right of withdrawal does not apply in your country. Returns and refunds follow each seller\'s published policy and the consumer law applicable where you live. Contact the seller first; Afriklink helps resolve disputes in good faith.'
            : "Le droit de rétractation de 14 jours de l'UE ne s'applique pas dans votre pays. Les retours et remboursements suivent la politique publiée par chaque vendeur et le droit de la consommation applicable chez vous. Contactez d'abord le vendeur ; Afriklink facilite la résolution de bonne foi." ?></p>
        <p><?= $en ? 'Platform contact:' : 'Contact plateforme :' ?> <strong><?= e($op['email'] ?? 'contact@afriklink.com') ?></strong>.</p>
    <?php endif; ?>

    <p class="legal-back"><a href="<?= e(url('/cgv')) ?>">← <?= $en ? 'Back to terms' : 'Retour aux conditions générales' ?></a></p>
</section>
