<?php
/** Affiche une note en étoiles. @var float $avg  @var int $count  @var bool $small (optionnel) */
$rounded = (int) round((float) ($avg ?? 0));
?>
<span class="stars<?= !empty($small) ? ' stars-sm' : '' ?>" title="<?= e(number_format((float) ($avg ?? 0), 1)) ?> / 5">
    <?php for ($i = 1; $i <= 5; $i++): ?><span class="star<?= $i <= $rounded ? ' is-on' : '' ?>">★</span><?php endfor; ?>
    <?php if (!empty($count)): ?><span class="stars-meta"><?= e(number_format((float) $avg, 1)) ?> · <?= (int) $count ?></span><?php endif; ?>
</span>
