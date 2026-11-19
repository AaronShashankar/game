<?php
use App\Services\GameRules;

/** @var string[] $subjects */
/** @var array $difficulties */
$title = 'New game';
$active = 'play';
require __DIR__ . '/../layouts/app-start.php';

$meta = [
    'Mathematics'        => ['calculator', 'Numbers, arithmetic and problem solving'],
    'Science'            => ['flask', 'Physics, chemistry and biology'],
    'General Knowledge'  => ['globe', 'World facts, geography and culture'],
    'History'            => ['landmark', 'Events, people and civilisations'],
    'English Vocabulary' => ['type', 'Words, meanings and synonyms'],
];
$diff = ucfirst(strtolower((string) ($_GET['difficulty'] ?? 'easy')));
if (!isset($difficulties[$diff])) $diff = 'Easy';
?>

<div class="page-head">
  <div>
    <h1>New game</h1>
    <p>Pick a difficulty, then choose what you want to be quizzed on.</p>
  </div>
</div>

<div class="card">
  <div class="row-between" style="flex-wrap:wrap">
    <div class="tabs" role="radiogroup" aria-label="Difficulty">
      <?php foreach ($difficulties as $name => $d): ?>
        <button class="tab<?= $name === $diff ? ' is-active' : '' ?>" role="radio" aria-checked="<?= $name === $diff ? 'true' : 'false' ?>" data-difficulty="<?= e($name) ?>"><?= e($name) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="row" id="diffSpec" style="flex-wrap:wrap; gap:6px"></div>
  </div>
</div>

<h2 class="mt-24" style="font-size:15px; margin-bottom:12px">Subject</h2>
<div class="subject-grid">
  <?php foreach ($subjects as $s): [$ico, $desc] = $meta[$s] ?? ['book', 'Questions on ' . $s]; ?>
    <button class="subject" data-subject="<?= e($s) ?>">
      <span class="icon-box"><?= icon($ico) ?></span>
      <span class="grow"><span class="subject-name"><?= e($s) ?></span><br><span class="subject-desc"><?= e($desc) ?></span></span>
      <?= icon('arrow-right', 'icon-sm') ?>
    </button>
  <?php endforeach; ?>
  <button class="subject" data-subject="<?= e(GameRules::MIXED_SUBJECT) ?>">
    <span class="icon-box"><?= icon('shuffle') ?></span>
    <span class="grow"><span class="subject-name">Mixed mode</span><br><span class="subject-desc">A bit of everything</span></span>
    <?= icon('arrow-right', 'icon-sm') ?>
  </button>
</div>

<script>window.GameConfig = <?= json_encode(['difficulties' => $difficulties, 'selected' => $diff], JSON_HEX_TAG) ?>;</script>
<?= page_script('choose-subject') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
