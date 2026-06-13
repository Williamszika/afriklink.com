<?php
/** @var array $boutique  @var array $product  @var list<array> $photos  @var array $seller  @var bool $is_owner  @var bool $seller_verified
 *  @var list<array> $reviews  @var array{avg:float,count:int} $rating  @var list<array> $related  @var array<int,string> $related_mains */
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
// Commande en ligne si en stock, et vitrine publiée (ou aperçu propriétaire).
$published = ($boutique['status'] ?? '') === 'published';
$canOrder = $inStock && ($published || $is_owner);
?>
<section class="listing-page">
    <p class="muted"><a href="<?= e(url('/boutique/' . $boutique['slug'])) ?>">← <?= e((string) $boutique['name']) ?></a></p>

    <div class="listing-layout">
        <?php $fulls = array_map(static fn ($ph) => CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 1400, 1050), $photos); ?>
        <div class="listing-media" data-gallery data-photos="<?= e((string) json_encode(array_values($fulls), JSON_UNESCAPED_SLASHES)) ?>">
            <?php if ($main !== null): ?>
                <button type="button" class="listing-main-zoom" data-zoom-open data-index="0" aria-label="<?= e(t('product.zoom')) ?>">
                    <img id="listing-main-photo" src="<?= e(CloudinaryService::imageUrl($main, 880, 660)) ?>" alt="<?= e((string) $product['name']) ?>" width="880" height="660">
                    <span class="zoom-hint" aria-hidden="true">🔍</span>
                </button>
            <?php endif; ?>
            <?php if (count($photos) > 1): ?>
                <div class="listing-thumbs">
                    <?php foreach ($photos as $i => $ph): ?>
                        <button type="button" class="thumb" data-index="<?= (int) $i ?>" data-gallery-full="<?= e(CloudinaryService::imageUrl((string) $ph['cloud_public_id'], 880, 660)) ?>">
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
                <?php if (\App\Models\Product::isPromoted($product)): ?><p class="promo-line">✨ <?= e(t('ads.badge')) ?></p><?php endif; ?>
                <?php if (($rating['count'] ?? 0) > 0): ?>
                    <p class="listing-rating"><a href="#avis"><?= render_partial('partials/stars', ['avg' => $rating['avg'], 'count' => $rating['count']]) ?></a></p>
                <?php endif; ?>
                <p class="listing-price"><?= e(format_price((int) $product['price_cents'], $cur)) ?></p>
                <p class="listing-tags">
                    <?php if ($inStock): ?>
                        <span class="badge badge-ok"><?= $product['stock'] === null ? e(t('product.in_stock')) : e(t('product.stock_n', ['n' => (int) $product['stock']])) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warn"><?= e(t('product.out_of_stock')) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!$inStock && $published): ?>
                    <div class="stock-alert" id="stock-alert">
                        <p class="stock-alert-cta">🔔 <?= e(t('stock.cta')) ?></p>
                        <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'] . '/alerte-stock')) ?>" class="stock-alert-form">
                            <?= csrf_field() ?>
                            <input type="email" name="email" maxlength="120" value="<?= old('email') ?>" placeholder="<?= e(t('order.f.email_ph')) ?>">
                            <input type="tel" name="phone" maxlength="22" value="<?= old('phone') ?>" placeholder="+221 …">
                            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('stock.subscribe_btn')) ?></button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($canOrder): ?>
                    <div class="product-buy">
                        <button type="button" class="btn btn-primary btn-block buy-now-btn" data-buy-now="<?= e((string) $product['public_id']) ?>">⚡ <?= e(t('bcart.buy_now')) ?></button>
                        <?= render_partial('partials/cart_stepper', ['id' => (string) $product['public_id'], 'size' => '', 'name' => (string) $product['name'], 'price' => (int) $product['price_cents'], 'add_label' => t('bcart.add_to_cart')]) ?>
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
                <?php if (!empty($aff_link)): ?>
                    <div class="aff-share">
                        <p class="aff-share-cta">💸 <?= e(t('aff.share_cta', ['rate' => (int) ($aff_rate ?? 5)])) ?></p>
                        <div class="aff-link-row">
                            <input type="text" class="aff-link-input" value="<?= e($aff_link) ?>" readonly aria-label="<?= e(t('aff.your_link')) ?>">
                            <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($aff_link) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= e(t('aff.copy')) ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($published && !$is_owner): ?>
                    <?php if ((int) (current_user_id() ?? 0) > 0): ?>
                        <details class="msg-ask">
                            <summary>💬 <?= e(t('msg.ask_seller')) ?></summary>
                            <form method="post" action="<?= e(url('/messages/demarrer')) ?>" class="msg-ask-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= e($boutique['slug']) ?>">
                                <input type="hidden" name="product" value="<?= e($product['public_id']) ?>">
                                <textarea name="body" rows="3" maxlength="2000" required placeholder="<?= e(t('msg.ask_ph')) ?>"></textarea>
                                <button type="submit" class="btn btn-ghost btn-block"><?= e(t('msg.send_seller')) ?></button>
                            </form>
                        </details>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-block" href="<?= e(url('/login')) ?>">💬 <?= e(t('msg.login_to_ask')) ?></a>
                    <?php endif; ?>
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

    <!-- Avis & notes -->
    <div class="panel" id="avis">
        <h2 class="panel-title">⭐ <?= e(t('review.title')) ?>
            <?php if (($rating['count'] ?? 0) > 0): ?> <?= render_partial('partials/stars', ['avg' => $rating['avg'], 'count' => $rating['count']]) ?><?php endif; ?>
        </h2>
        <?php if (empty($reviews)): ?>
            <p class="muted"><?= e(t('review.empty')) ?></p>
        <?php else: ?>
            <ul class="review-list">
                <?php foreach ($reviews as $rv): ?>
                    <li class="review-item">
                        <div class="review-head">
                            <?= render_partial('partials/stars', ['avg' => (int) $rv['rating'], 'count' => 0, 'small' => true]) ?>
                            <strong class="review-author"><?= e((string) $rv['author_name']) ?></strong>
                            <?php if (!empty($rv['verified'])): ?><span class="review-verified" title="<?= e(t('review.verified_hint')) ?>">✓ <?= e(t('review.verified')) ?></span><?php endif; ?>
                            <span class="review-date muted"><?= e(date('d/m/Y', strtotime((string) $rv['created_at']))) ?></span>
                            <?php if ($is_owner): ?>
                                <form method="post" action="<?= e(url('/boutique/avis/' . $rv['public_id'] . '/masquer')) ?>" class="inline-form review-hide">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="/boutique/<?= e($boutique['slug']) ?>/p/<?= e($product['public_id']) ?>#avis">
                                    <button class="link-button btn-danger" data-confirm="<?= e(t('review.hide_confirm')) ?>"><?= e(t('review.hide')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($rv['comment'])): ?><p class="review-comment"><?= nl2br(e((string) $rv['comment'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($published): ?>
            <details class="review-form-box" <?= has_error('review') ? 'open' : '' ?>>
                <summary>✍️ <?= e(t('review.cta')) ?></summary>
                <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'] . '/avis')) ?>" class="review-form">
                    <?= csrf_field() ?>
                    <div class="star-input" role="radiogroup" aria-label="<?= e(t('review.title')) ?>">
                        <?php for ($s = 5; $s >= 1; $s--): ?>
                            <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" <?= $s === 5 ? 'checked' : '' ?>>
                            <label for="star<?= $s ?>" title="<?= $s ?>/5">★</label>
                        <?php endfor; ?>
                    </div>
                    <label for="rv-name"><?= e(t('order.f.client')) ?></label>
                    <input type="text" id="rv-name" name="author_name" maxlength="80" required value="<?= old('author_name') ?>" placeholder="<?= e(t('order.f.client_ph')) ?>">
                    <label for="rv-contact"><?= e(t('review.verify_label')) ?></label>
                    <input type="text" id="rv-contact" name="purchase_contact" maxlength="120" value="<?= old('purchase_contact') ?>" placeholder="<?= e(t('review.verify_ph')) ?>">
                    <p class="form-hint muted">✓ <?= e(t('review.verify_hint')) ?></p>
                    <label for="rv-comment"><?= e(t('review.comment')) ?></label>
                    <textarea id="rv-comment" name="comment" maxlength="1000" rows="3" placeholder="<?= e(t('review.comment_ph')) ?>"><?= old('comment') ?></textarea>
                    <button type="submit" class="btn btn-primary"><?= e(t('review.submit')) ?></button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <!-- Produits recommandés -->
    <?php if (!empty($related)): ?>
        <div class="panel">
            <h2 class="panel-title">🛍️ <?= e(t('product.related')) ?></h2>
            <div class="product-grid">
                <?php foreach ($related as $rp): $rm = $related_mains[(int) $rp['id']] ?? null; ?>
                    <a class="product-card" href="<?= e(url('/boutique/' . $boutique['slug'] . '/p/' . $rp['public_id'])) ?>">
                        <span class="product-card-img">
                            <?php if ($rm !== null): ?><img src="<?= e(CloudinaryService::imageUrl($rm, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                        </span>
                        <span class="product-card-name"><?= e((string) $rp['name']) ?></span>
                        <span class="product-card-price"><?= e(format_price((int) $rp['price_cents'], $cur)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommandations : souvent achetés ensemble (co-achats) + vu récemment (historique). -->
    <?php if (!empty($fbt)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '🧩', 'title' => t('reco.fbt'), 'products' => $fbt, 'mains' => $reco_mains ?? []]) ?>
    <?php endif; ?>
    <?php if (!empty($recently_viewed)): ?>
        <?= render_partial('partials/product_rail', ['icon' => '🕒', 'title' => t('reco.recent'), 'products' => $recently_viewed, 'mains' => $reco_mains ?? []]) ?>
    <?php endif; ?>

    <?php if ($canOrder): ?>
        <!-- Le panier (JS) est posté ici, revalidé serveur, puis on passe à la caisse. -->
        <form method="post" action="<?= e(url('/boutique/' . $boutique['slug'] . '/caisse')) ?>" data-caisse-form hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="cart_json" data-cart-json value="[]">
        </form>

        <!-- Barre de panier (apparaît dès qu'un article est choisi) -->
        <div class="cart-bar" data-cart-bar hidden>
            <span class="cart-bar-info">🧺 <span data-cart-count>0</span> <?= e(t('rorder.items')) ?> · <strong data-cart-total>0</strong></span>
            <button type="button" class="btn btn-primary" data-cart-checkout><?= e(t('bcart.to_checkout')) ?> →</button>
        </div>
    <?php endif; ?>

    <?= render_partial('partials/assistant', ['boutique' => $boutique, 'wa' => $waPhone]) ?>
</section>
