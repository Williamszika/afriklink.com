<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var int $rate  @var ?array $program */
$nav = $active; // « active » sert au sidebar ; on garde un nom distinct pour l'état programme.

$keepPct  = rtrim(rtrim(number_format(affiliate_platform_keep_pct(), 1, ',', ''), '0'), ','); // « 1,5 »
$isLive   = false;
$cur      = 'EUR';
$shared   = 0;
$catalog  = 0;
if ($program !== null) {
    $shared  = (int) ($program['shared'] ?? 0);
    $catalog = (int) ($program['catalog'] ?? 0);
    $isLive  = $shared > 0;
    $cur     = (string) ($program['boutique']['currency'] ?? 'EUR');
}

// Étapes (cadrage fidèle au modèle réel : la participation se règle produit par produit).
$steps = [
    ['1', t('aff.step1_t'), t('aff.step1_d')],
    ['2', t('aff.step2_t'), t('aff.step2_d')],
    ['3', t('aff.step3_t'), t('aff.step3_d')],
];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $nav, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main saff">

        <div class="saff-topbar">
            <h1><?= e(t('aff.v_head')) ?></h1>
            <p><?= e(t('aff.vendor_lead')) ?></p>
        </div>

        <?php if ($program === null): ?>
            <div class="saff-panel"><div class="saff-empty">
                <div class="il" aria-hidden="true">🤝</div>
                <b><?= e(t('aff.no_shop')) ?></b>
                <p><a class="btn btn-gold" href="<?= e(url('/boutique/creer')) ?>" style="margin-top:.8rem"><?= e(t('shop.cta_create')) ?></a></p>
            </div></div>
        <?php else: ?>

            <!-- ÉTAT DU PROGRAMME -->
            <div class="saff-enable">
                <div class="eic" aria-hidden="true"><?= icon('users', ['size' => 26]) ?></div>
                <div class="et">
                    <h2><?= e(t('aff.enable_title')) ?>
                        <span class="saff-tag <?= $isLive ? 'on' : 'off' ?>"><?= e($isLive ? t('aff.status_on') : t('aff.status_off')) ?></span>
                    </h2>
                    <p><?= e(t('aff.enable_desc')) ?></p>
                    <?php if ($catalog > 0): ?>
                        <p class="saff-shared"><?= icon('tag', ['size' => 14]) ?> <?= e(t('aff.shared_count', ['n' => $shared, 'total' => $catalog])) ?></p>
                    <?php endif; ?>
                </div>
                <span class="saff-switch <?= $isLive ? 'on' : '' ?>" role="img"
                      aria-label="<?= e($isLive ? t('aff.status_on') : t('aff.status_off')) ?>"></span>
            </div>

            <!-- COMMENT ÇA MARCHE -->
            <div class="saff-panel">
                <h2 class="saff-sec"><span aria-hidden="true">⚙️</span> <?= e(t('aff.how_title')) ?></h2>
                <p class="saff-sub"><?= e(t('aff.how_lead')) ?></p>
                <div class="saff-steps">
                    <?php foreach ($steps as [$num, $st, $sd]): ?>
                        <div class="saff-step"><div class="num"><?= e($num) ?></div><b><?= e($st) ?></b><p><?= e($sd) ?></p></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RÉPARTITION D'UNE VENTE -->
            <div class="saff-panel">
                <h2 class="saff-sec"><span aria-hidden="true">💸</span> <?= e(t('aff.split_title')) ?></h2>
                <p class="saff-sub"><?= e(t('aff.split_lead')) ?></p>
                <div class="saff-split">
                    <div class="saff-bar" aria-hidden="true">
                        <div class="sb-you"><?= e(t('aff.split_you')) ?></div>
                        <div class="sb-af"><?= e(t('aff.split_platform', ['rate' => $keepPct])) ?></div>
                        <div class="sb-ref"><?= e(t('aff.split_ref')) ?></div>
                    </div>
                    <div class="saff-legend">
                        <div class="sl"><span class="d d-you"></span><span><b><?= e(t('aff.split_you')) ?></b> — <?= e(t('aff.split_you_d')) ?></span></div>
                        <div class="sl"><span class="d d-af"></span><span><b><?= e(t('aff.split_platform', ['rate' => $keepPct])) ?></b> — <?= e(t('aff.split_platform_d')) ?></span></div>
                        <div class="sl"><span class="d d-ref"></span><span><b><?= e(t('aff.split_ref')) ?></b> — <?= e(t('aff.split_ref_d')) ?></span></div>
                    </div>
                </div>
                <div class="saff-note">
                    <?= icon('info', ['size' => 18]) ?>
                    <span><?= e(t('aff.program_hint', ['rate' => $keepPct])) ?></span>
                </div>
            </div>

            <!-- VOS PRODUITS AFFILIÉS -->
            <div class="saff-panel">
                <div class="saff-prodcta">
                    <div class="pt">
                        <h2 class="saff-sec saff-sec--flush"><span aria-hidden="true">🛍️</span> <?= e(t('aff.products_head')) ?></h2>
                        <p class="saff-sub" style="margin-bottom:0"><?= e(t('aff.program_lead')) ?></p>
                    </div>
                    <a class="btn <?= $isLive ? 'btn-gold' : 'btn-green' ?>" href="<?= e(url('/affiliation/mes-produits')) ?>">
                        <?= icon('tag', ['size' => 16]) ?> <?= e(t('aff.program_manage')) ?> →
                    </a>
                </div>
                <?php if (!$isLive): ?>
                    <div class="saff-note saff-note--warn">
                        <?= icon('info', ['size' => 18]) ?>
                        <span><?= e(t('aff.enable_hint')) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PERFORMANCES -->
            <?php
            $pStats  = $program['stats'] ?? ['affiliates' => 0, 'sales' => 0, 'paid' => []];
            $paidStr = empty($pStats['paid'])
                ? '0'
                : implode(' · ', array_map(
                    static fn (int $c, string $c2): string => format_price_local($c, $c2),
                    $pStats['paid'],
                    array_keys($pStats['paid'])
                ));
            $hasSales = (int) ($pStats['sales'] ?? 0) > 0;
            $pTop     = $program['top'] ?? [];
            $pRecent  = $program['recent'] ?? [];
            ?>
            <div class="saff-panel">
                <h2 class="saff-sec saff-sec--flush"><span aria-hidden="true">📊</span> <?= e(t('aff.perf_title')) ?></h2>
                <div class="saff-perf">
                    <div class="saff-metric"><span class="ic" aria-hidden="true"><?= icon('users', ['size' => 17]) ?></span><b><?= (int) ($pStats['affiliates'] ?? 0) ?></b><span><?= e(t('aff.perf_affiliates')) ?></span></div>
                    <div class="saff-metric"><span class="ic" aria-hidden="true"><?= icon('bag', ['size' => 17]) ?></span><b><?= (int) ($pStats['sales'] ?? 0) ?></b><span><?= e(t('aff.perf_sales')) ?></span></div>
                    <div class="saff-metric"><span class="ic" aria-hidden="true"><?= icon('banknote', ['size' => 17]) ?></span><b><?= e($paidStr) ?></b><span><?= e(t('aff.perf_paid')) ?></span></div>
                </div>

                <?php if (!$hasSales): ?>
                    <p class="saff-perf-empty"><?= e(t('aff.perf_none')) ?></p>
                <?php else: ?>
                    <?php if ($pTop !== []): ?>
                        <div class="saff-block">
                            <h3 class="saff-h3"><?= icon('star', ['size' => 15]) ?> <?= e(t('aff.top_title')) ?></h3>
                            <ol class="saff-top">
                                <?php foreach ($pTop as $i => $tp): ?>
                                    <li class="saff-top-row">
                                        <span class="saff-rank r<?= (int) $i + 1 ?>"><?= (int) $i + 1 ?></span>
                                        <span class="saff-top-name"><?= e((string) $tp['name']) ?></span>
                                        <span class="saff-top-stats">
                                            <span class="muted"><?= (int) $tp['sales'] ?> <?= e(t('aff.top_sales')) ?></span>
                                            <strong><?= e(format_price_local((int) $tp['commission'], (string) $tp['currency'])) ?></strong>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <?php if ($pRecent !== []): ?>
                        <div class="saff-block">
                            <h3 class="saff-h3"><?= icon('list', ['size' => 15]) ?> <?= e(t('aff.recent_title')) ?></h3>
                            <div class="saff-table-wrap">
                                <table class="saff-table">
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
                                            <td><?= e(format_price_local((int) $r['amount_cents'], (string) $r['currency'])) ?></td>
                                            <td><strong><?= e(format_price_local((int) $r['commission_cents'], (string) $r['currency'])) ?></strong></td>
                                            <td><span class="saff-badge <?= $paid ? 'ok' : 'muted' ?>"><?= e($paid ? t('wallet.status.paid') : t('wallet.status.pending')) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>
