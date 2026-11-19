// Shared page behaviour: theme toggle (remembered per browser) and logout.
(function () {
  function readTheme() {
    try { return localStorage.getItem('theme'); } catch (e) { return null; }
  }

  window.toggleTheme = function () {
    const isDark = document.body.getAttribute('data-theme') === 'dark';
    const next = isDark ? 'light' : 'dark';
    document.body.setAttribute('data-theme', next);
    document.body.classList.toggle('dark', next === 'dark');
    try { localStorage.setItem('theme', next); } catch (e) { /* storage unavailable */ }
  };

  window.logout = async function () {
    try { await App.api('/api/auth/logout', { method: 'POST' }); } catch (e) { /* already logged out */ }
    location.href = App.url(location.pathname.endsWith('/admin-panel') ? 'admin' : 'user');
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (readTheme() === 'dark') {
      document.body.setAttribute('data-theme', 'dark');
      document.body.classList.add('dark');
    }
  });
})();
