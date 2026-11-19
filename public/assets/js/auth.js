// Player login, admin login and registration forms.
// The page decides which form is present; each handler only binds if its form exists.
(function () {
  function bind(formId, handler) {
    const form = document.getElementById(formId);
    if (form) form.addEventListener('submit', (e) => { e.preventDefault(); handler(form); });
  }

  async function login(form, endpoint, errorId, buttonId, next) {
    const errorDiv = document.getElementById(errorId);
    const button = document.getElementById(buttonId);
    errorDiv.textContent = '';
    button.disabled = true;
    try {
      const fd = new FormData(form);
      await App.api(endpoint, { body: { email: fd.get('email'), password: fd.get('password') } });
      location.href = App.url(next);
    } catch (err) {
      errorDiv.textContent = '❌ ' + err.message;
    } finally {
      button.disabled = false;
    }
  }

  // Player and admin login pages share the same markup ids.
  bind('loginForm', (form) => {
    const isAdmin = location.pathname.endsWith('/admin');
    login(form, isAdmin ? '/api/auth/admin-login' : '/api/auth/login',
          'loginErrorMessage', 'loginButton', isAdmin ? 'admin-panel' : 'dashboard');
  });

  bind('registerForm', async () => {
    const err = document.getElementById('regError');
    err.textContent = '';
    const body = {
      username: document.getElementById('regUsername').value.trim(),
      email: document.getElementById('regEmail').value.trim(),
      password: document.getElementById('regPassword').value,
      confirm: document.getElementById('regConfirm').value,
    };
    if (!body.username || !body.email || !body.password || !body.confirm) {
      err.textContent = 'All fields are required.';
      return;
    }
    if (body.password !== body.confirm) {
      err.textContent = "Passwords don't match.";
      return;
    }
    try {
      await App.api('/api/auth/register', { body });
      alert('✅ Account created! Please log in.');
      location.href = App.url('user');
    } catch (e) {
      err.textContent = e.message;
    }
  });
})();
