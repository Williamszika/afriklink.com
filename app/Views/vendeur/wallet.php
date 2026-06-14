<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var int $balance_cents  @var string $currency  @var int $threshold_cents  @var bool $can_withdraw
 *  @var list<array> $entries  @var list<array> $withdrawals */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1>💰 <?= e(t('wallet.title')) ?></h1>
            <p class="muted"><?= e(t('wallet.intro')) ?></p>
        </div>

        <div class="panel wallet-balance">
            <div class="wallet-balance-top">
                <span class="muted"><?= e(t('wallet.balance')) ?></span>
                <strong class="wallet-amount-big"><?= e(format_price($balance_cents, $currency)) ?></strong>
                <?php $approx = format_price_approx($balance_cents, $currency); if ($approx !== ''): ?>
                    <span class="price-approx">≈&nbsp;<?= e($approx) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($can_withdraw): ?>
                <form method="post" action="<?= e(url('/vendeur/portefeuille/retrait')) ?>" class="wallet-form">
                    <?= csrf_field() ?>
                    <p class="hint"><?= e(t('wallet.withdraw_full', ['amount' => format_price($balance_cents, $currency)])) ?></p>
                    <div class="grid-2">
                        <div>
                            <label for="wd-method"><?= e(t('wallet.method')) ?></label>
                            <select id="wd-method" name="method">
                                <option value="mobile_money"><?= e(t('wallet.method.mobile_money')) ?></option>
                                <option value="bank"><?= e(t('wallet.method.bank')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="wd-dest"><?= e(t('wallet.destination')) ?></label>
                            <input type="text" id="wd-dest" name="destination" maxlength="160" required placeholder="<?= e(t('wallet.destination_ph')) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= e(t('wallet.request')) ?></button>
                </form>
            <?php else: ?>
                <p class="hint wallet-threshold">🔒 <?= e(t('wallet.threshold_note', ['min' => format_price($threshold_cents, $currency)])) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($withdrawals !== []): ?>
            <div class="panel">
                <h2 class="panel-title"><?= e(t('wallet.withdrawals_title')) ?></h2>
                <ul class="order-list">
                    <?php foreach ($withdrawals as $w): $st = (string) $w['status']; ?>
                        <li class="order-row">
                            <div class="order-row-main">
                                <span class="order-shop"><?= e(format_price((int) $w['amount_cents'], (string) $w['currency'])) ?></span>
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
                            <strong class="wallet-amount <?= $isCredit ? 'is-credit' : 'is-debit' ?>"><?= $isCredit ? '+' : '−' ?> <?= e(format_price((int) $en['amount_cents'], (string) $en['currency'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
