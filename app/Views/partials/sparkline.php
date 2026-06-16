<?php
/**
 * Mini-graphe (sparkline) — ligne SVG compacte, sans JS (CSP-safe).
 * Prend la couleur du parent (currentColor).
 * @var list<int> $values  @var ?int $w  @var ?int $h
 */
$values = array_map('intval', $values ?? []);
$w = (int) ($w ?? 70);
$h = (int) ($h ?? 22);
$n = count($values);
$max = $values !== [] ? max($values) : 0;
$min = $values !== [] ? min($values) : 0;
$range = max(1, $max - $min);
$pts = [];
foreach ($values as $i => $v) {
    $x = $n > 1 ? round($i / ($n - 1) * $w, 1) : $w / 2;
    $y = round($h - 2 - (($v - $min) / $range) * ($h - 4), 1);
    $pts[] = $x . ' ' . $y;
}
$d = $pts !== [] ? 'M' . implode(' L', $pts) : '';
?>
<svg class="spark" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" aria-hidden="true">
    <?php if ($d !== ''): ?><path d="<?= e($d) ?>" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" opacity="0.9"/><?php endif; ?>
</svg>
