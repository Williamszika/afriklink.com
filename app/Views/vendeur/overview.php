<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_version
 *  @var ?string $avatar_url  @var int $completion  @var list<array> $onboarding_steps  @var array $dash */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$stage       = (string) ($dash['stage'] ?? 'A');
$next        = $dash['next'] ?? null;
$pending     = (int) ($dash['pending'] ?? 0);
$boutique    = $dash['boutique'] ?? null;
$gc          = (string) ($dash['currency'] ?? 'XOF');
$shopUrl     = ($boutique !== null && ($boutique['slug'] ?? '') !== '') ? url('/boutique/' . $boutique['slug']) : null;
$isC         = $stage === 'C';

/* Bloc « complétion du profil » (réutilisé selon l'étape) */
$renderProfilePanel = static function () use ($completion, $onboarding_steps): void { ?>
    <section class="sdash-panel">
        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">👤</span> <?= e(t('pro.dash.profile_panel')) ?></h2><span class="sdash-pct"><?= (int) $completion ?>&nbsp;%</span></div>
        <div class="sdash-progress"><i style="width:<?= (int) $completion ?>%"></i></div>
        <ul class="sdash-check">
            <?php foreach ($onboarding_steps as $item): $done = !empty($item['done']); ?>
                <?php if (!$done && ($item['href'] ?? null) !== null): ?>
                    <li class="sdash-check-item is-todo"><a href="<?= e($item['href']) ?>"><span class="box" aria-hidden="true"></span><span class="lbl"><?= e($item['label']) ?></span><span class="arrow" aria-hidden="true">→</span></a></li>
                <?php else: ?>
                    <li class="sdash-check-item<?= $done ? ' is-done' : '' ?>"><span class="box" aria-hidden="true"><?= $done ? '✓' : '' ?></span><span class="lbl"><?= e($item['label']) ?></span></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php };

/* Bloc « conseils » (toujours affiché) */
$renderTipsPanel = static function (): void { ?>
    <section class="sdash-panel">
        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">💡</span> <?= e(t('seller.tips_title')) ?></h2></div>
        <div class="sdash-tips">
            <div class="sdash-tip"><span aria-hidden="true">✅</span><span><?= e(t('seller.tip1')) ?></span></div>
            <div class="sdash-tip"><span aria-hidden="true">✅</span><span><?= e(t('seller.tip2')) ?></span></div>
            <div class="sdash-tip"><span aria-hidden="true">✅</span><span><?= e(t('seller.tip3')) ?></span></div>
        </div>
    </section>
<?php };
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sdash">

        <!-- Barre de titre : accueil + actions (secondaire puis principale) -->
        <div class="sdash-topbar">
            <div class="sdash-hello">
                <h1><?= e(t('pro.dash.welcome', ['name' => $companyName])) ?></h1>
                <p><?= e(t('seller.sub.' . $stage)) ?></p>
            </div>
            <div class="sdash-actions">
                <?php if ($shopUrl !== null): ?>
                    <a class="btn btn-ghost" href="<?= e($shopUrl) ?>" target="_blank" rel="noopener"><?= e(t('seller.dash.view_shop')) ?></a>
                <?php endif; ?>
                <a class="btn btn-gold" href="<?= e(url('/vendeur/vitrines')) ?>"><?= icon('plus', ['size' => 16]) ?> <?= e(t('seller.dash.create_storefront')) ?></a>
            </div>
        </div>

        <!-- Bandeau : vérification en cours -->
        <?php if (!$verified): ?>
            <div class="sdash-banner">
                <span class="sdash-banner-ic" aria-hidden="true">⏳</span>
                <div class="sdash-banner-txt">
                    <b><?= e(t('pro.dash.badge_pending')) ?></b>
                    <p><?= e(t('pro.dash.pending_delay')) ?></p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/vendeur/verification')) ?>"><?= e(t('seller.dash.follow')) ?></a>
            </div>
        <?php endif; ?>

        <!-- Bandeau : activer l'affiliation (boutique publiée) -->
        <?php if ($boutique !== null && ($boutique['status'] ?? '') === 'published' && empty($dash['aff_enabled'])): ?>
            <div class="sdash-banner sdash-banner--info">
                <span class="sdash-banner-ic" aria-hidden="true">📣</span>
                <div class="sdash-banner-txt"><b><?= e(t('aff.nudge_title')) ?></b><p><?= e(t('aff.nudge_body')) ?></p></div>
                <a class="btn btn-gold btn-sm" href="<?= e(url('/affiliation')) ?>"><?= e(t('aff.nudge_cta')) ?></a>
            </div>
        <?php endif; ?>

        <!-- KPIs (réels si vendeur actif, en sourdine « — » sinon) -->
        <?php
        $kpis = [
            ['icon' => 'package', 'label' => t('seller.kpi.orders_all'), 'value' => (string) (int) ($dash['order_n'] ?? 0),  'href' => url('/vendeur/commandes')],
            ['icon' => 'receipt', 'label' => t('seller.stat.orders'),    'value' => (string) $pending,                       'href' => url('/vendeur/commandes?filtre=a_traiter'), 'alert' => $pending > 0],
            ['icon' => 'eye',     'label' => t('seller.stat.views'),      'value' => (string) (int) ($dash['views'] ?? 0),    'href' => url('/boutique/stats')],
            ['icon' => 'tag',     'label' => t('seller.kpi.products'),    'value' => (string) (int) ($dash['product_n'] ?? 0), 'href' => url('/boutique/gerer')],
        ];
        ?>
        <div class="sdash-kpis">
            <?php foreach ($kpis as $k): ?>
                <a class="sdash-kpi<?= !empty($k['alert']) ? ' is-alert' : '' ?>" href="<?= e($k['href']) ?>">
                    <span class="sdash-kpi-ic" aria-hidden="true"><?= icon($k['icon'], ['size' => 19]) ?></span>
                    <b class="sdash-kpi-num"><?= $isC ? e($k['value']) : '—' ?></b>
                    <span class="sdash-kpi-lbl"><?= e($k['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$isC): ?><p class="sdash-soon"><?= e(t('seller.kpi.soon')) ?></p><?php endif; ?>

        <?php if ($isC): ?>
            <?php
            $revBars = array_map(static fn (array $p): array => [
                'value' => (int) $p['cents'],
                'label' => (string) (int) date('j', strtotime((string) $p['date'])),
                'title' => date('d/m', strtotime((string) $p['date'])) . ' · ' . format_price_owner((int) $p['cents'], $gc),
            ], (array) ($dash['revenue_by_day'] ?? []));
            $topProducts = (array) ($dash['top_products'] ?? []);
            $recent      = (array) ($dash['recent_orders'] ?? []);
            $lowStock    = (array) ($dash['low_stock'] ?? []);
            $revPrev = (int) ($dash['revenue_prev_month'] ?? 0);
            $revCur  = (int) ($dash['revenue_month'] ?? 0);
            $revDelta = $revPrev > 0 ? (int) round(($revCur - $revPrev) / $revPrev * 100) : ($revCur > 0 ? 100 : 0);
            $sb = (array) ($dash['status_breakdown'] ?? []);
            $sbTot = (int) ($sb['active'] ?? 0) + (int) ($sb['delivered'] ?? 0) + (int) ($sb['cancelled'] ?? 0);
            ?>
            <div class="sdash-grid">
                <!-- Colonne principale -->
                <div class="sdash-col">
                    <!-- Revenus -->
                    <section class="sdash-panel">
                        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">📈</span> <?= e(t('seller.cockpit.revenue_month')) ?></h2><span class="sdash-eyebrow"><?= e(t('wallet.gains_14d')) ?></span></div>
                        <div class="sdash-rev-top">
                            <span class="sdash-rev-amount"><?= e(format_price_owner($revCur, $gc)) ?></span>
                            <?php if ($revPrev > 0 || $revCur > 0): ?>
                                <span class="sdash-trend <?= $revDelta >= 0 ? 'is-up' : 'is-down' ?>"><?= $revDelta >= 0 ? '▲' : '▼' ?> <?= abs($revDelta) ?>&nbsp;%</span>
                                <span class="sdash-trend-cap"><?= e(t('seller.cockpit.vs_last_month')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?= render_partial('partials/area_chart', ['bars' => $revBars, 'cur' => $gc, 'height' => 130, 'uid' => 'rev']) ?>
                    </section>

                    <!-- Meilleures ventes -->
                    <section class="sdash-panel">
                        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🏆</span> <?= e(t('seller.cockpit.top_products')) ?></h2><span class="sdash-eyebrow"><?= e(t('seller.dash.d30')) ?></span></div>
                        <?php if ($topProducts === []): ?>
                            <p class="sdash-empty"><?= e(t('seller.cockpit.top_empty')) ?></p>
                        <?php else: ?>
                            <div class="sdash-bs-list">
                                <?php foreach ($topProducts as $i => $tp): ?>
                                    <div class="sdash-bs">
                                        <span class="sdash-bs-rank"><?= $i + 1 ?></span>
                                        <div class="sdash-bs-body"><span class="sdash-bs-name"><?= e((string) $tp['name']) ?></span><span class="sdash-bs-meta"><?= e(t('seller.dash.units_sold', ['n' => (int) $tp['units']])) ?></span></div>
                                        <span class="sdash-bs-price"><?= e(format_price_owner((int) $tp['cents'], $gc)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Dernières commandes -->
                    <section class="sdash-panel">
                        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🧾</span> <?= e(t('seller.cockpit.recent_orders')) ?></h2><a class="sdash-more" href="<?= e(url('/vendeur/commandes')) ?>"><?= e(t('seller.cockpit.all_orders')) ?> →</a></div>
                        <?php if ($recent === []): ?>
                            <p class="sdash-empty"><?= e(t('order.empty.toutes')) ?></p>
                        <?php else: ?>
                            <div class="sdash-ord-list">
                                <?php foreach ($recent as $o): $rst = (string) $o['status']; ?>
                                    <div class="sdash-ord">
                                        <div class="sdash-ord-id"><b>#<?= e(strtoupper(substr((string) $o['public_id'], 0, 6))) ?></b><span><?= e((string) $o['client_name']) ?></span></div>
                                        <span class="sdash-ord-amt"><?= e(format_price_owner((int) $o['total_cents'], (string) $o['currency'])) ?></span>
                                        <span class="sdash-status sdash-status--<?= $rst === 'delivered' ? 'ok' : ($rst === 'cancelled' ? 'no' : 'wait') ?>"><?= e(t('order.status.' . $rst)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Colonne latérale -->
                <div class="sdash-col">
                    <!-- Répartition des commandes -->
                    <section class="sdash-panel">
                        <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🍩</span> <?= e(t('seller.cockpit.status_title')) ?></h2></div>
                        <?php if ($sbTot === 0): ?>
                            <p class="sdash-empty"><?= e(t('order.empty.toutes')) ?></p>
                        <?php else: ?>
                            <div class="sdash-donut-wrap">
                                <?= render_partial('partials/donut', ['segments' => [
                                    ['value' => (int) ($sb['active'] ?? 0),    'color' => '#E5A02E', 'label' => t('seller.cockpit.status_active')],
                                    ['value' => (int) ($sb['delivered'] ?? 0), 'color' => '#1f7a5d', 'label' => t('seller.cockpit.status_delivered')],
                                    ['value' => (int) ($sb['cancelled'] ?? 0), 'color' => '#C0392B', 'label' => t('seller.cockpit.status_cancelled')],
                                ], 'center_label' => t('seller.cockpit.status_center')]) ?>
                                <ul class="sdash-legend">
                                    <li><span class="d" style="background:#E5A02E"></span><span><?= e(t('seller.cockpit.status_active')) ?></span><b><?= (int) ($sb['active'] ?? 0) ?></b></li>
                                    <li><span class="d" style="background:#1f7a5d"></span><span><?= e(t('seller.cockpit.status_delivered')) ?></span><b><?= (int) ($sb['delivered'] ?? 0) ?></b></li>
                                    <li><span class="d" style="background:#C0392B"></span><span><?= e(t('seller.cockpit.status_cancelled')) ?></span><b><?= (int) ($sb['cancelled'] ?? 0) ?></b></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ((int) $completion < 100) { $renderProfilePanel(); } ?>
                    <?php $renderTipsPanel(); ?>

                    <?php if ($lowStock !== []): ?>
                        <section class="sdash-panel">
                            <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">⚠️</span> <?= e(t('seller.cockpit.low_stock')) ?></h2></div>
                            <ul class="sdash-stock">
                                <?php foreach ($lowStock as $ls): ?>
                                    <li><span><?= e((string) $ls['name']) ?></span> <span class="badge badge-warn"><?= (int) $ls['stock'] ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('seller.cockpit.manage_stock')) ?> →</a></p>
                        </section>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Étape mise en route : action prioritaire + profil + conseils -->
            <?php if ($next !== null): ?>
                <a class="sdash-nba" href="<?= e($next['href']) ?>">
                    <span class="sdash-nba-badge"><?= e(t('seller.nba_badge')) ?></span>
                    <span class="sdash-nba-ic" aria-hidden="true"><?= icon($next['icon'], ['size' => 28]) ?></span>
                    <span class="sdash-nba-body"><b><?= e($next['title']) ?></b><span><?= e($next['desc']) ?></span></span>
                    <span class="btn btn-gold sdash-nba-cta"><?= e($next['cta']) ?> →</span>
                </a>
            <?php endif; ?>
            <div class="sdash-grid">
                <div class="sdash-col">
                    <?php if ((int) $completion < 100) { $renderProfilePanel(); } ?>
                </div>
                <div class="sdash-col">
                    <?php $renderTipsPanel(); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
