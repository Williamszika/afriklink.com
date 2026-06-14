<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $boutique  @var array $register  @var ?array $session  @var list<array> $movements
 *  @var int $expected  @var list<array> $sessions */
$cur = (string) $boutique['currency'];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1>🧾 <?= e(t('pos.title')) ?></h1>
            <p class="muted"><?= e(t('pos.lead')) ?> · <strong><?= e((string) $register['name']) ?></strong></p>
        </div>

        <?php if ($session === null): ?>
            <!-- Aucune session ouverte : ouverture avec fond de caisse -->
            <div class="panel pos-open">
                <h2 class="panel-title">🔓 <?= e(t('pos.open_title')) ?></h2>
                <p class="muted"><?= e(t('pos.open_hint')) ?></p>
                <form method="post" action="<?= e(url('/vendeur/point-de-vente/ouvrir')) ?>">
                    <?= csrf_field() ?>
                    <label for="pos-float"><?= e(t('pos.f.float', ['cur' => $cur])) ?></label>
                    <input type="text" id="pos-float" name="opening_float" inputmode="decimal" value="0" required>
                    <button type="submit" class="btn btn-primary"><?= e(t('pos.open_btn')) ?></button>
                </form>
            </div>
        <?php else: ?>
            <!-- Session ouverte : vente rapide + récap + mouvements + clôture -->
            <div class="panel pos-sale">
                <h2 class="panel-title">🛒 <?= e(t('pos.sale_title')) ?></h2>
                <?php if (empty($units)): ?>
                    <p class="muted"><?= e(t('shop.products_empty')) ?></p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/vendeur/point-de-vente/vente')) ?>">
                        <?= csrf_field() ?>
                        <label for="pos-unit"><?= e(t('pos.f.unit')) ?></label>
                        <select id="pos-unit" name="unit" required>
                            <?php foreach ($units as $u): $out = $u['stock'] !== null && (int) $u['stock'] <= 0; ?>
                                <option value="<?= e((string) $u['id']) ?>" <?= $out ? 'disabled' : '' ?>><?= e((string) $u['label']) ?> — <?= e(format_price((int) $u['price'], $cur)) ?><?php if ($u['stock'] !== null): ?> · <?= (int) $u['stock'] ?> <?= e(t('pos.in_stock')) ?><?php endif; ?><?php if ($out): ?> · <?= e(t('product.out_of_stock')) ?><?php endif; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="grid-2">
                            <div><label for="pos-qty"><?= e(t('pos.f.qty')) ?></label><input type="text" id="pos-qty" name="qty" inputmode="numeric" value="1" required></div>
                            <div><label for="pos-method"><?= e(t('pos.f.method')) ?></label>
                                <select id="pos-method" name="method">
                                    <option value="cash"><?= e(t('pos.pay.cash')) ?></option>
                                    <option value="card"><?= e(t('pos.pay.card')) ?></option>
                                    <option value="wave"><?= e(t('pos.pay.wave')) ?></option>
                                    <option value="orange_money"><?= e(t('pos.pay.orange_money')) ?></option>
                                    <option value="mtn_momo"><?= e(t('pos.pay.mtn_momo')) ?></option>
                                </select>
                            </div>
                        </div>
                        <label for="pos-received"><?= e(t('pos.f.received', ['cur' => $cur])) ?></label>
                        <input type="text" id="pos-received" name="received" inputmode="decimal" placeholder="0">
                        <p class="hint"><?= e(t('pos.received_hint')) ?></p>
                        <button type="submit" class="btn btn-primary btn-block">💵 <?= e(t('pos.sale_btn')) ?></button>
                    </form>
                    <p class="hint">🔁 <?= e(t('pos.shared_note')) ?></p>
                <?php endif; ?>
            </div>
            <div class="stat-grid cols-3">
                <div class="stat-card"><div class="num afk-mono"><?= e(format_price((int) $session['opening_float_cents'], $cur)) ?></div><div class="lbl"><?= e(t('pos.k.float')) ?></div></div>
                <div class="stat-card"><div class="num afk-mono"><?= e(format_price($expected, $cur)) ?></div><div class="lbl"><?= e(t('pos.k.expected')) ?></div></div>
                <div class="stat-card"><div class="num afk-mono"><?= e(date('H:i', strtotime((string) $session['opened_at']))) ?></div><div class="lbl"><?= e(t('pos.k.opened')) ?></div></div>
            </div>

            <?php if ($summary !== null): ?>
            <div class="panel">
                <div class="panel-title-row">
                    <h2 class="panel-title">📊 <?= e(t('pos.report_x')) ?></h2>
                    <a class="afk-link-all" href="<?= e(url('/vendeur/point-de-vente/session/' . $session['public_id'] . '/export')) ?>"><?= e(t('pos.export_csv')) ?> ↓</a>
                </div>
                <p class="pos-report-total"><?= e(t('pos.r.sales', ['n' => (int) $summary['count']])) ?> · <strong class="afk-mono"><?= e(format_price((int) $summary['total'], $cur)) ?></strong></p>
                <?php if (!empty($summary['tenders'])): ?>
                    <ul class="pos-tender-list">
                        <?php foreach ($summary['tenders'] as $method => $net): ?>
                            <li><span class="muted"><?= e(t('pos.pay.' . $method)) ?></span> <strong class="afk-mono"><?= e(format_price((int) $net, $cur)) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="panel">
                <h2 class="panel-title">💵 <?= e(t('pos.cash_title')) ?></h2>
                <form method="post" action="<?= e(url('/vendeur/point-de-vente/mouvement')) ?>" class="pos-move-form">
                    <?= csrf_field() ?>
                    <div class="grid-2">
                        <div>
                            <label for="pos-mtype"><?= e(t('pos.f.type')) ?></label>
                            <select id="pos-mtype" name="type">
                                <option value="paid_in"><?= e(t('pos.type.in')) ?></option>
                                <option value="paid_out"><?= e(t('pos.type.out')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="pos-mamount"><?= e(t('pos.f.amount', ['cur' => $cur])) ?></label>
                            <input type="text" id="pos-mamount" name="amount" inputmode="decimal" placeholder="0" required>
                        </div>
                    </div>
                    <label for="pos-mreason"><?= e(t('pos.f.reason')) ?></label>
                    <input type="text" id="pos-mreason" name="reason" maxlength="160" placeholder="<?= e(t('pos.f.reason_ph')) ?>" required>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('pos.move_btn')) ?></button>
                </form>
                <?php if (!empty($movements)): ?>
                    <ul class="pos-move-list">
                        <?php foreach ($movements as $m): $in = $m['type'] === 'paid_in'; ?>
                            <li class="pos-move-item">
                                <span class="pos-move-sign <?= $in ? 'is-in' : 'is-out' ?>"><?= $in ? '＋' : '−' ?> <?= e(format_price((int) $m['amount_cents'], $cur)) ?></span>
                                <span class="muted"><?= e((string) $m['reason']) ?> · <?= e(date('H:i', strtotime((string) $m['created_at']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="panel pos-close">
                <h2 class="panel-title">🔒 <?= e(t('pos.close_title')) ?></h2>
                <p class="muted"><?= e(t('pos.close_hint', ['expected' => format_price($expected, $cur)])) ?></p>
                <form method="post" action="<?= e(url('/vendeur/point-de-vente/fermer')) ?>">
                    <?= csrf_field() ?>
                    <label for="pos-counted"><?= e(t('pos.f.counted', ['cur' => $cur])) ?></label>
                    <input type="text" id="pos-counted" name="counted_cash" inputmode="decimal" value="0" required>
                    <button type="submit" class="btn btn-primary" data-confirm="<?= e(t('pos.close_confirm')) ?>"><?= e(t('pos.close_btn')) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <?php $closed = array_values(array_filter($sessions, static fn (array $s): bool => ($s['status'] ?? '') === 'closed')); ?>
        <?php if (!empty($closed)): ?>
            <div class="panel">
                <h2 class="panel-title">📒 <?= e(t('pos.history')) ?></h2>
                <table>
                    <thead><tr><th><?= e(t('pos.h.date')) ?></th><th><?= e(t('pos.h.expected')) ?></th><th><?= e(t('pos.h.counted')) ?></th><th><?= e(t('pos.h.variance')) ?></th><th><?= e(t('pos.h.report')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($closed as $s): $v = (int) ($s['variance_cents'] ?? 0); ?>
                            <tr>
                                <td><?= e(date('d/m H:i', strtotime((string) $s['closed_at']))) ?></td>
                                <td class="afk-mono"><?= e(format_price((int) ($s['expected_cash_cents'] ?? 0), $cur)) ?></td>
                                <td class="afk-mono"><?= e(format_price((int) ($s['counted_cash_cents'] ?? 0), $cur)) ?></td>
                                <td class="afk-mono pos-var <?= $v === 0 ? 'is-ok' : ($v > 0 ? 'is-over' : 'is-under') ?>"><?= ($v > 0 ? '+' : '') . e(format_price($v, $cur)) ?></td>
                                <td><a href="<?= e(url('/vendeur/point-de-vente/session/' . $s['public_id'] . '/export')) ?>">Z ↓</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
