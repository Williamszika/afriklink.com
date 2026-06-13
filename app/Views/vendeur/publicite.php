<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $products  @var array<int,string> $mains  @var int $promo_days */
use App\Models\Product;
use App\Services\CloudinaryService;
$cur = (string) ($boutique['currency'] ?? 'EUR');
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>📣 <?= e(t('ads.title')) ?></h1>
            <p class="muted"><?= e(t('ads.lead', ['days' => (int) $promo_days])) ?></p>
        </div>

        <?php if ($boutique === null): ?>
            <div class="panel"><p class="muted"><?= e(t('ads.no_shop')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('/boutique/creer')) ?>"><?= e(t('shop.create_cta')) ?></a>
            </div>
        <?php elseif ($products === []): ?>
            <div class="panel"><p class="muted"><?= e(t('shop.products_empty')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('/boutique/produits/nouveau')) ?>"><?= e(t('product.add')) ?></a>
            </div>
        <?php else: ?>
            <div class="panel">
                <div class="product-rows">
                    <?php foreach ($products as $p): ?>
                        <?php $main = $mains[(int) $p['id']] ?? null; $promoted = Product::isPromoted($p); ?>
                        <div class="product-row">
                            <div class="product-thumb">
                                <?php if ($main !== null): ?>
                                    <img src="<?= e(CloudinaryService::imageUrl($main, 140, 140)) ?>" alt="" loading="lazy" width="70" height="70">
                                <?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                            </div>
                            <div class="product-row-body">
                                <p class="product-row-title"><?= e((string) $p['name']) ?>
                                    <?php if ($promoted): ?><span class="promo-badge promo-badge--inline"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                                </p>
                                <p class="product-row-meta">
                                    <strong><?= e(format_price((int) $p['price_cents'], $cur)) ?></strong>
                                    <?php if ($promoted): ?>
                                        · <?= e(t('ads.until', ['date' => date('d/m/Y', strtotime((string) $p['promoted_until']))])) ?>
                                    <?php endif; ?>
                                </p>
                                <div class="product-row-actions">
                                    <form method="post" action="<?= e(url('/vendeur/publicite/' . $p['public_id'] . '/promouvoir')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <?php if ($promoted): ?>
                                            <button class="btn btn-ghost btn-sm" name="action" value="stop"><?= e(t('ads.stop')) ?></button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" name="action" value="promote">✨ <?= e(t('ads.promote', ['days' => (int) $promo_days])) ?></button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="panel">
                <ul class="tips">
                    <li>✨ <?= e(t('ads.tip_1')) ?></li>
                    <li>🏠 <?= e(t('ads.tip_2')) ?></li>
                    <li>🆓 <?= e(t('ads.tip_3')) ?></li>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>
