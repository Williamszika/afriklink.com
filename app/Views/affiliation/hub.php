<?php
/** @var string $code @var string $link @var int $rate @var bool $can_earn
 *  @var array $stats @var list<array> $recent @var list<array> $directory @var ?array $program
 *  @var list<array> $dir_products @var array<int,string> $dir_mains @var ?array $wallet */
// Héros « jusqu'à X % » : meilleur taux apporteur (R − part plateforme) parmi les produits.
$maxAffBps = 0;
foreach (($dir_products ?? []) as $dp) {
    $maxAffBps = max($maxAffBps, (int) ($dp['affiliate_rate_bps'] ?? 0) - affiliate_keep_bps());
}
$rateLbl = rtrim(rtrim(number_format(max(0, $maxAffBps) / 100, 1, ',', ''), '0'), ',');
?>
<section class="aff-hub">
    <div class="aff-hero">
        <?php if ($can_earn): ?>
            <h1><?= icon('banknote', ['size' => 26]) ?> <?= e(t('aff.hub_title')) ?>
                <?php if ($maxAffBps > 0): ?><span class="badge badge-ok"><?= e(t('aff.upto', ['rate' => $rateLbl])) ?></span><?php endif; ?>
            </h1>
            <p class="lead"><?= e(t('aff.hub_lead')) ?></p>
        <?php else: ?>
            <h1><?= icon('store', ['size' => 26]) ?> <?= e(t('aff.title')) ?></h1>
            <p class="lead"><?= e(t('aff.vendor_lead')) ?></p>
        <?php endif; ?>
    </div>

    <?= render_partial('partials/affiliate_hub', [
        'can_earn'     => $can_earn,
        'code'         => $code,
        'link'         => $link,
        'rate'         => $rate,
        'stats'        => $stats,
        'recent'       => $recent,
        'directory'    => $directory,
        'dir_products' => $dir_products,
        'dir_mains'    => $dir_mains,
        'program'      => $program,
        'wallet'       => $wallet,
    ]) ?>
</section>
