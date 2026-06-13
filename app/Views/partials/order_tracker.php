<?php
/**
 * Suivi de commande : une frise des étapes, l'étape atteinte est cochée.
 * @var string $current  statut actuel
 * @var list<array{key:string,label:string}> $steps  étapes ordonnées
 */
$keys = array_column($steps, 'key');
$idx = array_search($current, $keys, true);
?>
<div class="order-track-box">
    <p class="order-track-title"><?= e(t('order.track.title')) ?></p>
    <?php if ($current === 'cancelled'): ?>
        <p class="track-cancelled">✕ <?= e(t('order.track.cancelled')) ?></p>
    <?php else: ?>
        <ol class="order-track">
            <?php foreach ($steps as $i => $s): $done = $idx !== false && $i <= $idx; $isCur = $i === $idx; ?>
                <li class="track-step<?= $done ? ' is-done' : '' ?><?= $isCur ? ' is-current' : '' ?>">
                    <span class="track-dot"><?= $done ? '✓' : (int) ($i + 1) ?></span>
                    <span class="track-label"><?= e((string) $s['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
