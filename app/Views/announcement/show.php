<?php
/** @var array $ann  @var string $author_name */
?>
<article class="container ann-article">
    <p class="muted"><a href="<?= e(url('/')) ?>">← <?= e(t('ann.back_home')) ?></a></p>
    <span class="ann-tag">📢 <?= e(t('ann.tag')) ?></span>
    <h1><?= e((string) $ann['title']) ?></h1>
    <p class="muted ann-meta">
        <?php if ($author_name !== ''): ?><?= e($author_name) ?> · <?php endif; ?>
        <?= e(date('d/m/Y', strtotime((string) $ann['created_at']))) ?>
    </p>
    <?php if (!empty($ann['body'])): ?>
        <div class="ann-body listing-description"><?= nl2br(e((string) $ann['body'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($ann['link'])): ?>
        <p class="ann-cta"><a class="btn btn-primary" href="<?= e((string) $ann['link']) ?>" target="_blank" rel="noopener nofollow"><?= e(t('ann.read_more')) ?> ↗</a></p>
    <?php endif; ?>
</article>
