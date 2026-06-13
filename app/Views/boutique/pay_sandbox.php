<?php
/** @var array $order  @var ?array $boutique */
$cur = (string) $order['currency'];
$ref = strtoupper(substr((string) $order['public_id'], 0, 6));
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon" aria-hidden="true">💳</div>
    <h1><?= e(t('pay.sandbox_title')) ?></h1>
    <p class="muted"><?= e(t('pay.sandbox_sub', ['ref' => $ref])) ?><?php if ($boutique): ?> · <?= e((string) $boutique['name']) ?><?php endif; ?></p>

    <p class="cart-total-row"><span><?= e(t('rorder.total')) ?></span> <strong><?= e(format_price((int) $order['total_cents'], $cur)) ?></strong></p>
    <p class="notice notice-info"><?= e(t('pay.sandbox_note')) ?></p>

    <form method="post" action="<?= e(url('/boutique/commande/' . $order['public_id'] . '/regler')) ?>" class="pay-sandbox-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block" name="outcome" value="pay">✅ <?= e(t('pay.sandbox_pay')) ?></button>
        <button type="submit" class="btn btn-ghost btn-block" name="outcome" value="cancel"><?= e(t('pay.sandbox_cancel')) ?></button>
    </form>
</section>
