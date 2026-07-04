<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $products  @var array<int,string> $mains
 *  @var array<int,array> $campaigns  @var string $placement  @var array<int,int> $packages
 *  @var string $currency  @var string $billing  @var int $wallet_cents */
use App\Models\Product;
use App\Services\CloudinaryService;

$cur    = (string) ($boutique['currency'] ?? $currency);
$days0  = array_key_first($packages) ?: 7;
$hasProducts = $products !== [];

// Performances agrégées (somme des campagnes actives).
$totImp = 0; $totClk = 0; $activeN = 0;
foreach ($campaigns as $c) { $totImp += (int) ($c['impressions'] ?? 0); $totClk += (int) ($c['clicks'] ?? 0); $activeN++; }
$ctr = $totImp > 0 ? round($totClk / $totImp * 100, 1) : null;

// Bénéfices communs à toutes les formules (le placement est le même ; seule la durée change).
$benefits = [
    ['📈', t('ads.benefit_top_t'), t('ads.benefit_top_d')],
    ['🏷️', t('ads.benefit_badge_t'), t('ads.benefit_badge_d')],
    ['🖼️', t('ads.benefit_rotation_t'), t('ads.benefit_rotation_d')],
    ['📊', t('ads.benefit_stats_t'), t('ads.benefit_stats_d')],
];
$tierNames = [t('ads.tier_discover'), t('ads.tier_popular'), t('ads.tier_premium')];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main sads">

        <div class="sads-topbar">
            <h1><?= e(t('ads.title')) ?></h1>
            <p><?= e(t('ads.lead2')) ?></p>
        </div>

        <?php if ($boutique === null): ?>
            <div class="sads-panel"><div class="sads-empty"><div class="il" aria-hidden="true">📣</div><b><?= e(t('ads.no_shop')) ?></b><p><a class="btn btn-gold" href="<?= e(url('/boutique/creer')) ?>" style="margin-top:.8rem"><?= e(t('shop.cta_create')) ?></a></p></div></div>
        <?php else: ?>

            <!-- HERO SPOTLIGHT -->
            <div class="sads-hero">
                <span class="sads-eyebrow"><?= e(t('ads.hero_eyebrow')) ?></span>
                <h2><?= e(t('ads.hero_title')) ?></h2>
                <p><?= e(t('ads.hero_desc')) ?></p>
                <div class="sads-benefits">
                    <?php foreach ($benefits as [$em, $bt, $bd]): ?>
                        <div class="sads-benefit"><span class="ic" aria-hidden="true"><?= $em ?></span><div><b><?= e($bt) ?></b><span><?= e($bd) ?></span></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="sads-preview">
                    <span class="sads-badge-spon"><?= e(t('ads.badge')) ?></span>
                    <span class="sads-pv-thumb" aria-hidden="true"><?= icon('package', ['size' => 18]) ?></span>
                    <div><div class="sads-pv-t"><?= e(t('ads.preview_t')) ?></div><div class="sads-pv-s"><?= e(t('ads.preview_s')) ?></div></div>
                </div>
            </div>

            <!-- PRÉREQUIS -->
            <?php if (!$hasProducts): ?>
                <div class="sads-prereq">
                    <span class="pic" aria-hidden="true"><?= icon('package', ['size' => 20]) ?></span>
                    <div class="pt"><b><?= e(t('order.prereq')) ?></b><p><?= e(t('ads.prereq_d')) ?></p></div>
                    <a class="btn btn-gold btn-sm" href="<?= e(url('/boutique/produits/nouveau')) ?>"><?= icon('plus', ['size' => 15]) ?> <?= e(t('product.add')) ?></a>
                </div>
            <?php endif; ?>

            <!-- FORMULES -->
            <h2 class="sads-sec"><span aria-hidden="true">🎯</span> <?= e(t('ads.formulas_title')) ?></h2>
            <div class="sads-pkgs">
                <?php $i = 0; foreach ($packages as $d => $price): $featured = $i === 1; ?>
                    <div class="sads-pkg<?= $featured ? ' is-featured' : '' ?>">
                        <?php if ($featured): ?><span class="sads-ribbon"><?= e(t('ads.tier_popular')) ?></span><?php endif; ?>
                        <div class="sads-pname"><?= e($tierNames[$i] ?? t('ads.days', ['days' => (int) $d])) ?></div>
                        <div class="sads-price"><?= e($price !== null ? format_price_local((int) $price, $cur) : '—') ?></div>
                        <div class="sads-dur"><?= e(t('ads.dur', ['days' => (int) $d])) ?></div>
                        <ul>
                            <?php foreach ($benefits as [$em, $bt]): ?>
                                <li><?= icon('check', ['size' => 15]) ?> <?= e($bt) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($hasProducts): ?>
                            <a class="btn <?= $featured ? 'btn-gold' : 'btn-ghost' ?>" href="#promouvoir"><?= e(t('ads.choose')) ?></a>
                        <?php else: ?>
                            <button type="button" class="btn btn-ghost" disabled><?= e(t('ads.choose')) ?></button>
                        <?php endif; ?>
                    </div>
                <?php $i++; endforeach; ?>
            </div>

            <!-- PROMOUVOIR UN PRODUIT (flux réel préservé) -->
            <?php if ($hasProducts): ?>
                <h2 class="sads-sec" id="promouvoir"><span aria-hidden="true">✨</span> <?= e(t('ads.promote_section')) ?></h2>
                <?php if ($billing === 'wallet'): ?>
                    <p class="sads-billing"><?= icon('wallet', ['size' => 15]) ?> <?= e(t('ads.billing_wallet', ['amount' => format_price($wallet_cents, $cur)])) ?></p>
                <?php else: ?>
                    <p class="sads-billing"><?= icon('info', ['size' => 15]) ?> <?= e(t('ads.billing_sim')) ?></p>
                <?php endif; ?>
                <div class="sads-prods">
                    <?php foreach ($products as $p): $main = $mains[(int) $p['id']] ?? null; $camp = $campaigns[(int) $p['id']] ?? null; ?>
                        <div class="sads-prod">
                            <div class="sads-prod-thumb">
                                <?php if ($main !== null): ?><img src="<?= e(CloudinaryService::imageUrl($main, 140, 140)) ?>" alt="" loading="lazy" width="64" height="64"><?php else: ?><span aria-hidden="true"><?= icon('package', ['size' => 22]) ?></span><?php endif; ?>
                            </div>
                            <div class="sads-prod-body">
                                <p class="sads-prod-title"><?= e((string) $p['name']) ?><?php if ($camp !== null): ?> <span class="sads-badge-spon"><?= e(t('ads.badge')) ?></span><?php endif; ?></p>
                                <?php if ($camp !== null): ?>
                                    <?php $imp = (int) ($camp['impressions'] ?? 0); $clk = (int) ($camp['clicks'] ?? 0); $c2 = $imp > 0 ? round($clk / $imp * 100, 1) : 0.0; ?>
                                    <p class="sads-prod-meta"><?= e(t('ads.until', ['date' => date('d/m/Y', strtotime((string) $camp['ends_at']))])) ?></p>
                                    <div class="sads-cstats">
                                        <span><b><?= number_format((float) $imp, 0, ',', ' ') ?></b> <?= e(t('ads.impressions')) ?></span>
                                        <span><b><?= number_format((float) $clk, 0, ',', ' ') ?></b> <?= e(t('ads.clicks')) ?></span>
                                        <span><b><?= e(number_format($c2, 1, ',', ' ')) ?> %</b> CTR</span>
                                    </div>
                                    <form method="post" action="<?= e(url('/vendeur/publicite/' . $p['public_id'] . '/promouvoir')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" name="action" value="stop"><?= e(t('ads.stop')) ?></button>
                                    </form>
                                <?php else: ?>
                                    <p class="sads-prod-meta"><strong><?= e(format_price_local((int) $p['price_cents'], $cur)) ?></strong></p>
                                    <form method="post" action="<?= e(url('/vendeur/publicite/' . $p['public_id'] . '/promouvoir')) ?>" class="sads-buy" data-submit-once>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="promote">
                                        <input type="hidden" name="placement" value="<?= e($placement) ?>">
                                        <div class="sads-buy-tiers">
                                            <?php foreach ($packages as $d => $price): ?>
                                                <label class="sads-buy-tier">
                                                    <input type="radio" name="days" value="<?= (int) $d ?>" <?= (int) $d === (int) $days0 ? 'checked' : '' ?>>
                                                    <span class="sads-buy-box"><strong><?= e(t('ads.days', ['days' => (int) $d])) ?></strong><span><?= e($price !== null ? format_price_local((int) $price, $cur) : '—') ?></span></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="btn btn-green btn-sm" type="submit"><?= icon('star', ['size' => 15]) ?> <?= e(t('ads.promote_btn')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- PERFORMANCES -->
            <div class="sads-panel">
                <h2 class="sads-sec sads-sec--flush"><span aria-hidden="true">📊</span> <?= e(t('ads.perf_title')) ?></h2>
                <div class="sads-perf">
                    <div class="sads-metric"><span class="ic" aria-hidden="true"><?= icon('eye', ['size' => 17]) ?></span><b><?= number_format((float) $totImp, 0, ',', ' ') ?></b><span><?= e(t('ads.impressions')) ?></span></div>
                    <div class="sads-metric"><span class="ic" aria-hidden="true"><?= icon('star', ['size' => 17]) ?></span><b><?= number_format((float) $totClk, 0, ',', ' ') ?></b><span><?= e(t('ads.clicks')) ?></span></div>
                    <div class="sads-metric"><span class="ic" aria-hidden="true"><?= icon('megaphone', ['size' => 17]) ?></span><b><?= $ctr !== null ? e(number_format($ctr, 1, ',', ' ')) . ' %' : '—' ?></b><span><?= e(t('ads.ctr')) ?></span></div>
                </div>
                <?php if ($activeN === 0): ?><p class="sads-perf-empty"><?= e(t('ads.perf_empty')) ?></p><?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>
