<?php
/** @var array $boutique  @var list<array> $products  @var array<int,string> $mains  @var string $max_pct */
$cur    = (string) ($boutique['currency'] ?? 'EUR');
$maxBps = affiliate_max_bps();
?>
<section class="aff-vp">
    <div class="aff-hero">
        <h1><?= icon('tag', ['size' => 26]) ?> <?= e(t('aff.vp_title')) ?></h1>
        <p class="lead"><?= e(t('aff.vp_lead', ['max' => $max_pct])) ?></p>
    </div>

    <div class="panel">
        <?php if ($products === []): ?>
            <p class="muted"><?= e(t('aff.vp_empty')) ?></p>
            <p><a class="btn btn-primary" href="<?= e(url('/boutique/gerer')) ?>"><?= e(t('aff.vp_add_products')) ?></a></p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/affiliation/mes-produits')) ?>">
                <?= csrf_field() ?>
                <div class="table-wrap">
                    <table class="data-table aff-vp-table">
                        <thead><tr>
                            <th><?= e(t('aff.vp_product')) ?></th>
                            <th class="num"><?= e(t('aff.vp_price')) ?></th>
                            <th><?= e(t('aff.vp_enable')) ?></th>
                            <th class="num"><?= e(t('aff.vp_rate')) ?></th>
                            <th class="num"><?= e(t('aff.vp_earn')) ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $pid   = (int) $p['id'];
                            $bps   = (int) $p['affiliate_rate_bps'];
                            $isOn  = (int) $p['affiliate_enabled'] === 1;
                            $val   = $bps > 0 ? rtrim(rtrim(number_format($bps / 100, 1, '.', ''), '0'), '.') : $max_pct;
                            $earn  = affiliate_line_commission_cents((int) $p['price_cents'], $bps > 0 ? $bps : $maxBps);
                            $img   = $mains[$pid] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <span class="aff-vp-prod">
                                        <span class="aff-vp-thumb">
                                            <?php if ($img !== null): ?>
                                                <img src="<?= e(\App\Services\CloudinaryService::imageUrl($img, 80, 80)) ?>" alt="" width="38" height="38">
                                            <?php else: ?>
                                                <?= icon('package', ['size' => 18]) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= e((string) $p['name']) ?></span>
                                    </span>
                                </td>
                                <td class="num"><?= e(format_price((int) $p['price_cents'], $cur)) ?></td>
                                <td>
                                    <label class="switch-row">
                                        <input type="checkbox" name="enabled[<?= $pid ?>]" value="1" <?= $isOn ? 'checked' : '' ?>>
                                    </label>
                                </td>
                                <td class="num">
                                    <input class="aff-vp-rate" type="number" name="rate[<?= $pid ?>]" min="0" max="<?= e($max_pct) ?>" step="0.1" value="<?= e($val) ?>" inputmode="decimal"> %
                                </td>
                                <td class="num"><?= e(format_price($earn, $cur)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="hint"><?= e(t('aff.vp_hint', ['max' => $max_pct])) ?></p>
                <button type="submit" class="btn btn-primary"><?= e(t('aff.vp_save')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <p><a class="btn btn-ghost" href="<?= e(url('/affiliation')) ?>">← <?= e(t('aff.vp_back')) ?></a></p>
</section>
