<?php
/**
 * Bloc de consentement légal (formulaires d'inscription). Une case À COCHER
 * OBLIGATOIRE avant de créer un compte, accompagnée d'un lien vers CHAQUE
 * document (ouvert dans un nouvel onglet pour ne pas perdre la saisie).
 * Champ POST : accept_legal=1. Réutilisé par l'inscription particulier ET pro ;
 * la validation serveur (controllers) exige accept_legal === '1'.
 */
$docs = [
    ['emoji' => 'ℹ️', 'href' => '/a-propos',                'label' => t('nav.about')],
    ['emoji' => '📄', 'href' => '/mentions-legales',        'label' => t('footer.impressum')],
    ['emoji' => '⚖️', 'href' => '/cgv',                     'label' => t('footer.terms')],
    ['emoji' => '↩️', 'href' => '/retractation',            'label' => t('footer.withdrawal')],
    ['emoji' => '🔒', 'href' => '/confidentialite',         'label' => t('footer.privacy')],
    ['emoji' => '🍪', 'href' => '/confidentialite#cookies', 'label' => t('footer.cookies')],
];
$err = has_error('accept_legal');
?>
<div class="legal-consent<?= $err ? ' is-error' : '' ?>">
    <label class="legal-consent__check">
        <input type="checkbox" name="accept_legal" value="1" <?= old('accept_legal') === '1' ? 'checked' : '' ?> required>
        <span class="legal-consent__text">
            <strong><?= e(t('legal.consent_label')) ?></strong>
            <span class="legal-consent__hint"><?= e(t('legal.consent_hint')) ?></span>
        </span>
    </label>
    <ul class="legal-consent__docs">
        <?php foreach ($docs as $d): ?>
            <li>
                <a href="<?= e(url($d['href'])) ?>" target="_blank" rel="noopener">
                    <span class="legal-consent__ico" aria-hidden="true"><?= $d['emoji'] ?></span>
                    <span><?= e($d['label']) ?></span>
                    <span class="legal-consent__ext" aria-hidden="true">↗</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($err): ?><p class="field-error legal-consent__err"><?= e(error('accept_legal')) ?></p><?php endif; ?>
</div>
