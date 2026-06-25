<?php
/** @var array $listing  @var list<array> $photos */
use App\Services\CloudinaryService;

$categories = config('listings.categories', []);
$conditions = config('listings.conditions', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$selCat  = old('category')  ?: (string) $listing['category'];
$selCond = old('condition') ?: (string) $listing['item_condition'];
$selCur  = old('currency')  ?: (string) $listing['currency'];
$cents   = (int) $listing['price_cents'];
$priceVal = old('price') ?: (currency_is_integer((string) $listing['currency'])
    ? (string) intdiv($cents, 100)
    : rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.'));
$user = current_user() ?? [];
?>
<section class="auth-card auth-card--wide">
    <h1>✏️ <?= e(t('listing.edit_title')) ?></h1>

    <form method="post" action="<?= e(url('/annonce/' . $listing['public_id'] . '/modifier')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="title"><?= e(t('listing.field.title')) ?></label>
        <input type="text" id="title" name="title" value="<?= old('title') ?: e((string) $listing['title']) ?>" required
               maxlength="<?= (int) config('listings.title_max', 120) ?>">
        <?php if (has_error('title')): ?><p class="field-error"><?= e(error('title')) ?></p><?php endif; ?>

        <label for="description"><?= e(t('listing.field.description')) ?></label>
        <textarea id="description" name="description" rows="5" required
                  maxlength="<?= (int) config('listings.description_max', 5000) ?>"><?= old('description') ?: e((string) $listing['description']) ?></textarea>
        <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="category"><?= e(t('listing.field.category')) ?></label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $selCat === $cat ? 'selected' : '' ?>><?= e(t('listing.cat.' . $cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="condition"><?= e(t('listing.field.condition')) ?></label>
                <select id="condition" name="condition" required>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= e($cond) ?>" <?= $selCond === $cond ? 'selected' : '' ?>><?= e(t('listing.cond.' . $cond)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="price"><?= e(t('listing.field.price')) ?></label>
                <input type="text" id="price" name="price" value="<?= e($priceVal) ?>" inputmode="decimal" required>
                <?php if (has_error('price')): ?><p class="field-error"><?= e(error('price')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="currency"><?= e(t('listing.field.currency')) ?></label>
                <select id="currency" name="currency" required>
                    <?php foreach ($currencies as $cur): ?>
                        <option value="<?= e($cur) ?>" <?= $selCur === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label for="city"><?= e(t('field.city')) ?></label>
        <input type="text" id="city" name="city" value="<?= old('city') ?: e((string) ($listing['city'] ?? '')) ?>" maxlength="120">
        <?= render_partial('partials/geo_lock_controls') ?>

        <?php if (!empty($user['phone'])): ?>
            <label class="check-row">
                <input type="checkbox" name="whatsapp_optin" value="1"
                    <?= (old('whatsapp_optin') === '1' || (old('whatsapp_optin') === '' && !empty($listing['whatsapp_optin']))) ? 'checked' : '' ?>>
                <span><?= e(t('listing.field.whatsapp_optin')) ?></span>
            </label>
        <?php endif; ?>

        <label class="check-row">
            <input type="checkbox" name="clean_bg" value="1"
                <?= (old('clean_bg') === '1' || (old('clean_bg') === '' && !empty($listing['clean_bg']))) ? 'checked' : '' ?>>
            <span><?= e(t('listing.field.clean_bg')) ?></span>
        </label>
        <p class="hint"><?= e(t('listing.field.clean_bg_hint')) ?></p>

        <?php if ($photos !== []): ?>
            <label><?= e(t('listing.edit_photos_note')) ?></label>
            <div class="upload-previews">
                <?php foreach ($photos as $photo): ?>
                    <img src="<?= e(CloudinaryService::imageUrl((string) $photo['cloud_public_id'], 120, 90)) ?>" alt="" width="120" height="90">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block"><?= e(t('profile.save')) ?></button>
    </form>

    <p class="auth-alt"><a href="<?= e(url('/annonce/' . $listing['public_id'])) ?>">← <?= e(t('listing.back_to_listing')) ?></a></p>
</section>
