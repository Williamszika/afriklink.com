<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $orders  @var array<string,int> $counts
 *  @var string $filter  @var list<array> $products  @var array<int,list<array>> $items_by_order */

$statusBadge = static fn (string $s): string => match ($s) {
    'new'       => 'badge-warn',
    'confirmed' => 'badge-info',
    'shipped'   => 'badge-violet',
    'delivered' => 'badge-ok',
    default     => 'badge-neutral',
};
$tabs = [
    'a_traiter'  => (int) ($counts['new'] ?? 0),
    'confirmees' => (int) ($counts['confirmed'] ?? 0),
    'expediees'  => (int) ($counts['shipped'] ?? 0),
    'livrees'    => (int) ($counts['delivered'] ?? 0),
    'annulees'   => (int) ($counts['cancelled'] ?? 0),
    'toutes'     => (int) ($counts['total'] ?? 0),
];
$cur = (string) ($boutique['currency'] ?? 'EUR');
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('receipt', ['size' => 24]) ?> <?= e(t('order.title')) ?></h1>
            <p class="muted"><?= e(t('order.subtitle')) ?></p>
        </div>

        <?php if ($boutique === null): ?>
            <div class="panel">
                <div class="empty-state">
                    <p style="margin:0 0 6px" aria-hidden="true"><?= icon('store', ['size' => 34]) ?></p>
                    <p><?= e(t('order.need_shop')) ?></p>
                    <a class="btn btn-primary" href="<?= e(url('/boutique/creer')) ?>"><?= e(t('shop.cta_create')) ?></a>
                </div>
            </div>
        <?php else: ?>

            <!-- Enregistrer une commande (ventes WhatsApp / téléphone / sur place) -->
            <details class="panel order-record" <?= has_error('product') || has_error('client_name') || has_error('qty') || has_error('total') || has_error('client_phone') ? 'open' : '' ?>>
                <summary class="order-record-summary"><?= icon('plus', ['size' => 16]) ?> <?= e(t('order.record_cta')) ?></summary>
                <p class="muted"><?= e(t('order.record_hint')) ?></p>
                <?php if ($products === []): ?>
                    <p class="muted"><?= e(t('order.no_products')) ?> — <a href="<?= e(url('/boutique/produits/nouveau')) ?>"><?= e(t('product.add')) ?></a></p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/vendeur/commandes')) ?>" class="order-form">
                        <?= csrf_field() ?>
                        <div class="order-form-grid">
                            <div>
                                <label for="ord-product"><?= e(t('order.f.product')) ?></label>
                                <select id="ord-product" name="product" required>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= e((string) $p['public_id']) ?>" <?= old('product') === $p['public_id'] ? 'selected' : '' ?>>
                                            <?= e((string) $p['name']) ?> — <?= e(format_price((int) $p['price_cents'], $cur)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error('product')): ?><p class="field-error"><?= e(error('product')) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label for="ord-qty"><?= e(t('order.f.qty')) ?></label>
                                <input type="number" id="ord-qty" name="qty" min="1" max="999" value="<?= e(old('qty') ?: '1') ?>" required>
                                <?php if (has_error('qty')): ?><p class="field-error"><?= e(error('qty')) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label for="ord-client"><?= e(t('order.f.client')) ?></label>
                                <input type="text" id="ord-client" name="client_name" maxlength="80" value="<?= e(old('client_name')) ?>" required placeholder="<?= e(t('order.f.client_ph')) ?>">
                                <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label for="ord-phone"><?= e(t('order.f.phone')) ?></label>
                                <input type="tel" id="ord-phone" name="client_phone" maxlength="22" value="<?= e(old('client_phone')) ?>" placeholder="+49 …">
                                <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label for="ord-total"><?= e(t('order.f.total')) ?> <span class="muted">(<?= e(t('order.f.total_opt')) ?>)</span></label>
                                <input type="text" id="ord-total" name="total" inputmode="decimal" value="<?= e(old('total')) ?>" placeholder="<?= e(t('order.f.total_ph')) ?>">
                                <?php if (has_error('total')): ?><p class="field-error"><?= e(error('total')) ?></p><?php endif; ?>
                            </div>
                            <div class="order-form-note">
                                <label for="ord-note"><?= e(t('order.f.note')) ?></label>
                                <input type="text" id="ord-note" name="note" maxlength="500" value="<?= e(old('note')) ?>" placeholder="<?= e(t('order.f.note_ph')) ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><?= e(t('order.record_submit')) ?></button>
                    </form>
                <?php endif; ?>
            </details>

            <!-- Filtres par statut -->
            <div class="catalogue-filters" role="tablist">
                <?php foreach ($tabs as $key => $n): ?>
                    <a class="chip-filter <?= $filter === $key ? 'is-active' : '' ?>" role="tab"
                       aria-selected="<?= $filter === $key ? 'true' : 'false' ?>"
                       href="<?= e(url('/vendeur/commandes?filtre=' . $key)) ?>">
                        <?= e(t('order.filter.' . $key)) ?> <span class="chip-count"><?= $n ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($orders === []): ?>
                <div class="panel">
                    <div class="empty-state">
                        <p style="margin:0 0 6px" aria-hidden="true"><?= icon('receipt', ['size' => 34]) ?></p>
                        <p><?= e(t('order.empty.' . $filter)) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="order-rows">
                    <?php foreach ($orders as $o): ?>
                        <?php
                        $st = (string) $o['status'];
                        $phone = preg_replace('/\D+/', '', (string) ($o['client_phone'] ?? ''));
                        $ref = strtoupper(substr((string) $o['public_id'], 0, 6));
                        ?>
                        <?php $oItems = $items_by_order[(int) $o['id']] ?? []; ?>
                        <div class="panel order-row">
                            <div class="order-row-head">
                                <span class="order-ref">#<?= e($ref) ?></span>
                                <span class="badge <?= $statusBadge($st) ?>"><?= e(t('order.status.' . $st)) ?></span>
                                <span class="order-source"><?= e(t('order.source.' . (string) $o['source'])) ?></span>
                                <?php if (!empty($o['fulfillment'])): ?><span class="order-source"><?= e(t('shop.method.' . $o['fulfillment'])) ?></span><?php endif; ?>
                                <?php if (!empty($o['payment_term'])): ?><span class="order-source"><?= icon('card', ['size' => 14]) ?> <?= e(t('shop.payterm.' . $o['payment_term'])) ?></span><?php endif; ?>
                                <?php if (!empty($o['payment_method'])): ?><span class="order-source"><img class="pay-logo-inline" src="<?= e(asset('img/pay/' . $o['payment_method'] . '.svg')) ?>" alt="" width="22" height="14"> <?= e(t('shop.paymethod.' . $o['payment_method'])) ?></span><?php endif; ?>
                                <?php if (($o['source'] ?? '') === 'online'): $ps = (string) ($o['payment_status'] ?? 'unpaid'); ?>
                                    <span class="badge <?= $ps === 'paid' ? 'badge-ok' : 'badge-neutral' ?>"><?= e(t('order.pay.' . ($ps === 'paid' ? 'paid' : 'unpaid'))) ?></span>
                                <?php endif; ?>
                                <span class="order-date"><?= e(date('d/m/Y H:i', strtotime((string) $o['created_at']))) ?></span>
                            </div>
                            <?php if ($oItems !== []): ?>
                                <ul class="cart-lines">
                                    <?php foreach ($oItems as $li): ?>
                                        <li class="cart-line"><span><?= (int) $li['qty'] ?>× <?= e(order_item_name($li)) ?><?php if (!empty($li['variant_label'])): ?> <span class="order-variant"><?= e((string) $li['variant_label']) ?></span><?php endif; ?></span> <strong><?= e(format_price((int) $li['line_total_cents'], (string) $o['currency'])) ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="order-line"><strong class="order-total"><?= e(t('rorder.total')) ?> : <?= e(format_price_local((int) $o['total_cents'], (string) $o['currency'])) ?></strong></p>
                            <?php else: ?>
                                <p class="order-line">
                                    <strong><?= e((string) $o['product_name']) ?></strong> × <?= (int) $o['qty'] ?>
                                    · <strong class="order-total"><?= e(format_price_local((int) $o['total_cents'], (string) $o['currency'])) ?></strong>
                                </p>
                            <?php endif; ?>
                            <p class="order-client">
                                <?= icon('user', ['size' => 15]) ?> <?= e((string) $o['client_name']) ?>
                                <?php if ($phone !== ''): ?>
                                    · <a href="https://wa.me/<?= e($phone) ?>" target="_blank" rel="noopener"><img class="social-logo-sm" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="18" height="18"> <?= e((string) $o['client_phone']) ?></a>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($o['client_address'])): ?>
                                <p class="order-note"><?= icon('pin', ['size' => 15]) ?> <?= e((string) $o['client_address']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($o['dest_country'])): ?>
                                <p class="order-note"><?= icon('globe', ['size' => 15]) ?> <?= flag_emoji((string) $o['dest_country']) ?> <?= e(country_name((string) $o['dest_country'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($o['geo_lat']) && !empty($o['geo_lng'])): ?>
                                <p class="order-note"><?= icon('pin', ['size' => 15]) ?> <a href="https://www.google.com/maps?q=<?= e((string) $o['geo_lat']) ?>,<?= e((string) $o['geo_lng']) ?>" target="_blank" rel="noopener"><?= e(t('order.map_link')) ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($o['note'])): ?>
                                <p class="order-note"><?= icon('pencil', ['size' => 14]) ?> <?= e((string) $o['note']) ?></p>
                            <?php endif; ?>
                            <?php if (in_array($st, ['new', 'confirmed', 'shipped'], true)): ?>
                                <form method="post" action="<?= e(url('/vendeur/commandes/' . $o['public_id'] . '/statut')) ?>" class="order-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="retour" value="<?= e($filter) ?>">
                                    <?php if ($st === 'new'): ?>
                                        <button class="btn btn-primary btn-sm" name="action" value="confirm"><?= icon('check', ['size' => 16]) ?> <?= e(t('order.act.confirm')) ?></button>
                                    <?php elseif ($st === 'confirmed'): ?>
                                        <?php if (($o['source'] ?? '') === 'online'): ?>
                                            <div class="ship-fields">
                                                <select name="carrier" class="input-sm" aria-label="<?= e(t('order.ship.carrier')) ?>">
                                                    <?php foreach (delivery_carriers() as $ck => $clabel): ?>
                                                        <option value="<?= e($ck) ?>"<?= $ck === config('delivery.default', 'other') ? ' selected' : '' ?>><?= e($clabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" name="tracking_number" class="input-sm" maxlength="64"
                                                       placeholder="<?= e(t('order.ship.tracking_ph')) ?>" aria-label="<?= e(t('order.ship.tracking')) ?>">
                                            </div>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" name="action" value="ship"><?= icon('package', ['size' => 16]) ?> <?= e(t('order.act.ship')) ?></button>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" name="action" value="deliver"><?= icon('flag', ['size' => 16]) ?> <?= e(t('order.act.deliver')) ?></button>
                                    <?php endif; ?>
                                    <?php if ($st !== 'shipped'): ?>
                                        <button class="btn btn-ghost btn-sm btn-danger" name="action" value="cancel"
                                                data-confirm="<?= e(t('order.cancel_confirm')) ?>"><?= e(t('order.act.cancel')) ?></button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
