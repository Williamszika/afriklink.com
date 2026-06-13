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
                <p class="muted shop-url-row">
                    <a href="<?= e(url($publicPath)) ?>" target="_blank" rel="noopener"><?= e($baseUrl) ?><?= e($publicPath) ?> ↗</a>
                    <button type="button" class="btn-copy" data-copy="<?= e(url($publicPath)) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>" aria-label="<?= e(t('shop.copy_url')) ?>" title="<?= e(t('shop.copy_url')) ?>"><span class="ico-copy" aria-hidden="true">⧉</span> <?= e(t('shop.copy_url')) ?></button>
                </p>
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
        <?php $nActive = (int) ($counts['active'] ?? 0); $nTotal = (int) ($counts['total'] ?? 0); ?>
        <div class="stat-grid cols-4">
            <a class="stat-card stat-card--link" href="<?= e(url('/boutique/gerer?filtre=en_ligne')) ?>#catalogue"
               data-filter-to="en_ligne" title="<?= e(t('shop.kpi.online_cta')) ?>">
                <div class="num"><span aria-hidden="true">📦</span> <?= $nActive ?></div>
                <div class="lbl"><?= e(t('shop.kpi.online')) ?></div>
                <div class="stat-cta"><?= e(t('shop.kpi.online_cta')) ?> →</div>
            </a>
            <a class="stat-card stat-card--link" href="<?= e(url('/boutique/gerer?filtre=tous')) ?>#catalogue"
               data-filter-to="tous" title="<?= e(t('shop.kpi.total_cta')) ?>">
                <div class="num"><span aria-hidden="true">🗂️</span> <?= $nTotal ?></div>
                <div class="lbl"><?= e(t('shop.kpi.total')) ?></div>
                <div class="stat-cta"><?= e(t('shop.kpi.total_cta')) ?> →</div>
            </a>
            <?php $nPending = (int) ($orders_pending ?? 0); ?>
            <a class="stat-card stat-card--link<?= $nPending > 0 ? ' stat-card--urgent' : '' ?>"
               href="<?= e(url('/vendeur/commandes?filtre=a_traiter')) ?>" title="<?= e(t('shop.kpi.orders_cta')) ?>">
                <div class="num"><span aria-hidden="true">🧾</span> <?= $nPending ?></div>
                <div class="lbl"><?= e(t('seller.stat.orders')) ?></div>
                <div class="stat-cta"><?= e(t('shop.kpi.orders_cta')) ?> →</div>
            </a>
            <a class="stat-card stat-card--link" href="<?= e(url('/boutique/stats')) ?>" title="<?= e(t('shop.kpi.views_cta')) ?>">
                <div class="num"><span aria-hidden="true">👁️</span> <?= (int) ($views_total ?? 0) ?></div>
                <div class="lbl"><?= e(t('seller.stat.views')) ?></div>
                <div class="stat-cta"><?= e(t('shop.kpi.views_cta')) ?> →</div>
            </a>
        </div>

        <!-- Partage & QR code -->
        <div class="panel">
            <h2 class="panel-title">📣 <?= e(t('shop.share_title')) ?></h2>
            <?= render_partial('partials/share_row', [
                'share_url'  => url($publicPath),
                'share_text' => t('share.shop_text', ['name' => (string) $boutique['name']]),
            ]) ?>
            <div class="qr-block">
                <img class="qr-img" src="<?= e(url('/boutique/qr')) ?>" alt="<?= e(t('shop.qr_alt')) ?>" width="140" height="140">
                <div class="qr-side">
                    <p class="muted"><?= e(t('shop.qr_hint')) ?></p>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/qr?download=1')) ?>">⬇️ <?= e(t('shop.qr_download')) ?></a>
                </div>
            </div>
        </div>

        <!-- Catalogue -->
        <?php
        $filter   = $filter ?? 'tous';
        $nMasques = max(0, $nTotal - $nActive);
        $matches  = static fn (string $st, string $f): bool =>
            $f === 'tous' || ($f === 'en_ligne' && $st === 'active') || ($f === 'masques' && $st !== 'active');
        $visibleNow = $filter === 'en_ligne' ? $nActive : ($filter === 'masques' ? $nMasques : $nTotal);
        $emptyMsg = static fn (string $f): string => match ($f) {
            'en_ligne' => t('shop.filter_empty_online'),
            'masques'  => t('shop.filter_empty_hidden'),
            default    => t('shop.products_empty'),
        };
        ?>
        <div class="panel" id="catalogue" data-catalogue
             data-empty-tous="<?= e(t('shop.products_empty')) ?>"
             data-empty-en_ligne="<?= e(t('shop.filter_empty_online')) ?>"
             data-empty-masques="<?= e(t('shop.filter_empty_hidden')) ?>">
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
                <div class="catalogue-filters" role="tablist">
                    <?php foreach (['tous' => $nTotal, 'en_ligne' => $nActive, 'masques' => $nMasques] as $key => $n): ?>
                        <a class="chip-filter <?= $filter === $key ? 'is-active' : '' ?>" role="tab"
                           aria-selected="<?= $filter === $key ? 'true' : 'false' ?>" data-filter-to="<?= e($key) ?>"
                           href="<?= e(url('/boutique/gerer?filtre=' . $key)) ?>#catalogue">
                            <?= e(t('shop.filter.' . $key)) ?> <span class="chip-count"><?= (int) $n ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="filter-empty" data-filter-empty <?= $visibleNow > 0 ? 'hidden' : '' ?>>
                    <p><?= e($emptyMsg($filter)) ?></p>
                </div>

                <div class="product-rows">
                    <?php foreach ($products as $p): ?>
                        <?php
                        $main = $mains[(int) $p['id']] ?? null;
                        $active2 = $p['status'] === 'active';
                        $st = $active2 ? 'active' : 'hidden';
                        ?>
                        <div class="panel product-row<?= $matches($st, $filter) ? '' : ' is-hidden' ?>" data-status="<?= e($st) ?>">
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
                                    <?php if ($published && $active2): ?>
                                        <?= render_partial('partials/share_row', [
                                            'share_url'  => url($publicPath . '/p/' . $p['public_id']),
                                            'share_text' => t('share.product_text', ['name' => (string) $p['name']]),
                                            'compact'    => true,
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Politique de retour & remboursement -->
        <div class="panel">
            <h2 class="panel-title">↩️ <?= e(t('shop.policy_title')) ?></h2>
            <p class="muted"><?= e(t('shop.policy_hint')) ?></p>
            <form method="post" action="<?= e(url('/boutique/politique')) ?>">
                <?= csrf_field() ?>
                <textarea name="return_policy" rows="4" maxlength="2000" placeholder="<?= e(t('shop.policy_ph')) ?>"><?= e((string) ($boutique['return_policy'] ?? '')) ?></textarea>
                <button type="submit" class="btn btn-primary"><?= e(t('shop.policy_save')) ?></button>
            </form>
        </div>

    </div>
</div>
