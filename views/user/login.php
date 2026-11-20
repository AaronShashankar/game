<?php
$title = 'Log in';
require __DIR__ . '/../layouts/document-start.php';
?>
<div class="auth-top">
  <a href="<?= url('') ?>"><?= brand() ?></a>
  <?= theme_toggle() ?>
</div>

<main class="auth">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="auth-sub">Log in to continue your adventure.</p>

    <form id="loginForm" class="form" novalidate>
      <div class="field">
        <label for="loginUsername">Email</label>
        <input class="input" type="email" id="loginUsername" name="email" autocomplete="email"
          placeholder="you@example.com" required>
      </div>
      <div class="field">
        <label for="loginPassword">Password</label>
        <input class="input" type="password" id="loginPassword" name="password" autocomplete="current-password"
          placeholder="••••••••" required>
      </div>
      <div id="loginErrorMessage" class="form-error" role="alert"></div>
      <button type="submit" class="btn btn-primary btn-lg btn-block" id="loginButton">Log in</button>
    </form>

    <p class="auth-foot">New to MazeQuest? <a href="<?= url('register') ?>">Create an account</a></p>
  </div>
</main>

<?= page_script('auth') ?>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>