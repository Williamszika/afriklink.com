<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var string $code  @var string $link  @var int $rate
 *  @var array{clicks:int,conversions:int,earnings:array<string,int>} $stats  @var list<array> $recent */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🤝 <?= e(t('aff.title')) ?> <span class="badge badge-ok"><?= e(t('aff.rate_badge', ['rate' => (int) $rate])) ?></span></h1>
            <p class="muted"><?= e(t('aff.lead', ['rate' => (int) $rate])) ?></p>
        </div>

        <div class="panel">
            <h2 class="panel-title">🔗 <?= e(t('aff.your_link')) ?></h2>
            <?php if ($link !== ''): ?>
                <div class="aff-link-row">
                    <input type="text" class="aff-link-input" value="<?= e($link) ?>" readonly aria-label="<?= e(t('aff.your_link')) ?>">
                    <button type="button" class="btn btn-primary btn-sm" data-copy="<?= e($link) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= e(t('aff.copy')) ?></button>
                </div>
                <p class="hint"><?= e(t('aff.target_hint')) ?></p>
            <?php else: ?>
                <p class="muted"><?= e(t('aff.no_code')) ?></p>
            <?php endif; ?>
        </div>

        <div class="stat-grid cols-3">
            <div class="stat-card">
                <div class="num"><span aria-hidden="true">👆</span> <?= (int) $stats['clicks'] ?></div>
                <div class="lbl"><?= e(t('aff.clicks')) ?></div>
            </div>
            <div class="stat-card">
                <div class="num"><span aria-hidden="true">🛍️</span> <?= (int) $stats['conversions'] ?></div>
                <div class="lbl"><?= e(t('aff.conversions')) ?></div>
            </div>
            <div class="stat-card">
                <div class="num"><span aria-hidden="true">💸</span>
                    <?php if (empty($stats['earnings'])): ?>
                        0
                    <?php else: ?>
                        <?= e(implode(' · ', array_map(static fn (int $c, string $cur): string => format_price($c, $cur), $stats['earnings'], array_keys($stats['earnings'])))) ?>
                    <?php endif; ?>
                </div>
                <div class="lbl"><?= e(t('aff.earnings')) ?></div>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">📜 <?= e(t('aff.recent_title')) ?></h2>
            <?php if ($recent === []): ?>
                <p class="muted"><?= e(t('aff.none_yet')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th><?= e(t('aff.col_date')) ?></th>
                            <th><?= e(t('aff.col_order')) ?></th>
                            <th><?= e(t('aff.col_amount')) ?></th>
                            <th><?= e(t('aff.col_commission')) ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?></td>
                                <td><code><?= e(strtoupper(substr((string) $r['order_public_id'], 0, 8))) ?></code></td>
                                <td><?= e(format_price((int) $r['amount_cents'], (string) $r['currency'])) ?></td>
                                <td><strong><?= e(format_price((int) $r['commission_cents'], (string) $r['currency'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2 class="panel-title">💡 <?= e(t('aff.how_title')) ?></h2>
            <ul class="tips">
                <li>1️⃣ <?= e(t('aff.how_1')) ?></li>
                <li>2️⃣ <?= e(t('aff.how_2', ['rate' => (int) $rate])) ?></li>
                <li>3️⃣ <?= e(t('aff.how_3')) ?></li>
            </ul>
        </div>

    </div>
</div>
