<?php
/** @var list<array> $products  @var array<int,string> $mains */
use App\Services\CloudinaryService;
?>
<section class="wish-page">
    <h1>❤️ <?= e(t('wish.title')) ?></h1>
    <?php if ($products === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">❤️</p>
            <p><?= e(t('wish.empty')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('/explorer')) ?>"><?= e(t('wish.empty_cta')) ?></a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): $m = $mains[(int) $p['id']] ?? null; ?>
                <div class="product-card-wrap">
                    <a class="product-card" href="<?= e(url('/boutique/' . $p['boutique_slug'] . '/p/' . $p['public_id'])) ?>">
                        <span class="product-card-img"><?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 320, 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="listing-thumb-empty" aria-hidden="true">📦</span><?php endif; ?></span>
                        <span class="product-card-name"><?= e((string) $p['name']) ?></span>
                        <span class="product-card-price"><?= e(format_price_local((int) $p['price_cents'], (string) $p['currency'])) ?></span>
                        <span class="muted explore-card-shop"><?= e(t('explore.by', ['shop' => (string) $p['boutique_name']])) ?></span>
                    </a>
                    <?= render_partial('partials/wish_heart', ['pid' => (string) $p['public_id']]) ?>
                    <?= render_partial('partials/compare_toggle', ['pid' => (string) $p['public_id']]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
