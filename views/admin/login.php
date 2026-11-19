<?php
$title = 'Admin';
require __DIR__ . '/../layouts/document-start.php';
?>
<div class="auth-top">
  <a href="<?= url('') ?>"><?= brand() ?></a>
  <?= theme_toggle() ?>
</div>

<main class="auth">
  <div class="auth-card">
    <span class="chip"><?= icon('shield') ?>Admin</span>
    <h1 class="mt-16">Admin sign in</h1>
    <p class="auth-sub">Restricted to MazeQuest administrators.</p>

    <form id="loginForm" class="form" novalidate>
      <div class="field">
        <label for="loginUsername">Email</label>
        <input class="input" type="email" id="loginUsername" name="email" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="loginPassword">Password</label>
        <input class="input" type="password" id="loginPassword" name="password" autocomplete="current-password" required>
      </div>
      <div id="loginErrorMessage" class="form-error" role="alert"></div>
      <button type="submit" class="btn btn-primary btn-lg btn-block" id="loginButton">Sign in</button>
    </form>

    <p class="auth-foot"><a href="<?= url('') ?>">Back to MazeQuest</a></p>
  </div>
</main>

<?= page_script('auth') ?>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>
