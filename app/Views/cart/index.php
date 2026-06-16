<?php
/** @var list<array> $groups  @var int $count */
use App\Services\CloudinaryService;
?>
<section class="cart-page">
    <h1>🛒 <?= e(t('cart.title')) ?><?php if ($count > 0): ?> <span class="muted">(<?= e(t('cart.count', ['n' => $count])) ?>)</span><?php endif; ?></h1>

    <?php if ($groups === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">🛒</p>
            <p><?= e(t('cart.empty')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('/explorer')) ?>"><?= e(t('cart.empty_cta')) ?></a>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $g): ?>
            <div class="panel cart-group">
                <h2 class="panel-title">🏪 <a href="<?= e(url('/boutique/' . $g['slug'])) ?>"><?= e((string) $g['name']) ?></a></h2>
                <ul class="cart-items">
                    <?php foreach ($g['lines'] as $l): $p = $l['product']; $m = $l['main']; $purl = url('/boutique/' . $g['slug'] . '/p/' . $p['public_id']); ?>
                        <li class="cart-item">
                            <a class="cart-item-thumb" href="<?= e($purl) ?>">
                                <?php if ($m !== null): ?><img src="<?= e(CloudinaryService::imageUrl($m, 160, 160)) ?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true">📦</span><?php endif; ?>
                            </a>
                            <div class="cart-item-body">
                                <a class="cart-item-name" href="<?= e($purl) ?>"><?= e((string) $p['name']) ?></a>
                                <?php $cpct = product_promo_pct($p); ?>
                                <span class="muted"><?php if ($cpct > 0): ?><del><?= e(format_price((int) $p['price_cents'], (string) $g['currency'])) ?></del> <?php endif; ?><?= e(format_price((int) ($l['unit'] ?? $p['price_cents']), (string) $g['currency'])) ?><?php if ($cpct > 0): ?> <span class="discount-badge discount-badge--inline">−<?= $cpct ?>%</span><?php endif; ?></span>
                            </div>
                            <div class="cart-item-qty">
                                <form method="post" action="<?= e(url('/panier/modifier')) ?>" class="inline-form cart-qty-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="slug" value="<?= e($g['slug']) ?>">
                                    <input type="hidden" name="pid" value="<?= e((string) $p['public_id']) ?>">
                                    <button class="qty-btn" name="qty" value="<?= max(0, (int) $l['qty'] - 1) ?>" aria-label="−">−</button>
                                    <span class="qty-num"><?= (int) $l['qty'] ?></span>
                                    <button class="qty-btn" name="qty" value="<?= (int) $l['qty'] + 1 ?>" aria-label="+">＋</button>
                                </form>
                            </div>
                            <div class="cart-item-total">
                                <strong><?= e(format_price((int) $l['line_total'], (string) $g['currency'])) ?></strong>
                                <form method="post" action="<?= e(url('/panier/modifier')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="slug" value="<?= e($g['slug']) ?>">
                                    <input type="hidden" name="pid" value="<?= e((string) $p['public_id']) ?>">
                                    <button class="link-button btn-danger" name="qty" value="0"><?= e(t('cart.remove')) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="cart-group-foot">
                    <span class="cart-subtotal"><?= e(t('cart.subtotal')) ?> : <strong><?= e(format_price((int) $g['subtotal'], (string) $g['currency'])) ?></strong></span>
                    <form method="post" action="<?= e(url('/panier/' . $g['slug'] . '/caisse')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary">✅ <?= e(t('cart.checkout')) ?> →</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
