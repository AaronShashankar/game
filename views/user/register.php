<?php
use App\Services\GameRules;

$title = 'Create account';
require __DIR__ . '/../layouts/document-start.php';
?>
<div class="auth-top">
  <a href="<?= url('') ?>"><?= brand() ?></a>
  <?= theme_toggle() ?>
</div>

<main class="auth">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-sub">Start with <?= GameRules::STARTER_COINS ?> coins and <?= GameRules::STARTER_POWERUPS ?> of every
      power-up.</p>

    <form id="registerForm" class="form" novalidate>
      <div class="field">
        <label for="regUsername">Username</label>
        <input class="input" type="text" id="regUsername" autocomplete="username"
          placeholder="3–30 letters, numbers, . _ -" required>
      </div>
      <div class="field">
        <label for="regEmail">Email</label>
        <input class="input" type="email" id="regEmail" autocomplete="email" placeholder="you@example.com" required>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="regPassword">Password</label>
          <div class="input-wrap">
            <input class="input" type="password" id="regPassword" autocomplete="new-password"
              placeholder="Min. 6 characters" required minlength="6">
            <?= password_toggle('regPassword') ?>
          </div>
        </div>
        <div class="field">
          <label for="regConfirm">Confirm</label>
          <div class="input-wrap">
            <input class="input" type="password" id="regConfirm" autocomplete="new-password" placeholder="Repeat password"
              required>
            <?= password_toggle('regConfirm') ?>
          </div>
        </div>
      </div>
      <div id="regError" class="form-error" role="alert"></div>
      <button type="submit" class="btn btn-primary btn-lg btn-block" id="regButton">Create account</button>
    </form>

    <p class="auth-foot">Already have an account? <a href="<?= url('user') ?>">Log in</a></p>
  </div>
</main>

<?= page_script('auth') ?>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>