<?php
/** @var list<array> $products  @var array<int,string> $mains  @var array<int,array> $ratings */
use App\Services\CloudinaryService;
?>
<section class="compare-page">
    <h1>⇄ <?= e(t('compare.title')) ?></h1>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">⇄</p>
            <p><?= e(t('compare.empty')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('/explorer')) ?>"><?= e(t('wish.empty_cta')) ?></a>
        </div>
    <?php else: ?>
        <?php if (count($products) < 2): ?>
            <div class="notice notice-info"><p><?= e(t('compare.need_two')) ?></p></div>
        <?php endif; ?>
        <div class="compare-wrap">
            <table class="compare-table">
                <tbody>
                    <tr>
                        <th></th>
                        <?php foreach ($products as $p): $m = $mains[(int) $p['id']] ?? null; ?>
                            <td class="compare-col-head">
                                <a class="compare-prod" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                                    <span class="compare-img"><?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 300, 300)) ?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true">📦</span><?php endif; ?></span>
                                    <span class="compare-name"><?= e((string) $p['name']) ?></span>
                                </a>
                                <?= render_partial('partials/compare_toggle', ['pid' => (string) $p['public_id']]) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th><?= e(t('compare.row_price')) ?></th>
                        <?php foreach ($products as $p): ?><td class="compare-price"><?= render_partial('partials/price_dual', ['cents' => (int) $p['price_cents'], 'cur' => (string) $p['currency']]) ?></td><?php endforeach; ?>
                    </tr>
                    <tr>
                        <th><?= e(t('compare.row_rating')) ?></th>
                        <?php foreach ($products as $p): $r = $ratings[(int) $p['id']] ?? null; ?>
                            <td><?php if (!empty($r['count'])): ?><?= render_partial('partials/stars', ['avg' => $r['avg'], 'count' => $r['count'], 'small' => true]) ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th><?= e(t('compare.row_stock')) ?></th>
                        <?php foreach ($products as $p): $in = $p['stock'] === null || (int) $p['stock'] > 0; ?>
                            <td><?php if ($in): ?><span class="badge badge-ok"><?= e(t('product.in_stock')) ?></span><?php else: ?><span class="badge badge-warn"><?= e(t('product.out_of_stock')) ?></span><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th><?= e(t('compare.row_shop')) ?></th>
                        <?php foreach ($products as $p): ?><td><a href="<?= e(url('/boutique/' . $p['boutique_slug'])) ?>"><?= e((string) $p['boutique_name']) ?></a></td><?php endforeach; ?>
                    </tr>
                    <tr>
                        <th></th>
                        <?php foreach ($products as $p): ?><td><a class="btn btn-primary btn-sm" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>"><?= e(t('compare.view')) ?></a></td><?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
