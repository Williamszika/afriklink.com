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

        <?php if ($stage === 'C'): ?>
            <?php /* ---- Vendeur actif : KPIs réels en haut ---- */ ?>
            <div class="stat-grid cols-4">
                <?php
                $kpis = [
                    ['icon' => 'package', 'label' => t('seller.kpi.orders_all'), 'value' => (string) (int) ($dash['order_n'] ?? 0), 'href' => url('/vendeur/commandes')],
                    ['icon' => 'receipt', 'label' => t('seller.stat.orders'),    'value' => (string) $pending, 'href' => url('/vendeur/commandes?filtre=a_traiter'), 'urgent' => $pending > 0],
                    ['icon' => 'eye', 'label' => t('seller.stat.views'),     'value' => (string) (int) ($dash['views'] ?? 0), 'href' => url('/boutique/stats')],
                    ['icon' => 'tag', 'label' => t('seller.kpi.products'),   'value' => (string) (int) ($dash['product_n'] ?? 0), 'href' => url('/boutique/gerer')],
                ];
                foreach ($kpis as $s): ?>
                    <a class="stat-card stat-card--link<?= !empty($s['urgent']) ? ' stat-card--urgent' : '' ?>" href="<?= e($s['href']) ?>">
                        <div class="num"><?= icon($s['icon']) ?> <?= e($s['value']) ?></div>
                        <div class="lbl"><?= e($s['label']) ?></div>
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
