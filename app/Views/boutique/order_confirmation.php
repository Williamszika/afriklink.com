<?php
/** @var array $order  @var list<array> $items  @var ?array $boutique  @var string $seller_phone */
$cur = (string) $order['currency'];
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

    <p class="notice notice-info"><?= e(t('bcart.confirm_note')) ?></p>
    <?php if ($wa !== ''): ?>
        <p><a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank" href="https://wa.me/<?= e($wa) ?>?text=<?= $waText ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('bcart.send_whatsapp')) ?></a></p>
    <?php endif; ?>
    <?php if ($boutique): ?>
        <p><a class="btn btn-ghost" href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e((string) $boutique['name']) ?></a></p>
    <?php endif; ?>
</section>
