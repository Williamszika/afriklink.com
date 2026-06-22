<?php
/** @var array $boutique  @var list<array> $lines  @var int $total  @var bool $preview
 *  @var list<string> $terms  @var list<string> $pay_methods  @var list<string> $fulfillments
 *  @var array<string,int> $ship_map  @var string $delivery_delay */
$me = $me ?? [];
$savedAddr = isset($saved_address) && is_array($saved_address) ? \App\Models\UserAddress::oneLine($saved_address) : '';
$cur = (string) $boutique['currency'];
$curSym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur;
$firstFee = ($fulfillments[0] ?? null) !== null ? (int) ($ship_map[$fulfillments[0]] ?? 0) : 0;
$minOrder = (int) ($boutique['min_order_cents'] ?? 0);
$belowMin = $minOrder > 0 && $total < $minOrder;
// Délai de livraison : clé i18n si connue (same_day, 1_3…), sinon texte libre du vendeur.
$delayLabel = '';
if ($delivery_delay !== '') {
    $delayLabel = t('shop.prep.' . $delivery_delay);
    if ($delayLabel === 'shop.prep.' . $delivery_delay) {
        $delayLabel = $delivery_delay;
    }
}
?>
<section class="caisse">
    <h1 class="caisse-title">🧾 <?= e(t('caisse.title', ['shop' => (string) $boutique['name']])) ?></h1>
    <p class="muted"><a href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e(t('caisse.continue')) ?></a></p>
    <?php if (!empty($preview)): ?>
        <div class="notice notice-info"><p>👁️ <?= e(t('caisse.preview')) ?></p></div>
    <?php endif; ?>

    <div class="caisse-grid">
        <div class="panel caisse-cart">
            <h2 class="panel-title">🛒 <?= e(t('caisse.your_cart')) ?></h2>
            <ul class="cart-lines">
                <?php foreach ($lines as $l): ?>
                    <li class="cart-line">
                        <span><?= (int) $l['qty'] ?>× <?= e((string) $l['title']) ?> <span class="muted">(<?= e(format_price((int) $l['unit_price_cents'], $cur)) ?>)</span></span>
                        <strong><?= e(format_price((int) $l['qty'] * (int) $l['unit_price_cents'], $cur)) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php $zonesJson = json_encode(array_map(static fn (array $z): array => [
                'c'     => array_values(array_filter(array_map('trim', explode(',', strtoupper((string) ($z['countries'] ?? '')))))),
                'fee'   => (int) $z['fee_cents'],
                'free'  => (int) ($z['free_above_cents'] ?? 0),
                'tiers' => is_array($zt = json_decode((string) ($z['tiers'] ?? ''), true)) ? $zt : null,
            ], $shipping_zones ?? []), JSON_UNESCAPED_SLASHES); ?>
            <?php
            // Équivalent indicatif dans la devise de l'acheteur (≈) : on embarque le taux
            // pour que le TOTAL se convertisse aussi quand les frais changent (JS).
            $buyerCur = current_currency();
            $fxAttr = '';
            if (strtoupper($buyerCur) !== strtoupper($cur)) {
                $fxRate = \App\Services\ExchangeRates::rate($cur, $buyerCur); // taux précis (cohérent avec le serveur)
                if ($fxRate !== null) {
                    $fxSym = trim(str_replace('0', '', format_price(0, $buyerCur)));
                    $fxAttr = ' data-fx-rate="' . $fxRate . '" data-fx-int="' . (currency_is_integer($buyerCur) ? '1' : '0') . '" data-fx-sym="' . e($fxSym) . '"';
                }
            }
            ?>
            <div class="caisse-totals" data-ship-calc data-subtotal="<?= (int) $total ?>" data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>" data-zones="<?= e($zonesJson) ?>"<?= $fxAttr ?>>
                <p class="cart-total-row"><span><?= e(t('caisse.subtotal')) ?></span> <strong><?= e(format_price($total, $cur)) ?><?php $sa = format_price_approx($total, $cur); if ($sa !== ''): ?> <span class="price-approx" title="<?= e(t('price.approx_title')) ?>">≈&nbsp;<?= e($sa) ?></span><?php endif; ?></strong></p>
                <?php if ($fulfillments): ?>
                    <p class="cart-total-row"><span><?= e(t('caisse.shipping')) ?><?php if ($delayLabel !== ''): ?> · <span class="muted"><?= e($delayLabel) ?></span><?php endif; ?></span>
                        <strong data-ship-amount data-free="<?= e(t('caisse.free')) ?>"><?= $firstFee > 0 ? e(format_price($firstFee, $cur)) : e(t('caisse.free')) ?></strong></p>
                <?php endif; ?>
                <p class="cart-total-row caisse-total"><span><?= e(t('rorder.total')) ?></span> <strong data-grand-total><?= e(format_price($total + $firstFee, $cur)) ?></strong><?php if ($fxAttr !== ''): $ga = format_price_approx($total + $firstFee, $cur); ?> <span class="price-approx" data-grand-approx title="<?= e(t('price.approx_title')) ?>"><?= $ga !== '' ? e('≈ ' . $ga) : '' ?></span><?php endif; ?></p>
                <?php if ($minOrder > 0): ?>
                    <p class="caisse-minorder<?= $belowMin ? ' is-below' : '' ?>">
                        <?= $belowMin
                            ? '⚠️ ' . e(t('shop.min_order_blocked', ['min' => format_price($minOrder, $cur)]))
                            : '✓ ' . e(t('shop.min_order_ok', ['min' => format_price($minOrder, $cur)])) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <form class="panel caisse-form" method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/commander')) ?>" data-submit-once>
            <?= csrf_field() ?>
            <?php if ($fulfillments): ?>
                <label><?= e(t('bcart.fulfillment')) ?></label>
                <div class="lang-checks">
                    <?php foreach ($fulfillments as $i => $mth): ?>
                        <label class="check-pill"><input type="radio" name="fulfillment" value="<?= e($mth) ?>" data-fee="<?= (int) ($ship_map[$mth] ?? 0) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e(t('shop.method.' . $mth)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($shipping_zones)): ?>
                <label for="dest-country"><?= e(t('ship.dest_country')) ?></label>
                <select id="dest-country" name="dest_country" data-dest-country>
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach (($countries ?? []) as $code => $cn): ?>
                        <option value="<?= e((string) $code) ?>" <?= ($dest_country ?? '') === $code ? 'selected' : '' ?>><?= e((string) $cn) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('dest_country')): ?><p class="field-error"><?= e(error('dest_country')) ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($terms): ?>
                <label><?= e(t('bcart.pay_term')) ?></label>
                <div class="lang-checks">
                    <?php foreach ($terms as $i => $pt): ?>
                        <label class="check-pill"><input type="radio" name="payment_term" value="<?= e($pt) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e(t('shop.payterm.' . $pt)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($pay_methods): ?>
                <label><?= e(t('bcart.pay_method')) ?></label>
                <div class="lang-checks pay-method-checks">
                    <?php foreach ($pay_methods as $i => $pm): ?>
                        <label class="check-pill"><input type="radio" name="payment_method" value="<?= e($pm) ?>" <?= $i === 0 ? 'checked' : '' ?>><img src="<?= e(asset('img/pay/' . $pm . '.svg')) ?>" alt="" width="30" height="19"><span><?= e(t('shop.paymethod.' . $pm)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php
            // Moyens de paiement adaptés au PAYS de l'acheteur (via CinetPay) — affiché
            // si la boutique propose un encaissement en ligne (carte / mobile money).
            $onlineIntent = array_intersect($pay_methods, ['mobile_money', 'card', 'paypal']) !== [];
            if ($onlineIntent):
                $buyerCc = strtoupper((string) ($dest_country ?? '')) ?: strtoupper((string) (current_user()['country_code'] ?? ''));
                if ($buyerCc === '') { $buyerCc = detect_country_code(); }
                $ccMobile = country_mobile_money($buyerCc);
            ?>
                <div class="pay-country" data-mm-block>
                    <p class="hint pay-country-label"><?= e(t('caisse.choose_operator')) ?><?php if ($buyerCc !== ''): ?> <?= flag_emoji($buyerCc) ?> <?= e(country_name($buyerCc)) ?><?php endif; ?></p>
                    <div class="pay-operators">
                        <?php foreach ($ccMobile as $i => $op): ?>
                            <label class="op-chip"><input type="radio" name="payment_operator" value="<?= e($op) ?>" <?= $i === 0 ? 'checked' : '' ?>><span>📱 <?= e($op) ?></span></label>
                        <?php endforeach; ?>
                        <label class="op-chip"><input type="radio" name="payment_operator" value="<?= e(t('caisse.card')) ?>"<?= $ccMobile === [] ? ' checked' : '' ?>><span>💳 <?= e(t('caisse.card')) ?></span></label>
                    </div>
                </div>
            <?php endif; ?>
            <h3 class="caisse-section">📇 <?= e(t('order.f.your_details')) ?></h3>
            <?php if (empty($me)): ?>
                <p class="hint caisse-guest">👤 <?= e(t('caisse.guest_hint')) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('caisse.guest_login')) ?></a></p>
            <?php endif; ?>
            <label for="cl-name"><?= e(t('order.f.client')) ?></label>
            <input type="text" id="cl-name" name="client_name" maxlength="80" required value="<?= old('client_name') ?: e((string) ($me['full_name'] ?? '')) ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
            <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
            <p class="hint"><?= e(t('order.f.contact_hint')) ?></p>
            <?php if (has_error('contact')): ?><p class="field-error"><?= e(error('contact')) ?></p><?php endif; ?>
            <label for="cl-phone"><?= e(t('order.f.phone')) ?></label>
            <input type="tel" id="cl-phone" name="client_phone" maxlength="22" value="<?= old('client_phone') ?: e((string) ($me['phone'] ?? '')) ?>" placeholder="+221 …">
            <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
            <label for="cl-email"><?= e(t('order.f.email')) ?></label>
            <input type="email" id="cl-email" name="client_email" maxlength="120" value="<?= old('client_email') ?: e((string) ($me['email'] ?? '')) ?>" placeholder="<?= e(t('order.f.email_ph')) ?>">
            <?php if (has_error('client_email')): ?><p class="field-error"><?= e(error('client_email')) ?></p><?php endif; ?>
            <label for="cl-addr"><?= e(t('order.f.address')) ?></label>
            <input type="text" id="cl-addr" name="client_address" maxlength="220" value="<?= old('client_address') ?: e($savedAddr) ?>" placeholder="<?= e(t('order.f.address_ph')) ?>"
                   data-require-radio="fulfillment" data-require-when="local,international">
            <?php if ($savedAddr !== '' && !empty($me)): ?>
                <p class="hint"><a href="<?= e(url('/mes-adresses')) ?>"><?= e(t('addr.manage_link')) ?></a></p>
            <?php endif; ?>
            <?= render_partial('partials/share_location') ?>
            <?php if (has_error('client_address')): ?><p class="field-error"><?= e(error('client_address')) ?></p><?php endif; ?>
            <label for="cl-note"><?= e(t('order.f.note')) ?></label>
            <input type="text" id="cl-note" name="note" maxlength="500" value="<?= old('note') ?>" placeholder="<?= e(t('order.f.note_ph')) ?>">
            <label for="cl-promo">🏷️ <?= e(t('promo.label')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
            <input type="text" id="cl-promo" name="promo_code" maxlength="40" value="<?= old('promo_code') ?>" placeholder="<?= e(t('promo.ph')) ?>" autocapitalize="characters">
            <?= render_partial('partials/payment_strip', ['label' => false, 'secure' => true, 'compact' => true]) ?>
            <p class="caisse-reassure"><?= e(t('caisse.reassure')) ?></p>
            <button type="submit" class="btn btn-primary btn-block btn-lg"<?= $belowMin ? ' disabled' : '' ?>>✅ <?= e(t('caisse.validate')) ?></button>
        </form>
    </div>
</section>
