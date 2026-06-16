<?php
/**
 * Contenu du hub d'affiliation, partagé par l'espace particulier (/affiliation)
 * et l'espace vendeur (dans le shell vendeur). Aucun <head>/layout ici.
 *
 * Deux rôles distincts :
 *  - VENDEUR (professionnel) → voit UNIQUEMENT la configuration de son programme.
 *  - PARTICULIER ($can_earn) → voit son lien PERSONNEL (unique), ses gains, le
 *    portefeuille et l'annuaire à partager. Le lien n'est jamais montré au vendeur.
 *
 * @var string $code  @var string $link  @var int $rate  @var bool $can_earn
 * @var array{clicks:int,conversions:int,earnings:array<string,int>} $stats
 * @var list<array> $recent
 * @var list<array> $directory  boutiques participantes (opt-in)
 * @var ?array{boutique:array,enabled:bool,rate:int} $program  programme de SA boutique, ou null
 * @var list<array> $dir_products  produits participants  @var array<int,string> $dir_mains
 * @var ?array{balance:int,currency:string,threshold:int,can:bool,withdrawals:list<array>} $wallet
 */
$can_earn     = $can_earn ?? true;
$stats        = $stats ?? ['clicks' => 0, 'conversions' => 0, 'earnings' => []];
$recent       = $recent ?? [];
$directory    = $directory ?? [];
$dir_products = $dir_products ?? [];
$dir_mains    = $dir_mains ?? [];
$wallet       = $wallet ?? null;
?>

<!-- A. Configuration du programme — réservée au VENDEUR qui possède une boutique -->
<?php if ($program !== null): ?>
    <div class="panel">
        <h2 class="panel-title"><?= icon('store', ['size' => 18]) ?> <?= e(t('aff.program_title')) ?>
            <span class="badge <?= $program['enabled'] ? 'badge-ok' : 'badge-muted' ?>"><?= e($program['enabled'] ? t('aff.program_on') : t('aff.program_off')) ?></span>
        </h2>
        <p class="muted"><?= e(t('aff.program_lead')) ?></p>
        <form method="post" action="<?= e(url('/affiliation/programme')) ?>" class="aff-program-form">
            <?= csrf_field() ?>
            <label class="switch-row">
                <input type="checkbox" name="enabled" value="1" <?= $program['enabled'] ? 'checked' : '' ?>>
                <span><?= e(t('aff.program_enable')) ?></span>
            </label>
            <label class="aff-rate-field">
                <span><?= e(t('aff.program_rate')) ?></span>
                <input type="number" name="rate" min="1" max="30" step="1" value="<?= (int) $program['rate'] ?>" inputmode="numeric">
            </label>
            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('aff.program_save')) ?></button>
        </form>
        <p class="hint"><?= e(t('aff.program_hint')) ?></p>
    </div>

    <?php /* ---- Performance du programme (côté vendeur) ---- */ ?>
    <?php $pStats = $program['stats'] ?? null; $pRecent = $program['recent'] ?? []; ?>
    <?php if ($pStats !== null): ?>
        <?php $paidStr = empty($pStats['paid']) ? '0' : implode(' · ', array_map(static fn (int $c, string $cur): string => format_price($c, $cur), $pStats['paid'], array_keys($pStats['paid']))); ?>
        <div class="panel aff-perf-panel">
            <h2 class="panel-title"><?= icon('chart', ['size' => 18]) ?> <?= e(t('aff.perf_title')) ?></h2>
            <div class="perf-tiles">
                <div class="perf-tile perf-tile--green">
                    <span class="perf-coin"><?= icon('users', ['size' => 22]) ?></span>
                    <span class="perf-num"><?= (int) $pStats['affiliates'] ?></span>
                    <span class="perf-lbl"><?= e(t('aff.perf_affiliates')) ?></span>
                </div>
                <div class="perf-tile perf-tile--teal">
                    <span class="perf-coin"><?= icon('bag', ['size' => 22]) ?></span>
                    <span class="perf-num"><?= (int) $pStats['sales'] ?></span>
                    <span class="perf-lbl"><?= e(t('aff.perf_sales')) ?></span>
                </div>
                <div class="perf-tile perf-tile--gold">
                    <span class="perf-coin"><?= icon('banknote', ['size' => 22]) ?></span>
                    <span class="perf-num"><?= e($paidStr) ?></span>
                    <span class="perf-lbl"><?= e(t('aff.perf_paid')) ?></span>
                </div>
            </div>
            <?php if ($pRecent === []): ?>
                <div class="perf-empty">
                    <span class="perf-empty-coin"><?= icon('sparkle', ['size' => 30]) ?></span>
                    <p><?= e(t('aff.perf_none')) ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th><?= e(t('aff.col_date')) ?></th>
                            <th><?= e(t('aff.col_amount')) ?></th>
                            <th><?= e(t('aff.col_commission')) ?></th>
                            <th><?= e(t('aff.col_status')) ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($pRecent as $r): $paid = !empty($r['paid_out_at']); ?>
                            <tr>
                                <td><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?></td>
                                <td><?= e(format_price((int) $r['amount_cents'], (string) $r['currency'])) ?></td>
                                <td><strong><?= e(format_price((int) $r['commission_cents'], (string) $r['currency'])) ?></strong></td>
                                <td><span class="badge <?= $paid ? 'badge-ok' : 'badge-muted' ?>"><?= e($paid ? t('wallet.status.paid') : t('wallet.status.pending')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($can_earn): /* ===== Espace PARTICULIER : lien personnel + gains ===== */ ?>

    <!-- 1. Lien personnel (UNIQUE par particulier) -->
    <div class="panel">
        <h2 class="panel-title"><?= icon('link', ['size' => 18]) ?> <?= e(t('aff.your_link')) ?></h2>
        <?php if ($link !== ''): ?>
            <div class="aff-link-row">
                <input type="text" class="aff-link-input" value="<?= e($link) ?>" readonly aria-label="<?= e(t('aff.your_link')) ?>">
                <button type="button" class="btn btn-primary btn-sm" data-copy="<?= e($link) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 15]) ?> <?= e(t('aff.copy')) ?></button>
            </div>
            <p class="hint"><?= e(t('aff.target_hint')) ?></p>
        <?php else: ?>
            <p class="muted"><?= e(t('aff.no_code')) ?></p>
        <?php endif; ?>
    </div>

    <!-- 2. Statistiques -->
    <div class="stat-grid cols-3">
        <div class="stat-card">
            <div class="num"><?= icon('pointer', ['size' => 18]) ?> <?= (int) $stats['clicks'] ?></div>
            <div class="lbl"><?= e(t('aff.clicks')) ?></div>
        </div>
        <div class="stat-card">
            <div class="num"><?= icon('bag', ['size' => 18]) ?> <?= (int) $stats['conversions'] ?></div>
            <div class="lbl"><?= e(t('aff.conversions')) ?></div>
        </div>
        <div class="stat-card">
            <div class="num"><?= icon('wallet', ['size' => 18]) ?>
                <?php if (empty($stats['earnings'])): ?>
                    0
                <?php else: ?>
                    <?= e(implode(' · ', array_map(static fn (int $c, string $cur): string => format_price($c, $cur), $stats['earnings'], array_keys($stats['earnings'])))) ?>
                <?php endif; ?>
            </div>
            <div class="lbl"><?= e(t('aff.earnings')) ?></div>
        </div>
    </div>

    <!-- 2b. Portefeuille : solde + retrait -->
    <?php if ($wallet !== null): ?>
        <div class="panel">
            <h2 class="panel-title"><?= icon('wallet', ['size' => 18]) ?> <?= e(t('aff.wallet_title')) ?></h2>
            <p class="muted"><?= e(t('aff.wallet_lead')) ?></p>
            <div class="aff-wallet">
                <div class="aff-wallet-balance">
                    <span class="lbl"><?= e(t('aff.wallet_balance')) ?></span>
                    <strong class="aff-wallet-amount"><?= e(format_price((int) $wallet['balance'], (string) $wallet['currency'])) ?></strong>
                </div>
                <?php if (!empty($wallet['can'])): ?>
                    <form method="post" action="<?= e(url('/affiliation/retrait')) ?>" class="aff-wd-form">
                        <?= csrf_field() ?>
                        <label class="aff-wd-field">
                            <span><?= e(t('wallet.method')) ?></span>
                            <select name="method">
                                <option value="mobile_money"><?= e(t('wallet.method.mobile_money')) ?></option>
                                <option value="bank"><?= e(t('wallet.method.bank')) ?></option>
                            </select>
                        </label>
                        <label class="aff-wd-field aff-wd-dest">
                            <span><?= e(t('wallet.destination')) ?></span>
                            <input type="text" name="destination" maxlength="160" required placeholder="<?= e(t('wallet.destination_ph')) ?>">
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm"><?= e(t('wallet.request')) ?></button>
                    </form>
                <?php else: ?>
                    <p class="hint"><?= e(t('aff.wallet_threshold', ['min' => format_price((int) $wallet['threshold'], (string) $wallet['currency'])])) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($wallet['withdrawals'])): ?>
                <ul class="aff-wd-list">
                    <?php foreach (array_slice($wallet['withdrawals'], 0, 3) as $w): ?>
                        <li>
                            <span><?= e(format_price((int) $w['amount_cents'], (string) $w['currency'])) ?></span>
                            <span class="badge <?= ($w['status'] ?? '') === 'paid' ? 'badge-ok' : 'badge-muted' ?>"><?= e(t('wallet.status.' . ($w['status'] ?? 'pending'))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 3. Annuaire des boutiques participantes -->
    <div class="panel">
        <h2 class="panel-title"><?= icon('tag', ['size' => 18]) ?> <?= e(t('aff.directory_title')) ?></h2>
        <?php if ($directory === []): ?>
            <p class="muted"><?= e(t('aff.directory_empty')) ?></p>
        <?php else: ?>
            <p class="hint"><?= e(t('aff.directory_lead')) ?></p>
            <div class="aff-directory">
                <?php foreach ($directory as $shop): ?>
                    <?php
                    $slug     = (string) $shop['slug'];
                    $shopRate = (int) $shop['affiliation_rate_pct'];
                    $shopLink = $link !== '' ? $link . '?to=' . rawurlencode('/boutique/' . $slug) : url('/boutique/' . $slug);
                    $logoId   = (string) ($shop['logo_public_id'] ?? '');
                    $place    = place_label((string) ($shop['city'] ?? ''), (string) ($shop['country_code'] ?? ''));
                    ?>
                    <div class="aff-shop">
                        <a class="aff-shop-head" href="<?= e(url('/boutique/' . $slug)) ?>" target="_blank" rel="noopener">
                            <?php if ($logoId !== ''): ?>
                                <img class="aff-shop-logo" src="<?= e(\App\Services\CloudinaryService::imageUrl($logoId, 96, 96)) ?>" alt="" width="44" height="44">
                            <?php else: ?>
                                <span class="aff-shop-logo aff-shop-logo--empty" aria-hidden="true">🛍️</span>
                            <?php endif; ?>
                            <span class="aff-shop-id">
                                <span class="aff-shop-name"><?= e((string) $shop['name']) ?></span>
                                <?php if ($place !== ''): ?><span class="aff-shop-place"><?= e($place) ?></span><?php endif; ?>
                            </span>
                            <span class="badge badge-ok aff-shop-rate"><?= e(t('aff.rate_badge', ['rate' => $shopRate])) ?></span>
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm btn-block" data-copy="<?= e($shopLink) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 15]) ?> <?= e(t('aff.directory_copy')) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3b. Produits participants à partager -->
    <?php if ($dir_products !== []): ?>
        <div class="panel">
            <h2 class="panel-title"><?= icon('bag', ['size' => 18]) ?> <?= e(t('aff.products_title')) ?></h2>
            <p class="hint"><?= e(t('aff.products_lead')) ?></p>
            <div class="aff-directory aff-products">
                <?php foreach ($dir_products as $p): ?>
                    <?php
                    $pPath = '/boutique/' . (string) $p['boutique_slug'] . '/p/' . (string) $p['public_id'];
                    $pLink = $link !== '' ? $link . '?to=' . rawurlencode($pPath) : url($pPath);
                    $pImg  = $dir_mains[(int) $p['id']] ?? null;
                    ?>
                    <div class="aff-shop aff-product">
                        <a class="aff-product-head" href="<?= e(url($pPath)) ?>" target="_blank" rel="noopener">
                            <span class="aff-product-img">
                                <?php if ($pImg !== null): ?>
                                    <img src="<?= e(\App\Services\CloudinaryService::imageUrl($pImg, 200, 200)) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="listing-thumb-empty" aria-hidden="true"><?= icon('package') ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="aff-shop-id">
                                <span class="aff-shop-name"><?= e((string) $p['name']) ?></span>
                                <span class="aff-shop-place"><?= e((string) $p['boutique_name']) ?> · <?= e(format_price((int) $p['price_cents'], (string) $p['currency'])) ?></span>
                            </span>
                            <span class="badge badge-ok aff-shop-rate"><?= e(t('aff.rate_badge', ['rate' => (int) $p['affiliation_rate_pct']])) ?></span>
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm btn-block" data-copy="<?= e($pLink) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 15]) ?> <?= e(t('aff.directory_copy')) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. Dernières ventes attribuées -->
    <div class="panel">
        <h2 class="panel-title"><?= icon('list', ['size' => 18]) ?> <?= e(t('aff.recent_title')) ?></h2>
        <?php if ($recent === []): ?>
            <p class="muted"><?= e(t('aff.none_yet')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr>
                        <th><?= e(t('aff.col_date')) ?></th>
                        <th><?= e(t('aff.col_order')) ?></th>
                        <th><?= e(t('aff.col_amount')) ?></th>
                        <th><?= e(t('aff.col_commission')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?></td>
                            <td><code><?= e(strtoupper(substr((string) $r['order_public_id'], 0, 8))) ?></code></td>
                            <td><?= e(format_price((int) $r['amount_cents'], (string) $r['currency'])) ?></td>
                            <td><strong><?= e(format_price((int) $r['commission_cents'], (string) $r['currency'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 5. Comment ça marche -->
    <div class="panel">
        <h2 class="panel-title"><?= icon('lightbulb', ['size' => 18]) ?> <?= e(t('aff.how_title')) ?></h2>
        <ul class="tips">
            <li>1️⃣ <?= e(t('aff.how_1')) ?></li>
            <li>2️⃣ <?= e(t('aff.how_2b')) ?></li>
            <li>3️⃣ <?= e(t('aff.how_3')) ?></li>
        </ul>
    </div>

<?php endif; /* fin espace particulier */ ?>
