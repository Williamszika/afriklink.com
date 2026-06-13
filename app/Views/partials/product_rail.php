<?php
/**
 * Rail de produits réutilisable (recommandations).
 * @var string $title  @var string $icon
 * @var list<array> $products  chaque produit : id, public_id, name, price_cents, boutique_slug, currency
 * @var array<int,string> $mains  id produit -> identifiant Cloudinary de la photo principale
 */
if (empty($products)) {
    return;
}
?>
<div class="panel reco-rail">
    <h2 class="panel-title"><?= e($icon) ?> <?= e($title) ?></h2>
    <div class="product-grid">
        <?php foreach ($products as $p): $m = $mains[(int) $p['id']] ?? null; ?>
            <a class="product-card" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                <span class="product-card-img">
                    <?php if ($m !== null): ?><img src="<?= e(\App\Services\CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?>
                </span>
                <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                <span class="product-card-price"><?= e(format_price((int) $p['price_cents'], (string) $p['currency'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
