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
    'title' => date('d/m', strtotime((string) $p['date'])) . ' · ' . format_price_owner((int) $p['cents'], $gc),
], $gains_by_day);
$shopTotal = array_sum(array_map(static fn (array $s): int => (int) $s['cents'], $gains_by_shop));
$pct       = $threshold_cents > 0 ? min(100, (int) round($balance_cents / max(1, $threshold_cents) * 100)) : ($balance_cents > 0 ? 100 : 0);
$remaining = max(0, $threshold_cents - $balance_cents);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sdash swal">

        <div class="sdash-topbar">
            <div class="sdash-hello">
                <h1>💰 <?= e(t('wallet.title')) ?></h1>
                <p><?= e(t('wallet.intro', ['min' => format_price_owner($threshold_cents, $currency)])) ?></p>
            </div>
            <div class="sdash-actions">
                <a class="btn btn-ghost" href="<?= e(url('/vendeur/reglages')) ?>#sec-payout"><?= e(t('wallet.configure_payout')) ?></a>
            </div>
        </div>

        <!-- Solde + gains -->
        <div class="sdash-grid">
            <div class="sdash-col">
                <div class="swal-balance">
                    <span class="lab"><?= e(t('wallet.balance')) ?></span>
                    <div class="amt"><?= e(format_price_owner($balance_cents, $currency)) ?></div>
                    <span class="lock"><span aria-hidden="true">🔒</span> <?= e(t('wallet.threshold_note', ['min' => format_price_owner($threshold_cents, $currency)])) ?></span>
                    <?php if (!$can_withdraw): ?>
                        <div class="swal-thr">
                            <div class="cap"><span><?= e(t('wallet.threshold_reached', ['amount' => format_price_owner($balance_cents, $currency)])) ?></span><span><?= e(t('wallet.threshold_of', ['amount' => format_price_owner($threshold_cents, $currency)])) ?></span></div>
                            <div class="bar"><i style="width:<?= max(2, $pct) ?>%"></i></div>
                            <?php if ($remaining > 0): ?><div class="cap swal-thr-note"><span><?= e(t('wallet.threshold_remaining', ['amount' => format_price_owner($remaining, $currency)])) ?></span></div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="acts">
                        <?php if ($can_withdraw): ?>
                            <a class="btn btn-light" href="#withdraw"><?= e(t('wallet.request')) ?></a>
                        <?php else: ?>
                            <button type="button" class="btn btn-light" disabled><?= e(t('wallet.request')) ?></button>
                        <?php endif; ?>
                        <a class="btn swal-ghost-on-green" href="<?= e(url('/vendeur/reglages')) ?>#sec-payout"><?= e(t('wallet.configure_payout')) ?></a>
                    </div>
                </div>
            </div>

            <div class="sdash-col">
                <section class="sdash-panel">
                    <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">📈</span> <?= e(t('wallet.gains_title')) ?></h2></div>
                    <div class="swal-earn">
                        <div class="row"><span class="ic" aria-hidden="true">📊</span><div><div class="v"><?= e(format_price_owner((int) $gains_summary['total_cents'], $gc)) ?></div><div class="k"><?= e(t('wallet.gains_total')) ?></div></div></div>
                        <div class="row"><span class="ic" aria-hidden="true">🗓️</span><div><div class="v"><?= e(format_price_owner((int) $gains_summary['month_cents'], $gc)) ?></div><div class="k"><?= e(t('wallet.gains_month')) ?></div></div></div>
                        <div class="row"><span class="ic" aria-hidden="true">🧾</span><div><div class="v"><?= (int) $gains_summary['count'] ?></div><div class="k"><?= e(t('wallet.gains_orders')) ?></div></div></div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Graphique + par vitrine -->
        <div class="sdash-grid">
            <div class="sdash-col">
                <section class="sdash-panel">
                    <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">📊</span> <?= e(t('wallet.gains_total')) ?></h2><span class="sdash-eyebrow"><?= e(t('wallet.gains_14d')) ?></span></div>
                    <?php if ($shopTotal > 0): ?>
                        <?= render_partial('partials/area_chart', ['bars' => $dayBars, 'cur' => $gc, 'height' => 130, 'uid' => 'wal']) ?>
                    <?php else: ?>
                        <p class="sdash-empty"><?= e(t('wallet.gains_empty')) ?></p>
                    <?php endif; ?>
                </section>
            </div>
            <div class="sdash-col">
                <section class="sdash-panel">
                    <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🏪</span> <?= e(t('wallet.by_shop')) ?></h2></div>
                    <?php if (count($gains_by_shop) > 0 && $shopTotal > 0): ?>
                        <?php foreach ($gains_by_shop as $s): $p = (int) round((int) $s['cents'] / $shopTotal * 100); $col = ($s['kind'] ?? '') === 'restaurant' ? 'var(--s-gold)' : 'var(--s-green)'; ?>
                            <div class="swal-sf">
                                <div class="top"><span class="nm"><span class="dot" style="background:<?= $col ?>"></span> <?= e((string) $s['label']) ?></span><span class="amt"><?= e(format_price_owner((int) $s['cents'], $gc)) ?> <small><?= $p ?>&nbsp;%</small></span></div>
                                <div class="bar"><i style="width:<?= max(2, $p) ?>%;background:<?= $col ?>"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="sdash-empty"><?= e(t('wallet.gains_empty')) ?></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <!-- Demander un retrait (si seuil atteint) -->
        <?php if ($can_withdraw): ?>
            <?php $pm = (string) ($payout['payout_method'] ?? ''); $pd = (string) ($payout['payout_destination'] ?? ''); ?>
            <section class="sdash-panel" id="withdraw">
                <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">💸</span> <?= e(t('wallet.request')) ?></h2></div>
                <form method="post" action="<?= e(url('/vendeur/portefeuille/retrait')) ?>" class="swal-wd" data-submit-once>
                    <?= csrf_field() ?>
                    <p class="sdash-empty"><?= e(t('wallet.withdraw_full', ['amount' => format_price_owner($balance_cents, $currency)])) ?></p>
                    <div class="swal-wd-grid">
                        <div>
                            <label class="swal-lbl" for="wd-method"><?= e(t('wallet.method')) ?></label>
                            <select id="wd-method" name="method">
                                <option value="mobile_money" <?= $pm === 'mobile_money' ? 'selected' : '' ?>><?= e(t('wallet.method.mobile_money')) ?></option>
                                <option value="bank" <?= $pm === 'bank' ? 'selected' : '' ?>><?= e(t('wallet.method.bank')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="swal-lbl" for="wd-dest"><?= e(t('wallet.destination')) ?></label>
                            <input type="text" id="wd-dest" name="destination" maxlength="160" required value="<?= e($pd) ?>" placeholder="<?= e(t('wallet.destination_ph')) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gold"><?= e(t('wallet.request')) ?></button>
                </form>
            </section>
        <?php endif; ?>

        <!-- Retraits demandés -->
        <?php if ($withdrawals !== []): ?>
            <section class="sdash-panel">
                <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🏦</span> <?= e(t('wallet.withdrawals_title')) ?></h2></div>
                <div class="swal-ledger">
                    <?php foreach ($withdrawals as $w): $st = (string) $w['status']; ?>
                        <div class="swal-led-row">
                            <div class="swal-led-main"><b><?= e(format_price_owner((int) $w['amount_cents'], (string) $w['currency'])) ?></b><span><?= e(t('wallet.method.' . $w['method'])) ?> · <?= e(date('d/m/Y', strtotime((string) $w['created_at']))) ?></span></div>
                            <span class="sdash-status sdash-status--<?= $st === 'paid' ? 'ok' : ($st === 'rejected' ? 'no' : 'wait') ?>"><?= e(t('wallet.status.' . $st)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Historique -->
        <section class="sdash-panel">
            <div class="sdash-panel-head"><h2><span class="sdash-sic" aria-hidden="true">🕘</span> <?= e(t('wallet.history')) ?></h2></div>
            <?php if ($entries === []): ?>
                <div class="swal-hist-empty">
                    <div class="il" aria-hidden="true">🧾</div>
                    <b><?= e(t('wallet.no_moves')) ?></b>
                    <p><?= e(t('wallet.history_empty')) ?></p>
                </div>
            <?php else: ?>
                <div class="swal-ledger">
                    <?php foreach ($entries as $en): $isCredit = ($en['type'] ?? '') === 'credit'; ?>
                        <div class="swal-led-row">
                            <div class="swal-led-main"><b><?= e(t('wallet.source.' . $en['source'])) ?></b><span><?= e(date('d/m/Y', strtotime((string) $en['created_at']))) ?></span></div>
                            <strong class="swal-led-amt <?= $isCredit ? 'is-credit' : 'is-debit' ?>"><?= $isCredit ? '+' : '−' ?> <?= e(format_price_owner((int) $en['amount_cents'], (string) $en['currency'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</div>
