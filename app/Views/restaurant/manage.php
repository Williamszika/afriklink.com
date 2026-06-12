<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $resto  @var list<array> $categories  @var list<array> $items  @var array $counts */
$published = ($resto['status'] ?? 'draft') === 'published';
$cur = (string) $resto['currency'];
$diets = config('restaurant.diets', []);
$baseUrl = preg_replace('#^https?://#', '', rtrim((string) (config('app.url') ?: 'afriklink.com'), '/'));
$byCat = [];
foreach ($items as $it) { $byCat[(int) $it['category_id']][] = $it; }
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="panel shop-admin-head">
            <div class="shop-logo shop-logo--empty" aria-hidden="true">🍽️</div>
            <div class="shop-admin-id">
                <h1><?= e((string) $resto['name']) ?>
                    <span class="badge <?= $published ? 'badge-ok' : 'badge-warn' ?>"><?= e(t($published ? 'shop.status.published' : 'shop.status.draft')) ?></span>
                </h1>
                <p class="muted shop-url-row">
                    <a href="<?= e(url('/restaurant/' . $resto['slug'])) ?>" target="_blank" rel="noopener"><?= e($baseUrl) ?>/restaurant/<?= e((string) $resto['slug']) ?> ↗</a>
                    <button type="button" class="btn-copy" data-copy="<?= e(url('/restaurant/' . $resto['slug'])) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><span class="ico-copy" aria-hidden="true">⧉</span> <?= e(t('shop.copy_url')) ?></button>
                </p>
            </div>
            <div class="shop-admin-actions">
                <form method="post" action="<?= e(url('/restaurant/publier')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <?php if ($published): ?>
                        <button class="btn btn-ghost btn-sm" name="action" value="unpublish"><?= e(t('shop.unpublish')) ?></button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" name="action" value="publish"><?= e(t('shop.publish')) ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$published): ?><div class="notice notice-info"><p><?= e(t('resto.draft_banner')) ?></p></div><?php endif; ?>

        <div class="stat-grid cols-3">
            <div class="stat-card"><div class="num">🍲 <?= (int) $counts['available'] ?></div><div class="lbl"><?= e(t('resto.kpi.available')) ?></div></div>
            <div class="stat-card"><div class="num">🗂️ <?= count($categories) ?></div><div class="lbl"><?= e(t('resto.kpi.categories')) ?></div></div>
            <div class="stat-card"><div class="num">📋 <?= (int) $counts['total'] ?></div><div class="lbl"><?= e(t('resto.kpi.total')) ?></div></div>
        </div>

        <!-- Ajouter une catégorie -->
        <div class="panel">
            <h2 class="panel-title">🗂️ <?= e(t('resto.categories_title')) ?></h2>
            <form method="post" action="<?= e(url('/restaurant/categorie')) ?>" class="inline-add">
                <?= csrf_field() ?>
                <input type="text" name="name" maxlength="60" placeholder="<?= e(t('resto.cat_ph')) ?>" required>
                <button class="btn btn-ghost btn-sm">+ <?= e(t('resto.cat_add')) ?></button>
            </form>
            <?php if ($categories === []): ?>
                <p class="muted"><?= e(t('resto.no_categories')) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($categories !== []): ?>
            <!-- Ajouter un plat -->
            <div class="panel">
                <h2 class="panel-title">➕ <?= e(t('resto.item_add_title')) ?></h2>
                <form method="post" action="<?= e(url('/restaurant/plat')) ?>" class="resto-item-form">
                    <?= csrf_field() ?>
                    <div class="grid-2">
                        <div>
                            <label for="i-cat"><?= e(t('resto.f.item_cat')) ?></label>
                            <select id="i-cat" name="category"><?php foreach ($categories as $c): ?><option value="<?= e((string) $c['public_id']) ?>" <?= ($precat ?? '') === $c['public_id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div>
                            <label for="i-price"><?= e(t('resto.f.item_price')) ?> (<?= e($cur) ?>)</label>
                            <input type="text" id="i-price" name="price" inputmode="decimal" required placeholder="0">
                            <?php if (has_error('item_price')): ?><p class="field-error"><?= e(error('item_price')) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <label for="i-name"><?= e(t('resto.f.item_name')) ?></label>
                    <input type="text" id="i-name" name="name" maxlength="80" required placeholder="<?= e(t('resto.f.item_name_ph')) ?>">
                    <?php if (has_error('item_name')): ?><p class="field-error"><?= e(error('item_name')) ?></p><?php endif; ?>
                    <label for="i-desc"><?= e(t('resto.f.item_desc')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                    <input type="text" id="i-desc" name="description" maxlength="400" placeholder="<?= e(t('resto.f.item_desc_ph')) ?>">
                    <label><?= e(t('resto.f.diets')) ?></label>
                    <div class="lang-checks">
                        <?php foreach ($diets as $d): ?><label class="check-pill"><input type="checkbox" name="diets[]" value="<?= e($d) ?>"><span><?= e(t('resto.diet.' . $d)) ?></span></label><?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= e(t('resto.item_add')) ?></button>
                </form>
            </div>

            <!-- Carte actuelle -->
            <div class="panel">
                <h2 class="panel-title">📋 <?= e(t('resto.menu_title')) ?></h2>
                <?php foreach ($categories as $c): ?>
                    <div class="menu-cat">
                        <div class="menu-cat-head">
                            <form method="post" action="<?= e(url('/restaurant/categorie/' . $c['public_id'] . '/renommer')) ?>" class="cat-rename inline-form">
                                <?= csrf_field() ?>
                                <input type="text" name="name" value="<?= e((string) $c['name']) ?>" maxlength="60" required
                                       aria-label="<?= e(t('resto.cat_rename_aria')) ?>">
                                <button class="btn btn-ghost btn-sm" title="<?= e(t('resto.cat_rename')) ?>">✓ <?= e(t('resto.cat_rename')) ?></button>
                            </form>
                            <div class="menu-cat-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/restaurant/gerer?cat=' . $c['public_id'])) ?>#i-name">➕ <?= e(t('resto.add_dish_here')) ?></a>
                                <form method="post" action="<?= e(url('/restaurant/categorie/' . $c['public_id'] . '/suppr')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button class="link-button btn-danger" data-confirm="<?= e(t('resto.cat_delete_confirm')) ?>"><?= e(t('product.delete')) ?></button>
                                </form>
                            </div>
                        </div>
                        <?php $list = $byCat[(int) $c['id']] ?? []; ?>
                        <?php if ($list === []): ?>
                            <p class="muted hint"><?= e(t('resto.cat_empty')) ?></p>
                        <?php else: ?>
                            <?php foreach ($list as $it): $avail = (int) $it['is_available'] === 1; ?>
                                <div class="menu-item-row<?= $avail ? '' : ' is-off' ?>">
                                    <div class="menu-item-main">
                                        <span class="menu-item-name"><?= e((string) $it['name']) ?>
                                            <?php foreach (array_filter(explode(',', (string) ($it['diets'] ?? ''))) as $dt): ?><span class="diet-badge"><?= e(t('resto.diet.' . $dt)) ?></span><?php endforeach; ?>
                                            <?php if (!$avail): ?><span class="badge badge-neutral"><?= e(t('resto.unavailable')) ?></span><?php endif; ?>
                                        </span>
                                        <?php if (!empty($it['description'])): ?><span class="menu-item-desc"><?= e((string) $it['description']) ?></span><?php endif; ?>
                                    </div>
                                    <span class="menu-item-price"><?= e(format_price((int) $it['price_cents'], $cur)) ?></span>
                                    <form method="post" action="<?= e(url('/restaurant/plat/' . $it['public_id'] . '/statut')) ?>" class="inline-form menu-item-actions">
                                        <?= csrf_field() ?>
                                        <button class="link-button" name="action" value="<?= $avail ? 'unavailable' : 'available' ?>"><?= e($avail ? t('resto.mark_off') : t('resto.mark_on')) ?></button>
                                        <button class="link-button btn-danger" name="action" value="delete" data-confirm="<?= e(t('resto.item_delete_confirm')) ?>">✕</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
