<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $boutique  @var array{total:int,d7:int,d30:int} $totals
 *  @var list<array{day:string,views:int}> $daily  @var array<int,int> $by_product
 *  @var array<int,string> $names */

$max = 1;
foreach ($daily as $d) {
    $max = max($max, $d['views']);
}
$sum30 = array_sum($by_product);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>📈 <?= e(t('stats.title')) ?></h1>
            <p class="muted"><?= e(t('stats.subtitle', ['name' => (string) $boutique['name']])) ?></p>
        </div>

        <div class="stat-grid">
            <div class="stat-card"><div class="num"><span aria-hidden="true">👁️</span> <?= (int) $totals['total'] ?></div>
                <div class="lbl"><?= e(t('stats.total')) ?></div></div>
            <div class="stat-card"><div class="num"><span aria-hidden="true">🗓️</span> <?= (int) $totals['d7'] ?></div>
                <div class="lbl"><?= e(t('stats.d7')) ?></div></div>
            <div class="stat-card"><div class="num"><span aria-hidden="true">📅</span> <?= (int) $totals['d30'] ?></div>
                <div class="lbl"><?= e(t('stats.d30')) ?></div></div>
        </div>

        <!-- 14 derniers jours -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('stats.chart_title')) ?></h2>
            <div class="stats-bars" role="img" aria-label="<?= e(t('stats.chart_title')) ?>">
                <?php foreach ($daily as $d): ?>
                    <div class="stats-col" title="<?= e(date('d/m', strtotime($d['day']))) ?> : <?= (int) $d['views'] ?>">
                        <span class="stats-count"><?= $d['views'] > 0 ? (int) $d['views'] : '' ?></span>
                        <span class="stats-bar" style="height: <?= max(3, (int) round($d['views'] * 100 / $max)) ?>%"></span>
                        <span class="stats-day"><?= e(date('d/m', strtotime($d['day']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Détail par page (30 jours) -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('stats.pages_title')) ?></h2>
            <?php if ($by_product === []): ?>
                <div class="empty-state"><p><?= e(t('stats.empty')) ?></p></div>
            <?php else: ?>
                <div class="views-rows">
                    <?php foreach ($by_product as $pid => $n): ?>
                        <?php $share = $sum30 > 0 ? (int) round($n * 100 / $sum30) : 0; ?>
                        <div class="views-row">
                            <span class="views-name">
                                <?= $pid === 0 ? '🏪 ' . e(t('stats.shop_page')) : '📦 ' . e($names[$pid] ?? t('stats.deleted_product')) ?>
                            </span>
                            <span class="views-track" aria-hidden="true"><span class="views-fill" style="width: <?= max(2, $share) ?>%"></span></span>
                            <span class="views-n"><?= (int) $n ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="hint"><?= e(t('stats.note')) ?></p>
        </div>

        <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/gerer')) ?>">← <?= e(t('shop.manage_link')) ?></a></p>
    </div>
</div>
