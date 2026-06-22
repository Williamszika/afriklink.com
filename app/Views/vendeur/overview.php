<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_version
 *  @var ?string $avatar_url  @var int $completion  @var list<array> $onboarding_steps  @var array $dash */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$stage       = (string) ($dash['stage'] ?? 'A');
$next        = $dash['next'] ?? null;
$pending     = (int) ($dash['pending'] ?? 0);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= e(t('pro.dash.welcome', ['name' => $companyName])) ?></h1>
            <p class="muted"><?= e(t('seller.sub.' . $stage)) ?></p>
        </div>

        <?php if (!$verified): ?>
            <div class="notice notice-warn dash-verif">
                <p><strong><?= e(t('pro.dash.badge_pending')) ?></strong> — <?= e(t('pro.dash.pending_delay')) ?></p>
            </div>
        <?php endif; ?>

        <?php
        // Incitation : si la boutique est publiée mais l'affiliation n'est pas activée.
        $afShop = $dash['boutique'] ?? null;
        if ($afShop !== null && ($afShop['status'] ?? '') === 'published' && empty($dash['aff_enabled'])):
        ?>
            <div class="notice notice-info aff-nudge">
                <div class="aff-nudge-txt">
                    <strong><?= icon('megaphone', ['size' => 16]) ?> <?= e(t('aff.nudge_title')) ?></strong>
                    <p class="muted"><?= e(t('aff.nudge_body')) ?></p>
                </div>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/affiliation')) ?>"><?= e(t('aff.nudge_cta')) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($stage === 'C'): ?>
            <?php /* ---- Vendeur actif : KPIs réels en haut ---- */ ?>
            <div class="kpi-grid">
                <?php
                $ordSpark  = array_map(static fn (array $r): int => (int) ($r['n'] ?? 0), (array) ($dash['orders_by_day'] ?? []));
                $viewSpark = array_map(static fn (array $r): int => (int) ($r['views'] ?? 0), (array) ($dash['views_by_day'] ?? []));
                $prodSpark = array_map(static fn (array $r): int => (int) ($r['n'] ?? 0), (array) ($dash['products_by_day'] ?? []));
                $kpis = [
                    ['icon' => 'package', 'tone' => 'forest', 'label' => t('seller.kpi.orders_all'), 'value' => (string) (int) ($dash['order_n'] ?? 0), 'href' => url('/vendeur/commandes'), 'spark' => $ordSpark],
                    ['icon' => 'receipt', 'tone' => 'gold',   'label' => t('seller.stat.orders'),    'value' => (string) $pending, 'href' => url('/vendeur/commandes?filtre=a_traiter'), 'urgent' => $pending > 0, 'spark' => $ordSpark],
                    ['icon' => 'eye',     'tone' => 'teal',   'label' => t('seller.stat.views'),     'value' => (string) (int) ($dash['views'] ?? 0), 'href' => url('/boutique/stats'), 'spark' => $viewSpark],
                    ['icon' => 'tag',     'tone' => 'rose',   'label' => t('seller.kpi.products'),   'value' => (string) (int) ($dash['product_n'] ?? 0), 'href' => url('/boutique/gerer'), 'spark' => $prodSpark],
                ];
                foreach ($kpis as $s): ?>
                    <a class="kpi3d kpi3d--<?= e($s['tone']) ?><?= !empty($s['urgent']) ? ' is-urgent' : '' ?>" href="<?= e($s['href']) ?>">
                        <span class="kpi3d-top">
                            <span class="kpi3d-ico" aria-hidden="true"><?= icon($s['icon'], ['size' => 26]) ?></span>
                            <span class="kpi3d-body">
                                <span class="kpi3d-num"><?= e($s['value']) ?></span>
                                <span class="kpi3d-lbl"><?= e($s['label']) ?></span>
                            </span>
                        </span>
                        <span class="kpi3d-spark"><?= render_partial('partials/sparkline', ['values' => $s['spark']]) ?></span>
                        <?php if (!empty($s['urgent'])): ?><span class="kpi3d-dot" aria-hidden="true"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($pending > 0 && $next !== null): ?>
                <a class="panel nba-card nba-card--inline" href="<?= e($next['href']) ?>">
                    <div class="nba-row">
                        <span class="nba-ico" aria-hidden="true"><?= icon($next['icon'], ['size' => 30]) ?></span>
                        <div class="nba-body"><h2 class="nba-title"><?= e($next['title']) ?></h2><p class="nba-desc"><?= e($next['desc']) ?></p></div>
                        <span class="btn btn-primary nba-cta"><?= e($next['cta']) ?> →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php /* ---- Cockpit chiffré : revenu, meilleures ventes, commandes, stock ---- */ ?>
            <?php
            $gc = (string) ($dash['currency'] ?? 'XOF');
            $revBars = array_map(static fn (array $p): array => [
                'value' => (int) $p['cents'],
                'label' => (string) (int) date('j', strtotime((string) $p['date'])),
                'title' => date('d/m', strtotime((string) $p['date'])) . ' · ' . format_price_owner((int) $p['cents'], $gc),
            ], (array) ($dash['revenue_by_day'] ?? []));
            $topProducts = (array) ($dash['top_products'] ?? []);
            $lowStock    = (array) ($dash['low_stock'] ?? []);
            $recent      = (array) ($dash['recent_orders'] ?? []);
            $conversion  = $dash['conversion'] ?? null;
            $revPrev = (int) ($dash['revenue_prev_month'] ?? 0);
            $revCur  = (int) ($dash['revenue_month'] ?? 0);
            $revDelta = $revPrev > 0 ? (int) round(($revCur - $revPrev) / $revPrev * 100) : ($revCur > 0 ? 100 : 0);
            ?>
            <div class="cockpit-grid">
                <div class="panel cockpit-rev">
                    <div class="cockpit-rev-head">
                        <div>
                            <span class="muted"><?= e(t('seller.cockpit.revenue_month')) ?></span>
                            <strong class="cockpit-rev-amount"><?= e(format_price_owner($revCur, $gc)) ?></strong>
                            <?php if ($revPrev > 0 || $revCur > 0): ?>
                                <span class="rev-delta <?= $revDelta >= 0 ? 'is-up' : 'is-down' ?>"><?= $revDelta >= 0 ? '▲' : '▼' ?> <?= abs($revDelta) ?> %</span>
                                <span class="rev-delta-cap"><?= e(t('seller.cockpit.vs_last_month')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($conversion !== null): ?>
                            <div class="cockpit-conv">
                                <span class="muted"><?= e(t('seller.cockpit.conversion')) ?></span>
                                <strong><?= e((string) $conversion) ?> %</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="gains-chart-title"><?= e(t('wallet.gains_14d')) ?></p>
                    <?= render_partial('partials/area_chart', ['bars' => $revBars, 'cur' => $gc, 'height' => 130, 'uid' => 'rev']) ?>
                </div>

                <div class="panel cockpit-top">
                    <h2 class="panel-title">🏆 <?= e(t('seller.cockpit.top_products')) ?></h2>
                    <?php if ($topProducts === []): ?>
                        <p class="muted"><?= e(t('seller.cockpit.top_empty')) ?></p>
                    <?php else: ?>
                        <?php $tpMax = 1; foreach ($topProducts as $tp) { $tpMax = max($tpMax, (int) $tp['cents']); } ?>
                        <ol class="top-bars">
                            <?php foreach ($topProducts as $i => $tp): $pct = max(6, (int) round((int) $tp['cents'] / $tpMax * 100)); ?>
                                <li class="top-bar">
                                    <span class="top-bar-rank"><?= $i + 1 ?></span>
                                    <span class="top-bar-main">
                                        <span class="top-bar-info"><span class="top-bar-name"><?= e((string) $tp['name']) ?></span><span class="top-bar-val"><?= (int) $tp['units'] ?>× · <?= e(format_price_owner((int) $tp['cents'], $gc)) ?></span></span>
                                        <span class="top-bar-track"><span class="top-bar-fill" style="width:<?= $pct ?>%"></span></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cockpit-grid">
                <?php $sb = (array) ($dash['status_breakdown'] ?? []); $sbTot = (int) ($sb['active'] ?? 0) + (int) ($sb['delivered'] ?? 0) + (int) ($sb['cancelled'] ?? 0); ?>
                <div class="panel cockpit-donut">
                    <h2 class="panel-title">🍩 <?= e(t('seller.cockpit.status_title')) ?></h2>
                    <?php if ($sbTot === 0): ?>
                        <p class="muted"><?= e(t('order.empty.toutes')) ?></p>
                    <?php else: ?>
                        <div class="donut-wrap">
                            <?= render_partial('partials/donut', ['segments' => [
                                ['value' => (int) ($sb['active'] ?? 0),    'color' => '#E5A02E', 'label' => t('seller.cockpit.status_active')],
                                ['value' => (int) ($sb['delivered'] ?? 0), 'color' => '#1f7a5d', 'label' => t('seller.cockpit.status_delivered')],
                                ['value' => (int) ($sb['cancelled'] ?? 0), 'color' => '#C0392B', 'label' => t('seller.cockpit.status_cancelled')],
                            ], 'center_label' => t('seller.cockpit.status_center')]) ?>
                            <ul class="donut-legend">
                                <li><span class="dot" style="background:#E5A02E"></span><span><?= e(t('seller.cockpit.status_active')) ?></span><strong><?= (int) ($sb['active'] ?? 0) ?></strong></li>
                                <li><span class="dot" style="background:#1f7a5d"></span><span><?= e(t('seller.cockpit.status_delivered')) ?></span><strong><?= (int) ($sb['delivered'] ?? 0) ?></strong></li>
                                <li><span class="dot" style="background:#C0392B"></span><span><?= e(t('seller.cockpit.status_cancelled')) ?></span><strong><?= (int) ($sb['cancelled'] ?? 0) ?></strong></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <h2 class="panel-title"><?= icon('package', ['size' => 18]) ?> <?= e(t('seller.cockpit.recent_orders')) ?></h2>
                    <?php if ($recent === []): ?>
                        <p class="muted"><?= e(t('order.empty.toutes')) ?></p>
                    <?php else: ?>
                        <ul class="cockpit-orders">
                            <?php foreach ($recent as $o): $rst = (string) $o['status']; ?>
                                <li>
                                    <span class="cockpit-order-id"><strong>#<?= e(strtoupper(substr((string) $o['public_id'], 0, 6))) ?></strong> · <?= e((string) $o['client_name']) ?></span>
                                    <span class="cockpit-order-right">
                                        <?= e(format_price_owner((int) $o['total_cents'], (string) $o['currency'])) ?>
                                        <span class="ann-status ann-status--<?= e($rst === 'delivered' ? 'approved' : ($rst === 'cancelled' ? 'rejected' : 'pending')) ?>"><?= e(t('order.status.' . $rst)) ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/vendeur/commandes')) ?>"><?= e(t('seller.cockpit.all_orders')) ?> →</a></p>
                    <?php endif; ?>
                </div>

            </div>

            <?php if ($lowStock !== []): ?>
                <div class="cockpit-grid">
                    <div class="panel cockpit-lowstock">
                        <h2 class="panel-title">⚠️ <?= e(t('seller.cockpit.low_stock')) ?></h2>
                        <ul class="cockpit-stock">
                            <?php foreach ($lowStock as $ls): ?>
                                <li><span><?= e((string) $ls['name']) ?></span> <span class="badge badge-warn"><?= (int) $ls['stock'] ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('seller.cockpit.manage_stock')) ?> →</a></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($next !== null): ?>
            <?php /* ---- Mise en route / prêt à vendre : action prioritaire en avant ---- */ ?>
            <a class="panel nba-card" href="<?= e($next['href']) ?>">
                <span class="nba-badge"><?= e(t('seller.nba_badge')) ?></span>
                <div class="nba-row">
                    <span class="nba-ico" aria-hidden="true"><?= icon($next['icon'], ['size' => 30]) ?></span>
                    <div class="nba-body">
                        <h2 class="nba-title"><?= e($next['title']) ?></h2>
                        <p class="nba-desc"><?= e($next['desc']) ?></p>
                    </div>
                    <span class="btn btn-primary nba-cta"><?= e($next['cta']) ?> →</span>
                </div>
            </a>
        <?php endif; ?>

        <?php /* ---- Progression d'onboarding (tant que le profil n'est pas complet) ---- */ ?>
        <?php if ((int) $completion < 100): ?>
            <div class="panel dash-progress">
                <div class="progress-head">
                    <strong><?= e(t('seller.completion', ['pct' => $completion])) ?></strong>
                    <span class="muted"><?= (int) $completion ?>%</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width: <?= (int) $completion ?>%"></div></div>
                <ul class="checklist" style="margin-top:14px">
                    <?php foreach ($onboarding_steps as $item): ?>
                        <li class="<?= !empty($item['done']) ? 'done' : '' ?>">
                            <span class="check-ico"><?= !empty($item['done']) ? '✅' : '⬜' ?></span>
                            <?php if (empty($item['done']) && ($item['href'] ?? null) !== null): ?>
                                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                            <?php else: ?>
                                <?= e($item['label']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($stage !== 'C'): ?>
            <?php /* ---- KPIs en sourdine : jamais « 0 € » à un débutant ---- */ ?>
            <div class="stat-grid cols-4 kpi-muted">
                <?php foreach ([t('seller.kpi.orders_all'), t('seller.stat.orders'), t('seller.stat.views'), t('seller.kpi.products')] as $lbl): ?>
                    <div class="stat-card is-muted">
                        <div class="num">—</div>
                        <div class="lbl"><?= e($lbl) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="kpi-soon-note muted"><?= e(t('seller.kpi.soon')) ?></p>
        <?php endif; ?>

        <div class="panel">
            <h2 class="panel-title"><?= icon('lightbulb') ?> <?= e(t('seller.tips_title')) ?></h2>
            <ul class="tips">
                <li><?= icon('camera', ['size' => 18]) ?> <?= e(t('seller.tip1')) ?></li>
                <li><?= icon('zap', ['size' => 18]) ?> <?= e(t('seller.tip2')) ?></li>
                <li><?= icon('shield', ['size' => 18]) ?> <?= e(t('seller.tip3')) ?></li>
            </ul>
        </div>

    </div>
</div>
