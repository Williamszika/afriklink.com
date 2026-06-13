<?php
/** @var array $order  @var list<array> $items  @var ?array $resto  @var string $seller_phone */
$cur = (string) $order['currency'];
$ref = strtoupper(substr((string) $order['public_id'], 0, 6));
// Message WhatsApp récapitulatif pour le restaurant (WhatsApp boutique, sinon téléphone vendeur).
$wa = preg_replace('/\D+/', '', (string) (($resto['contact_whatsapp'] ?? '') ?: ($seller_phone ?? '')));
$lines = [];
foreach ($items as $it) {
    $lines[] = $it['qty'] . '× ' . $it['title'] . ' (' . format_price((int) $it['line_total_cents'], $cur) . ')';
}
$waText = rawurlencode(
    t('rorder.wa_intro', ['ref' => $ref]) . "\n" . implode("\n", $lines)
    . "\n" . t('rorder.total') . ' : ' . format_price((int) $order['subtotal_cents'], $cur)
    . "\n" . t('rorder.service') . ' : ' . t('resto.service.' . $order['service'])
    . "\n" . $order['client_name'] . ($order['client_phone'] ? ' · ' . $order['client_phone'] : '')
);
?>
<section class="auth-card pay-result">
    <div class="pay-result-icon" aria-hidden="true">🧾</div>
    <h1><?= e(t('rorder.confirm_title')) ?></h1>
    <p class="muted"><?= e(t('rorder.confirm_sub', ['ref' => $ref])) ?></p>

    <ul class="cart-lines confirm-lines">
        <?php foreach ($items as $it): ?>
            <li class="cart-line"><span><?= (int) $it['qty'] ?>× <?= e((string) $it['title']) ?></span> <strong><?= e(format_price((int) $it['line_total_cents'], $cur)) ?></strong></li>
        <?php endforeach; ?>
    </ul>
    <p class="cart-total-row"><span><?= e(t('rorder.total')) ?></span> <strong><?= e(format_price((int) $order['subtotal_cents'], $cur)) ?></strong></p>
    <p class="hint"><?= e(t('resto.service.' . $order['service'])) ?> · <?= e((string) $order['client_name']) ?></p>

    <p class="notice notice-info"><?= e(t('rorder.confirm_note')) ?></p>
    <?php if ($wa !== ''): ?>
        <p><a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank" href="https://wa.me/<?= e($wa) ?>?text=<?= $waText ?>">💬 <?= e(t('rorder.send_whatsapp')) ?></a></p>
    <?php endif; ?>
    <?php if ($resto): ?>
        <p><a class="btn btn-ghost" href="<?= e(url('/restaurant/' . $resto['slug'])) ?>">← <?= e((string) $resto['name']) ?></a></p>
    <?php endif; ?>
</section>
