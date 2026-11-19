  </main>
  <footer>
    <div class="footer-content">
      <div class="col">
        <h3>🎮 MazeQuest</h3>
        <p>Learn while playing. The ultimate educational maze adventure.</p>
      </div>
      <div class="col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="<?= url('#features') ?>">Features</a></li>
          <li><a href="<?= url('howtoplay') ?>">How To Play</a></li>
          <li><a href="<?= url('game') ?>">Play</a></li>
        </ul>
      </div>
      <div class="col">
        <h4>Game</h4>
        <ul>
          <li><a href="<?= url('/user') ?>">Login</a></li>
          <li><a href="<?= url('register') ?>">Register</a></li>
          <li><a href="<?= url('profile') ?>">Profile</a></li>
        </ul>
      </div>
      <div class="col">
        <h4>Connect</h4>
        <div class="social">
          <a href="#">🐦</a>
          <a href="#">📘</a>
          <a href="#">📸</a>
        </div>
      </div>
    </div>
    <div style="text-align:center; margin-top: 20px; font-size: 0.9em; opacity: 0.7;">
      &copy; <?= date('Y') ?> MazeQuest. All rights reserved.
    </div>
  </footer>
</body>
</html>
