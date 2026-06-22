<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var int $balance_cents  @var string $currency  @var int $threshold_cents  @var bool $can_withdraw
 *  @var list<array> $entries  @var list<array> $withdrawals
 *  @var string $gains_currency  @var array{total_cents:int,month_cents:int,count:int} $gains_summary
 *  @var list<array{date:string,cents:int}> $gains_by_day  @var list<array{label:string,kind:string,cents:int}> $gains_by_shop */
$gc = $gains_currency;
$payout = $payout ?? [];
$dayBars = array_map(static fn (array $p): array => [
    'value' => (int) $p['cents'],
    'label' => (string) (int) date('j', strtotime((string) $p['date'])),
    'title' => date('d/m', strtotime((string) $p['date'])) . ' · ' . format_price_local((int) $p['cents'], $gc),
], $gains_by_day);
$shopTotal = array_sum(array_map(static fn (array $s): int => (int) $s['cents'], $gains_by_shop));
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1>💰 <?= e(t('wallet.title')) ?></h1>
            <p class="muted"><?= e(t('wallet.intro')) ?></p>
        </div>

        <!-- Solde retirable -->
        <div class="panel wallet-balance">
            <div class="wallet-balance-top">
                <span class="muted"><?= e(t('wallet.balance')) ?></span>
                <strong class="wallet-amount-big"><?= e(format_price($balance_cents, $currency)) ?></strong>
                <?php $approx = format_price_approx($balance_cents, $currency); if ($approx !== ''): ?>
                    <span class="price-approx">≈&nbsp;<?= e($approx) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($can_withdraw): ?>
                <?php $pm = (string) ($payout['payout_method'] ?? ''); $pd = (string) ($payout['payout_destination'] ?? ''); ?>
                <form method="post" action="<?= e(url('/vendeur/portefeuille/retrait')) ?>" class="wallet-form">
                    <?= csrf_field() ?>
                    <p class="hint"><?= e(t('wallet.withdraw_full', ['amount' => format_price($balance_cents, $currency)])) ?></p>
                    <div class="grid-2">
                        <div>
                            <label for="wd-method"><?= e(t('wallet.method')) ?></label>
                            <select id="wd-method" name="method">
                                <option value="mobile_money" <?= $pm === 'mobile_money' ? 'selected' : '' ?>><?= e(t('wallet.method.mobile_money')) ?></option>
                                <option value="bank" <?= $pm === 'bank' ? 'selected' : '' ?>><?= e(t('wallet.method.bank')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="wd-dest"><?= e(t('wallet.destination')) ?></label>
                            <input type="text" id="wd-dest" name="destination" maxlength="160" required value="<?= e($pd) ?>" placeholder="<?= e(t('wallet.destination_ph')) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= e(t('wallet.request')) ?></button>
                </form>
            <?php else: ?>
                <p class="hint wallet-threshold">🔒 <?= e(t('wallet.threshold_note', ['min' => format_price($threshold_cents, $currency)])) ?></p>
            <?php endif; ?>
        </div>

        <!-- Mes gains (chiffre d'affaires) -->
        <div class="panel">
            <h2 class="panel-title">📈 <?= e(t('wallet.gains_title')) ?></h2>
            <div class="gains-stats">
                <div class="gains-stat">
                    <span class="gains-stat-val"><?= e(format_price_local((int) $gains_summary['total_cents'], $gc)) ?></span>
                    <span class="gains-stat-lbl"><?= e(t('wallet.gains_total')) ?></span>
                </div>
                <div class="gains-stat">
                    <span class="gains-stat-val"><?= e(format_price_local((int) $gains_summary['month_cents'], $gc)) ?></span>
                    <span class="gains-stat-lbl"><?= e(t('wallet.gains_month')) ?></span>
                </div>
                <div class="gains-stat">
                    <span class="gains-stat-val"><?= (int) $gains_summary['count'] ?></span>
                    <span class="gains-stat-lbl"><?= e(t('wallet.gains_orders')) ?></span>
                </div>
            </div>

            <?php if ($shopTotal > 0): ?>
                <p class="gains-chart-title"><?= e(t('wallet.gains_14d')) ?></p>
                <?= render_partial('partials/bar_chart', ['bars' => $dayBars, 'cur' => $gc, 'height' => 130]) ?>
            <?php else: ?>
                <div class="empty-state"><p><?= e(t('wallet.gains_empty')) ?></p></div>
            <?php endif; ?>
        </div>

        <!-- Provenance : par vitrine -->
        <?php if (count($gains_by_shop) > 0 && $shopTotal > 0): ?>
            <div class="panel">
                <h2 class="panel-title">🏪 <?= e(t('wallet.by_shop')) ?></h2>
                <ul class="provenance-list">
                    <?php foreach ($gains_by_shop as $s): $pct = $shopTotal > 0 ? (int) round((int) $s['cents'] / $shopTotal * 100) : 0; ?>
                        <li class="provenance-row">
                            <div class="provenance-head">
                                <span><?= $s['kind'] === 'restaurant' ? icon('utensils', ['size' => 15]) : icon('store', ['size' => 15]) ?> <strong><?= e((string) $s['label']) ?></strong></span>
                                <span class="provenance-amount"><?= e(format_price_local((int) $s['cents'], $gc)) ?> <span class="muted">· <?= $pct ?>%</span></span>
                            </div>
                            <div class="provenance-track"><div class="provenance-fill provenance-fill--<?= e($s['kind']) ?>" style="width:<?= $pct ?>%"></div></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($withdrawals !== []): ?>
            <div class="panel">
                <h2 class="panel-title"><?= e(t('wallet.withdrawals_title')) ?></h2>
                <ul class="order-list">
                    <?php foreach ($withdrawals as $w): $st = (string) $w['status']; ?>
                        <li class="order-row">
                            <div class="order-row-main">
                                <span class="order-shop"><?= e(format_price_local((int) $w['amount_cents'], (string) $w['currency'])) ?></span>
                                <span class="muted order-meta"><?= e(t('wallet.method.' . $w['method'])) ?> · <?= e(date('d/m/Y', strtotime((string) $w['created_at']))) ?></span>
                            </div>
                            <span class="ann-status ann-status--<?= e($st === 'paid' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending')) ?>"><?= e(t('wallet.status.' . $st)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2 class="panel-title"><?= e(t('wallet.history')) ?></h2>
            <?php if ($entries === []): ?>
                <div class="empty-state"><p><?= e(t('wallet.history_empty')) ?></p></div>
            <?php else: ?>
                <ul class="wallet-ledger">
                    <?php foreach ($entries as $en): $isCredit = ($en['type'] ?? '') === 'credit'; ?>
                        <li class="wallet-entry">
                            <span class="wallet-entry-label"><?= e(t('wallet.source.' . $en['source'])) ?> <span class="muted">· <?= e(date('d/m/Y', strtotime((string) $en['created_at']))) ?></span></span>
                            <strong class="wallet-amount <?= $isCredit ? 'is-credit' : 'is-debit' ?>"><?= $isCredit ? '+' : '−' ?> <?= e(format_price_local((int) $en['amount_cents'], (string) $en['currency'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
