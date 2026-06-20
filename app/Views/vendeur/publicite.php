<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $products  @var array<int,string> $mains
 *  @var array<int,array> $campaigns  @var string $placement  @var array<int,int> $packages
 *  @var string $currency  @var string $billing  @var int $wallet_cents */
use App\Models\Product;
use App\Services\CloudinaryService;
$cur  = (string) ($boutique['currency'] ?? $currency);
$days0 = array_key_first($packages) ?: 7;
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('megaphone', ['size' => 24]) ?> <?= e(t('ads.title')) ?></h1>
            <p class="muted"><?= e(t('spotlight.home_empty')) ?></p>
            <p class="muted"><?= e(t('ads.lead2')) ?></p>
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

            <!-- Bandeau forfaits : l'offre « À la une » et ses durées -->
            <div class="panel ad-offer">
                <div class="ad-offer__head">
                    <span class="ad-offer__ic"><?= icon('sparkle', ['size' => 20]) ?></span>
                    <div>
                        <strong><?= e(t('ads.offer_title')) ?></strong>
                        <p class="muted"><?= e(t('ads.offer_text')) ?></p>
                    </div>
                </div>
                <div class="ad-offer__tiers">
                    <?php foreach ($packages as $d => $price): ?>
                        <span class="ad-tier"><strong><?= e(t('ads.days', ['days' => (int) $d])) ?></strong><span><?= e($price !== null ? format_price((int) $price, $cur) : '—') ?></span></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($billing === 'wallet'): ?>
                    <p class="ad-billing muted"><?= icon('wallet', ['size' => 15]) ?> <?= e(t('ads.billing_wallet', ['amount' => format_price($wallet_cents, $cur)])) ?></p>
                <?php else: ?>
                    <p class="ad-billing muted"><?= icon('info', ['size' => 15]) ?> <?= e(t('ads.billing_sim')) ?></p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="product-rows">
                    <?php foreach ($products as $p): ?>
                        <?php
                        $main = $mains[(int) $p['id']] ?? null;
                        $camp = $campaigns[(int) $p['id']] ?? null;
                        ?>
                        <div class="product-row">
                            <div class="product-thumb">
                                <?php if ($main !== null): ?>
                                    <img src="<?= e(CloudinaryService::imageUrl($main, 140, 140)) ?>" alt="" loading="lazy" width="70" height="70">
                                <?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span><?php endif; ?>
                            </div>
                            <div class="product-row-body">
                                <p class="product-row-title"><?= e((string) $p['name']) ?>
                                    <?php if ($camp !== null): ?><span class="promo-badge promo-badge--inline"><?= e(t('ads.badge')) ?></span><?php endif; ?>
                                </p>

                                <?php if ($camp !== null): ?>
                                    <?php
                                    $imp = (int) ($camp['impressions'] ?? 0);
                                    $clk = (int) ($camp['clicks'] ?? 0);
                                    $ctr = $imp > 0 ? round($clk / $imp * 100, 1) : 0.0;
                                    ?>
                                    <p class="product-row-meta">
                                        <?= e(t('ads.until', ['date' => date('d/m/Y', strtotime((string) $camp['ends_at']))])) ?>
                                    </p>
                                    <div class="ad-stats">
                                        <span class="ad-stat"><strong><?= number_format((float) $imp, 0, ',', ' ') ?></strong><span><?= e(t('ads.impressions')) ?></span></span>
                                        <span class="ad-stat"><strong><?= number_format((float) $clk, 0, ',', ' ') ?></strong><span><?= e(t('ads.clicks')) ?></span></span>
                                        <span class="ad-stat"><strong><?= e(number_format($ctr, 1, ',', ' ')) ?> %</strong><span>CTR</span></span>
                                    </div>
                                    <div class="product-row-actions">
                                        <form method="post" action="<?= e(url('/vendeur/publicite/' . $p['public_id'] . '/promouvoir')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm" name="action" value="stop"><?= e(t('ads.stop')) ?></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <p class="product-row-meta"><strong><?= e(format_price((int) $p['price_cents'], $cur)) ?></strong></p>
                                    <form method="post" action="<?= e(url('/vendeur/publicite/' . $p['public_id'] . '/promouvoir')) ?>" class="ad-buy">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="promote">
                                        <input type="hidden" name="placement" value="<?= e($placement) ?>">
                                        <div class="ad-buy__tiers">
                                            <?php foreach ($packages as $d => $price): ?>
                                                <label class="ad-buy__tier">
                                                    <input type="radio" name="days" value="<?= (int) $d ?>" <?= (int) $d === (int) $days0 ? 'checked' : '' ?>>
                                                    <span class="ad-buy__tierbox">
                                                        <strong><?= e(t('ads.days', ['days' => (int) $d])) ?></strong>
                                                        <span><?= e($price !== null ? format_price((int) $price, $cur) : '—') ?></span>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('sparkle', ['size' => 16]) ?> <?= e(t('ads.promote_btn')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <ul class="tips">
                    <li><?= icon('sparkle', ['size' => 16]) ?> <?= e(t('ads.tip_1')) ?></li>
                    <li><?= icon('home', ['size' => 16]) ?> <?= e(t('ads.tip_2')) ?></li>
                    <li><?= icon('tag', ['size' => 16]) ?> <?= e(t('ads.tip_3b')) ?></li>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>
