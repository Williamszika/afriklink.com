<?php
/** @var array $payment */
$amount = format_price((int) $payment['amount_cents'], (string) $payment['currency']);
?>
<section class="auth-card pay-sim">
    <div class="pay-sim-badge">🧪 <?= e(t('pay.sim_badge')) ?></div>
    <h1><?= e(t('pay.sim_title')) ?></h1>
    <p class="muted"><?= e((string) $payment['description']) ?></p>
    <p class="pay-sim-amount"><?= e($amount) ?></p>
    <p class="hint"><?= e(t('pay.sim_explain')) ?></p>

    <form method="post" action="<?= e(url('/paiement/simulation/' . $payment['public_id'])) ?>" class="pay-sim-actions">
        <?= csrf_field() ?>
        <button type="submit" name="outcome" value="pay" class="btn btn-primary btn-block">✅ <?= e(t('pay.sim_pay')) ?></button>
        <button type="submit" name="outcome" value="fail" class="btn btn-ghost btn-block">✖ <?= e(t('pay.sim_fail')) ?></button>
    </form>
</section>
