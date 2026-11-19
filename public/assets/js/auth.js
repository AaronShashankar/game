// Player login, admin login and registration forms.
// The page decides which form is present; each handler only binds if its form exists.
(function () {
  const $ = (id) => document.getElementById(id);

  // Where to go after signing in: ?next=/some/page (same-site paths only,
  // so the parameter can't be used to bounce people to another website).
  function nextUrl(fallback) {
    const next = new URLSearchParams(location.search).get('next') || '';
    return /^\/(?![\/\\])[\w\-\/?=&%.]*$/.test(next) ? App.url(next.slice(1)) : App.url(fallback);
  }

  // Keep ?next= on the link between the login and sign-up pages.
  const next = new URLSearchParams(location.search).get('next');
  if (next) {
    document.querySelectorAll('.auth-foot a').forEach((a) => {
      a.href += (a.href.includes('?') ? '&' : '?') + 'next=' + encodeURIComponent(next);
    });
  }

  function bind(formId, handler) {
    const form = $(formId);
    if (form) form.addEventListener('submit', (e) => { e.preventDefault(); handler(form); });
  }

  async function busy(button, label, work) {
    const original = button.textContent;
    button.disabled = true;
    button.textContent = label;
    try { await work(); } finally { button.disabled = false; button.textContent = original; }
  }

  // Player and admin login pages share the same markup ids.
  bind('loginForm', (form) => {
    const isAdmin = location.pathname.endsWith('/admin');
    const error = $('loginErrorMessage');
    error.textContent = '';
    const fd = new FormData(form);
    if (!fd.get('email') || !fd.get('password')) {
      error.textContent = 'Enter your email and password.';
      return;
    }
    busy($('loginButton'), 'Signing in…', async () => {
      try {
        await App.api(isAdmin ? '/api/auth/admin-login' : '/api/auth/login',
          { body: { email: fd.get('email'), password: fd.get('password') } });
        location.href = isAdmin ? App.url('admin-panel') : nextUrl('dashboard');
      } catch (err) {
        error.textContent = err.message;
      }
    });
  });

  bind('registerForm', () => {
    const error = $('regError');
    error.textContent = '';
    const body = {
      username: $('regUsername').value.trim(),
      email: $('regEmail').value.trim(),
      password: $('regPassword').value,
      confirm: $('regConfirm').value,
    };
    if (!body.username || !body.email || !body.password || !body.confirm) {
      error.textContent = 'Please fill in every field.';
      return;
    }
    if (body.password !== body.confirm) {
      error.textContent = "Passwords don't match.";
      return;
    }
    busy($('regButton'), 'Creating account…', async () => {
      try {
        await App.api('/api/auth/register', { body });
        // Sign straight in with the same credentials.
        await App.api('/api/auth/login', { body: { email: body.email, password: body.password } });
        location.href = nextUrl('dashboard');
      } catch (e) {
        error.textContent = e.message;
      }
    });
  });
})();
