<?php
/** @var array $boutique  @var list<array> $lines  @var int $total  @var bool $preview
 *  @var list<string> $terms  @var list<string> $pay_methods  @var list<string> $fulfillments
 *  @var array<string,int> $ship_map  @var string $delivery_delay  @var array<int,string> $line_images */
$me = $me ?? [];
$savedAddr = isset($saved_address) && is_array($saved_address) ? \App\Models\UserAddress::oneLine($saved_address) : '';
$cur = (string) $boutique['currency'];
$curSym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur;
// Transporteurs proposés (niveau 1) : s'ils existent, ils remplacent les modes
// « livraison » génériques (local/international) ; le client choisit son transporteur.
$carrierOpts  = $carrier_options ?? [];
$shippedModes = ['local', 'international'];
$shownMethods = $carrierOpts !== []
    ? array_values(array_filter($fulfillments, static fn (string $m): bool => !in_array($m, $shippedModes, true)))
    : $fulfillments;
$firstFee = $shownMethods !== []
    ? (int) ($ship_map[$shownMethods[0]] ?? 0)
    : ($carrierOpts !== [] ? (int) $carrierOpts[0]['fee'] : 0);
$minOrder = (int) ($boutique['min_order_cents'] ?? 0);
$belowMin = $minOrder > 0 && $total < $minOrder;
$lineImages = $line_images ?? [];
$prefillCity = (string) (detected_geo()['city'] ?? '');
// Pays + ville détectés → préremplis ET verrouillés (déverrouillables). On ne
// verrouille qu'à la 1re présentation (pas après une erreur où le client a édité).
$lockGeo = old('dest_country') === '' && old('client_city') === ''
    && ($dest_country ?? '') !== '' && $prefillCity !== '';
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
                <?php foreach ($lines as $l): $img = $lineImages[(int) ($l['product_id'] ?? 0)] ?? null; ?>
                    <li class="cart-line">
                        <span class="cart-line__thumb">
                            <?php if ($img !== null): ?><img src="<?= e(\App\Services\CloudinaryService::imageUrl($img, 96, 96)) ?>" alt="" loading="lazy" width="48" height="48"><?php else: ?><span class="cart-line__ph" aria-hidden="true"><?= icon('package', ['size' => 18]) ?></span><?php endif; ?>
                        </span>
                        <span class="cart-line__body">
                            <span class="cart-line__title"><?= (int) $l['qty'] ?>× <?= e((string) $l['title']) ?></span>
                            <span class="muted cart-line__unit"><?= e(format_price((int) $l['unit_price_cents'], $cur)) ?></span>
                        </span>
                        <strong class="cart-line__total"><?= e(format_price((int) $l['qty'] * (int) $l['unit_price_cents'], $cur)) ?></strong>
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
            $buyerCur = current_currency();
            $fxAttr = '';
            if (strtoupper($buyerCur) !== strtoupper($cur)) {
                $fxRate = \App\Services\ExchangeRates::rate($cur, $buyerCur);
                if ($fxRate !== null) {
                    $fxSym = trim(str_replace('0', '', format_price(0, $buyerCur)));
                    $fxAttr = ' data-fx-rate="' . $fxRate . '" data-fx-int="' . (currency_is_integer($buyerCur) ? '1' : '0') . '" data-fx-sym="' . e($fxSym) . '"';
                }
            }
            ?>
            <div class="caisse-totals" data-ship-calc data-subtotal="<?= (int) $total ?>" data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>" data-zones="<?= e($zonesJson) ?>"<?= $fxAttr ?>>
                <p class="cart-total-row"><span><?= e(t('caisse.subtotal')) ?></span> <strong><?= e(format_price($total, $cur)) ?><?php $sa = format_price_approx($total, $cur); if ($sa !== ''): ?> <span class="price-approx" title="<?= e(t('price.approx_title')) ?>">≈&nbsp;<?= e($sa) ?></span><?php endif; ?></strong></p>
                <?php if ($fulfillments): ?>
                    <p class="cart-total-row"><span><?= e(t('caisse.shipping')) ?></span>
                        <strong data-ship-amount data-free="<?= e(t('caisse.free')) ?>"><?= $firstFee > 0 ? e(format_price($firstFee, $cur)) : e(t('caisse.free')) ?></strong></p>
                    <?php if ($delayLabel !== ''): ?><p class="caisse-eta">🚚 <?= e(t('caisse.eta', ['delay' => $delayLabel])) ?></p><?php endif; ?>
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

        <form class="panel caisse-form" method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/commander')) ?>" data-submit-once data-checkout-wizard data-contact-msg="<?= e(t('order.err_contact')) ?>">
            <?= csrf_field() ?>
            <ol class="wiz-progress" aria-hidden="true">
                <li data-wiz-dot><span class="wiz-dot__n">1</span><span class="wiz-dot__t"><?= e(t('order.f.your_details')) ?></span></li>
                <li data-wiz-dot><span class="wiz-dot__n">2</span><span class="wiz-dot__t"><?= e(t('caisse.step_receive')) ?></span></li>
                <li data-wiz-dot><span class="wiz-dot__n">3</span><span class="wiz-dot__t"><?= e(t('caisse.step_pay')) ?></span></li>
            </ol>

            <!-- Étape 1 — Vos coordonnées -->
            <section class="caisse-step" data-step="1">
                <h3 class="caisse-step__h"><span class="caisse-step__n">1</span> <?= e(t('order.f.your_details')) ?></h3>
                <?php if (empty($me)): ?>
                    <p class="hint caisse-guest">👤 <?= e(t('caisse.guest_hint')) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('caisse.guest_login')) ?></a></p>
                <?php endif; ?>
                <label for="cl-name"><?= e(t('caisse.f.name')) ?></label>
                <input type="text" id="cl-name" name="client_name" maxlength="80" required value="<?= old('client_name') ?: e((string) ($me['full_name'] ?? '')) ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
                <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
                <p class="hint"><?= e(t('order.f.contact_hint')) ?></p>
                <?php if (has_error('contact')): ?><p class="field-error"><?= e(error('contact')) ?></p><?php endif; ?>
                <label for="cl-phone"><?= e(t('caisse.f.phone')) ?></label>
                <input type="tel" id="cl-phone" name="client_phone" maxlength="22" value="<?= old('client_phone') ?: e((string) ($me['phone'] ?? '')) ?>" placeholder="+221 …" data-wiz-contact>
                <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
                <label for="cl-email"><?= e(t('caisse.f.email')) ?></label>
                <input type="email" id="cl-email" name="client_email" maxlength="120" value="<?= old('client_email') ?: e((string) ($me['email'] ?? '')) ?>" placeholder="<?= e(t('order.f.email_ph')) ?>" data-wiz-contact>
                <?php if (has_error('client_email')): ?><p class="field-error"><?= e(error('client_email')) ?></p><?php endif; ?>
                <div class="wiz-nav"><button type="button" class="btn btn-primary btn-block" data-wiz-next><?= e(t('wiz.next')) ?> →</button></div>
            </section>

            <!-- Étape 2 — Réception (adresse + type de livraison) -->
            <section class="caisse-step" data-step="2">
                <h3 class="caisse-step__h"><span class="caisse-step__n">2</span> <?= e(t('caisse.step_receive')) ?></h3>
                <label for="cl-country"><?= e(t('caisse.f.country')) ?></label>
                <select id="cl-country" data-dest-country class="<?= $lockGeo ? 'locked-field is-locked' : '' ?>"<?= $lockGeo ? ' disabled aria-disabled="true" tabindex="-1"' : ' name="dest_country"' ?>>
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach (($countries ?? []) as $code => $cn): ?>
                        <option value="<?= e((string) $code) ?>" <?= (old('dest_country') ?: ($dest_country ?? '')) === $code ? 'selected' : '' ?>><?= e((string) $cn) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($lockGeo): ?><input type="hidden" name="dest_country" id="cl-country_value" value="<?= e((string) ($dest_country ?? '')) ?>"><?php endif; ?>
                <?php if (has_error('dest_country')): ?><p class="field-error"><?= e(error('dest_country')) ?></p><?php endif; ?>
                <label for="cl-city"><?= e(t('caisse.f.city')) ?></label>
                <input type="text" id="cl-city" name="client_city" maxlength="80" value="<?= old('client_city') ?: e($prefillCity) ?>" placeholder="<?= e(t('field.city')) ?>"<?= $lockGeo ? ' readonly class="is-locked"' : '' ?>>
                <?php if ($lockGeo): ?>
                    <p class="hint geo-lock-note" data-geo-lock-note><button type="button" class="link-button" data-geo-unlock><?= e(t('geo.unlock')) ?></button></p>
                <?php endif; ?>
                <label for="cl-addr"><?= e(t('caisse.addr_main')) ?></label>
                <input type="text" id="cl-addr" name="client_address" maxlength="180" value="<?= old('client_address') ?: e($savedAddr) ?>" placeholder="<?= e(t('caisse.addr_main_ph')) ?>"
                       data-require-radio="fulfillment" data-require-when="local,international">
                <?php if (has_error('client_address')): ?><p class="field-error"><?= e(error('client_address')) ?></p><?php endif; ?>
                <label for="cl-addr2"><?= e(t('caisse.addr_extra')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="cl-addr2" name="client_address2" maxlength="120" value="<?= old('client_address2') ?>" placeholder="<?= e(t('caisse.addr_extra_ph')) ?>">
                <label for="cl-postal"><?= e(t('caisse.f.postal')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="cl-postal" name="client_postal" maxlength="16" value="<?= old('client_postal') ?>" placeholder="<?= e(t('field.postal')) ?>" autocomplete="postal-code">
                <?= render_partial('partials/share_location') ?>
                <?php if ($shownMethods || $carrierOpts): ?>
                    <label class="caisse-deliv-label"><?= e($carrierOpts ? t('caisse.carrier_label') : t('bcart.fulfillment')) ?></label>
                    <div class="lang-checks">
                        <?php $fi = 0; ?>
                        <?php foreach ($shownMethods as $mth): ?>
                            <label class="check-pill"><input type="radio" name="fulfillment" value="<?= e($mth) ?>" data-fee="<?= (int) ($ship_map[$mth] ?? 0) ?>" <?= $fi++ === 0 ? 'checked' : '' ?>><span><?= e(t('shop.method.' . $mth)) ?></span></label>
                        <?php endforeach; ?>
                        <?php foreach ($carrierOpts as $opt): ?>
                            <label class="check-pill check-pill--carrier"><input type="radio" name="fulfillment" value="carrier:<?= e($opt['c']) ?>" data-fee="<?= (int) $opt['fee'] ?>" data-carrier <?= $fi++ === 0 ? 'checked' : '' ?>><span><?= e($opt['label']) ?> <strong>· <?= e(format_price((int) $opt['fee'], $cur)) ?></strong></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($carrierOpts): ?><p class="hint caisse-carrier-hint"><?= icon('truck', ['size' => 14]) ?> <?= e(t('caisse.carrier_hint')) ?></p><?php endif; ?>
                <?php endif; ?>
                <div class="wiz-nav">
                    <button type="button" class="btn btn-ghost" data-wiz-prev>← <?= e(t('wiz.prev')) ?></button>
                    <button type="button" class="btn btn-primary" data-wiz-next><?= e(t('wiz.next')) ?> →</button>
                </div>
            </section>

            <!-- Étape 3 — Paiement -->
            <section class="caisse-step" data-step="3">
                <h3 class="caisse-step__h"><span class="caisse-step__n">3</span> <?= e(t('caisse.step_pay')) ?></h3>
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
                            <label class="check-pill"><input type="radio" name="payment_method" value="<?= e($pm) ?>" <?= $i === 0 ? 'checked' : '' ?> data-pay-method><img src="<?= e(asset('img/pay/' . $pm . '.svg')) ?>" alt="" width="30" height="19"><span><?= e(t('shop.paymethod.' . $pm)) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php
                // Opérateur mobile money — n'apparaît qu'au clic sur « Mobile Money » (JS).
                $buyerCc = strtoupper((string) ($dest_country ?? '')) ?: strtoupper((string) (current_user()['country_code'] ?? ''));
                if ($buyerCc === '') { $buyerCc = detect_country_code(); }
                $ccMobile = country_mobile_money($buyerCc);
                ?>
                <div class="pay-country" data-mm-block hidden>
                    <p class="hint pay-country-label"><?= e(t('caisse.choose_operator')) ?><?php if ($buyerCc !== ''): ?> <?= flag_emoji($buyerCc) ?> <?= e(country_name($buyerCc)) ?><?php endif; ?></p>
                    <div class="pay-operators">
                        <?php foreach ($ccMobile as $i => $op): ?>
                            <label class="op-chip"><input type="radio" name="payment_operator" value="<?= e($op) ?>" <?= $i === 0 ? 'checked' : '' ?>><span>📱 <?= e($op) ?></span></label>
                        <?php endforeach; ?>
                        <label class="op-chip"><input type="radio" name="payment_operator" value="<?= e(t('caisse.card')) ?>"<?= $ccMobile === [] ? ' checked' : '' ?>><span>💳 <?= e(t('caisse.card')) ?></span></label>
                    </div>
                </div>
                <label for="cl-promo">🏷️ <?= e(t('promo.label')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="cl-promo" name="promo_code" maxlength="40" value="<?= old('promo_code') ?>" placeholder="<?= e(t('promo.ph')) ?>" autocapitalize="characters">
                <label for="cl-note"><?= e(t('order.f.note')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="cl-note" name="note" maxlength="500" value="<?= old('note') ?>" placeholder="<?= e(t('order.f.note_ph')) ?>">
                <?= render_partial('partials/payment_strip', ['label' => false, 'secure' => false, 'compact' => true]) ?>
                <p class="caisse-reassure"><?= e(t('caisse.reassure')) ?></p>
                <div class="wiz-nav">
                    <button type="button" class="btn btn-ghost" data-wiz-prev>← <?= e(t('wiz.prev')) ?></button>
                    <button type="submit" class="btn btn-primary btn-lg"<?= $belowMin ? ' disabled' : '' ?>>✅ <?= e(t('caisse.validate')) ?></button>
                </div>
            </section>
        </form>
    </div>
</section>
