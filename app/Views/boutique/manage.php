<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url  @var array $boutique */
use App\Services\CloudinaryService;

$published = ($boutique['status'] ?? 'draft') === 'published';
$logo   = $boutique['logo_public_id'] ?? null;
$banner = $boutique['banner_public_id'] ?? null;
$baseUrl = preg_replace('#^https?://#', '', rtrim((string) (config('app.url') ?: 'afriklink.com'), '/'));
$publicPath = '/boutique/' . $boutique['slug'];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🛍️ <?= e((string) $boutique['name']) ?>
                <span class="badge <?= $published ? 'badge-ok' : 'badge-warn' ?>"><?= e(t($published ? 'shop.status.published' : 'shop.status.draft')) ?></span>
            </h1>
            <p class="muted"><?= e($baseUrl) ?><?= e($publicPath) ?></p>
        </div>

        <?php if (!$published): ?>
            <div class="notice notice-info"><p><?= e(t('shop.draft_banner')) ?></p></div>
        <?php endif; ?>

        <!-- Aperçu vitrine -->
        <div class="panel shop-preview">
            <?php if ($banner !== null): ?>
                <img class="shop-banner" src="<?= e(CloudinaryService::imageUrl($banner, 900, 240)) ?>" alt="">
            <?php else: ?>
                <div class="shop-banner shop-banner--empty"></div>
            <?php endif; ?>
            <div class="shop-preview-id">
                <?php if ($logo !== null): ?>
                    <img class="shop-logo" src="<?= e(CloudinaryService::imageUrl($logo, 120, 120)) ?>" alt="" width="60" height="60">
                <?php else: ?>
                    <div class="shop-logo shop-logo--empty" aria-hidden="true">🛍️</div>
                <?php endif; ?>
                <div>
                    <p class="shop-name"><?= e((string) $boutique['name']) ?></p>
                    <?php if (!empty($boutique['tagline'])): ?><p class="muted"><?= e((string) $boutique['tagline']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="shop-preview-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(url($publicPath)) ?>" target="_blank" rel="noopener"><?= e(t('shop.view_public')) ?> ↗</a>
            </div>
        </div>

        <!-- Produits (prochaine étape) -->
        <div class="panel">
            <h2 class="panel-title">📦 <?= e(t('shop.products_title')) ?></h2>
            <div class="empty-state">
                <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">📦</p>
                <p><?= e(t('shop.products_empty')) ?></p>
                <span class="chip-soon"><?= e(t('dash.soon')) ?></span>
            </div>
        </div>

        <!-- Publication -->
        <div class="panel">
            <h2 class="panel-title">🚀 <?= e(t('shop.publish_title')) ?></h2>
            <p class="muted"><?= e($published ? t('shop.publish_desc_on') : t('shop.publish_desc_off')) ?></p>
            <form method="post" action="<?= e(url('/boutique/publier')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <?php if ($published): ?>
                    <button class="btn btn-ghost" name="action" value="unpublish"><?= e(t('shop.unpublish')) ?></button>
                <?php else: ?>
                    <button class="btn btn-primary" name="action" value="publish"><?= e(t('shop.publish')) ?></button>
                <?php endif; ?>
            </form>
        </div>

    </div>
</div>
