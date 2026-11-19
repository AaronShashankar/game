</main>
<footer class="site-footer">
  <div class="container">
    <?= brand() ?>
    <nav class="footer-links" aria-label="Footer">
      <a href="<?= url('howtoplay') ?>">How to play</a>
      <a href="<?= url('leaderboard') ?>">Leaderboard</a>
      <a href="<?= url('user') ?>">Log in</a>
    </nav>
    <p class="subtle" style="font-size:13px">&copy; <?= date('Y') ?> MazeQuest</p>
  </div>
</footer>
<?php require __DIR__ . '/document-end.php'; ?>
