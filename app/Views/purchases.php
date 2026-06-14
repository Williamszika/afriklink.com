<?php
/** @var array $user  @var list<array> $orders */
?>
<section class="container narrow">
    <div class="page-head">
        <h1>🛒 <?= e(t('purchases.title')) ?></h1>
        <p class="muted"><?= e(t('purchases.subtitle')) ?></p>
    </div>

    <?php if ($orders === []): ?>
        <div class="panel empty-state">
            <p><?= e(t('dash.buys_empty')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('/explorer')) ?>"><?= e(t('purchases.explore')) ?></a>
        </div>
    <?php else: ?>
        <div class="panel">
            <ul class="order-list">
                <?php foreach ($orders as $o): ?>
                    <li class="order-row">
                        <a class="order-row-main" href="<?= e(url('/boutique/commande/' . $o['public_id'])) ?>">
                            <span class="order-shop"><?= e((string) $o['boutique_name']) ?>
                                <span class="muted">· #<?= e(strtoupper(substr((string) $o['public_id'], 0, 6))) ?></span></span>
                            <span class="muted order-meta"><?= e(date('d/m/Y', strtotime((string) $o['created_at']))) ?> · <?= e(format_price((int) $o['total_cents'], (string) $o['currency'])) ?></span>
                        </a>
                        <span class="order-status order-status--<?= e((string) $o['status']) ?>"><?= e(t('order.status.' . $o['status'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
