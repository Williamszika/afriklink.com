<?php
/** @var array $boutique  @var array $product  @var list<array> $photos  @var array $seller  @var bool $is_owner  @var bool $seller_verified */
use App\Services\CloudinaryService;

$cur     = (string) $boutique['currency'];
$main    = $photos[0]['cloud_public_id'] ?? null;
$hasVideo = !empty($product['video_public_id']);
// Bouton « Commander » : WhatsApp de la boutique en priorité, sinon le téléphone du vendeur.
$waPhone = preg_replace('/\D+/', '', (string) ($boutique['contact_whatsapp'] ?? '') ?: (string) ($seller['phone'] ?? ''));
$inStock = $product['stock'] === null || (int) $product['stock'] > 0;
$productUrl = url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id']);
$waText  = rawurlencode(t('product.wa_text', ['name' => (string) $product['name']]) . ' ' . $productUrl);
$curSym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XOF' => 'F CFA', 'NGN' => '₦'][$cur] ?? $cur;
$methods = array_values(array_filter(explode(',', (string) ($boutique['delivery_methods'] ?? ''))));
$payTerms = array_values(array_filter(explode(',', (string) ($boutique['payment_terms'] ?? ''))));
$payMethods = array_values(array_filter(explode(',', (string) ($boutique['payment_methods'] ?? ''))));
// Commande en ligne possible si la vitrine est publiée et le produit en stock.
$canOrder = ($boutique['status'] ?? '') === 'published' && $inStock;
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
            <div class="panel" data-cart-root data-cur-int="<?= currency_is_integer($cur) ? '1' : '0' ?>" data-cur-sym="<?= e($curSym) ?>">
                <h1 class="listing-title"><?= e((string) $product['name']) ?></h1>
                <p class="listing-price"><?= e(format_price((int) $product['price_cents'], $cur)) ?></p>
                <p class="listing-tags">
                    <?php if ($inStock): ?>
                        <span class="badge badge-ok"><?= $product['stock'] === null ? e(t('product.in_stock')) : e(t('product.stock_n', ['n' => (int) $product['stock']])) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warn"><?= e(t('product.out_of_stock')) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($canOrder): ?>
                    <div class="product-buy">
                        <?= render_partial('partials/cart_stepper', ['id' => (string) $product['public_id'], 'size' => '', 'name' => (string) $product['name'], 'price' => (int) $product['price_cents']]) ?>
                    </div>
                <?php endif; ?>
                <?php if ($waPhone !== '' && $boutique['status'] === 'published'): ?>
                    <a class="btn btn-ghost btn-block btn-wa" rel="noopener" target="_blank"
                       href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waText ?>"><img class="social-logo" src="<?= e(social_logo('whatsapp')) ?>" alt="" width="22" height="22"> <?= e(t('product.order_whatsapp')) ?></a>
                <?php endif; ?>
                <?php if (!empty($seller_verified)): ?>
                    <p class="verified-line" title="<?= e(t('shop.verified_hint')) ?>">✅ <?= e(t('shop.verified_seller')) ?></p>
                <?php endif; ?>
                <?= render_partial('partials/share_row', [
                    'share_url'  => $productUrl,
                    'share_text' => t('share.product_text', ['name' => (string) $product['name']]),
                ]) ?>
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

    <?php if ($canOrder): ?>
        <!-- Commander : panier + coordonnées -->
        <form id="commander" class="panel resto-checkout" method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/commander')) ?>" data-cart-form hidden>
            <?= csrf_field() ?>
            <h2 class="panel-title">🧺 <?= e(t('rorder.your_order')) ?></h2>
            <ul class="cart-lines" data-cart-lines></ul>
            <p class="cart-total-row"><span><?= e(t('rorder.total')) ?></span> <strong data-cart-total>0</strong></p>
            <?php if ($methods): ?>
                <label><?= e(t('bcart.fulfillment')) ?></label>
                <div class="lang-checks">
                    <?php foreach ($methods as $i => $mth): ?>
                        <label class="check-pill"><input type="radio" name="fulfillment" value="<?= e($mth) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e(t('shop.method.' . $mth)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($payTerms): ?>
                <label><?= e(t('bcart.pay_term')) ?></label>
                <div class="lang-checks">
                    <?php foreach ($payTerms as $i => $pt): ?>
                        <label class="check-pill"><input type="radio" name="payment_term" value="<?= e($pt) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e(t('shop.payterm.' . $pt)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($payMethods): ?>
                <label><?= e(t('bcart.pay_method')) ?></label>
                <div class="lang-checks pay-method-checks">
                    <?php foreach ($payMethods as $i => $pm): ?>
                        <label class="check-pill"><input type="radio" name="payment_method" value="<?= e($pm) ?>" <?= $i === 0 ? 'checked' : '' ?>><img src="<?= e(asset('img/pay/' . $pm . '.svg')) ?>" alt="" width="30" height="19"><span><?= e(t('shop.paymethod.' . $pm)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label for="cl-name"><?= e(t('order.f.client')) ?></label>
            <input type="text" id="cl-name" name="client_name" maxlength="80" required value="<?= old('client_name') ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
            <?php if (has_error('client_name')): ?><p class="field-error"><?= e(error('client_name')) ?></p><?php endif; ?>
            <label for="cl-phone"><?= e(t('order.f.phone')) ?></label>
            <input type="tel" id="cl-phone" name="client_phone" maxlength="22" value="<?= old('client_phone') ?>" placeholder="+221 …">
            <label for="cl-email"><?= e(t('order.f.email')) ?></label>
            <input type="email" id="cl-email" name="client_email" maxlength="120" value="<?= old('client_email') ?>" placeholder="<?= e(t('order.f.email_ph')) ?>">
            <?php if (has_error('client_email')): ?><p class="field-error"><?= e(error('client_email')) ?></p><?php endif; ?>
            <label for="cl-note"><?= e(t('order.f.note')) ?></label>
            <input type="text" id="cl-note" name="note" maxlength="500" value="<?= old('note') ?>" placeholder="<?= e(t('order.f.note_ph')) ?>">
            <input type="hidden" name="cart_json" data-cart-json value="[]">
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('rorder.send')) ?></button>
        </form>

        <!-- Barre de panier (apparaît dès qu'un article est choisi) -->
        <div class="cart-bar" data-cart-bar hidden>
            <span class="cart-bar-info">🧺 <span data-cart-count>0</span> <?= e(t('rorder.items')) ?> · <strong data-cart-total>0</strong></span>
            <button type="button" class="btn btn-primary" data-cart-checkout><?= e(t('rorder.checkout')) ?> →</button>
        </div>
    <?php endif; ?>
</section>
