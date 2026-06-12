<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $boutique  @var list<array> $products  @var array<int,string> $mains  @var array $counts */
use App\Services\CloudinaryService;

$published = ($boutique['status'] ?? 'draft') === 'published';
$logo   = $boutique['logo_public_id'] ?? null;
$banner = $boutique['banner_public_id'] ?? null;
$cur    = (string) $boutique['currency'];
$baseUrl = preg_replace('#^https?://#', '', rtrim((string) (config('app.url') ?: 'afriklink.com'), '/'));
$publicPath = '/boutique/' . $boutique['slug'];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <!-- En-tête boutique -->
        <div class="panel shop-admin-head">
            <?php if ($logo !== null): ?>
                <img class="shop-logo" src="<?= e(CloudinaryService::imageUrl($logo, 120, 120)) ?>" alt="" width="56" height="56">
            <?php else: ?>
                <div class="shop-logo shop-logo--empty" aria-hidden="true">🛍️</div>
            <?php endif; ?>
            <div class="shop-admin-id">
                <h1><?= e((string) $boutique['name']) ?>
                    <span class="badge <?= $published ? 'badge-ok' : 'badge-warn' ?>"><?= e(t($published ? 'shop.status.published' : 'shop.status.draft')) ?></span>
                </h1>
                <p class="muted"><a href="<?= e(url($publicPath)) ?>" target="_blank" rel="noopener"><?= e($baseUrl) ?><?= e($publicPath) ?> ↗</a></p>
            </div>
            <div class="shop-admin-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/modifier')) ?>">✏️ <?= e(t('shop.edit_shop')) ?></a>
                <form method="post" action="<?= e(url('/boutique/publier')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <?php if ($published): ?>
                        <button class="btn btn-ghost btn-sm" name="action" value="unpublish"><?= e(t('shop.unpublish')) ?></button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" name="action" value="publish"><?= e(t('shop.publish')) ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$published): ?>
            <div class="notice notice-info"><p><?= e(t('shop.draft_banner')) ?></p></div>
        <?php endif; ?>

        <!-- Indicateurs -->
        <div class="stat-grid cols-4">
            <div class="stat-card"><div class="num"><span aria-hidden="true">📦</span> <?= (int) ($counts['active'] ?? 0) ?></div>
                <div class="lbl"><?= e(t('shop.kpi.online')) ?></div></div>
            <div class="stat-card"><div class="num"><span aria-hidden="true">🗂️</span> <?= (int) ($counts['total'] ?? 0) ?></div>
                <div class="lbl"><?= e(t('shop.kpi.total')) ?></div></div>
            <div class="stat-card"><div class="num"><span aria-hidden="true">🧾</span> 0</div>
                <div class="lbl"><?= e(t('seller.stat.orders')) ?></div><div class="phase"><?= e(t('dash.phase', ['n' => 3])) ?></div></div>
            <div class="stat-card"><div class="num"><span aria-hidden="true">👁️</span> 0</div>
                <div class="lbl"><?= e(t('seller.stat.views')) ?></div><div class="phase"><?= e(t('dash.phase', ['n' => 4])) ?></div></div>
        </div>

        <!-- Catalogue -->
        <div class="panel">
            <div class="panel-title-row">
                <h2 class="panel-title">📦 <?= e(t('shop.products_title')) ?></h2>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/boutique/produits/nouveau')) ?>">+ <?= e(t('product.add')) ?></a>
            </div>

            <?php if ($products === []): ?>
                <div class="empty-state">
                    <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">📦</p>
                    <p><?= e(t('shop.products_empty')) ?></p>
                    <a class="btn btn-primary" href="<?= e(url('/boutique/produits/nouveau')) ?>"><?= e(t('product.add_first')) ?></a>
                </div>
            <?php else: ?>
                <div class="product-rows">
                    <?php foreach ($products as $p): ?>
                        <?php $main = $mains[(int) $p['id']] ?? null; $active2 = $p['status'] === 'active'; ?>
                        <div class="panel product-row">
                            <div class="product-thumb">
                                <?php if ($main !== null): ?>
                                    <img src="<?= e(CloudinaryService::imageUrl($main, 140, 140)) ?>" alt="" loading="lazy" width="70" height="70">
                                <?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                            </div>
                            <div class="product-row-body">
                                <p class="product-row-title"><?= e((string) $p['name']) ?>
                                    <span class="badge <?= $active2 ? 'badge-ok' : 'badge-neutral' ?>"><?= e(t($active2 ? 'product.status.active' : 'product.status.hidden')) ?></span>
                                </p>
                                <p class="product-row-meta">
                                    <strong><?= e(format_price((int) $p['price_cents'], $cur)) ?></strong>
                                    · <?= $p['stock'] === null ? e(t('product.stock_unlimited')) : e(t('product.stock_n', ['n' => (int) $p['stock']])) ?>
                                </p>
                                <div class="product-row-actions">
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/produits/' . $p['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                                    <form method="post" action="<?= e(url('/boutique/produits/' . $p['public_id'] . '/statut')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <?php if ($active2): ?>
                                            <button class="btn btn-ghost btn-sm" name="action" value="hide"><?= e(t('product.hide')) ?></button>
                                        <?php else: ?>
                                            <button class="btn btn-ghost btn-sm" name="action" value="activate"><?= e(t('product.show')) ?></button>
                                        <?php endif; ?>
                                        <button class="btn btn-ghost btn-sm btn-danger" name="action" value="delete"
                                                data-confirm="<?= e(t('product.delete_confirm')) ?>"><?= e(t('product.delete')) ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
