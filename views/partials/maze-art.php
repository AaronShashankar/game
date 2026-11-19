<?php
// Decorative maze for the landing page, generated with the same depth-first
// algorithm as the game (fixed seed, so it looks the same on every visit).
$n = 9;
$cell = 36;
$pad = 4;
$seed = 20261119;
$rand = function (int $max) use (&$seed): int {           // small LCG, independent of mt_rand
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    return $seed % $max;
};

$walls = [];   // [r][c] => ['t'=>bool,'r'=>bool,'b'=>bool,'l'=>bool]
$seen = [];
for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) $walls[$r][$c] = ['t' => true, 'r' => true, 'b' => true, 'l' => true];
$stack = [[0, 0]];
$seen['0,0'] = true;
while ($stack) {
    [$r, $c] = end($stack);
    $options = [];
    foreach ([[-1, 0, 't', 'b'], [1, 0, 'b', 't'], [0, -1, 'l', 'r'], [0, 1, 'r', 'l']] as [$dr, $dc, $a, $b]) {
        $nr = $r + $dr; $nc = $c + $dc;
        if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n && !isset($seen["$nr,$nc"])) $options[] = [$nr, $nc, $a, $b];
    }
    if (!$options) { array_pop($stack); continue; }
    [$nr, $nc, $a, $b] = $options[$rand(count($options))];
    $walls[$r][$c][$a] = false;
    $walls[$nr][$nc][$b] = false;
    $seen["$nr,$nc"] = true;
    $stack[] = [$nr, $nc];
}
$walls[0][0]['l'] = false;
$walls[$n - 1][$n - 1]['r'] = false;

// Solution path (BFS) for the trail and gate positions.
$prev = ['0,0' => null];
$queue = [[0, 0]];
while ($queue) {
    [$r, $c] = array_shift($queue);
    foreach ([[-1, 0, 't'], [1, 0, 'b'], [0, -1, 'l'], [0, 1, 'r']] as [$dr, $dc, $w]) {
        $nr = $r + $dr; $nc = $c + $dc;
        if (!$walls[$r][$c][$w] && $nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n && !array_key_exists("$nr,$nc", $prev)) {
            $prev["$nr,$nc"] = [$r, $c];
            $queue[] = [$nr, $nc];
        }
    }
}
$path = [];
for ($p = [$n - 1, $n - 1]; $p !== null; $p = $prev["{$p[0]},{$p[1]}"]) array_unshift($path, $p);

$xy = fn (array $p) => [$pad + $p[1] * $cell + $cell / 2, $pad + $p[0] * $cell + $cell / 2];
$len = count($path);
$playerAt = (int) floor($len * 0.32);
$trail = implode(' ', array_map(fn ($p) => implode(',', $xy($p)), array_slice($path, 0, $playerAt + 1)));
$gateIdx = [(int) floor($len * 0.5), (int) floor($len * 0.68), (int) floor($len * 0.84)];
$size = $n * $cell + $pad * 2;

$lines = [];
for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) {
    $x = $pad + $c * $cell; $y = $pad + $r * $cell; $w = $walls[$r][$c];
    if ($w['t']) $lines[] = "M$x {$y}h$cell";
    if ($w['l']) $lines[] = "M$x {$y}v$cell";
    if ($r === $n - 1 && $w['b']) $lines[] = 'M' . $x . ' ' . ($y + $cell) . "h$cell";
    if ($c === $n - 1 && $w['r']) $lines[] = 'M' . ($x + $cell) . " {$y}v$cell";
}
[$px, $py] = $xy($path[$playerAt]);
[$ex, $ey] = $xy($path[$len - 1]);
?>
<svg class="maze-art" viewBox="0 0 <?= $size ?> <?= $size ?>" role="img" aria-label="A maze with a player, three question gates and an exit">
  <polyline class="trail" points="<?= $trail ?>"/>
  <path class="wall" d="<?= implode('', $lines) ?>"/>
  <?php foreach ($gateIdx as $i => $g): [$gx, $gy] = $xy($path[$g]); ?>
    <rect class="gate" x="<?= $gx - 11 ?>" y="<?= $gy - 11 ?>" width="22" height="22" rx="6"/>
    <text class="gate-text" x="<?= $gx ?>" y="<?= $gy + 4 ?>" text-anchor="middle"><?= $i + 1 ?></text>
  <?php endforeach; ?>
  <rect class="exit" x="<?= $ex - 9 ?>" y="<?= $ey - 9 ?>" width="18" height="18" rx="5"/>
  <circle class="me" cx="<?= $px ?>" cy="<?= $py ?>" r="9"/>
</svg>
