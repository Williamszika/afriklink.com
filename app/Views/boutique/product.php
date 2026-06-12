<?php
/** @var array $boutique  @var array $product  @var list<array> $photos  @var array $seller  @var bool $is_owner */
use App\Services\CloudinaryService;

$cur     = (string) $boutique['currency'];
$main    = $photos[0]['cloud_public_id'] ?? null;
$hasVideo = !empty($product['video_public_id']);
$waPhone = preg_replace('/\D+/', '', (string) ($seller['phone'] ?? ''));
$inStock = $product['stock'] === null || (int) $product['stock'] > 0;
$waText  = rawurlencode(t('product.wa_text', ['name' => (string) $product['name']]) . ' ' . url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']));
?>
<section class="listing-page">
    <p class="muted"><a href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e((string) $boutique['name']) ?></a></p>

    <div class="listing-layout">
        <div class="listing-media">
            <?php if ($main !== null): ?>
                <img id="listing-main-photo" src="<?= e(CloudinaryService::imageUrl($main, 880, 660)) ?>" alt="<?= e((string) $product['name']) ?>" width="880" height="660">
            <?php endif; ?>
            <?php if (count($photos) > 1): ?>
                <div class="listing-thumbs">
                    <?php foreach ($photos as $ph): ?>
                        <button type="button" class="thumb" data-gallery-full="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 880, 660)) ?>">
                            <img src="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 120, 90)) ?>" alt="" loading="lazy" width="120" height="90">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($hasVideo): ?>
                <video controls preload="none" playsinline class="listing-video"
                       poster="<?= e(CloudinaryService::videoPosterUrl((string) $product['video_public_id'], 880)) ?>"
                       src="<?= e(CloudinaryService::videoUrl((string) $product['video_public_id'])) ?>"></video>
            <?php endif; ?>
        </div>

        <div class="listing-side">
            <div class="panel">
                <h1 class="listing-title"><?= e((string) $product['name']) ?></h1>
                <p class="listing-price"><?= e(format_price((int) $product['price_cents'], $cur)) ?></p>
                <p class="listing-tags">
                    <?php if ($inStock): ?>
                        <span class="badge badge-ok"><?= $product['stock'] === null ? e(t('product.in_stock')) : e(t('product.stock_n', ['n' => (int) $product['stock']])) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warn"><?= e(t('product.out_of_stock')) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($waPhone !== '' && $boutique['status'] === 'published'): ?>
                    <a class="btn btn-primary btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waText ?>">💬 <?= e(t('product.order_whatsapp')) ?></a>
                <?php endif; ?>
                <?php if ($is_owner): ?>
                    <div class="listing-owner-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/produits/' . $product['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('shop.manage_link')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($product['description'])): ?>
        <div class="panel">
            <h2 class="panel-title"><?= e(t('product.f.description')) ?></h2>
            <p class="listing-description"><?= nl2br(e((string) $product['description'])) ?></p>
        </div>
    <?php endif; ?>
</section>
