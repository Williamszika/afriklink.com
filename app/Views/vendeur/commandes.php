<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $orders  @var array<string,int> $counts
 *  @var string $filter  @var list<array> $products  @var array<int,list<array>> $items_by_order */
$tabs = [
    'a_traiter'  => (int) ($counts['new'] ?? 0),
    'confirmees' => (int) ($counts['confirmed'] ?? 0),
    'expediees'  => (int) ($counts['shipped'] ?? 0),
    'livrees'    => (int) ($counts['delivered'] ?? 0),
    'annulees'   => (int) ($counts['cancelled'] ?? 0),
    'toutes'     => (int) ($counts['total'] ?? 0),
];
$dotColor = [
    'a_traiter' => '#B47A11', 'confirmees' => '#14502E', 'expediees' => '#C7922E',
    'livrees' => '#2E7D46', 'annulees' => '#B21E4B', 'toutes' => '',
];
$cur = (string) ($boutique['currency'] ?? 'EUR');
$recordOpen = $products === [] || has_error('product') || has_error('client_name') || has_error('qty') || has_error('total') || has_error('client_phone');
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sdash sord">

        <div class="sdash-topbar">
            <div class="sdash-hello">
                <h1><?= e(t('order.title')) ?></h1>
                <p><?= e(t('order.subtitle')) ?></p>
            </div>
            <?php if ($boutique !== null && $products !== []): ?>
                <div class="sdash-actions"><a class="btn btn-gold" href="#record"><?= icon('plus', ['size' => 16]) ?> <?= e(t('order.record_cta')) ?></a></div>
            <?php endif; ?>
        </div>

        <?php if ($boutique === null): ?>
            <section class="sdash-panel">
                <div class="sord-empty">
                    <div class="il" aria-hidden="true">🏪</div>
                    <b><?= e(t('order.need_shop')) ?></b>
                    <p><a class="btn btn-gold" href="<?= e(url('/boutique/creer')) ?>" style="margin-top:.8rem"><?= e(t('shop.cta_create')) ?></a></p>
                </div>
            </section>
        <?php else: ?>

            <!-- Enregistrer une commande (WhatsApp / téléphone / sur place) -->
            <details class="sord-callout" id="record" <?= $recordOpen ? 'open' : '' ?>>
                <summary class="sord-callout-sum">
                    <span class="sord-callout-ic" aria-hidden="true">🧾</span>
                    <span class="sord-callout-txt"><b><?= e(t('order.record_cta')) ?></b><span><?= e(t('order.record_hint')) ?></span></span>
                    <span class="sord-callout-chev" aria-hidden="true">▾</span>
                </summary>
                <div class="sord-callout-body">
                    <p class="sord-phase"><?= e(t('order.record_phase3')) ?></p>
                    <?php if ($products === []): ?>
                        <div class="sord-prereq">
                            <span aria-hidden="true">⚠️</span>
                            <span><?= e(t('order.prereq')) ?></span>
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/produits/nouveau')) ?>"><?= e(t('product.add')) ?></a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/vendeur/commandes')) ?>" class="sord-form" data-submit-once>
                            <?= csrf_field() ?>
                            <div class="sord-form-grid">
                                <div>
                                    <label class="sord-lbl" for="ord-product"><?= e(t('order.f.product')) ?></label>
                                    <select id="ord-product" name="product" required>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= e((string) $p['public_id']) ?>" <?= old('product') === $p['public_id'] ? 'selected' : '' ?>><?= e((string) $p['name']) ?> — <?= e(format_price((int) $p['price_cents'], $cur)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (has_error('product')): ?><p class="field-error"><?= e(error('product')) ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label class="sord-lbl" for="ord-qty"><?= e(t('order.f.qty')) ?></label>
                                    <input type="number" id="ord-qty" name="qty" min="1" max="999" value="<?= e(old('qty') ?: '1') ?>" required>
                                    <?php if (has_error('qty')): ?><p class="field-error"><?= e(error('qty')) ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label class="sord-lbl" for="ord-client"><?= e(t('order.f.client')) ?></label>
                                    <input type="text" id="ord-client" name="client_name" maxlength="80" value="<?= e(old('client_name')) ?>" required placeholder="<?= e(t('order.f.client_ph')) ?>">
                                    <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label class="sord-lbl" for="ord-phone"><?= e(t('order.f.phone')) ?></label>
                                    <input type="tel" id="ord-phone" name="client_phone" maxlength="22" value="<?= e(old('client_phone')) ?>" placeholder="+49 …">
                                    <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label class="sord-lbl" for="ord-total"><?= e(t('order.f.total')) ?> <span class="muted">(<?= e(t('order.f.total_opt')) ?>)</span></label>
                                    <input type="text" id="ord-total" name="total" inputmode="decimal" value="<?= e(old('total')) ?>" placeholder="<?= e(t('order.f.total_ph')) ?>">
                                    <?php if (has_error('total')): ?><p class="field-error"><?= e(error('total')) ?></p><?php endif; ?>
                                </div>
                                <div class="sord-form-note">
                                    <label class="sord-lbl" for="ord-note"><?= e(t('order.f.note')) ?></label>
                                    <input type="text" id="ord-note" name="note" maxlength="500" value="<?= e(old('note')) ?>" placeholder="<?= e(t('order.f.note_ph')) ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-gold"><?= e(t('order.record_submit')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>

            <!-- Filtres par statut -->
            <div class="sord-filters" role="tablist" aria-label="<?= e(t('order.title')) ?>">
                <?php foreach ($tabs as $key => $n): ?>
                    <a class="sord-ftab <?= $filter === $key ? 'on' : '' ?>" role="tab" aria-selected="<?= $filter === $key ? 'true' : 'false' ?>" href="<?= e(url('/vendeur/commandes?filtre=' . $key)) ?>">
                        <?php if ($dotColor[$key] !== ''): ?><span class="dot" style="background:<?= $dotColor[$key] ?>"></span><?php endif; ?>
                        <span><?= e(t('order.filter.' . $key)) ?></span>
                        <span class="cnt"><?= $n ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($orders === []): ?>
                <section class="sdash-panel">
                    <div class="sord-empty">
                        <div class="il" aria-hidden="true"><?= $filter === 'a_traiter' ? '👌' : '📦' ?></div>
                        <b><?= e(t($filter === 'a_traiter' ? 'order.uptodate' : 'order.nothing')) ?></b>
                        <p><?= e(t('order.empty.' . $filter)) ?></p>
                    </div>
                </section>
            <?php else: ?>
                <div class="sord-list">
                    <?php foreach ($orders as $o):
                        $st    = (string) $o['status'];
                        $phone = preg_replace('/\D+/', '', (string) ($o['client_phone'] ?? ''));
                        $ref   = strtoupper(substr((string) $o['public_id'], 0, 6));
                        $oItems = $items_by_order[(int) $o['id']] ?? [];
                    ?>
                        <div class="sord-order">
                            <div class="sord-order-head">
                                <div class="sord-order-id"><b>#<?= e($ref) ?></b><span><?= e(date('d/m/Y H:i', strtotime((string) $o['created_at']))) ?></span></div>
                                <span class="sord-status sord-status--<?= e($st) ?>"><?= e(t('order.status.' . $st)) ?></span>
                            </div>

                            <div class="sord-order-tags">
                                <span class="sord-tag"><?= e(t('order.source.' . (string) $o['source'])) ?></span>
                                <?php if (!empty($o['fulfillment'])): ?><span class="sord-tag"><?= e(t('shop.method.' . $o['fulfillment'])) ?></span><?php endif; ?>
                                <?php if (!empty($o['payment_term'])): ?><span class="sord-tag"><?= icon('card', ['size' => 13]) ?> <?= e(t('shop.payterm.' . $o['payment_term'])) ?></span><?php endif; ?>
                                <?php if (!empty($o['payment_method'])): ?><span class="sord-tag"><img class="pay-logo-inline" src="<?= e(asset('img/pay/' . $o['payment_method'] . '.svg')) ?>" alt="" width="20" height="13"> <?= e(t('shop.paymethod.' . $o['payment_method'])) ?></span><?php endif; ?>
                                <?php if (($o['source'] ?? '') === 'online'): $ps = (string) ($o['payment_status'] ?? 'unpaid'); ?>
                                    <span class="sord-tag sord-tag--<?= $ps === 'paid' ? 'ok' : 'muted' ?>"><?= e(t('order.pay.' . ($ps === 'paid' ? 'paid' : 'unpaid'))) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($oItems !== []): ?>
                                <ul class="sord-lines">
                                    <?php foreach ($oItems as $li): ?>
                                        <li><span><?= (int) $li['qty'] ?>× <?= e(order_item_name($li)) ?><?php if (!empty($li['variant_label'])): ?> <span class="muted"><?= e((string) $li['variant_label']) ?></span><?php endif; ?></span> <strong><?= e(format_price((int) $li['line_total_cents'], (string) $o['currency'])) ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="sord-order-total"><?= e(t('rorder.total')) ?> : <strong><?= e(format_price_local((int) $o['total_cents'], (string) $o['currency'])) ?></strong></p>
                            <?php else: ?>
                                <p class="sord-order-total"><strong><?= e((string) $o['product_name']) ?></strong> × <?= (int) $o['qty'] ?> · <strong><?= e(format_price_local((int) $o['total_cents'], (string) $o['currency'])) ?></strong></p>
                            <?php endif; ?>

                            <p class="sord-order-client">
                                <?= icon('user', ['size' => 15]) ?> <?= e((string) $o['client_name']) ?>
                                <?php if ($phone !== ''): ?> · <a href="https://wa.me/<?= e($phone) ?>" target="_blank" rel="noopener"><img class="social-logo-sm" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="16" height="16"> <?= e((string) $o['client_phone']) ?></a><?php endif; ?>
                            </p>
                            <?php if (!empty($o['client_address'])): ?><p class="sord-order-note"><?= icon('pin', ['size' => 14]) ?> <?= e((string) $o['client_address']) ?></p><?php endif; ?>
                            <?php if (!empty($o['dest_country'])): ?><p class="sord-order-note"><?= icon('globe', ['size' => 14]) ?> <?= flag_emoji((string) $o['dest_country']) ?> <?= e(country_name((string) $o['dest_country'])) ?></p><?php endif; ?>
                            <?php if (!empty($o['geo_lat']) && !empty($o['geo_lng'])): ?><p class="sord-order-note"><?= icon('pin', ['size' => 14]) ?> <a href="https://www.google.com/maps?q=<?= e((string) $o['geo_lat']) ?>,<?= e((string) $o['geo_lng']) ?>" target="_blank" rel="noopener"><?= e(t('order.map_link')) ?></a></p><?php endif; ?>
                            <?php if (!empty($o['note'])): ?><p class="sord-order-note"><?= icon('pencil', ['size' => 13]) ?> <?= e((string) $o['note']) ?></p><?php endif; ?>

                            <?php if (in_array($st, ['new', 'confirmed', 'shipped'], true)): ?>
                                <form method="post" action="<?= e(url('/vendeur/commandes/' . $o['public_id'] . '/statut')) ?>" class="sord-order-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="retour" value="<?= e($filter) ?>">
                                    <?php if ($st === 'new'): ?>
                                        <button class="btn btn-green btn-sm" name="action" value="confirm"><?= icon('check', ['size' => 15]) ?> <?= e(t('order.act.confirm')) ?></button>
                                    <?php elseif ($st === 'confirmed'): ?>
                                        <?php if (($o['source'] ?? '') === 'online'): ?>
                                            <div class="sord-ship">
                                                <?php $selCarrier = (string) ($o['carrier'] ?? '') !== '' ? (string) $o['carrier'] : (string) config('delivery.default', 'other'); ?>
                                                <select name="carrier" class="input-sm" aria-label="<?= e(t('order.ship.carrier')) ?>">
                                                    <?php foreach (delivery_carriers() as $ck => $clabel): ?><option value="<?= e($ck) ?>"<?= $ck === $selCarrier ? ' selected' : '' ?>><?= e($clabel) ?></option><?php endforeach; ?>
                                                </select>
                                                <input type="text" name="tracking_number" class="input-sm" maxlength="64" placeholder="<?= e(t('order.ship.tracking_ph')) ?>" aria-label="<?= e(t('order.ship.tracking')) ?>">
                                            </div>
                                        <?php endif; ?>
                                        <button class="btn btn-green btn-sm" name="action" value="ship"><?= icon('package', ['size' => 15]) ?> <?= e(t('order.act.ship')) ?></button>
                                    <?php else: ?>
                                        <button class="btn btn-green btn-sm" name="action" value="deliver"><?= icon('flag', ['size' => 15]) ?> <?= e(t('order.act.deliver')) ?></button>
                                    <?php endif; ?>
                                    <?php if ($st !== 'shipped'): ?>
                                        <button class="btn btn-sm sord-cancel" name="action" value="cancel" data-confirm="<?= e(t('order.cancel_confirm')) ?>"><?= e(t('order.act.cancel')) ?></button>
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
