<?php
/** @var array $user  @var bool $media_ready */
$categories = config('listings.categories', []);
$conditions = config('listings.conditions', []);
$currencies = config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']);
$selCat     = old('category');
$selCond    = old('condition');
$geoCur     = currency_for_country((string) (detected_geo()['country_code'] ?? '')) ?? '';
$selCur     = old('currency') ?: ($geoCur ?: (string) ($user['preferred_currency'] ?? 'EUR'));
$city       = old('city') ?: (string) ($user['city'] ?? '');
$hasPhone   = !empty($user['phone']);
$maxPhotos  = (int) config('listings.max_photos', 5);
$maxVideoS  = (int) config('listings.max_video_seconds', 60);
?>
<section class="auth-card auth-card--wide">
    <h1>🏷️ <?= e(t('listing.create_title')) ?></h1>
    <p class="muted"><?= e(t('listing.create_desc')) ?></p>

    <?php if (!$media_ready): ?>
        <div class="notice notice-warning">
            <p><strong><?= e(t('listing.media_unconfigured')) ?></strong></p>
            <p class="hint"><?= e(t('listing.media_unconfigured_hint')) ?></p>
        </div>
    <?php else: ?>
    <form method="post" action="<?= e(url('/vendre')) ?>" id="listing-form" novalidate
          data-cam-capture="<?= e(t('cam.capture')) ?>"
          data-cam-done="<?= e(t('cam.done')) ?>"
          data-cam-start="<?= e(t('cam.start')) ?>"
          data-cam-stop="<?= e(t('cam.stop')) ?>"
          data-cam-flip="<?= e(t('cam.flip')) ?>"
          data-cam-added="<?= e(t('cam.added')) ?>"
          data-cam-error="<?= e(t('cam.error')) ?>"
          data-cam-max="<?= e(t('cam.max_s', ['max' => $maxVideoS])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="photos_json" id="photos_json" value="">
        <input type="hidden" name="video_public_id" id="video_public_id" value="">

        <label for="title"><?= e(t('listing.field.title')) ?></label>
        <input type="text" id="title" name="title" value="<?= old('title') ?>" required
               maxlength="<?= (int) config('listings.title_max', 120) ?>" placeholder="<?= e(t('listing.field.title_ph')) ?>">
        <?php if (has_error('title')): ?><p class="field-error"><?= e(error('title')) ?></p><?php endif; ?>

        <label for="description"><?= e(t('listing.field.description')) ?></label>
        <textarea id="description" name="description" rows="5" required
                  maxlength="<?= (int) config('listings.description_max', 5000) ?>"
                  placeholder="<?= e(t('listing.field.description_ph')) ?>"><?= old('description') ?></textarea>
        <?php if (has_error('description')): ?><p class="field-error"><?= e(error('description')) ?></p><?php endif; ?>

        <div class="grid-2">
            <div>
                <label for="category"><?= e(t('listing.field.category')) ?></label>
                <select id="category" name="category" required>
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $selCat === $cat ? 'selected' : '' ?>><?= e(t('listing.cat.' . $cat)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('category')): ?><p class="field-error"><?= e(error('category')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="condition"><?= e(t('listing.field.condition')) ?></label>
                <select id="condition" name="condition" required>
                    <option value=""><?= e(t('field.choose')) ?></option>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= e($cond) ?>" <?= $selCond === $cond ? 'selected' : '' ?>><?= e(t('listing.cond.' . $cond)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('condition')): ?><p class="field-error"><?= e(error('condition')) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="price"><?= e(t('listing.field.price')) ?></label>
                <input type="text" id="price" name="price" value="<?= old('price') ?>" inputmode="decimal" required placeholder="25">
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
        <input type="text" id="city" name="city" value="<?= e($city) ?>" maxlength="120">
        <?= render_partial('partials/geo_lock_controls') ?>

        <!-- Photos : fichiers, appareil photo, glisser-déposer ou coller -->
        <label><?= e(t('listing.field.photos', ['max' => $maxPhotos])) ?></label>
        <div class="upload-zone" id="photo-zone">
            <div class="upload-actions">
                <label class="btn btn-ghost btn-sm" for="photo-input">📁 <?= e(t('listing.btn.choose_files')) ?></label>
                <button type="button" class="btn btn-ghost btn-sm" id="open-photo-camera">📷 <?= e(t('listing.btn.take_photo')) ?></button>
            </div>
            <p class="hint"><?= e(t('listing.field.photos_hint')) ?></p>
            <input type="file" id="photo-input" class="file-hidden" accept="image/*" multiple>
            <input type="file" id="photo-camera" class="file-hidden" accept="image/*" capture="environment">
        </div>
        <div class="upload-previews" id="photo-previews"></div>
        <?php if (has_error('photos')): ?><p class="field-error"><?= e(error('photos')) ?></p><?php endif; ?>
        <p class="field-error" id="photo-error" hidden></p>

        <!-- Vidéo : fichier, caméra ou glisser-déposer -->
        <label><?= e(t('listing.field.video', ['max' => $maxVideoS])) ?></label>
        <div class="upload-zone" id="video-zone">
            <div class="upload-actions">
                <label class="btn btn-ghost btn-sm" for="video-input">📁 <?= e(t('listing.btn.choose_video')) ?></label>
                <button type="button" class="btn btn-ghost btn-sm" id="open-video-camera">🎥 <?= e(t('listing.btn.record_video')) ?></button>
            </div>
            <p class="hint"><?= e(t('listing.field.video_hint', ['max' => $maxVideoS])) ?></p>
            <input type="file" id="video-input" class="file-hidden" accept="video/mp4,video/quicktime,video/webm">
            <input type="file" id="video-camera" class="file-hidden" accept="video/*" capture="environment">
        </div>
        <div class="upload-previews" id="video-preview"></div>
        <?php if (has_error('video')): ?><p class="field-error"><?= e(error('video')) ?></p><?php endif; ?>
        <p class="field-error" id="video-error" hidden></p>

        <?php if ($hasPhone): ?>
            <label class="check-row">
                <input type="checkbox" name="whatsapp_optin" value="1" <?= old('whatsapp_optin') === '1' ? 'checked' : '' ?>>
                <span><?= e(t('listing.field.whatsapp_optin')) ?></span>
            </label>
            <p class="hint"><?= e(t('listing.field.whatsapp_hint')) ?></p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block" id="listing-submit"><?= e(t('listing.submit')) ?></button>
        <p class="hint" id="upload-status" hidden></p>
    </form>
    <?php endif; ?>

    <p class="auth-alt"><a href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a></p>
</section>
