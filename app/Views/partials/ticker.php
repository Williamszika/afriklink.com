<?php
/**
 * Bandeau d'actualités défilant du bas de page. CSP-safe : défilement par
 * animation CSS pure (aucun JS inline). Le contenu est dupliqué (2 passes) pour
 * une boucle sans couture. Chaque info est un lien cliquable.
 */
$ticker = \App\Services\NewsTicker::items();
if ($ticker === []) {
    return;
}
?>
<aside class="ticker" aria-label="<?= e(t('ticker.label')) ?>">
    <span class="ticker-tag"><?= icon('megaphone', ['size' => 14]) ?> <?= e(t('ticker.label')) ?></span>
    <div class="ticker-viewport">
        <div class="ticker-track">
            <?php for ($pass = 0; $pass < 2; $pass++): ?>
                <?php foreach ($ticker as $it): ?>
                    <a class="ticker-item ticker--<?= e($it['kind']) ?>" href="<?= e($it['href']) ?>"<?= $pass === 1 ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
                        <span class="ticker-ico" aria-hidden="true"><?= $it['icon'] ?></span> <?= e($it['text']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
</aside>
