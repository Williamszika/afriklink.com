<?php
/** @var string $link  @var list<array> $rows */
?>
<section class="aff-links">
    <div class="aff-hero">
        <h1><?= icon('link', ['size' => 26]) ?> <?= e(t('aff.links_title')) ?></h1>
        <p class="lead"><?= e(t('aff.links_lead')) ?></p>
    </div>

    <div class="panel">
        <?php if ($rows === []): ?>
            <p class="muted"><?= e(t('aff.links_empty')) ?></p>
            <p><a class="btn btn-primary" href="<?= e(url('/affiliation/produits')) ?>"><?= icon('bag', ['size' => 15]) ?> <?= e(t('aff.links_browse')) ?></a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr>
                        <th><?= e(t('aff.links_link')) ?></th>
                        <th class="num"><?= e(t('aff.clicks')) ?></th>
                        <th class="num"><?= e(t('aff.conversions')) ?></th>
                        <th class="num"><?= e(t('aff.earnings')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $target = (string) ($r['target'] ?? '');
                        $label  = ((string) ($r['label'] ?? '')) !== '' ? (string) $r['label'] : t('aff.links_generic');
                        $full   = $link !== '' ? ($target !== '' ? $link . '?to=' . rawurlencode($target) : $link) : '';
                        $earn   = empty($r['earnings'])
                            ? '0'
                            : implode(' · ', array_map(static fn (int $c, string $cur): string => format_price($c, $cur), $r['earnings'], array_keys($r['earnings'])));
                        ?>
                        <tr>
                            <td>
                                <?php if ($target !== ''): ?>
                                    <a href="<?= e(url($target)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
                                <?php else: ?>
                                    <?= e($label) ?>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= icon('pointer', ['size' => 14]) ?> <?= (int) $r['clicks'] ?></td>
                            <td class="num"><?= icon('bag', ['size' => 14]) ?> <?= (int) $r['sales'] ?></td>
                            <td class="num"><strong><?= e($earn) ?></strong></td>
                            <td class="num">
                                <?php if ($full !== ''): ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($full) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 14]) ?> <?= e(t('aff.directory_copy')) ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p><a class="btn btn-ghost" href="<?= e(url('/affiliation')) ?>">← <?= e(t('aff.vp_back')) ?></a></p>
</section>
