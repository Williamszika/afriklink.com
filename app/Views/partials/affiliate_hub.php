<?php
/**
 * Contenu du hub d'affiliation, partagé par l'espace particulier (/affiliation)
 * et l'espace vendeur (dans le shell vendeur). Aucun <head>/layout ici.
 *
 * @var string $code  @var string $link  @var int $rate
 * @var array{clicks:int,conversions:int,earnings:array<string,int>} $stats
 * @var list<array> $recent
 * @var list<array> $directory  boutiques participantes (opt-in)
 * @var ?array{boutique:array,enabled:bool,rate:int} $program  programme de SA boutique, ou null
 */
?>
<!-- 1. Lien personnel -->
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

<!-- 3. Configuration du programme (uniquement si le membre a une boutique) -->
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
<?php endif; ?>

<!-- 4. Annuaire des boutiques participantes -->
<div class="panel">
    <h2 class="panel-title"><?= icon('tag', ['size' => 18]) ?> <?= e(t('aff.directory_title')) ?></h2>
    <?php if ($directory === []): ?>
        <p class="muted"><?= e(t('aff.directory_empty')) ?></p>
    <?php else: ?>
        <p class="hint"><?= e(t('aff.directory_lead')) ?></p>
        <div class="aff-directory">
            <?php foreach ($directory as $shop): ?>
                <?php
                $slug      = (string) $shop['slug'];
                $shopRate  = (int) $shop['affiliation_rate_pct'];
                $shopLink  = $link !== '' ? $link . '?to=' . rawurlencode('/boutique/' . $slug) : url('/boutique/' . $slug);
                $logoId    = (string) ($shop['logo_public_id'] ?? '');
                $place     = place_label((string) ($shop['city'] ?? ''), (string) ($shop['country_code'] ?? ''));
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
                    <?php if ($link !== ''): ?>
                        <button type="button" class="btn btn-ghost btn-sm btn-block" data-copy="<?= e($shopLink) ?>" data-copied="✓ <?= e(t('shop.copied')) ?>"><?= icon('copy', ['size' => 15]) ?> <?= e(t('aff.directory_copy')) ?></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 5. Dernières ventes attribuées -->
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

<!-- 6. Comment ça marche -->
<div class="panel">
    <h2 class="panel-title"><?= icon('lightbulb', ['size' => 18]) ?> <?= e(t('aff.how_title')) ?></h2>
    <ul class="tips">
        <li>1️⃣ <?= e(t('aff.how_1')) ?></li>
        <li>2️⃣ <?= e(t('aff.how_2b')) ?></li>
        <li>3️⃣ <?= e(t('aff.how_3')) ?></li>
    </ul>
</div>
