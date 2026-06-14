<?php
/** @var string $code @var string $link @var int $rate
 *  @var array $stats @var list<array> $recent @var list<array> $directory @var ?array $program */
$maxRate = $directory !== [] ? max(array_map(static fn (array $s): int => (int) $s['affiliation_rate_pct'], $directory)) : 0;
?>
<section class="aff-hub">
    <div class="aff-hero">
        <h1><?= icon('banknote', ['size' => 26]) ?> <?= e(t('aff.hub_title')) ?>
            <?php if ($maxRate > 0): ?><span class="badge badge-ok"><?= e(t('aff.upto', ['rate' => $maxRate])) ?></span><?php endif; ?>
        </h1>
        <p class="lead"><?= e(t('aff.hub_lead')) ?></p>
    </div>

    <?= render_partial('partials/affiliate_hub', [
        'code'      => $code,
        'link'      => $link,
        'rate'      => $rate,
        'stats'     => $stats,
        'recent'    => $recent,
        'directory' => $directory,
        'program'   => $program,
    ]) ?>
</section>
