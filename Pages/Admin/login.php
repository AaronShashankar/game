<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Login — MazeQuest</title>
  <link rel="stylesheet" href="assets/css/global.css" />
  <link rel="stylesheet" href="assets/css/login_admin.css" />
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
        <p><a href="<?= url('index') ?>">← Back to Home</a></p>
      </div>
    </div>
  </div>

  <script>
    function toggleTheme() {
      const isDark = document.body.getAttribute('data-theme') === 'dark';
      document.body.setAttribute('data-theme', isDark ? 'light' : 'dark');
    }

    document.getElementById('loginForm').addEventListener('submit', async function(e){
        e.preventDefault();
        const errorDiv = document.getElementById('loginErrorMessage');
        errorDiv.textContent = ''; 
        document.getElementById('loginButton').disabled = true;

        const formData = new FormData(e.target);

        try {
            const response = await fetch('<?= url('api/') ?>admin_login.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                const userProfile = {
                    username: data.username,
                    email: data.email,
                    isAdmin: true
                };
                localStorage.setItem('user', JSON.stringify(userProfile));
                window.location.href = '<?= url('admin-panel') ?>'; 
            } else {
                errorDiv.textContent = '❌ ' + data.message;
            }
        } catch (error) {
            errorDiv.textContent = '❌ Login failed. Check connection.';
        } finally {
            document.getElementById('loginButton').disabled = false;
        }
    });
  </script>
</body>
</html>
