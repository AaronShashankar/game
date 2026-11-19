<?php
// Public page; logged-in players see it inside the app shell.
$title = 'Leaderboard';
$active = 'leaderboard';
$signedIn = current_player() !== null;
require __DIR__ . '/../layouts/' . ($signedIn ? 'app-start.php' : 'header.php');
?>

<?php if (!$signedIn): ?><div class="container page-hero" style="padding-bottom:32px"><?php endif; ?>
<div class="page-head"<?= $signedIn ? '' : ' style="margin-bottom:0"' ?>>
  <div>
    <h1>Leaderboard</h1>
    <p>Every point here was earned in a server-verified game.</p>
  </div>
</div>
<?php if (!$signedIn): ?></div><div class="container"><?php endif; ?>

<div class="card card-flush">
  <div class="row-between" style="padding:16px; border-bottom:1px solid var(--border); flex-wrap:wrap">
    <div class="tabs" role="tablist" aria-label="Rank by">
      <button class="tab is-active" data-type="xp">XP</button>
      <button class="tab" data-type="weekly">This week</button>
      <button class="tab" data-type="coins">Coins</button>
      <button class="tab" data-type="level">Level</button>
      <button class="tab" data-type="streak">Streak</button>
    </div>
    <span class="subtle" style="font-size:13px">Top 50</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th style="width:64px">Rank</th><th>Player</th><th class="num">Level</th><th class="num" id="xpHead">XP</th><th class="num">Coins</th><th class="num">Streak</th></tr>
      </thead>
      <tbody id="leaderRows"><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<?php if (!$signedIn): ?></div><?php endif; ?>
<script>window.CurrentPlayer = <?= json_encode($signedIn ? current_player()['username'] : null, JSON_HEX_TAG) ?>;</script>
<?= page_script('leaderboard') ?>
<?php require __DIR__ . '/../layouts/' . ($signedIn ? 'app-end.php' : 'footer.php'); ?>
