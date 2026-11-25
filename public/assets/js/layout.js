// Shared page behaviour: light/dark theme (remembered per browser) and logout.
(function () {
  const root = document.documentElement;
  const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

  // The effective theme: an explicit choice, otherwise the OS setting.
  window.currentTheme = () => root.dataset.theme || (prefersDark() ? 'dark' : 'light');

  window.toggleTheme = function () {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('theme', next); } catch (e) { /* storage unavailable */ }
    window.dispatchEvent(new CustomEvent('themechange', { detail: next }));
  };

  // Password fields: the eye button beside a field (password_toggle() in
  // helpers.php) shows or hides what was typed.
  function setPasswordVisible(button, input, visible) {
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.title = visible ? 'Hide password' : 'Show password';
  }

  document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-password-toggle]');
    const input = button && document.getElementById(button.dataset.passwordToggle);
    if (input) setPasswordVisible(button, input, input.type === 'password');
  });

  // Hide them again when a form is sent, so browsers don't remember the
  // password as plain text and it isn't left showing on screen.
  document.addEventListener('submit', (e) => {
    e.target.querySelectorAll('[data-password-toggle]').forEach((button) => {
      const input = document.getElementById(button.dataset.passwordToggle);
      if (input) setPasswordVisible(button, input, false);
    });
  }, true);

  window.logout = async function () {
    try { await App.api('/api/auth/logout', { method: 'POST' }); } catch (e) { /* already logged out */ }
    location.href = App.url(location.pathname.endsWith('/admin-panel') ? 'admin' : 'user');
  };
})();
