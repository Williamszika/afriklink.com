<?php
/**
 * Rangée de partage : WhatsApp, Facebook, TikTok (copie + ouverture), copier le lien.
 * @var string $share_url   adresse absolue à partager
 * @var string $share_text  texte d'accroche (le lien est ajouté à la suite)
 * @var bool   $compact     (optionnel) variante compacte : icônes seules, sans libellé
 */
$compact = !empty($compact);
$wa = 'https://wa.me/?text=' . rawurlencode($share_text . ' ' . $share_url);
$fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($share_url);
?>
<div class="share-row<?= $compact ? ' share-row--compact' : '' ?>">
    <?php if (!$compact): ?><span class="share-label"><?= e(t('share.label')) ?></span><?php endif; ?>
    <a class="share-btn share-btn--wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"
       title="WhatsApp" aria-label="WhatsApp"><img class="social-logo-sm" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="20" height="20"><?= $compact ? '' : ' WhatsApp' ?></a>
    <a class="share-btn share-btn--fb" href="<?= e($fb) ?>" target="_blank" rel="noopener"
       title="Facebook" aria-label="Facebook"><img class="social-logo-sm" src="<?= e(social_logo('facebook')) ?>" alt="" width="20" height="20"><?= $compact ? '' : ' Facebook' ?></a>
    <button type="button" class="share-btn share-btn--tt" data-copy="<?= e($share_url) ?>"
            data-copied="✓ <?= e(t('shop.copied')) ?>" data-open="https://www.tiktok.com/upload"
            title="TikTok — <?= e(t('share.tiktok_hint')) ?>" aria-label="TikTok"><img class="social-logo-sm" src="<?= e(social_logo('tiktok')) ?>" alt="" width="20" height="20"><?= $compact ? '' : ' TikTok' ?></button>
    <button type="button" class="share-btn" data-copy="<?= e($share_url) ?>"
            data-copied="✓ <?= e(t('shop.copied')) ?>" title="<?= e(t('share.copy')) ?>"
            aria-label="<?= e(t('share.copy')) ?>"><span class="ico-copy" aria-hidden="true">⧉</span><?= $compact ? '' : ' ' . e(t('share.copy')) ?></button>
</div>
