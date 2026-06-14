<?php
/** @var array $order  @var list<array> $items  @var ?array $boutique  @var array $seller  @var int $subtotal */
$cur      = (string) $order['currency'];
$ref      = strtoupper(substr((string) $order['public_id'], 0, 6));
$ship     = (int) ($order['shipping_cents'] ?? 0);
$disc     = (int) ($order['discount_cents'] ?? 0);
$total    = (int) $order['total_cents'];
$shopName = (string) ($boutique['name'] ?? '');
$paid     = (string) ($order['payment_status'] ?? '') === 'paid';
$sub      = $subtotal > 0 ? $subtotal : $total;
$dateStr  = date('d/m/Y', strtotime((string) $order['created_at']));
// Lignes : soit le détail panier, soit l'article unique (repli).
$rows = [];
if ($items === []) {
    $rows[] = ['title' => (string) $order['product_name'], 'qty' => (int) $order['qty'], 'line' => $total];
} else {
    foreach ($items as $it) {
        $rows[] = ['title' => (string) $it['title'], 'qty' => (int) $it['qty'], 'line' => (int) $it['line_total_cents']];
    }
}
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? t('invoice.title', ['ref' => $ref])) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/logo-cauri.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>
        /* ---- Facture AfrikaLink — vert forêt + or, motif wax, cauri ---- */
        :root { --inv-forest:#103D30; --inv-forest-700:#0B2C22; --inv-forest-300:#3C7A66;
                --inv-or:#E5A02E; --inv-or-soft:#F5D699; --inv-cream:#FBF7EF; --inv-cream-2:#F2EBDD;
                --inv-ink:#16241F; --inv-muted:#5B6B62; --inv-ok:#1E8F6E; --inv-warn:#C77A12;
                --inv-line:rgba(16,36,30,.12); }
        body.invoice-page { background:var(--inv-cream); color:var(--inv-ink);
            font-family:var(--afk-body,"Inter",system-ui,sans-serif); padding:26px 16px; margin:0; }
        .afk-invoice, .afk-invoice * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }

        .inv-actions { max-width:760px; margin:0 auto 16px; display:flex; gap:10px; flex-wrap:wrap; }

        .afk-invoice { max-width:760px; margin:0 auto; background:#fff; border-radius:18px;
            overflow:hidden; box-shadow:0 30px 60px -30px rgba(16,36,30,.5); border:1px solid var(--inv-line); }

        /* En-tête : bandeau forêt + wax doré */
        .inv-band { position:relative; display:flex; justify-content:space-between; align-items:flex-start;
            gap:18px; padding:26px 30px; color:#fff; border-bottom:4px solid var(--inv-or);
            background:linear-gradient(135deg,var(--inv-forest) 0%,var(--inv-forest-700) 100%); }
        .inv-band::before { content:""; position:absolute; inset:0; opacity:.5; pointer-events:none;
            background-image:var(--afk-wax); background-size:38px 38px; }
        .inv-band > * { position:relative; }
        .inv-brand { display:flex; align-items:center; gap:12px; }
        .inv-cauri { width:46px; height:46px; display:flex; align-items:center; justify-content:center;
            background:rgba(251,247,239,.96); border-radius:12px; box-shadow:0 6px 16px -8px rgba(0,0,0,.6); flex:none; }
        .inv-cauri .cauri { width:30px; height:40px; display:block; }
        .inv-wordmark { font-family:var(--afk-display,"Bricolage Grotesque",sans-serif); font-weight:800;
            font-size:1.5rem; letter-spacing:-.02em; line-height:1; }
        .inv-wordmark span { color:var(--inv-or); }
        .inv-tag { display:block; font-family:var(--afk-body); font-weight:500; font-size:.66rem;
            text-transform:uppercase; letter-spacing:.16em; color:var(--inv-or-soft); margin-top:5px; }

        .inv-doc { text-align:right; }
        .inv-doctype { font-family:var(--afk-display,"Bricolage Grotesque",sans-serif); font-weight:800;
            font-size:1.9rem; letter-spacing:.04em; line-height:1; }
        .inv-doc dl { margin:10px 0 0; display:grid; grid-template-columns:auto auto; gap:2px 10px;
            justify-content:end; font-size:.82rem; }
        .inv-doc dt { color:var(--inv-or-soft); text-align:right; }
        .inv-doc dd { margin:0; font-family:var(--afk-mono,"Space Mono",monospace); font-weight:700; }

        .inv-body { padding:26px 30px 30px; }

        /* Parties : Vendeur / Client */
        .inv-parties { display:flex; gap:16px; margin-bottom:22px; }
        .inv-party { flex:1; background:var(--inv-cream); border:1px solid var(--inv-line);
            border-left:3px solid var(--inv-or); border-radius:12px; padding:13px 16px; }
        .inv-party h3 { margin:0 0 5px; font-size:.68rem; text-transform:uppercase; letter-spacing:.1em;
            color:var(--inv-forest-300); font-weight:700; }
        .inv-party-name { font-weight:700; font-size:1.02rem; color:var(--inv-ink); }
        .inv-party .muted { color:var(--inv-muted); font-size:.86rem; line-height:1.45; }

        /* Tableau des articles */
        table.inv-table { width:100%; border-collapse:collapse; margin-bottom:18px; }
        .inv-table thead th { background:var(--inv-forest); color:#fff; font-size:.7rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.06em; text-align:left; padding:10px 12px; }
        .inv-table thead th:first-child { border-radius:8px 0 0 8px; }
        .inv-table thead th:last-child { border-radius:0 8px 8px 0; }
        .inv-table th.num, .inv-table td.num { text-align:right; white-space:nowrap;
            font-variant-numeric:tabular-nums; }
        .inv-table tbody td { padding:11px 12px; border-bottom:1px solid var(--inv-line);
            font-size:.92rem; vertical-align:top; }
        .inv-table tbody tr:nth-child(even) td { background:var(--inv-cream); }
        .inv-table .it-title { font-weight:600; }
        .inv-table td.num.amount { font-weight:700; color:var(--inv-forest); }

        /* Récap : tampon + totaux */
        .inv-summary { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
        .inv-summary-left { padding-top:6px; }
        .inv-stamp { display:inline-block; transform:rotate(-7deg); border:3px double currentColor;
            border-radius:9px; padding:7px 16px; font-family:var(--afk-display,"Bricolage Grotesque",sans-serif);
            font-weight:800; font-size:1.05rem; text-transform:uppercase; letter-spacing:.08em; opacity:.92; }
        .inv-stamp.is-paid { color:var(--inv-ok); }
        .inv-stamp.is-unpaid { color:var(--inv-warn); }
        .inv-terms { margin:14px 2px 0; font-size:.82rem; color:var(--inv-muted); max-width:240px; line-height:1.5; }

        table.inv-totals { width:300px; border-collapse:collapse; }
        .inv-totals td { padding:5px 10px; font-size:.9rem; }
        .inv-totals td.num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .inv-totals .inv-grand td { font-family:var(--afk-display,"Bricolage Grotesque",sans-serif);
            font-weight:800; font-size:1.15rem; color:var(--inv-forest);
            background:linear-gradient(180deg,var(--inv-cream-2),var(--inv-cream)); border-top:2px solid var(--inv-or);
            padding-top:10px; padding-bottom:10px; }
        .inv-totals .inv-grand td:first-child { border-radius:0 0 0 10px; }
        .inv-totals .inv-grand td:last-child { border-radius:0 0 10px 0; }

        .inv-thanks { margin:26px 0 0; text-align:center; font-size:.95rem; color:var(--inv-ink);
            padding-top:18px; border-top:1px dashed var(--inv-line); }

        /* Pied de marque */
        .inv-foot { display:flex; align-items:center; justify-content:center; gap:7px;
            padding:14px 30px; background:var(--inv-cream); border-top:1px solid var(--inv-line);
            font-size:.78rem; color:var(--inv-muted); }
        .inv-foot .cauri { width:15px; height:20px; }
        .inv-foot b { color:var(--inv-forest); font-weight:700; }
        .inv-foot b span { color:var(--inv-or); }

        @media (max-width:560px) {
            .inv-band { flex-direction:column; }
            .inv-doc, .inv-doc dl { text-align:left; justify-content:start; }
            .inv-doc dt { text-align:left; }
            .inv-parties, .inv-summary { flex-direction:column; }
            table.inv-totals { width:100%; }
        }
        @media print {
            .no-print { display:none !important; }
            body.invoice-page { background:#fff; padding:0; }
            .afk-invoice { max-width:100%; border:0; border-radius:0; box-shadow:none; }
            @page { margin:12mm; }
        }
    </style>
</head>
<body class="invoice-page">
    <div class="inv-actions no-print">
        <button type="button" class="btn btn-primary" data-print>🖨️ <?= e(t('invoice.print')) ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/boutique/commande/' . $order['public_id'])) ?>">← <?= e(t('invoice.back')) ?></a>
    </div>

    <div class="afk-invoice">
        <header class="inv-band">
            <div class="inv-brand">
                <span class="inv-cauri"><?= render_partial('partials/logo', ['uid' => 'inv']) ?></span>
                <span>
                    <span class="inv-wordmark">Afrik<span>link</span></span>
                    <span class="inv-tag"><?= e($shopName !== '' ? $shopName : t('invoice.heading')) ?></span>
                </span>
            </div>
            <div class="inv-doc">
                <div class="inv-doctype"><?= e(t('invoice.heading')) ?></div>
                <dl>
                    <dt><?= e(t('invoice.ref_label')) ?></dt><dd>#<?= e($ref) ?></dd>
                    <dt><?= e(t('invoice.date_label')) ?></dt><dd><?= e($dateStr) ?></dd>
                </dl>
            </div>
        </header>

        <div class="inv-body">
            <div class="inv-parties">
                <div class="inv-party">
                    <h3><?= e(t('invoice.from')) ?></h3>
                    <div class="inv-party-name"><?= e($shopName) ?></div>
                    <?php if (!empty($boutique['city']) || !empty($boutique['country_code'])): ?>
                        <div class="muted"><?= e(place_label((string) ($boutique['city'] ?? ''), (string) ($boutique['country_code'] ?? ''))) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($seller['email'])): ?><div class="muted"><?= e((string) $seller['email']) ?></div><?php endif; ?>
                    <?php if (!empty($seller['phone'])): ?><div class="muted"><?= e((string) $seller['phone']) ?></div><?php endif; ?>
                </div>
                <div class="inv-party">
                    <h3><?= e(t('invoice.to')) ?></h3>
                    <div class="inv-party-name"><?= e((string) $order['client_name']) ?></div>
                    <?php if (!empty($order['client_address'])): ?><div class="muted"><?= e((string) $order['client_address']) ?></div><?php endif; ?>
                    <?php if (!empty($order['client_phone'])): ?><div class="muted"><?= e((string) $order['client_phone']) ?></div><?php endif; ?>
                </div>
            </div>

            <table class="inv-table">
                <thead>
                    <tr>
                        <th><?= e(t('invoice.item')) ?></th>
                        <th class="num"><?= e(t('invoice.qty')) ?></th>
                        <th class="num"><?= e(t('invoice.unit')) ?></th>
                        <th class="num"><?= e(t('invoice.amount')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): $q = max(1, (int) $r['qty']); $unit = (int) round($r['line'] / $q); ?>
                        <tr>
                            <td class="it-title"><?= e((string) $r['title']) ?></td>
                            <td class="num"><?= (int) $r['qty'] ?></td>
                            <td class="num"><?= e(format_price($unit, $cur)) ?></td>
                            <td class="num amount"><?= e(format_price((int) $r['line'], $cur)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="inv-summary">
                <div class="inv-summary-left">
                    <span class="inv-stamp <?= $paid ? 'is-paid' : 'is-unpaid' ?>"><?= e($paid ? t('invoice.paid') : t('invoice.unpaid')) ?></span>
                    <?php
                    // N'afficher les conditions que si la clé de traduction existe (jamais de clé brute).
                    $termKey = (string) ($order['payment_term'] ?? '');
                    $termLbl = $termKey !== '' ? t('shop.payterm.' . $termKey) : '';
                    $termOk  = $termLbl !== '' && $termLbl !== 'shop.payterm.' . $termKey;
                    ?>
                    <p class="inv-terms">
                        <?php if ($termOk): ?><?= e(t('shop.f.payment_terms')) ?> : <?= e($termLbl) ?><br><?php endif; ?>
                        <?= e(t('invoice.pay_status')) ?> : <strong><?= e($paid ? t('invoice.paid') : t('invoice.unpaid')) ?></strong>
                    </p>
                </div>
                <table class="inv-totals">
                    <tr><td><?= e(t('caisse.subtotal')) ?></td><td class="num"><?= e(format_price($sub, $cur)) ?></td></tr>
                    <?php if ($ship > 0): ?><tr><td><?= e(t('caisse.shipping')) ?></td><td class="num"><?= e(format_price($ship, $cur)) ?></td></tr><?php endif; ?>
                    <?php if ($disc > 0): ?><tr><td><?= e(t('order.receipt.discount')) ?></td><td class="num">−<?= e(format_price($disc, $cur)) ?></td></tr><?php endif; ?>
                    <tr class="inv-grand"><td><?= e(t('rorder.total')) ?></td><td class="num"><?= e(format_price($total, $cur)) ?></td></tr>
                </table>
            </div>

            <div class="inv-thanks"><?= e(t('invoice.thanks', ['shop' => $shopName])) ?></div>
        </div>

        <footer class="inv-foot">
            <?= render_partial('partials/logo', ['uid' => 'invf']) ?>
            <span><?= e(t('invoice.powered')) ?> · <b>Afrik<span>link</span></b> · #<?= e($ref) ?></span>
        </footer>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
