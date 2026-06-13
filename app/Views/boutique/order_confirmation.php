<?php
/** @var array $order  @var list<array> $items  @var ?array $boutique  @var string $seller_phone
 *  @var string $term  @var string $status  @var string $pay_status  @var int $due_cents  @var int $rest_cents  @var bool $pay_ready */
$cur = (string) $order['currency'];
$payStatus = $pay_status ?? (string) ($order['payment_status'] ?? 'unpaid');
$term = $term ?? (string) ($order['payment_term'] ?? '');
$status = $status ?? (string) ($order['status'] ?? 'new');
$ref = strtoupper(substr((string) $order['public_id'], 0, 6));
// Message WhatsApp récapitulatif pour la boutique (WhatsApp boutique, sinon téléphone vendeur).
$wa = preg_replace('/\D+/', '', (string) (($boutique['contact_whatsapp'] ?? '') ?: ($seller_phone ?? '')));
$lines = [];
foreach ($items as $it) {
    $lines[] = $it['qty'] . '× ' . $it['title'] . ' (' . format_price((int) $it['line_total_cents'], $cur) . ')';
}
$waText = rawurlencode(
    t('bcart.wa_intro', ['ref' => $ref]) . "\n" . implode("\n", $lines)
    . "\n" . t('rorder.total') . ' : ' . format_price((int) $order['total_cents'], $cur)
    . (!empty($order['fulfillment']) ? "\n" . t('bcart.fulfillment') . ' : ' . t('shop.method.' . $order['fulfillment']) : '')
    . "\n" . $order['client_name'] . ($order['client_phone'] ? ' · ' . $order['client_phone'] : '')
);
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon" aria-hidden="true">🧾</div>
    <h1><?= e(t('rorder.confirm_title')) ?></h1>
    <p class="muted"><?= e(t('bcart.confirm_sub', ['ref' => $ref])) ?></p>

    <ul class="cart-lines confirm-lines">
        <?php foreach ($items as $it): ?>
            <li class="cart-line"><span><?= (int) $it['qty'] ?>× <?= e((string) $it['title']) ?></span> <strong><?= e(format_price((int) $it['line_total_cents'], $cur)) ?></strong></li>
        <?php endforeach; ?>
    </ul>
    <p class="cart-total-row"><span><?= e(t('rorder.total')) ?></span> <strong><?= e(format_price((int) $order['total_cents'], $cur)) ?></strong></p>
    <?php if (!empty($order['fulfillment'])): ?>
        <p class="hint"><?= e(t('shop.method.' . $order['fulfillment'])) ?> · <?= e((string) $order['client_name']) ?></p>
    <?php else: ?>
        <p class="hint"><?= e((string) $order['client_name']) ?></p>
    <?php endif; ?>

    <?php if ($term !== ''): ?>
        <p class="hint"><?= e(t('shop.f.payment_terms')) ?> : <strong><?= e(t('shop.payterm.' . $term)) ?></strong></p>
    <?php endif; ?>
    <?php if (!empty($order['payment_method'])): ?>
        <p class="hint"><?= e(t('bcart.method_label')) ?> : <img class="pay-logo-inline" src="<?= e(asset('img/pay/' . $order['payment_method'] . '.svg')) ?>" alt="" width="26" height="16"> <strong><?= e(t('shop.paymethod.' . $order['payment_method'])) ?></strong></p>
    <?php endif; ?>

    <?php if ($payStatus === 'paid'): ?>
        <?php if ($term === 'deposit'): ?>
            <p class="pay-paid-badge">✅ <?= e(t('pay.deposit_paid', ['rest' => format_price((int) ($rest_cents ?? 0), $cur)])) ?></p>
        <?php else: ?>
            <p class="pay-paid-badge">✅ <?= e(t('pay.status_paid')) ?></p>
        <?php endif; ?>
    <?php elseif (!empty($pay_ready)): ?>
        <form method="post" action="<?= e(url('/boutique/commande/' . $order['public_id'] . '/payer')) ?>" class="pay-now-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg">💳 <?= e(t('pay.pay_amount', ['amount' => format_price((int) ($due_cents ?? 0), $cur)])) ?></button>
        </form>
        <?php if ($term === 'deposit'): ?>
            <p class="hint"><?= e(t('pay.deposit_hint', ['rest' => format_price((int) ($rest_cents ?? 0), $cur)])) ?></p>
        <?php else: ?>
            <p class="hint"><?= e(t('pay.pay_now_hint')) ?></p>
        <?php endif; ?>
    <?php elseif ($status === 'new'): ?>
        <p class="notice notice-info">⏳ <?= e(t('bcart.await_confirm')) ?></p>
    <?php else: ?>
        <p class="hint">💵 <?= e(t('bcart.pay_on_delivery')) ?></p>
    <?php endif; ?>
    <?php if ($wa !== ''): ?>
        <p><a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank" href="https://wa.me/<?= e($wa) ?>?text=<?= $waText ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('bcart.send_whatsapp')) ?></a></p>
    <?php endif; ?>
    <?php if ($boutique): ?>
        <p><a class="btn btn-ghost" href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e((string) $boutique['name']) ?></a></p>
    <?php endif; ?>
</section>
