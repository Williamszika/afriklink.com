<?php
/**
 * Rail des produits sponsorisés (« À la une »). Identique au rail de reco mais
 * les liens passent par /sp/{campaign_pid} pour comptabiliser les clics quand
 * le produit provient d'une campagne payante (sinon lien direct).
 * @var list<array> $products  id, public_id, name, price_cents, boutique_slug, currency, campaign_pid?
 * @var array<int,string> $mains
 */
if (empty($products)) {
    return;
}
?>
<div class="product-grid">
    <?php foreach ($products as $p): $m = $mains[(int) $p['id']] ?? null;
        $href = !empty($p['campaign_pid'])
            ? url('/sp/' . $p['campaign_pid'])
            : url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id']); ?>
        <div class="product-card-wrap">
            <a class="product-card" href="<?= e($href) ?>">
                <span class="product-card-img">
                    <?php if ($m !== null): ?><img src="<?= e(\App\Services\CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span><?php endif; ?>
                    <span class="promo-badge"><?= e(t('ads.badge')) ?></span>
                </span>
                <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                <span class="product-card-price"><?= render_partial('partials/price_dual', ['cents' => (int) $p['price_cents'], 'cur' => (string) $p['currency']]) ?></span>
            </a>
            <?= render_partial('partials/wish_heart', ['pid' => (string) $p['public_id']]) ?>
        </div>
    <?php endforeach; ?>
</div>
