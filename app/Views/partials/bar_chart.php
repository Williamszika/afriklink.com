<?php
/**
 * Graphique en barres — rendu CSS pur (hauteurs en %), compatible CSP stricte
 * (aucun JS, aucune lib externe ; les attributs `style` inline sont autorisés
 * par style-src 'unsafe-inline'). Survol = valeur détaillée (title).
 * @var list<array{value:int,label:string,title:string}> $bars
 * @var string $cur  @var ?int $height
 */
$bars = $bars ?? [];
$h    = (int) ($height ?? 130);
$max  = 1;
foreach ($bars as $b) {
    $max = max($max, (int) $b['value']);
}
?>
<div class="chart" style="--chart-h:<?= $h ?>px">
    <?php foreach ($bars as $b): $v = (int) $b['value']; $pct = $v > 0 ? max(3, (int) round($v / $max * 100)) : 0; ?>
        <div class="chart-col" title="<?= e((string) ($b['title'] ?? '')) ?>">
            <span class="chart-col-track"><span class="chart-col-fill" style="height:<?= $pct ?>%"></span></span>
            <span class="chart-col-x"><?= e((string) ($b['label'] ?? '')) ?></span>
        </div>
    <?php endforeach; ?>
</div>
