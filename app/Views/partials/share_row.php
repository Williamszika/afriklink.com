<?php
/**
 * Rangée de partage : WhatsApp, Facebook, copier le lien.
 * @var string $share_url   adresse absolue à partager
 * @var string $share_text  texte d'accroche (le lien est ajouté à la suite)
 */
$wa = 'https://wa.me/?text=' . rawurlencode($share_text . ' ' . $share_url);
$fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($share_url);
?>
<div class="share-row">
    <span class="share-label"><?= e(t('share.label')) ?></span>
    <a class="share-btn share-btn--wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
    <a class="share-btn share-btn--fb" href="<?= e($fb) ?>" target="_blank" rel="noopener">📘 Facebook</a>
    <button type="button" class="share-btn" data-copy="<?= e($share_url) ?>"
            data-copied="✓ <?= e(t('shop.copied')) ?>"><span class="ico-copy" aria-hidden="true">⧉</span> <?= e(t('share.copy')) ?></button>
</div>
