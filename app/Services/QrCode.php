<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Générateur de QR code (modèle 2) sans aucune dépendance — mode octets,
 * correction d'erreur M, versions 1 à 10 (≈ 213 caractères max), sortie SVG
 * vectorielle (nette à l'impression). Algorithme : ISO/IEC 18004.
 * Usage : QrCode::svg('https://exemple.com/boutique/mon-slug').
 */
final class QrCode
{
    /** Capacité en octets (mode octets, niveau M) par version 1..10. */
    private const CAPACITY = [1 => 14, 26, 42, 62, 84, 106, 122, 152, 180, 213];

    /** Nombre total de codewords par version 1..10. */
    private const TOTAL_CW = [1 => 26, 44, 70, 100, 134, 172, 196, 242, 292, 346];

    /**
     * Structure des blocs niveau M par version : [ec par bloc, [tailles données]].
     * @var array<int, array{0:int, 1:list<int>}>
     */
    private const BLOCKS = [
        1  => [10, [16]],
        2  => [16, [28]],
        3  => [26, [44]],
        4  => [18, [32, 32]],
        5  => [24, [43, 43]],
        6  => [16, [27, 27, 27, 27]],
        7  => [18, [31, 31, 31, 31]],
        8  => [22, [38, 38, 39, 39]],
        9  => [22, [36, 36, 36, 37, 37]],
        10 => [26, [43, 43, 43, 43, 44]],
    ];

    /** Centres des motifs d'alignement par version 1..10. */
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /**
     * Rend le QR code en SVG (carré, fond blanc, zone de silence de 4 modules).
     * @param int $scale taille d'un module en px (le SVG reste vectoriel)
     */
    public static function svg(string $text, int $scale = 8): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $quiet = 4;
        $dim = ($n + 2 * $quiet) * $scale;
        $path = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) {
                    $path .= 'M' . ($c + $quiet) . ' ' . ($r + $quiet) . 'h1v1h-1z';
                }
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim
            . '" viewBox="0 0 ' . ($n + 2 * $quiet) . ' ' . ($n + 2 * $quiet) . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . $path . '" fill="#000000"/></svg>';
    }

    /**
     * Matrice de modules (true = noir). @return list<list<bool>>
     * @throws \InvalidArgumentException si le texte dépasse la version 10.
     */
    public static function matrix(string $text): array
    {
        $len = strlen($text);
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            if ($len <= self::CAPACITY[$v]) { $version = $v; break; }
        }
        if ($version === 0) {
            throw new \InvalidArgumentException('QR : texte trop long (max ' . self::CAPACITY[10] . ' octets)');
        }

        $codewords = self::buildCodewords($text, $version);
        $size = 17 + 4 * $version;

        // $grid[r][c] : null tant que non posé ; $func[r][c] : module de fonction.
        $grid = array_fill(0, $size, array_fill(0, $size, null));
        $func = array_fill(0, $size, array_fill(0, $size, false));
        self::placeFunctionPatterns($grid, $func, $version, $size);

        // Essaie les 8 masques sur une copie, garde celui de pénalité minimale.
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $g = $grid;
            self::placeData($g, $func, $codewords, $mask, $size);
            self::placeFormatInfo($g, $version, $mask, $size);
            $score = self::penalty($g, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $g;
            }
        }

        /** @var list<list<bool>> */
        return array_map(static fn (array $row): array => array_map(static fn ($v): bool => (bool) $v, $row), $best);
    }

    /* ---- Encodage octets + Reed-Solomon ----------------------------- */

    /** @return list<int> codewords finaux entrelacés (données + correction) */
    private static function buildCodewords(string $text, int $version): array
    {
        [$ecPerBlock, $blockSizes] = self::BLOCKS[$version];
        $dataCw = array_sum($blockSizes);

        // Mode octets (0100) + longueur (8 bits en v1-9, 16 bits en v10) + données.
        $bits = '0100';
        $bits .= str_pad(decbin(strlen($text)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        // Terminateur (≤ 4 zéros), alignement sur l'octet, octets de bourrage.
        $bits .= str_repeat('0', min(4, $dataCw * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }
        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }
        for ($i = 0; count($data) < $dataCw; $i++) {
            $data[] = $i % 2 === 0 ? 0xEC : 0x11;
        }

        // Découpe en blocs + correction Reed-Solomon par bloc.
        $blocksData = [];
        $blocksEc = [];
        $offset = 0;
        foreach ($blockSizes as $bs) {
            $block = array_slice($data, $offset, $bs);
            $offset += $bs;
            $blocksData[] = $block;
            $blocksEc[] = self::reedSolomon($block, $ecPerBlock);
        }

        // Entrelacement : i-ème codeword de chaque bloc, puis la correction.
        $out = [];
        $maxLen = max(array_map('count', $blocksData));
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($blocksData as $b) {
                if ($i < count($b)) { $out[] = $b[$i]; }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($blocksEc as $b) {
                $out[] = $b[$i];
            }
        }
        return $out;
    }

    /** Codewords de correction Reed-Solomon dans GF(256), polynôme 0x11D. @param list<int> $data @return list<int> */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        static $exp = null, $log = null;
        if ($exp === null) {
            $exp = [];
            $log = [];
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) { $x ^= 0x11D; }
            }
            for ($i = 255; $i < 512; $i++) { $exp[$i] = $exp[$i - 255]; }
        }

        // Polynôme générateur : produit des (x − α^i), coefficients du plus
        // haut degré (indice 0, toujours 1) au plus bas.
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef; // terme × x
                $next[$j + 1] ^= $coef === 0 ? 0 : $exp[($log[$coef] + $i) % 255]; // terme × α^i
            }
            $gen = $next;
        }

        // Division polynomiale : reste = correction.
        $rem = array_fill(0, $ecCount, 0);
        foreach ($data as $d) {
            $factor = $d ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                $lf = $log[$factor];
                for ($j = 0; $j < $ecCount; $j++) {
                    $g = $gen[$j + 1];
                    if ($g !== 0) {
                        $rem[$j] ^= $exp[($log[$g] + $lf) % 255];
                    }
                }
            }
        }
        return $rem;
    }

    /* ---- Motifs de fonction ----------------------------------------- */

    /** @param array<int,array<int,?bool>> $grid @param array<int,array<int,bool>> $func */
    private static function placeFunctionPatterns(array &$grid, array &$func, int $version, int $size): void
    {
        $set = static function (int $r, int $c, bool $dark) use (&$grid, &$func, $size): void {
            if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) { return; }
            $grid[$r][$c] = $dark;
            $func[$r][$c] = true;
        };

        // Trois motifs de repérage (7×7) + séparateurs blancs d'un module.
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$top, $left]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $dark = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                    $set($top + $r, $left + $c, $dark);
                }
            }
        }

        // Motifs d'alignement (5×5) AVANT la synchro : un centre déjà posé est
        // dans un repère (on saute) ; ceux sur la ligne/colonne 6 doivent exister.
        $centers = self::ALIGN[$version];
        foreach ($centers as $cr) {
            foreach ($centers as $cc) {
                if ($grid[$cr][$cc] !== null) { continue; }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $set($cr + $r, $cc + $c, abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                    }
                }
            }
        }

        // Lignes de synchronisation (ligne et colonne 6).
        for ($i = 8; $i < $size - 8; $i++) {
            if ($grid[6][$i] === null) { $set(6, $i, $i % 2 === 0); }
            if ($grid[$i][6] === null) { $set($i, 6, $i % 2 === 0); }
        }

        // Réserve les zones d'information de format (remplies après masquage).
        for ($i = 0; $i < 9; $i++) {
            if (!$func[8][$i]) { $set(8, $i, false); }
            if (!$func[$i][8]) { $set($i, 8, false); }
        }
        for ($i = 0; $i < 8; $i++) {
            if (!$func[8][$size - 1 - $i]) { $set(8, $size - 1 - $i, false); }
            if (!$func[$size - 1 - $i][8]) { $set($size - 1 - $i, 8, false); }
        }
        $set($size - 8, 8, true); // module sombre fixe

        // Information de version (versions ≥ 7) : deux zones 3×6.
        if ($version >= 7) {
            $vbits = self::bchVersion($version);
            for ($i = 0; $i < 18; $i++) {
                $dark = (($vbits >> $i) & 1) === 1;
                $set(intdiv($i, 3), $i % 3 + $size - 11, $dark);
                $set($i % 3 + $size - 11, intdiv($i, 3), $dark);
            }
        }
    }

    /* ---- Données + masque ------------------------------------------- */

    /** @param array<int,array<int,?bool>> $grid @param array<int,array<int,bool>> $func @param list<int> $codewords */
    private static function placeData(array &$grid, array $func, array $codewords, int $mask, int $size): void
    {
        $bitIndex = 7;
        $byteIndex = 0;
        $row = $size - 1;
        $inc = -1;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) { $col--; } // la colonne 6 est la ligne de synchro
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if ($func[$row][$cc]) { continue; }
                    $dark = false;
                    if ($byteIndex < count($codewords)) {
                        $dark = (($codewords[$byteIndex] >> $bitIndex) & 1) === 1;
                    }
                    if (self::maskAt($mask, $row, $cc)) { $dark = !$dark; }
                    $grid[$row][$cc] = $dark;
                    if (--$bitIndex === -1) { $byteIndex++; $bitIndex = 7; }
                }
                $row += $inc;
                if ($row < 0 || $row >= $size) {
                    $row -= $inc;
                    $inc = -$inc;
                    break;
                }
            }
        }
    }

    private static function maskAt(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
            6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
            default => (($r * $c) % 3 + ($r + $c) % 2) % 2 === 0,
        };
    }

    /** Info de format (niveau M + masque), BCH(15,5) ^ 0x5412, posée en double. */
    private static function placeFormatInfo(array &$grid, int $version, int $mask, int $size): void
    {
        $data = (0b00 << 3) | $mask; // niveau M = 00
        $bits = $data << 10;
        while (self::bitLen($bits) >= 11) {
            $bits ^= 0x537 << (self::bitLen($bits) - 11);
        }
        $bits = ((($data << 10) | $bits) ^ 0x5412);

        for ($i = 0; $i < 15; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            // Copie verticale (autour du repère haut-gauche puis bas-gauche).
            if ($i < 6) { $grid[$i][8] = $dark; }
            elseif ($i < 8) { $grid[$i + 1][8] = $dark; }
            else { $grid[$size - 15 + $i][8] = $dark; }
            // Copie horizontale (haut-gauche puis haut-droit).
            if ($i < 8) { $grid[8][$size - 1 - $i] = $dark; }
            elseif ($i < 9) { $grid[8][15 - $i] = $dark; }
            else { $grid[8][15 - $i - 1] = $dark; }
        }
        $grid[$size - 8][8] = true;
    }

    /** Info de version : 6 bits + BCH(18,6) avec le polynôme 0x1F25. */
    private static function bchVersion(int $version): int
    {
        $bits = $version << 12;
        while (self::bitLen($bits) >= 13) {
            $bits ^= 0x1F25 << (self::bitLen($bits) - 13);
        }
        return ($version << 12) | $bits;
    }

    private static function bitLen(int $x): int
    {
        $n = 0;
        while ($x > 0) { $n++; $x >>= 1; }
        return $n;
    }

    /* ---- Pénalité (choix du masque) ---------------------------------- */

    /** @param array<int,array<int,?bool>> $g */
    private static function penalty(array $g, int $size): int
    {
        $score = 0;
        $dark = 0;

        // Règle 1 : suites ≥ 5 modules de même couleur (lignes et colonnes).
        for ($r = 0; $r < $size; $r++) {
            $runRow = 1;
            $runCol = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($g[$r][$c] === $g[$r][$c - 1]) {
                    if (++$runRow === 5) { $score += 3; } elseif ($runRow > 5) { $score++; }
                } else { $runRow = 1; }
                if ($g[$c][$r] === $g[$c - 1][$r]) {
                    if (++$runCol === 5) { $score += 3; } elseif ($runCol > 5) { $score++; }
                } else { $runCol = 1; }
            }
        }

        // Règle 2 : blocs 2×2 uniformes. Règle 4 : comptage des sombres.
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($g[$r][$c]) { $dark++; }
                if ($r + 1 < $size && $c + 1 < $size
                    && $g[$r][$c] === $g[$r][$c + 1]
                    && $g[$r][$c] === $g[$r + 1][$c]
                    && $g[$r][$c] === $g[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Règle 3 : motif 1011101 précédé/suivi de 0000 (faux repères).
        $needles = [[true, false, true, true, true, false, true, false, false, false, false],
                    [false, false, false, false, true, false, true, true, true, false, true]];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c + 11 <= $size; $c++) {
                foreach ($needles as $n) {
                    $hitRow = true;
                    $hitCol = true;
                    for ($i = 0; $i < 11; $i++) {
                        if ($g[$r][$c + $i] !== $n[$i]) { $hitRow = false; }
                        if ($g[$c + $i][$r] !== $n[$i]) { $hitCol = false; }
                        if (!$hitRow && !$hitCol) { break; }
                    }
                    if ($hitRow) { $score += 40; }
                    if ($hitCol) { $score += 40; }
                }
            }
        }

        // Règle 4 : écart à 50 % de modules sombres.
        $ratio = (int) (abs($dark * 100 / ($size * $size) - 50) / 5);
        return $score + $ratio * 10;
    }
}
