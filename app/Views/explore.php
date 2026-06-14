<?php
/** Explorer — recherche marketplace.
 * @var list<string> $categories  @var array $f  @var int $page  @var bool $has_next
 * @var list<array> $products  @var array<int,string> $mains  @var array<int,array> $ratings */
use App\Models\Product;
use App\Services\CloudinaryService;

$countries = $countries ?? [];
$baseParams = array_filter([
    'q' => $f['q'], 'categorie' => $f['category'], 'pays' => $f['country'], 'ville' => $f['city'],
    'stock' => !empty($f['in_stock']) ? '1' : '', 'min' => $f['min'], 'max' => $f['max'], 'tri' => $f['sort'],
], static fn ($v): bool => $v !== '' && $v !== null);
$qs = static fn (array $over): string => http_build_query(array_merge($baseParams, $over));
?>
<section class="explore">
    <h1 class="explore-h1">🧭 <?= e(t('explore.title')) ?></h1>
    <p class="muted"><?= e(t('explore.search_lead')) ?></p>

    <form class="explore-search" method="get" action="<?= e(url('/explorer')) ?>">
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('explore.search_ph')) ?>" class="explore-q" aria-label="<?= e(t('explore.search_ph')) ?>">
        <select name="categorie" class="explore-filter" aria-label="<?= e(t('explore.all_categories')) ?>">
            <option value=""><?= e(t('explore.all_categories')) ?></option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $f['category'] === $c ? 'selected' : '' ?>><?= e(t('listing.cat.' . $c)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($countries !== []): ?>
            <select name="pays" class="explore-filter" aria-label="<?= e(t('explore.country_all')) ?>">
                <option value=""><?= e(t('explore.country_all')) ?></option>
                <?php foreach ($countries as $pc): ?>
                    <option value="<?= e($pc) ?>" <?= $f['country'] === $pc ? 'selected' : '' ?>><?= e(trim(flag_emoji($pc) . ' ' . country_name($pc))) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input type="text" name="ville" value="<?= e($f['city']) ?>" placeholder="<?= e(t('explore.city_ph')) ?>" class="explore-city" aria-label="<?= e(t('explore.city_ph')) ?>">
        <input type="number" name="min" value="<?= e($f['min']) ?>" placeholder="<?= e(t('explore.price_min')) ?>" min="0" inputmode="numeric" class="explore-price" aria-label="<?= e(t('explore.price_min')) ?>">
        <input type="number" name="max" value="<?= e($f['max']) ?>" placeholder="<?= e(t('explore.price_max')) ?>" min="0" inputmode="numeric" class="explore-price" aria-label="<?= e(t('explore.price_max')) ?>">
        <select name="tri" class="explore-filter" aria-label="<?= e(t('explore.sort_label')) ?>">
            <?php foreach (['recent', 'price_asc', 'price_desc'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $f['sort'] === $s ? 'selected' : '' ?>><?= e(t('explore.sort.' . $s)) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="explore-check"><input type="checkbox" name="stock" value="1" <?= !empty($f['in_stock']) ? 'checked' : '' ?>> <?= e(t('explore.in_stock')) ?></label>
        <button type="submit" class="btn btn-primary">🔎 <?= e(t('explore.search_btn')) ?></button>
    </form>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">🔎</p>
            <p><?= e(t('explore.empty')) ?></p>
            <a class="btn btn-ghost" href="<?= e(url('/explorer')) ?>"><?= e(t('explore.reset')) ?></a>
        </div>
    <?php else: ?>
        <div class="product-grid explore-results">
            <?php foreach ($products as $p): $m = $mains[(int) $p['id']] ?? null; $r = $ratings[(int) $p['id']] ?? null; ?>
                <div class="product-card-wrap">
                    <a class="product-card" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                        <span class="product-card-img">
                            <?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                            <?php if (Product::isPromoted($p)): ?><span class="promo-badge"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                        </span>
                        <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                        <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $p['price_cents'], 'cur' => (string) $p['currency']]) ?></span>
                        <span class="muted explore-card-shop"><?= e(t('explore.by', ['shop' => (string) $p['boutique_name']])) ?></span>
                        <?php if (!empty($r['count'])): ?>
                            <span class="product-card-rating"><?= render_partial('partials/stars', ['avg' => $r['avg'], 'count' => $r['count'], 'small' => true]) ?></span>
                        <?php endif; ?>
                    </a>
                    <?= render_partial('partials/wish_heart', ['pid' => (string) $p['public_id']]) ?>
                    <?= render_partial('partials/compare_toggle', ['pid' => (string) $p['public_id']]) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($page > 1 || $has_next): ?>
            <div class="explore-pager">
                <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="?<?= e($qs(['page' => $page - 1])) ?>"><?= e(t('explore.prev')) ?></a><?php endif; ?>
                <span class="muted explore-page-num"><?= (int) $page ?></span>
                <?php if ($has_next): ?><a class="btn btn-ghost btn-sm" href="?<?= e($qs(['page' => $page + 1])) ?>"><?= e(t('explore.next')) ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
