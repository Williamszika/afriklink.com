<?php
/** @var list<array> $addresses  @var array $countries  @var array $prefill */
$prefill = $prefill ?? [];
?>
<section class="container narrow">
    <div class="page-head">
        <h1>📍 <?= e(t('addr.title')) ?></h1>
        <p class="muted"><?= e(t('addr.subtitle')) ?></p>
    </div>

    <?php if ($addresses !== []): ?>
        <div class="panel">
            <ul class="addr-list">
                <?php foreach ($addresses as $a): ?>
                    <li class="addr-item">
                        <div class="addr-body">
                            <p class="addr-line">
                                <strong><?= e((string) $a['recipient_name']) ?></strong>
                                <?php if (!empty($a['label'])): ?><span class="badge"><?= e((string) $a['label']) ?></span><?php endif; ?>
                                <?php if (!empty($a['is_default'])): ?><span class="badge badge-ok">✓ <?= e(t('addr.default')) ?></span><?php endif; ?>
                            </p>
                            <p class="muted"><?= e(\App\Models\UserAddress::oneLine($a)) ?></p>
                        </div>
                        <div class="addr-actions">
                            <?php if (empty($a['is_default'])): ?>
                                <form method="post" action="<?= e(url('/mes-adresses/' . $a['id'] . '/defaut')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm"><?= e(t('addr.make_default')) ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/mes-adresses/' . $a['id'] . '/suppr')) ?>" data-confirm="<?= e(t('addr.delete_confirm')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm btn-danger"><?= e(t('addr.delete')) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/mes-adresses')) ?>" class="panel" novalidate>
        <?= csrf_field() ?>
        <h2 class="panel-title"><?= e(t('addr.add_title')) ?></h2>
        <div class="grid-2">
            <div>
                <label for="ad-name"><?= e(t('addr.recipient')) ?></label>
                <input type="text" id="ad-name" name="recipient_name" maxlength="128" required value="<?= old('recipient_name') ?>">
                <?php if (has_error('recipient_name')): ?><p class="field-error"><?= e(error('recipient_name')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="ad-label"><?= e(t('addr.label')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="ad-label" name="label" maxlength="64" value="<?= old('label') ?>" placeholder="<?= e(t('addr.label_ph')) ?>">
            </div>
        </div>
        <label for="ad-l1"><?= e(t('addr.line1')) ?></label>
        <input type="text" id="ad-l1" name="line1" maxlength="191" required value="<?= old('line1') ?>">
        <?php if (has_error('line1')): ?><p class="field-error"><?= e(error('line1')) ?></p><?php endif; ?>
        <label for="ad-l2"><?= e(t('addr.line2')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
        <input type="text" id="ad-l2" name="line2" maxlength="191" value="<?= old('line2') ?>">
        <div class="grid-2">
            <div>
                <label for="ad-city"><?= e(t('addr.city')) ?></label>
                <input type="text" id="ad-city" name="city" maxlength="128" required value="<?= old('city') ?: e((string) ($prefill['city'] ?? '')) ?>">
                <?php if (has_error('city')): ?><p class="field-error"><?= e(error('city')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="ad-pc"><?= e(t('addr.postal')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="text" id="ad-pc" name="postal_code" maxlength="32" value="<?= old('postal_code') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="ad-cc"><?= e(t('addr.country')) ?></label>
                <select id="ad-cc" name="country_code" required>
                    <option value=""><?= e(t('addr.country_ph')) ?></option>
                    <?php foreach ($countries as $code => $c): ?>
                        <option value="<?= e((string) $code) ?>" <?= (old('country_code') ?: ($prefill['country_code'] ?? '')) === $code ? 'selected' : '' ?>><?= e(country_name((string) $code)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('country_code')): ?><p class="field-error"><?= e(error('country_code')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="ad-phone"><?= e(t('addr.phone')) ?> <span class="muted">(<?= e(t('field.optional')) ?>)</span></label>
                <input type="tel" id="ad-phone" name="phone" maxlength="32" value="<?= old('phone') ?>">
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_default" value="1">
            <span><?= e(t('addr.set_default')) ?></span>
        </label>
        <button type="submit" class="btn btn-primary"><?= e(t('addr.add_btn')) ?></button>
    </form>
    <?php if (\App\Services\AddressCheck::enabled()): ?>
        <p class="muted addr-geo-credit" style="font-size:.78rem;margin-top:10px">🗺️ <?= e(t('addr.geo_credit')) ?></p>
    <?php endif; ?>
</section>
