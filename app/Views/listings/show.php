<?php
/** @var array $listing  @var list<array> $photos  @var array $seller  @var bool $is_owner  @var ?string $avatar_version */
use App\Services\CloudinaryService;

$mainPhoto  = $photos[0]['cloud_public_id'] ?? null;
$hasVideo   = !empty($listing['video_public_id']);
$sellerName = trim((string) ($seller['full_name'] ?? '')) ?: ('@' . (string) ($seller['nickname'] ?? ''));
$cc         = strtoupper((string) ($listing['country_code'] ?? ''));
$place      = trim((!empty($listing['city']) ? (string) $listing['city'] : '') . ($cc !== '' ? ' · ' . flag_emoji($cc) . ' ' . country_name($cc) : ''), ' ·');
$avatarUrl  = avatar_url($seller, $avatar_version ?? null);
$waPhone    = preg_replace('/\D+/', '', (string) ($seller['phone'] ?? ''));
$showWa     = !empty($listing['whatsapp_optin']) && $waPhone !== '' && $listing['status'] === 'active';
$waText     = rawurlencode(t('listing.wa_text', ['title' => (string) $listing['title']]) . ' ' . url('/annonce/' . $listing['public_id']));
?>
<section class="listing-page">

    <?php if ($is_owner && $listing['status'] !== 'active'): ?>
        <div class="notice notice-warning">
            <p><?= e(t('listing.owner_status_' . $listing['status'])) ?></p>
        </div>
    <?php endif; ?>

    <div class="listing-layout">
        <div class="listing-media">
            <?php $lc = !empty($listing['clean_bg']); // détourage choisi par l'annonceur ?>
            <?php if ($listing['status'] === 'sold'): ?>
                <span class="sold-ribbon"><?= e(t('listing.status.sold')) ?></span>
            <?php endif; ?>
            <?php if ($mainPhoto !== null): ?>
                <img id="listing-main-photo" src="<?= e(CloudinaryService::imageUrl($mainPhoto, 880, 660, $lc)) ?>"
                     alt="<?= e((string) $listing['title']) ?>" width="880" height="660">
            <?php endif; ?>
            <?php if (count($photos) > 1 || $hasVideo): ?>
                <div class="listing-thumbs">
                    <?php foreach ($photos as $i => $photo): ?>
                        <button type="button" class="thumb" data-gallery-full="<?= e(CloudinaryService::imageUrl((string) $photo['cloud_public_id'], 880, 660, $lc)) ?>">
                            <img src="<?= e(CloudinaryService::imageUrl((string) $photo['cloud_public_id'], 120, 90, $lc)) ?>" alt="" loading="lazy" width="120" height="90">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($hasVideo): ?>
                <video controls preload="none" playsinline class="listing-video"
                       poster="<?= e(CloudinaryService::videoPosterUrl((string) $listing['video_public_id'], 880)) ?>"
                       src="<?= e(CloudinaryService::videoUrl((string) $listing['video_public_id'])) ?>"></video>
            <?php endif; ?>
        </div>

        <div class="listing-side">
            <div class="panel">
                <h1 class="listing-title"><?= e((string) $listing['title']) ?></h1>
                <p class="listing-price"><?= render_partial('partials/price_dual', ['cents' => (int) $listing['price_cents'], 'cur' => (string) $listing['currency']]) ?></p>
                <p class="listing-tags">
                    <span class="badge badge-neutral"><?= e(t('listing.cat.' . $listing['category'])) ?></span>
                    <span class="badge badge-ok"><?= e(t('listing.cond.' . $listing['item_condition'])) ?></span>
                </p>
                <?php if ($place !== ''): ?><p class="muted">📍 <?= e($place) ?></p><?php endif; ?>
                <p class="muted hint"><?= e(t('listing.published', ['date' => date('d/m/Y', strtotime((string) $listing['created_at']))])) ?></p>

                <?php if ($showWa): ?>
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waText ?>">
                        <img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('listing.contact_whatsapp')) ?>
                    </a>
                <?php endif; ?>

                <?php if ($is_owner): ?>
                    <div class="listing-owner-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/annonce/' . $listing['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/annonces')) ?>"><?= e(t('listing.mine_title')) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel seller-card">
                <h2 class="panel-title"><?= e(t('listing.seller')) ?></h2>
                <div class="seller-row">
                    <?php if ($avatarUrl !== null): ?>
                        <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="" width="64" height="64">
                    <?php else: ?>
                        <div class="avatar" aria-hidden="true"><?= e(user_initials($seller)) ?></div>
                    <?php endif; ?>
                    <div>
                        <p class="seller-name"><?= e($sellerName) ?></p>
                        <p class="muted hint"><?= e(t('dashboard.member_since')) ?> <?= e(substr((string) ($seller['created_at'] ?? ''), 0, 10)) ?></p>
                    </div>
                </div>
                <?php if (!$is_owner && !empty($seller['public_id'])): ?>
                    <?php if (current_user_id() !== null): ?>
                        <form method="post" action="<?= e(url('/messages/contacter')) ?>" class="seller-contact-form" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="to" value="<?= e((string) $seller['public_id']) ?>">
                            <input type="hidden" name="listing" value="<?= e((string) $listing['public_id']) ?>">
                            <label class="sr-only" for="msg-body"><?= e(t('msg.contact_label')) ?></label>
                            <textarea id="msg-body" name="body" rows="3" maxlength="2000" required placeholder="<?= e(t('msg.contact_ph')) ?>"></textarea>
                            <button type="submit" class="btn btn-primary btn-block"><?= icon('chat', ['size' => 16]) ?> <?= e(t('msg.contact_send')) ?></button>
                        </form>
                    <?php else: ?>
                        <p class="hint"><a href="<?= e(url('/login')) ?>"><?= e(t('msg.login_to_contact')) ?></a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title"><?= e(t('listing.field.description')) ?></h2>
        <p class="listing-description"><?= nl2br(e((string) $listing['description'])) ?></p>
    </div>

</section>
