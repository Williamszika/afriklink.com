<?php
/**
 * Conditions de paiement (quand) + moyens de paiement (comment) que le vendeur
 * accepte. Réutilisé par l'assistant (étape 3) et la page d'édition.
 * @var list<string> $terms_sel   conditions déjà choisies
 * @var list<string> $methods_sel moyens déjà cochés
 */
$terms   = config('shop.payment_terms', []);
$methods = config('shop.payment_methods', []);
$termsSel   = old_array('payment_terms')   !== [] ? old_array('payment_terms')   : (array) ($terms_sel ?? []);
$methodsSel = old_array('payment_methods') !== [] ? old_array('payment_methods') : (array) ($methods_sel ?? []);
$provSel = old('payment_provider') !== '' ? old('payment_provider') : (string) ($provider ?? '');
?>
<label><?= e(t('shop.f.payment_terms')) ?></label>
<p class="hint"><?= e(t('shop.f.payment_terms_hint')) ?></p>
<div class="pay-checks">
    <?php foreach ($terms as $tk): ?>
        <label class="pay-pill">
            <input type="checkbox" name="payment_terms[]" value="<?= e($tk) ?>" <?= in_array($tk, $termsSel, true) ? 'checked' : '' ?>>
            <img class="pay-logo" src="<?= e(asset('img/pay/' . $tk . '.svg')) ?>" alt="" width="40" height="26">
            <span class="pay-name"><?= e(t('shop.payterm.' . $tk)) ?></span>
        </label>
    <?php endforeach; ?>
</div>

<label><?= e(t('shop.f.payment_methods')) ?></label>
<p class="hint"><?= e(t('shop.f.payment_methods_hint')) ?></p>
<div class="pay-checks">
    <?php foreach ($methods as $mk): ?>
        <label class="pay-pill">
            <input type="checkbox" name="payment_methods[]" value="<?= e($mk) ?>" <?= in_array($mk, $methodsSel, true) ? 'checked' : '' ?>>
            <img class="pay-logo" src="<?= e(asset('img/pay/' . $mk . '.svg')) ?>" alt="" width="40" height="26">
            <span class="pay-name"><?= e(t('shop.paymethod.' . $mk)) ?></span>
        </label>
    <?php endforeach; ?>
</div>
<p class="hint">🔒 <?= e(t('shop.f.payment_soon')) ?></p>

<label for="pay-provider"><?= e(t('pay.provider_label')) ?></label>
<p class="hint"><?= e(t('pay.provider_hint')) ?></p>
<select id="pay-provider" name="payment_provider">
    <option value=""><?= e(t('pay.provider_none')) ?></option>
    <?php foreach (\App\Services\Payment\PaymentProviders::all() as $pkey => $pp): ?>
        <option value="<?= e($pkey) ?>" <?= $provSel === $pkey ? 'selected' : '' ?>>
            <?= e($pp->label()) ?> — <?= $pp->isConfigured() ? '✅ ' . e(t('pay.ready')) : '⏳ ' . e(t('pay.to_configure')) ?>
        </option>
    <?php endforeach; ?>
</select>
<p class="hint"><a href="<?= e(url('/paiement/tester')) ?>">🧪 <?= e(t('pay.test_link')) ?></a></p>
