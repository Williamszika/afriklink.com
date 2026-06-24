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

        <?php if (isset($readiness)): ?>
        <div class="panel readiness-panel">
            <div class="readiness-head">
                <h2 class="panel-title">✅ <?= e(t('shop.ready.title')) ?></h2>
                <span class="readiness-score"><?= (int) $readiness['score'] ?>%</span>
            </div>
            <div class="readiness-bar"><span style="width:<?= (int) $readiness['score'] ?>%"></span></div>
            <ul class="readiness-list">
                <?php foreach ($readiness['items'] as $it): ?>
                    <li class="<?= $it['done'] ? 'is-done' : 'is-todo' ?>">
                        <span class="readiness-ico" aria-hidden="true"><?= $it['done'] ? '✓' : '○' ?></span>
                        <?php if (!$it['done'] && !empty($it['href'])): ?>
                            <a href="<?= e(url($it['href'])) ?>"><?= e(t('shop.ready.' . $it['key'])) ?></a>
                        <?php else: ?>
                            <?= e(t('shop.ready.' . $it['key'])) ?>
                        <?php endif; ?>
                        <?php if (!$it['req']): ?> <span class="muted">(<?= e(t('shop.ready.optional')) ?>)</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$readiness['ready']): ?>
                <p class="hint"><?= e(t('shop.ready.hint')) ?></p>
            <?php elseif (!$published): ?>
                <p class="readiness-ok">🎉 <?= e(t('shop.ready.ok')) ?></p>
            <?php endif; ?>
            <?php if (!empty($readiness['warnings'])): ?>
                <div class="readiness-warnings">
                    <p class="rw-title"><?= e(t('shop.warn.title')) ?></p>
                    <ul>
                        <?php foreach ($readiness['warnings'] as $wn): ?>
                            <li class="rw-<?= e($wn['level']) ?>">
                                <span><?= e(t('shop.warn.' . $wn['key'])) ?></span>
                                <?php if (!empty($wn['href'])): ?> <a href="<?= e(url($wn['href'])) ?>"><?= e(t('shop.warn.fix')) ?> →</a><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
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
            <?php if (!empty($restock_count)): ?>
                <p class="restock-summary">🔁 <?= e(t('forecast.restock_summary', ['n' => (int) $restock_count])) ?></p>
            <?php endif; ?>

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
                                    <?php if (!empty($p['pinned'])): ?><span class="badge badge-pin">📌 <?= e(t('product.pinned')) ?></span><?php endif; ?>
                                </p>
                                <p class="product-row-meta">
                                    <strong><?= e(format_price_local((int) $p['price_cents'], $cur)) ?></strong>
                                    · <?= $p['stock'] === null ? e(t('product.stock_unlimited')) : e(t('product.stock_n', ['n' => (int) $p['stock']])) ?>
                                </p>
                                <?php $fc = $forecasts[(int) $p['id']] ?? null; ?>
                                <?php if ($fc !== null && $fc['status'] !== 'unlimited'): ?>
                                    <p class="product-forecast forecast-<?= e($fc['status']) ?>" title="<?= e(t('forecast.rate', ['sold' => (int) $fc['sold'], 'win' => (int) $fc['window']])) ?>">
                                        <?php if ($fc['status'] === 'out'): ?>
                                            🔴 <?= e(t('forecast.out')) ?>
                                        <?php elseif ($fc['status'] === 'nodata'): ?>
                                            <span class="muted">📊 <?= e(t('forecast.nodata')) ?></span>
                                        <?php else: ?>
                                            <?= $fc['status'] === 'critical' ? '⚠️' : ($fc['status'] === 'soon' ? '🟠' : '🟢') ?>
                                            <?= e(t('forecast.days', ['n' => (int) $fc['days_left']])) ?>
                                            <span class="muted forecast-rate"><?= e(t('forecast.rate', ['sold' => (int) $fc['sold'], 'win' => (int) $fc['window']])) ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="product-row-actions">
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/produits/' . $p['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                                    <form method="post" action="<?= e(url('/boutique/produits/' . $p['public_id'] . '/statut')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <?php if ($active2): ?>
                                            <button class="btn btn-ghost btn-sm" name="action" value="hide"><?= e(t('product.hide')) ?></button>
                                            <?php if (!empty($p['pinned'])): ?>
                                                <button class="btn btn-ghost btn-sm" name="action" value="unpin">📌 <?= e(t('product.unpin')) ?></button>
                                            <?php else: ?>
                                                <button class="btn btn-ghost btn-sm" name="action" value="pin">📌 <?= e(t('product.pin')) ?></button>
                                            <?php endif; ?>
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

        <!-- Zones de livraison (groupes de pays × tarif) -->
        <div class="panel" id="zones">
            <h2 class="panel-title"><?= icon('truck', ['size' => 18]) ?> <?= e(t('ship.zone.title')) ?></h2>
            <p class="muted"><?= e(t('ship.zone.lead')) ?></p>
            <?php if (!empty($shipping_zones)): ?>
                <ul class="zone-list">
                    <?php foreach ($shipping_zones as $z):
                        $codes = array_filter(array_map('trim', explode(',', (string) ($z['countries'] ?? ''))));
                        $names = $codes === []
                            ? t('ship.zone.rest')
                            : implode(', ', array_map(static fn (string $c): string => (string) (config('countries')[$c] ?? $c), $codes));
                    ?>
                        <li class="zone-row">
                            <div class="zone-info">
                                <strong><?= e((string) $z['name']) ?></strong>
                                <span class="muted zone-countries"><?= e($names) ?></span>
                                <span class="zone-meta">
                                    <?php $nt = !empty($z['tiers']) ? count(json_decode((string) $z['tiers'], true) ?: []) : 0; ?>
                                    <?php if ($nt > 0): ?>
                                        <?= e(t('ship.zone.tiers_n', ['n' => $nt])) ?>
                                    <?php else: ?>
                                        <?= e(format_price_local((int) $z['fee_cents'], $cur)) ?><?php if (!empty($z['free_above_cents'])): ?> · <?= e(t('ship.zone.free_above', ['amount' => format_price((int) $z['free_above_cents'], $cur)])) ?><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($z['delay'])): ?> · <?= e(t('shop.prep.' . $z['delay'])) ?><?php endif; ?>
                                </span>
                            </div>
                            <form method="post" action="<?= e(url('/boutique/livraison/zones/' . $z['public_id'] . '/suppr')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <button class="link-button btn-danger" data-confirm="<?= e(t('ship.zone.del_confirm')) ?>"><?= e(t('product.delete')) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <details class="zone-add">
                <summary><?= icon('plus', ['size' => 16]) ?> <?= e(t('ship.zone.add')) ?></summary>
                <form method="post" action="<?= e(url('/boutique/livraison/zones')) ?>" class="zone-form" data-submit-once>
                    <?= csrf_field() ?>
                    <label for="z-name"><?= e(t('ship.zone.f.name')) ?></label>
                    <input type="text" id="z-name" name="name" maxlength="60" required placeholder="<?= e(t('ship.zone.f.name_ph')) ?>">

                    <label><?= e(t('ship.zone.f.countries')) ?></label>
                    <label class="check-pill"><input type="checkbox" name="rest" value="1"> <span><?= e(t('ship.zone.f.rest')) ?></span></label>
                    <select name="countries[]" multiple size="6" class="zone-countries-select">
                        <?php foreach (config('countries', []) as $code => $cn): ?>
                            <option value="<?= e((string) $code) ?>"><?= e((string) $cn) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint"><?= e(t('ship.zone.f.countries_hint')) ?></p>

                    <div class="grid-2">
                        <div>
                            <label for="z-fee"><?= e(t('ship.zone.f.fee', ['cur' => $cur])) ?></label>
                            <input type="text" id="z-fee" name="fee" inputmode="decimal" value="0" required>
                        </div>
                        <div>
                            <label for="z-free"><?= e(t('ship.zone.f.free_above', ['cur' => $cur])) ?></label>
                            <input type="text" id="z-free" name="free_above" inputmode="decimal" placeholder="<?= e(t('ship.zone.f.free_above_ph')) ?>">
                        </div>
                    </div>
                    <label for="z-delay"><?= e(t('ship.zone.f.delay')) ?></label>
                    <select id="z-delay" name="delay">
                        <option value=""><?= e(t('field.choose')) ?></option>
                        <?php foreach (config('shop.prep_options', []) as $opt): ?>
                            <option value="<?= e((string) $opt) ?>"><?= e(t('shop.prep.' . $opt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="z-tiers"><?= e(t('ship.zone.f.tiers')) ?></label>
                    <textarea id="z-tiers" name="tiers" rows="3" placeholder="<?= e(t('ship.zone.f.tiers_ph')) ?>"></textarea>
                    <p class="hint"><?= e(t('ship.zone.f.tiers_hint')) ?></p>
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('ship.zone.add_btn')) ?></button>
                </form>
            </details>
        </div>

        <!-- Transporteurs proposés au client (niveau 1 : le client choisit + tarif fixe) -->
        <?php
        $shopCar = shop_carriers($boutique);
        $carMap = [];
        foreach ($shopCar as $rc) { $carMap[$rc['scope'] . ':' . $rc['c']] = (int) $rc['fee']; }
        $carScopes = ['intl' => t('carrier.scope.intl'), 'eu' => t('carrier.scope.eu'), 'ci' => t('carrier.scope.ci')];
        $curInt = currency_is_integer($cur);
        ?>
        <div class="panel" id="carriers">
            <h2 class="panel-title"><?= icon('truck', ['size' => 18]) ?> <?= e(t('shop.carriers_title')) ?></h2>
            <p class="muted"><?= e(t('shop.carriers_lead')) ?></p>
            <form method="post" action="<?= e(url('/boutique/livraison/transporteurs')) ?>" data-submit-once>
                <?= csrf_field() ?>
                <?php foreach ($carScopes as $scope => $scopeLabel): ?>
                    <fieldset class="carrier-group">
                        <legend><?= e($scopeLabel) ?></legend>
                        <div class="carrier-rows">
                            <?php foreach (carriers_for_scope($scope) as $ck => $cdef): $key = $scope . ':' . $ck; $on = isset($carMap[$key]); ?>
                                <label class="carrier-row">
                                    <input type="checkbox" name="car[<?= e($scope) ?>][<?= e($ck) ?>]" value="1"<?= $on ? ' checked' : '' ?>>
                                    <span class="carrier-row__name"><?= e(carrier_label($ck)) ?></span>
                                    <span class="carrier-row__fee">
                                        <input type="text" inputmode="decimal" name="carfee[<?= e($scope) ?>][<?= e($ck) ?>]" class="input-sm"
                                               value="<?= $on ? e(number_format($carMap[$key] / 100, $curInt ? 0 : 2, '.', '')) : '' ?>"
                                               placeholder="<?= e(t('shop.carriers_fee_ph')) ?>">
                                        <span class="muted"><?= e($cur) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(t('shop.carriers_save')) ?></button>
            </form>
        </div>

        <!-- Promotions / codes promo -->
        <div class="panel" id="promos">
            <h2 class="panel-title">🏷️ <?= e(t('promo.title')) ?></h2>
            <p class="muted"><?= e(t('promo.lead')) ?></p>
            <form method="post" action="<?= e(url('/boutique/promotions')) ?>" class="promo-form">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div>
                        <label for="promo-code"><?= e(t('promo.f.code')) ?></label>
                        <input type="text" id="promo-code" name="code" maxlength="40" placeholder="<?= e(t('promo.f.code_ph')) ?>" required style="text-transform:uppercase">
                    </div>
                    <div>
                        <label for="promo-type"><?= e(t('promo.f.type')) ?></label>
                        <select id="promo-type" name="type">
                            <option value="percent"><?= e(t('promo.type.percent')) ?></option>
                            <option value="amount"><?= e(t('promo.type.amount', ['cur' => $cur])) ?></option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="promo-value"><?= e(t('promo.f.value')) ?></label>
                        <input type="text" id="promo-value" name="value" inputmode="decimal" placeholder="10" required>
                    </div>
                    <div>
                        <label for="promo-min"><?= e(t('promo.f.min')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                        <input type="text" id="promo-min" name="min_order" inputmode="decimal" placeholder="0">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(t('promo.create_btn')) ?></button>
            </form>
            <?php if (!empty($discounts)): ?>
                <ul class="promo-list">
                    <?php foreach ($discounts as $d): $on = ($d['status'] ?? '') === 'active'; ?>
                        <li class="promo-item<?= $on ? '' : ' is-off' ?>">
                            <span class="promo-code-tag"><?= e((string) $d['code']) ?></span>
                            <span class="muted"><?= $d['type'] === 'amount' ? e(format_price_local((int) $d['value'], $cur)) : (int) $d['value'] . ' %' ?><?php if (!empty($d['min_order_cents'])): ?> · <?= e(t('promo.min_short', ['amount' => format_price((int) $d['min_order_cents'], $cur)])) ?><?php endif; ?> · <?= (int) ($d['uses'] ?? 0) ?> <?= e(t('promo.uses')) ?></span>
                            <form method="post" action="<?= e(url('/boutique/promotions/' . $d['id'] . '/statut')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <button class="link-button" name="action" value="<?= $on ? 'disable' : 'enable' ?>"><?= e(t($on ? 'promo.disable' : 'promo.enable')) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
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
