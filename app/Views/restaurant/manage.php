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
            <div class="shop-logo shop-logo--empty" aria-hidden="true"><?= icon('utensils', ['size' => 34]) ?></div>
            <div class="shop-admin-id">
                <h1><?= e((string) $resto['name']) ?>
                    <span class="badge <?= $published ? 'badge-ok' : 'badge-warn' ?>"><?= e(t($published ? 'shop.status.published' : 'shop.status.draft')) ?></span>
                </h1>
                <p class="muted shop-url-row">
                    <a href="<?= e(url('/restaurant/' . $resto['slug'])) ?>" target="_blank" rel="noopener"><?= e($baseUrl) ?>/restaurant/<?= e((string) $resto['slug']) ?> ↗</a>
                    <button type="button" class="btn-copy" data-copy="<?= e(url('/restaurant/' . $resto['slug'])) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><span class="ico-copy" aria-hidden="true"><?= icon('copy', ['size' => 15]) ?></span> <?= e(t('shop.copy_url')) ?></button>
                </p>
            </div>
            <div class="shop-admin-actions">
                <?php $rPending = \App\Models\RestaurantOrder::pendingForUser((int) $user['id']); ?>
                <a class="btn btn-ghost btn-sm<?= $rPending > 0 ? ' stat-card--urgent' : '' ?>" href="<?= e(url('/restaurant/commandes')) ?>"><?= icon('receipt', ['size' => 15]) ?> <?= e(t('rorder.nav')) ?><?= $rPending > 0 ? ' (' . $rPending . ')' : '' ?></a>
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
            <div class="stat-card"><div class="num"><?= icon('utensils', ['size' => 18]) ?> <?= (int) $counts['available'] ?></div><div class="lbl"><?= e(t('resto.kpi.available')) ?></div></div>
            <div class="stat-card"><div class="num"><?= icon('folder', ['size' => 18]) ?> <?= count($categories) ?></div><div class="lbl"><?= e(t('resto.kpi.categories')) ?></div></div>
            <div class="stat-card"><div class="num"><?= icon('list', ['size' => 18]) ?> <?= (int) $counts['total'] ?></div><div class="lbl"><?= e(t('resto.kpi.total')) ?></div></div>
        </div>

        <!-- Ajouter une catégorie -->
        <div class="panel">
            <h2 class="panel-title"><?= icon('folder', ['size' => 18]) ?> <?= e(t('resto.categories_title')) ?></h2>
            <form method="post" action="<?= e(url('/restaurant/categorie')) ?>" class="cat-add-form">
                <?= csrf_field() ?>
                <div class="inline-add">
                    <select id="cat-choice" name="choice" aria-label="<?= e(t('resto.cat_choice')) ?>">
                        <?php foreach (config('restaurant.standard_categories', []) as $key => $kKind): ?>
                            <option value="<?= e($key) ?>"><?= $kKind === 'drink' ? '🥤' : '🍽️' ?> <?= e(t('resto.cat.' . $key)) ?></option>
                        <?php endforeach; ?>
                        <option value="autre">✍️ <?= e(t('resto.cat.autre')) ?></option>
                    </select>
                    <button class="btn btn-ghost btn-sm">+ <?= e(t('resto.cat_add')) ?></button>
                </div>
                <div class="other-box" data-other-for="#cat-choice" data-other-value="autre" hidden>
                    <label for="cat-name"><?= e(t('field.other_specify')) ?></label>
                    <div class="inline-add">
                        <input type="text" id="cat-name" name="name" maxlength="60" placeholder="<?= e(t('resto.cat_ph')) ?>">
                        <select name="kind" aria-label="<?= e(t('resto.cat_kind')) ?>">
                            <option value="dish"><?= e(t('resto.kind.dish')) ?></option>
                            <option value="drink"><?= e(t('resto.kind.drink')) ?></option>
                        </select>
                    </div>
                </div>
            </form>
            <?php if ($categories === []): ?>
                <p class="muted"><?= e(t('resto.no_categories')) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($categories !== []): ?>
            <!-- Ajouter un plat / une boisson (le formulaire s'adapte à la catégorie) -->
            <?php $volumes = config('restaurant.drink_volumes', []); ?>
            <div class="panel">
                <h2 class="panel-title"><?= icon('plus', ['size' => 18]) ?> <?= e(t('resto.item_add_title')) ?></h2>
                <form method="post" action="<?= e(url('/restaurant/plat')) ?>" class="resto-item-form" data-itemform
                      data-l-dish="<?= e(t('resto.f.item_name')) ?>" data-l-drink="<?= e(t('resto.f.drink_name')) ?>">
                    <?= csrf_field() ?>
                    <label for="i-cat"><?= e(t('resto.f.item_cat')) ?></label>
                    <select id="i-cat" name="category" data-itemcat>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e((string) $c['public_id']) ?>" data-kind="<?= e((string) ($c['kind'] ?? 'dish')) ?>" <?= ($precat ?? '') === $c['public_id'] ? 'selected' : '' ?>>
                                <?= ($c['kind'] ?? '') === 'drink' ? '🥤 ' : '' ?><?= e((string) $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="i-name" data-item-namelabel><?= e(t('resto.f.item_name')) ?></label>
                    <input type="text" id="i-name" name="name" maxlength="80" required placeholder="<?= e(t('resto.f.item_name_ph')) ?>">
                    <?php if (has_error('item_name')): ?><p class="field-error"><?= e(error('item_name')) ?></p><?php endif; ?>

                    <!-- Bloc PLAT standard -->
                    <div data-kind-block="dish">
                        <label for="i-price"><?= e(t('resto.f.item_price')) ?> (<?= e($cur) ?>)</label>
                        <input type="text" id="i-price" name="price" inputmode="decimal" placeholder="0">
                        <?php if (has_error('item_price')): ?><p class="field-error"><?= e(error('item_price')) ?></p><?php endif; ?>
                        <label for="i-desc"><?= e(t('resto.f.item_desc')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="i-desc" name="description" maxlength="400" placeholder="<?= e(t('resto.f.item_desc_ph')) ?>">
                        <label><?= e(t('resto.f.diets')) ?></label>
                        <div class="lang-checks">
                            <?php foreach ($diets as $d): ?><label class="check-pill"><input type="checkbox" name="diets[]" value="<?= e($d) ?>"><span><?= e(t('resto.diet.' . $d)) ?></span></label><?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bloc BOISSON : contenances cochables + prix -->
                    <div data-kind-block="drink" hidden>
                        <label><?= e(t('resto.drink_volumes_label')) ?></label>
                        <p class="hint"><?= e(t('resto.drink_volumes_hint')) ?></p>
                        <div class="vol-rows">
                            <?php foreach ($volumes as $v): ?>
                                <div class="vol-row" data-vol-row>
                                    <label class="vol-check">
                                        <input type="checkbox" name="vol[]" value="<?= e($v) ?>">
                                        <span class="vol-size"><?= e(rtrim(rtrim((string) $v, '0'), '.')) ?> L</span>
                                    </label>
                                    <input type="text" name="vol_price[<?= e($v) ?>]" inputmode="decimal" class="vol-price" disabled
                                           placeholder="<?= e(t('resto.f.item_price')) ?> (<?= e($cur) ?>)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (has_error('item_price')): ?><p class="field-error"><?= e(error('item_price')) ?></p><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= e(t('resto.item_add')) ?></button>
                </form>
            </div>

            <!-- Carte actuelle -->
            <div class="panel">
                <h2 class="panel-title"><?= icon('list', ['size' => 18]) ?> <?= e(t('resto.menu_title')) ?></h2>
                <?php foreach ($categories as $c): ?>
                    <div class="menu-cat">
                        <div class="menu-cat-head">
                            <form method="post" action="<?= e(url('/restaurant/categorie/' . $c['public_id'] . '/renommer')) ?>" class="cat-rename inline-form">
                                <?= csrf_field() ?>
                                <input type="text" name="name" value="<?= e((string) $c['name']) ?>" maxlength="60" required
                                       aria-label="<?= e(t('resto.cat_rename_aria')) ?>">
                                <button class="btn btn-ghost btn-sm" title="<?= e(t('resto.cat_rename')) ?>"><?= icon('check', ['size' => 15]) ?> <?= e(t('resto.cat_rename')) ?></button>
                            </form>
                            <div class="menu-cat-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/restaurant/gerer?cat=' . $c['public_id'])) ?>#i-name"><?= icon('plus', ['size' => 15]) ?> <?= e(t('resto.add_dish_here')) ?></a>
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
                                        <?php $vars = \App\Models\MenuItem::variants($it['variants'] ?? null); ?>
                                        <?php if ($vars !== []): ?>
                                            <span class="menu-item-vars">
                                                <?php foreach ($vars as $vr): $vOut = !empty($vr['out']); ?>
                                                    <span class="vol-tag<?= $vOut ? ' is-out' : '' ?>">
                                                        <?= e(rtrim(rtrim((string) $vr['v'], '0'), '.')) ?> L · <?= e(format_price((int) $vr['p'], $cur)) ?><?= $vOut ? ' — ' . e(t('resto.size_out')) : '' ?>
                                                        <form method="post" action="<?= e(url('/restaurant/plat/' . $it['public_id'] . '/contenance')) ?>" class="inline-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="vol" value="<?= e((string) $vr['v']) ?>">
                                                            <button class="link-button vol-out-btn" name="action" value="<?= $vOut ? 'in' : 'out' ?>"
                                                                    title="<?= e($vOut ? t('resto.size_mark_in') : t('resto.size_mark_out')) ?>"><?= $vOut ? '↩ ' . e(t('resto.size_mark_in')) : e(t('resto.size_mark_out')) ?></button>
                                                        </form>
                                                    </span>
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="menu-item-price"><?= $vars !== [] ? e(t('resto.from_price', ['price' => format_price((int) $it['price_cents'], $cur)])) : e(format_price((int) $it['price_cents'], $cur)) ?></span>
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

        <!-- Zones de livraison locales (par quartier) -->
        <div class="panel" id="zones">
            <h2 class="panel-title"><?= icon('pin', ['size' => 18]) ?> <?= e(t('darea.title')) ?></h2>
            <p class="muted"><?= e(t('darea.lead')) ?></p>
            <?php if (!empty($delivery_areas)): ?>
                <ul class="zone-list">
                    <?php foreach ($delivery_areas as $z): ?>
                        <li class="zone-row">
                            <div class="zone-info">
                                <strong><?= e((string) $z['name']) ?></strong>
                                <span class="zone-meta"><?= e(format_price((int) $z['fee_cents'], $cur)) ?><?php if (!empty($z['free_above_cents'])): ?> · <?= e(t('ship.zone.free_above', ['amount' => format_price((int) $z['free_above_cents'], $cur)])) ?><?php endif; ?><?php if (!empty($z['delay'])): ?> · <?= e(t('shop.prep.' . $z['delay'])) ?><?php endif; ?></span>
                            </div>
                            <form method="post" action="<?= e(url('/restaurant/livraison/zones/' . $z['public_id'] . '/suppr')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <button class="link-button btn-danger" data-confirm="<?= e(t('darea.del_confirm')) ?>"><?= e(t('product.delete')) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <details class="zone-add">
                <summary><?= icon('plus', ['size' => 16]) ?> <?= e(t('darea.add')) ?></summary>
                <form method="post" action="<?= e(url('/restaurant/livraison/zones')) ?>" class="zone-form" data-submit-once>
                    <?= csrf_field() ?>
                    <label for="da-name"><?= e(t('darea.f.name')) ?></label>
                    <input type="text" id="da-name" name="name" maxlength="60" required placeholder="<?= e(t('darea.f.name_ph')) ?>">
                    <div class="grid-2">
                        <div><label for="da-fee"><?= e(t('ship.zone.f.fee', ['cur' => $cur])) ?></label><input type="text" id="da-fee" name="fee" inputmode="decimal" value="0" required></div>
                        <div><label for="da-free"><?= e(t('ship.zone.f.free_above', ['cur' => $cur])) ?></label><input type="text" id="da-free" name="free_above" inputmode="decimal" placeholder="<?= e(t('ship.zone.f.free_above_ph')) ?>"></div>
                    </div>
                    <label for="da-delay"><?= e(t('ship.zone.f.delay')) ?></label>
                    <select id="da-delay" name="delay">
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php foreach (config('shop.prep_options', []) as $opt): ?>
                            <option value="<?= e((string) $opt) ?>"><?= e(t('shop.prep.' . $opt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('darea.add_btn')) ?></button>
                </form>
            </details>
        </div>

        <!-- Encaissement en ligne (conditions + moyens + fournisseur) -->
        <div class="panel">
            <h2 class="panel-title"><?= icon('card', ['size' => 18]) ?> <?= e(t('resto.payment_title')) ?></h2>
            <p class="muted"><?= e(t('resto.payment_hint')) ?></p>
            <?php
            $rTerms = array_filter(explode(',', (string) ($resto['payment_terms'] ?? '')));
            $rMethods = array_filter(explode(',', (string) ($resto['payment_methods'] ?? '')));
            ?>
            <form method="post" action="<?= e(url('/restaurant/paiement')) ?>" class="resto-pay-form">
                <?= csrf_field() ?>
                <label><?= e(t('shop.f.payment_terms')) ?></label>
                <div class="lang-checks">
                    <?php foreach ((array) config('shop.payment_terms', []) as $pt): ?>
                        <label class="check-pill"><input type="checkbox" name="payment_terms[]" value="<?= e($pt) ?>" <?= in_array($pt, $rTerms, true) ? 'checked' : '' ?>><span><?= e(t('shop.payterm.' . $pt)) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <label><?= e(t('shop.f.payment_methods')) ?></label>
                <div class="lang-checks pay-method-checks">
                    <?php foreach ((array) config('shop.payment_methods', []) as $pm): ?>
                        <label class="check-pill"><input type="checkbox" name="payment_methods[]" value="<?= e($pm) ?>" <?= in_array($pm, $rMethods, true) ? 'checked' : '' ?>><img src="<?= e(asset('img/pay/' . $pm . '.svg')) ?>" alt="" width="30" height="19"><span><?= e(t('shop.paymethod.' . $pm)) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <label for="r-provider"><?= e(t('pay.provider_label')) ?></label>
                <select id="r-provider" name="payment_provider">
                    <option value=""><?= e(t('pay.provider_none')) ?></option>
                    <?php foreach ((array) config('payment.providers', []) as $key => $p): if (!empty($p['always'])) { continue; } ?>
                        <option value="<?= e((string) $key) ?>" <?= ($resto['payment_provider'] ?? '') === $key ? 'selected' : '' ?>><?= e((string) $p['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?= e(t('pay.env_hint')) ?></p>
                <button type="submit" class="btn btn-primary"><?= e(t('resto.payment_save')) ?></button>
            </form>
        </div>

    </div>
</div>
