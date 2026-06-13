<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $resto  @var list<array> $orders  @var array<int,list<array>> $items_by_order  @var string $filter */
$cur = (string) $resto['currency'];
$statusBadge = static fn (string $s): string => match ($s) {
    'new' => 'badge-warn', 'confirmed' => 'badge-info', 'ready' => 'badge-violet',
    'delivered' => 'badge-ok', default => 'badge-neutral',
};
$tabs = ['new', 'confirmed', 'ready', 'delivered', 'cancelled', 'all'];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1>🧾 <?= e(t('rorder.orders_title', ['name' => (string) $resto['name']])) ?></h1>
            <p class="muted"><?= e(t('rorder.orders_sub')) ?> · <a href="<?= e(url('/restaurant/gerer')) ?>">← <?= e(t('resto.menu_title')) ?></a></p>
        </div>

        <div class="catalogue-filters" role="tablist">
            <?php foreach ($tabs as $k): ?>
                <a class="chip-filter <?= $filter === $k ? 'is-active' : '' ?>" href="<?= e(url('/restaurant/commandes?filtre=' . $k)) ?>"><?= e(t('rorder.filter.' . $k)) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($orders === []): ?>
            <div class="panel"><div class="empty-state"><p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">🧾</p><p><?= e(t('rorder.empty')) ?></p></div></div>
        <?php else: ?>
            <div class="order-rows">
                <?php foreach ($orders as $o): $st = (string) $o['status']; $ref = strtoupper(substr((string) $o['public_id'], 0, 6)); $phone = preg_replace('/\D+/', '', (string) ($o['client_phone'] ?? '')); ?>
                    <div class="panel order-row">
                        <div class="order-row-head">
                            <span class="order-ref">#<?= e($ref) ?></span>
                            <span class="badge <?= $statusBadge($st) ?>"><?= e(t('rorder.st.' . $st)) ?></span>
                            <span class="order-source"><?= e(t('resto.service.' . $o['service'])) ?></span>
                            <span class="order-date"><?= e(date('d/m/Y H:i', strtotime((string) $o['created_at']))) ?></span>
                        </div>
                        <ul class="cart-lines">
                            <?php foreach (($items_by_order[(int) $o['id']] ?? []) as $li): ?>
                                <li class="cart-line"><span><?= (int) $li['qty'] ?>× <?= e((string) $li['title']) ?></span> <strong><?= e(format_price((int) $li['line_total_cents'], $cur)) ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="order-line"><strong class="order-total"><?= e(t('rorder.total')) ?> : <?= e(format_price((int) $o['subtotal_cents'], $cur)) ?></strong></p>
                        <p class="order-client">👤 <?= e((string) $o['client_name']) ?>
                            <?php if ($phone !== ''): ?> · <a href="https://wa.me/<?= e($phone) ?>" target="_blank" rel="noopener">💬 <?= e((string) $o['client_phone']) ?></a><?php endif; ?>
                        </p>
                        <?php if (!empty($o['note'])): ?><p class="order-note">📝 <?= e((string) $o['note']) ?></p><?php endif; ?>
                        <?php if (in_array($st, ['new', 'confirmed', 'ready'], true)): ?>
                            <form method="post" action="<?= e(url('/restaurant/commandes/' . $o['public_id'] . '/statut')) ?>" class="order-actions">
                                <?= csrf_field() ?>
                                <input type="hidden" name="retour" value="<?= e($filter) ?>">
                                <?php if ($st === 'new'): ?>
                                    <button class="btn btn-primary btn-sm" name="action" value="confirm">✅ <?= e(t('rorder.act.confirm')) ?></button>
                                <?php elseif ($st === 'confirmed'): ?>
                                    <button class="btn btn-primary btn-sm" name="action" value="ready">🍽️ <?= e(t('rorder.act.ready')) ?></button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" name="action" value="deliver">🏁 <?= e(t('rorder.act.deliver')) ?></button>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm btn-danger" name="action" value="cancel" data-confirm="<?= e(t('order.cancel_confirm')) ?>"><?= e(t('rorder.act.cancel')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
