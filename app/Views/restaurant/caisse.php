<?php
/** @var array $resto  @var list<array> $lines  @var int $total  @var list<string> $services */
$cur = (string) $resto['currency'];
?>
<section class="caisse">
    <h1 class="caisse-title">🧾 <?= e(t('caisse.title', ['shop' => (string) $resto['name']])) ?></h1>
    <p class="muted"><a href="<?= e(url('/restaurant/' . $resto['slug'])) ?>">← <?= e(t('caisse.continue')) ?></a></p>

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
            <label for="cl-name"><?= e(t('order.f.client')) ?></label>
            <input type="text" id="cl-name" name="client_name" maxlength="80" required value="<?= old('client_name') ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
            <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
            <label for="cl-phone"><?= e(t('order.f.phone')) ?></label>
            <input type="tel" id="cl-phone" name="client_phone" maxlength="22" value="<?= old('client_phone') ?>" placeholder="+221 …">
            <?php if (has_error('client_phone')): ?><p class="field-error"><?= e(error('client_phone')) ?></p><?php endif; ?>
            <label for="cl-note"><?= e(t('order.f.note')) ?></label>
            <input type="text" id="cl-note" name="note" maxlength="500" value="<?= old('note') ?>" placeholder="<?= e(t('rorder.note_ph')) ?>">
            <button type="submit" class="btn btn-primary btn-block btn-lg">✅ <?= e(t('caisse.validate_order')) ?></button>
        </form>
    </div>
</section>
