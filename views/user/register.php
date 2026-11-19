<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Register — MazeQuest</title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>" />
  <?= app_scripts() ?>
</head>
<body class="auth-body">

  <div class="auth-card">
    <div class="auth-icon">👤</div>
    <h1>Join MazeQuest</h1>
    <p class="subtitle">Start your learning adventure today</p>

    <form id="registerForm" class="auth-form">
      <label>Username</label>
      <input type="text" id="regUsername" placeholder="Choose a username" required />

      <label>Email</label>
      <input type="email" id="regEmail" placeholder="your@email.com" required />

      <label>Password</label>
      <input type="password" id="regPassword" placeholder="Min. 6 characters" required minlength="6" />

      <label>Confirm Password</label>
      <input type="password" id="regConfirm" placeholder="Confirm your password" required />

      <div id="regError" style="color:#ff6b6b; font-size:12px; margin-top:6px;"></div>
      <button type="submit" class="btn-auth">Create Account</button>
    </form>

    <p class="auth-footer">Already have an account? <a href="<?= url('user') ?>">Login</a></p>
    <a href="<?= url('') ?>" class="back-home">← Back to Home</a>
  </div>

  <?= page_script('auth') ?>

</body>
</html>
