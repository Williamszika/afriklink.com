<?php
/** Explorer public — page d'attente avant la découverte complète (boutiques,
 *  produits, annonces, recherche, « près de moi »). */
$geo = detected_geo();
$here = trim(implode(', ', array_filter([$geo['city'] ?? null, $geo['country'] ?? null])));
?>
<section class="explore-hero">
    <div class="explore-hero-inner">
        <p class="explore-emoji" aria-hidden="true">🧭</p>
        <h1><?= e(t('explore.title')) ?></h1>
        <p class="lead"><?= e(t('explore.subtitle')) ?></p>

        <?php if ($here !== ''): ?>
            <p class="explore-here">📍 <?= e(t('explore.near', ['place' => $here])) ?></p>
        <?php endif; ?>

        <div class="explore-grid">
            <div class="explore-card"><span aria-hidden="true">🛍️</span><?= e(t('explore.f.shops')) ?></div>
            <div class="explore-card"><span aria-hidden="true">📦</span><?= e(t('explore.f.products')) ?></div>
            <div class="explore-card"><span aria-hidden="true">🔎</span><?= e(t('explore.f.search')) ?></div>
            <div class="explore-card"><span aria-hidden="true">📍</span><?= e(t('explore.f.nearby')) ?></div>
        </div>

        <span class="chip-soon"><?= e(t('explore.soon')) ?></span>
        <p><a class="btn btn-primary" href="<?= e(url('/')) ?>">← <?= e(t('nav.home')) ?></a></p>
    </div>
</section>
