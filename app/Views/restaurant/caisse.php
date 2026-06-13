<?php
/** @var array $resto  @var list<array> $lines  @var int $total  @var bool $preview
 *  @var list<string> $services  @var list<string> $terms  @var list<string> $pay_methods */
$cur = (string) $resto['currency'];
?>
<section class="caisse">
    <h1 class="caisse-title">🧾 <?= e(t('caisse.title', ['shop' => (string) $resto['name']])) ?></h1>
    <p class="muted"><a href="<?= e(url('/restaurant/' . $resto['slug'])) ?>">← <?= e(t('caisse.continue')) ?></a></p>
    <?php if (!empty($preview)): ?>
        <div class="notice notice-info"><p>👁️ <?= e(t('caisse.preview')) ?></p></div>
    <?php endif; ?>

    <div class="caisse-grid">
        <div class="panel caisse-cart">
            <h2 class="panel-title">🧺 <?= e(t('caisse.your_cart')) ?></h2>
            <ul class="cart-lines">
                <?php foreach ($lines as $l): ?>
                    <li class="cart-line">
                        <span><?= (int) $l['qty'] ?>× <?= e((string) $l['title']) ?> <span class="muted">(<?= e(format_price((int) $l['unit_price_cents'], $cur)) ?>)</span></span>
                        <strong><?= e(format_price((int) $l['qty'] * (int) $l['unit_price_cents'], $cur)) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="cart-total-row caisse-total"><span><?= e(t('rorder.total')) ?></span> <strong><?= e(format_price($total, $cur)) ?></strong></p>
        </div>

        <form class="panel caisse-form" method="post" action="<?= e(url('/restaurant/' . $resto['slug'] . '/commander')) ?>">
            <?= csrf_field() ?>
            <label><?= e(t('rorder.service')) ?></label>
            <div class="lang-checks">
                <?php foreach ($services as $i => $s): ?>
                    <label class="check-pill"><input type="radio" name="service" value="<?= e($s) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e(t('resto.service.' . $s)) ?></span></label>
                <?php endforeach; ?>
            </div>
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
            <h3 class="caisse-section">📇 <?= e(t('order.f.your_details')) ?></h3>
            <label for="cl-name"><?= e(t('order.f.client')) ?></label>
            <input type="text" id="cl-name" name="client_name" maxlength="80" required value="<?= old('client_name') ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
            <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
            <p class="hint"><?= e(t('order.f.contact_hint')) ?></p>
            <?php if (has_error('contact')): ?><p class="field-error"><?= e(error('contact')) ?></p><?php endif; ?>
            <label for="cl-phone"><?= e(t('order.f.phone')) ?></label>
            <input type="tel" id="cl-phone" name="client_phone" maxlength="22" value="<?= old('client_phone') ?>" placeholder="+221 …">
            <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
            <label for="cl-email"><?= e(t('order.f.email')) ?></label>
            <input type="email" id="cl-email" name="client_email" maxlength="120" value="<?= old('client_email') ?>" placeholder="<?= e(t('order.f.email_ph')) ?>">
            <?php if (has_error('client_email')): ?><p class="field-error"><?= e(error('client_email')) ?></p><?php endif; ?>
            <label for="cl-addr"><?= e(t('order.f.address')) ?></label>
            <input type="text" id="cl-addr" name="client_address" maxlength="220" value="<?= old('client_address') ?>" placeholder="<?= e(t('order.f.address_ph')) ?>">
            <label for="cl-note"><?= e(t('order.f.note')) ?></label>
            <input type="text" id="cl-note" name="note" maxlength="500" value="<?= old('note') ?>" placeholder="<?= e(t('rorder.note_ph')) ?>">
            <button type="submit" class="btn btn-primary btn-block btn-lg">✅ <?= e($terms ? t('caisse.validate') : t('caisse.validate_order')) ?></button>
        </form>
    </div>
</section>
