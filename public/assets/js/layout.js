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

  window.logout = async function () {
    try { await App.api('/api/auth/logout', { method: 'POST' }); } catch (e) { /* already logged out */ }
    location.href = App.url(location.pathname.endsWith('/admin-panel') ? 'admin' : 'user');
  };
})();
