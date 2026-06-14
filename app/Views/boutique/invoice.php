<?php
/** @var array $order  @var list<array> $items  @var ?array $boutique  @var array $seller  @var int $subtotal */
$cur   = (string) $order['currency'];
$ref   = strtoupper(substr((string) $order['public_id'], 0, 6));
$ship  = (int) ($order['shipping_cents'] ?? 0);
$disc  = (int) ($order['discount_cents'] ?? 0);
$total = (int) $order['total_cents'];
$shopName = (string) ($boutique['name'] ?? '');
$paid  = (string) ($order['payment_status'] ?? '') === 'paid';
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? t('invoice.title', ['ref' => $ref])) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>
        body.invoice-page { background:#fff; color:#1a1a1a; padding:24px; }
        .invoice { max-width:720px; margin:0 auto; }
        .invoice-actions { max-width:720px; margin:0 auto 18px; display:flex; gap:10px; }
        .inv-head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0b7a4b; padding-bottom:12px; margin-bottom:16px; }
        .inv-shop { font-size:1.3rem; font-weight:800; }
        .inv-meta { text-align:right; font-size:.9rem; color:#444; }
        .inv-parties { display:flex; justify-content:space-between; gap:24px; margin-bottom:18px; font-size:.92rem; }
        .inv-parties h3 { margin:0 0 4px; font-size:.78rem; text-transform:uppercase; color:#777; letter-spacing:.04em; }
        table.inv-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
        .inv-table th { text-align:left; font-size:.76rem; text-transform:uppercase; color:#777; border-bottom:1px solid #ddd; padding:6px 8px; }
        .inv-table td { padding:7px 8px; border-bottom:1px solid #eee; }
        .inv-table td.num, .inv-table th.num { text-align:right; white-space:nowrap; }
        .inv-totals { margin-left:auto; width:280px; border-collapse:collapse; }
        .inv-totals td { padding:3px 8px; }
        .inv-totals .inv-grand td { font-weight:800; font-size:1.05rem; border-top:2px solid #0b7a4b; padding-top:8px; }
        .inv-foot { margin-top:24px; font-size:.85rem; color:#666; text-align:center; }
        @media print { .no-print { display:none !important; } body.invoice-page { padding:0; } @page { margin:14mm; } }
    </style>
</head>
<body class="invoice-page">
    <div class="invoice-actions no-print">
        <button type="button" class="btn btn-primary" data-print>🖨️ <?= e(t('invoice.print')) ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/boutique/commande/' . $order['public_id'])) ?>">← <?= e(t('invoice.back')) ?></a>
    </div>
    <div class="invoice">
        <div class="inv-head">
            <div>
                <div class="inv-shop"><?= e($shopName) ?></div>
                <?php if (!empty($boutique['city']) || !empty($boutique['country_code'])): ?>
                    <div class="muted"><?= e(place_label((string) ($boutique['city'] ?? ''), (string) ($boutique['country_code'] ?? ''))) ?></div>
                <?php endif; ?>
            </div>
            <div class="inv-meta">
                <div><strong><?= e(t('invoice.heading')) ?></strong></div>
                <div>#<?= e($ref) ?></div>
                <div><?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?></div>
            </div>
        </div>

        <div class="inv-parties">
            <div>
                <h3><?= e(t('invoice.from')) ?></h3>
                <div><?= e($shopName) ?></div>
                <?php if (!empty($seller['email'])): ?><div class="muted"><?= e((string) $seller['email']) ?></div><?php endif; ?>
                <?php if (!empty($seller['phone'])): ?><div class="muted"><?= e((string) $seller['phone']) ?></div><?php endif; ?>
            </div>
            <div>
                <h3><?= e(t('invoice.to')) ?></h3>
                <div><?= e((string) $order['client_name']) ?></div>
                <?php if (!empty($order['client_address'])): ?><div class="muted"><?= e((string) $order['client_address']) ?></div><?php endif; ?>
                <?php if (!empty($order['client_phone'])): ?><div class="muted"><?= e((string) $order['client_phone']) ?></div><?php endif; ?>
            </div>
        </div>

        <table class="inv-table">
            <thead><tr><th><?= e(t('invoice.item')) ?></th><th class="num"><?= e(t('invoice.qty')) ?></th><th class="num"><?= e(t('invoice.amount')) ?></th></tr></thead>
            <tbody>
                <?php if ($items === []): ?>
                    <tr><td><?= e((string) $order['product_name']) ?></td><td class="num"><?= (int) $order['qty'] ?></td><td class="num"><?= e(format_price($total, $cur)) ?></td></tr>
                <?php else: foreach ($items as $it): ?>
                    <tr><td><?= e((string) $it['title']) ?></td><td class="num"><?= (int) $it['qty'] ?></td><td class="num"><?= e(format_price((int) $it['line_total_cents'], $cur)) ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <table class="inv-totals">
            <tr><td><?= e(t('caisse.subtotal')) ?></td><td class="num"><?= e(format_price($subtotal > 0 ? $subtotal : $total, $cur)) ?></td></tr>
            <?php if ($ship > 0): ?><tr><td><?= e(t('caisse.shipping')) ?></td><td class="num"><?= e(format_price($ship, $cur)) ?></td></tr><?php endif; ?>
            <?php if ($disc > 0): ?><tr><td><?= e(t('order.receipt.discount')) ?></td><td class="num">−<?= e(format_price($disc, $cur)) ?></td></tr><?php endif; ?>
            <tr class="inv-grand"><td><?= e(t('rorder.total')) ?></td><td class="num"><?= e(format_price($total, $cur)) ?></td></tr>
        </table>

        <p class="muted" style="margin-top:14px">
            <?php if (!empty($order['payment_term'])): ?><?= e(t('shop.f.payment_terms')) ?> : <?= e(t('shop.payterm.' . $order['payment_term'])) ?> · <?php endif; ?>
            <?= e(t('invoice.pay_status')) ?> : <?= e($paid ? t('invoice.paid') : t('invoice.unpaid')) ?>
        </p>

        <div class="inv-foot"><?= e(t('invoice.thanks', ['shop' => $shopName])) ?></div>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
