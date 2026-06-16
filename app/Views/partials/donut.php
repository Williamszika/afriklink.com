<?php
/**
 * Donut SVG (sans JS, CSP-safe) — segments par état, total au centre.
 * @var list<array{value:int,color:string,label:string}> $segments
 * @var ?int $size  @var ?string $center_label
 */
$segments = $segments ?? [];
$size = (int) ($size ?? 132);
$stroke = 18;
$r = 46;
$c = 2 * M_PI * $r;
$total = 0;
foreach ($segments as $s) {
    $total += max(0, (int) $s['value']);
}
$offset = 0.0;
?>
<svg class="donut" viewBox="0 0 120 120" width="<?= $size ?>" height="<?= $size ?>" role="img">
    <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="var(--brand-50)" stroke-width="<?= $stroke ?>"/>
    <?php if ($total > 0): foreach ($segments as $s): $v = max(0, (int) $s['value']); if ($v <= 0) { continue; } $len = $v / $total * $c; ?>
        <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="<?= e((string) $s['color']) ?>" stroke-width="<?= $stroke ?>"
                stroke-dasharray="<?= round($len, 2) ?> <?= round($c - $len, 2) ?>" stroke-dashoffset="<?= round(-$offset, 2) ?>"
                transform="rotate(-90 60 60)"/>
        <?php $offset += $len; endforeach; endif; ?>
    <text x="60" y="58" text-anchor="middle" class="donut-num"><?= (int) $total ?></text>
    <text x="60" y="74" text-anchor="middle" class="donut-cap"><?= e((string) ($center_label ?? '')) ?></text>
</svg>
