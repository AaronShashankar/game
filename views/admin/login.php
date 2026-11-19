<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Login — MazeQuest</title>
  <link rel="stylesheet" href="<?= e(asset('css/global.css')) ?>" />
  <link rel="stylesheet" href="<?= e(asset('css/login_admin.css')) ?>" />
  <?= app_scripts() ?>
</head>
<body>

  <button class="theme-toggle" onclick="toggleTheme()">🌓 Theme</button>

  <div class="auth-wrapper">
    <div class="card auth-card">
      <div class="auth-title">🔐 Admin Access</div>
      <p style="margin-bottom: 20px; font-size: 14px;">Secure login for MazeQuest administrators.</p>

      <form id="loginForm" class="auth-form">
        <label>Admin Email</label>
        <input type="email" id="loginUsername" name="email" placeholder="Enter admin email" required />

        <label>Password</label>
        <input type="password" id="loginPassword" name="password" placeholder="Enter password" required />

        <div id="loginErrorMessage" style="color: red; font-size: 13px; margin-bottom: 10px;"></div>

        <button type="submit" class="btn-primary" id="loginButton">Admin Login</button>
      </form>

      <div class="auth-footer">
        <p><a href="<?= url('') ?>">← Back to Home</a></p>
      </div>
    </div>
  </div>

  <?= page_script('auth') ?>
</body>
</html>
