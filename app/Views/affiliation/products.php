<?php
/** @var string $link  @var list<array> $products  @var array<int,string> $mains
 *  @var array{q:string,category:string,sort:string} $filters  @var list<string> $categories */
?>
<section class="aff-cat">
    <div class="aff-hero">
        <h1><?= icon('bag', ['size' => 26]) ?> <?= e(t('aff.cat_title')) ?></h1>
        <p class="lead"><?= e(t('aff.cat_lead')) ?></p>
    </div>

    <form method="get" action="<?= e(url('/affiliation/produits')) ?>" class="panel aff-cat-filters">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('aff.cat_search')) ?>" aria-label="<?= e(t('aff.cat_search')) ?>">
        <select name="cat" aria-label="<?= e(t('aff.cat_all_categories')) ?>">
            <option value=""><?= e(t('aff.cat_all_categories')) ?></option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e(t('listing.cat.' . $c)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tri" aria-label="<?= e(t('aff.cat_sort')) ?>">
            <option value=""><?= e(t('aff.cat_sort_recent')) ?></option>
            <option value="commission"  <?= $filters['sort'] === 'commission'  ? 'selected' : '' ?>><?= e(t('aff.cat_sort_commission')) ?></option>
            <option value="price_asc"   <?= $filters['sort'] === 'price_asc'   ? 'selected' : '' ?>><?= e(t('aff.cat_sort_price_asc')) ?></option>
            <option value="price_desc"  <?= $filters['sort'] === 'price_desc'  ? 'selected' : '' ?>><?= e(t('aff.cat_sort_price_desc')) ?></option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', ['size' => 15]) ?> <?= e(t('aff.cat_filter')) ?></button>
    </form>

    <div class="panel">
        <?php if ($products === []): ?>
            <p class="muted"><?= e(t('aff.cat_empty')) ?></p>
        <?php else: ?>
            <div class="aff-directory aff-products">
                <?php foreach ($products as $p): ?>
                    <?php
                    $pPath = '/boutique/' . (string) $p['boutique_slug'] . '/p/' . (string) $p['public_id'];
                    $pLink = $link !== '' ? $link . '?to=' . rawurlencode($pPath) : url($pPath);
                    $pImg  = $mains[(int) $p['id']] ?? null;
                    $cur   = (string) $p['currency'];
                    $earn  = affiliate_line_commission_cents((int) $p['price_cents'], (int) $p['affiliate_rate_bps']);
                    ?>
                    <div class="aff-shop aff-product">
                        <a class="aff-product-head" href="<?= e(url($pPath)) ?>" target="_blank" rel="noopener">
                            <span class="aff-product-img">
                                <?php if ($pImg !== null): ?>
                                    <img src="<?= e(\App\Services\CloudinaryService::imageUrl($pImg, 200, 200)) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="aff-shop-id">
                                <span class="aff-shop-name"><?= e((string) $p['name']) ?></span>
                                <span class="aff-shop-place"><?= e((string) $p['boutique_name']) ?> · <?= e(format_price((int) $p['price_cents'], $cur)) ?></span>
                            </span>
                        </a>
                        <div class="aff-cat-earn"><?= icon('wallet', ['size' => 14]) ?> <?= e(t('aff.cat_earn', ['amount' => format_price($earn, $cur)])) ?> <span class="muted">/ <?= e(t('aff.cat_per_sale')) ?></span></div>
                        <?php if ($link !== ''): ?>
                            <button type="button" class="btn btn-ghost btn-sm btn-block" data-copy="<?= e($pLink) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 15]) ?> <?= e(t('aff.directory_copy')) ?></button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <p><a class="btn btn-ghost" href="<?= e(url('/affiliation')) ?>">← <?= e(t('aff.vp_back')) ?></a></p>
</section>
