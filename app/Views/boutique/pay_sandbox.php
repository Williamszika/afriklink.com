<?php
/** @var array $order  @var ?array $boutique  @var int $amount_cents  @var bool $is_deposit */
$cur = (string) $order['currency'];
$ref = strtoupper(substr((string) $order['public_id'], 0, 6));
$amount = (int) ($amount_cents ?? $order['total_cents']);
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon" aria-hidden="true">💳</div>
    <h1><?= e(t('pay.sandbox_title')) ?></h1>
    <p class="muted"><?= e(t('pay.sandbox_sub', ['ref' => $ref])) ?><?php if ($boutique): ?> · <?= e((string) $boutique['name']) ?><?php endif; ?></p>

    <p class="cart-total-row"><span><?= e(!empty($is_deposit) ? t('pay.deposit_to_pay') : t('pay.amount_to_pay')) ?></span> <strong><?= e(format_price_local($amount, $cur)) ?></strong></p>
    <?php if (!empty($is_deposit)): ?>
        <p class="hint"><?= e(t('pay.deposit_hint', ['rest' => format_price(max(0, (int) $order['total_cents'] - $amount), $cur)])) ?></p>
    <?php endif; ?>
    <?php if (!empty($order['payment_method'])): ?>
        <p class="hint"><?= e(t('bcart.method_label')) ?> : <img class="pay-logo-inline" src="<?= e(asset('img/pay/' . $order['payment_method'] . '.svg')) ?>" alt="" width="26" height="16"> <strong><?= e(t('shop.paymethod.' . $order['payment_method'])) ?></strong></p>
    <?php endif; ?>
    <p class="notice notice-info"><?= e(t('pay.sandbox_note')) ?></p>

    <form method="post" action="<?= e($settle_url ?? url('/boutique/commande/' . $order['public_id'] . '/regler')) ?>" class="pay-sandbox-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block" name="outcome" value="pay">✅ <?= e(t('pay.sandbox_pay')) ?></button>
        <button type="submit" class="btn btn-ghost btn-block" name="outcome" value="cancel"><?= e(t('pay.sandbox_cancel')) ?></button>
    </form>
</section>
