<?php
/**
 * Graphique en AIRE dégradée (SVG inline — sans JS, compatible CSP stricte).
 * Aire remplie + ligne + points, dont le dernier (aujourd'hui) mis en valeur.
 * @var list<array{value:int,label:string,title:string}> $bars
 * @var string $cur  @var ?int $height  @var ?string $uid (id unique du dégradé)
 */
$bars = array_values($bars ?? []);
$H    = (int) ($height ?? 140);
$W    = 600;
$pad  = 10;
$uid  = (string) ($uid ?? 'area');
$n    = count($bars);
$max  = 1;
foreach ($bars as $b) {
    $max = max($max, (int) $b['value']);
}
$pts = [];
foreach ($bars as $i => $b) {
    $x = $n > 1 ? round($i / ($n - 1) * $W, 1) : $W / 2;
    $y = round($H - $pad - ((int) $b['value'] / $max) * ($H - 2 * $pad), 1);
    $pts[] = [$x, $y];
}
$line = '';
foreach ($pts as $i => $p) {
    $line .= ($i === 0 ? 'M' : 'L') . $p[0] . ' ' . $p[1] . ' ';
}
$area = $n > 0 ? $line . 'L' . $pts[$n - 1][0] . ' ' . $H . ' L' . $pts[0][0] . ' ' . $H . ' Z' : '';
?>
<div class="area-chart" style="--area-h:<?= $H ?>px">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="none" class="area-svg" aria-hidden="true">
        <defs>
            <linearGradient id="<?= e($uid) ?>-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--brand)" stop-opacity="0.30"/>
                <stop offset="100%" stop-color="var(--brand)" stop-opacity="0.02"/>
            </linearGradient>
        </defs>
        <?php if ($area !== ''): ?>
            <path class="area-fill" d="<?= e($area) ?>" fill="url(#<?= e($uid) ?>-fill)"/>
            <path class="area-line" d="<?= e($line) ?>" fill="none" stroke="var(--brand)" stroke-width="2.5" pathLength="1"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        <?php endif; ?>
        <?php foreach ($pts as $i => $p): $last = $i === $n - 1; ?>
            <circle class="area-dot<?= $last ? ' is-last' : '' ?>" cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="<?= $last ? 5 : 3 ?>"
                    fill="<?= $last ? 'var(--accent)' : '#fff' ?>" stroke="var(--brand)" stroke-width="2" vector-effect="non-scaling-stroke">
                <title><?= e((string) ($bars[$i]['title'] ?? '')) ?></title>
            </circle>
        <?php endforeach; ?>
    </svg>
    <div class="area-x">
        <?php foreach ($bars as $i => $b): if ($n <= 8 || $i % (int) ceil($n / 7) === 0 || $i === $n - 1): ?>
            <span style="left:<?= $n > 1 ? round($i / ($n - 1) * 100, 1) : 50 ?>%"><?= e((string) ($b['label'] ?? '')) ?></span>
        <?php endif; endforeach; ?>
    </div>
</div>
