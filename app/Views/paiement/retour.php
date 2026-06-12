<?php
/** @var array $payment  @var \App\Services\Payment\PaymentResult $result */
$paid = $result->isPaid();
$amount = format_price((int) $payment['amount_cents'], (string) $payment['currency']);
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon <?= $paid ? 'is-ok' : 'is-ko' ?>" aria-hidden="true"><?= $paid ? '✅' : '✖' ?></div>
    <h1><?= e($paid ? t('pay.res_paid') : t('pay.res_failed')) ?></h1>
    <p class="pay-sim-amount"><?= e($amount) ?></p>
    <dl class="recap-list">
        <dt><?= e(t('pay.ref')) ?></dt><dd><?= e(strtoupper(substr((string) $payment['public_id'], 0, 8))) ?></dd>
        <dt><?= e(t('pay.provider')) ?></dt><dd><?= e(\App\Services\Payment\PaymentProviders::resolve((string) $payment['provider'])->label()) ?></dd>
        <dt><?= e(t('pay.status')) ?></dt><dd><?= e(t('pay.st.' . $result->status)) ?></dd>
    </dl>
    <p class="hint"><?= e(t('pay.res_note')) ?></p>
    <p><a class="btn btn-primary" href="<?= e(url('/paiement/tester')) ?>"><?= e(t('pay.back_tester')) ?></a></p>
</section>
