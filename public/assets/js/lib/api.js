// Shared browser helpers. Loaded on every page after window.App = {base}.
//
//   App.url('dashboard')              -> "/game/dashboard"
//   await App.api('/api/me')          -> parsed JSON (throws App.ApiError on failure)
//   await App.api('/api/checkin', {method: 'POST'})
//   await App.api('/api/runs/answer', {body: {...}})   (body implies POST)
(function () {
  const App = (window.App = window.App || {});
  App.base = App.base || '';

  App.url = (path = '') => App.base + '/' + String(path).replace(/^\/+/, '');

  class ApiError extends Error {
    constructor(status, code, message, body) {
      super(message);
      this.status = status;   // HTTP status (0 = network failure)
      this.code = code;       // e.g. "INSUFFICIENT_COINS"
      this.body = body;
    }
  }
  App.ApiError = ApiError;

  App.api = async function (path, { method, body, timeout = 8000 } = {}) {
    const opts = {
      method: method || (body !== undefined ? 'POST' : 'GET'),
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(timeout),
    };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(App.url(path), opts);
    } catch (e) {
      throw new ApiError(0, 'NETWORK', 'Network error — is the server running?');
    }
    let data = null;
    try { data = await res.json(); } catch (e) { /* non-JSON */ }
    if (!res.ok || !data || data.success === false) {
      // Session expired on a protected page: go to the login page.
      if (res.status === 401 && !path.startsWith('/api/auth/')) {
        const adminArea = location.pathname.endsWith('/admin-panel');
        location.href = App.url(adminArea ? 'admin' : 'user');
      }
      throw new ApiError(res.status, data?.code || 'HTTP_' + res.status, data?.message || 'Request failed', data);
    }
    return data;
  };

  // Escape text for safe insertion into innerHTML.
  App.escape = (text) => {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  };

  // Avatar fallback: a generated initial instead of a third-party service.
  App.avatar = (src, name) =>
    src || 'data:image/svg+xml,' + encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="32" fill="#e9e9e5"/>` +
      `<text x="32" y="41" font-size="26" font-weight="600" text-anchor="middle" fill="#55555d" font-family="Inter,system-ui,sans-serif">` +
      `${App.escape((name || '?').charAt(0).toUpperCase())}</text></svg>`);

  // <svg> markup for an icon in the page's sprite (views/layouts/icons.php).
  App.icon = (name, cls = '') => `<svg class="icon ${cls}" aria-hidden="true"><use href="#i-${name}"/></svg>`;

  // Small notification at the bottom of the screen. type: '' | 'success' | 'danger'
  App.toast = (message, type = '') => {
    const stack = document.getElementById('toasts');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = 'toast' + (type ? ' toast-' + type : '');
    const ico = { success: 'check', danger: 'x' }[type];
    el.innerHTML = (ico ? App.icon(ico) : '') + '<span></span>';
    el.querySelector('span').textContent = message;
    stack.appendChild(el);
    setTimeout(() => { el.classList.add('leaving'); setTimeout(() => el.remove(), 200); }, 2600);
  };

  // Keep the coin counter in the top bar in step with server responses.
  App.setCoins = (coins) => {
    const el = document.getElementById('shellCoins');
    if (!el || coins == null || el.textContent === String(coins)) return;
    el.textContent = coins;
    el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
  };
})();
