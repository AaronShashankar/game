// Leaderboard page: tabs switch the ranking; your own row is highlighted.
// ?type=daily (etc.) opens a tab directly.
(function () {
  const rows = document.getElementById('leaderRows');
  const head = document.getElementById('leaderHead');
  const note = document.getElementById('leaderNote');
  const tabs = document.querySelectorAll('[data-type]');
  const TYPES = ['xp', 'weekly', 'daily', 'coins', 'level', 'streak'];
  const fmtTime = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
  const me = window.CurrentPlayer;

  function player(p) {
    return `<span class="row" style="gap:10px"><img class="avatar" style="width:28px;height:28px" src="${App.escape(App.avatar(p.avatar, p.username))}" alt="">
      <strong style="font-weight:500">${App.escape(p.username)}</strong>
      ${p.username === me ? '<span class="chip chip-accent">You</span>' : ''}</span>`;
  }

  async function load(type) {
    tabs.forEach((t) => t.classList.toggle('is-active', t.dataset.type === type));
    const daily = type === 'daily';
    const cols = daily
      ? ['Rank', 'Player', 'Level', 'Time']
      : ['Rank', 'Player', 'Level', type === 'weekly' ? 'XP this week' : 'XP', 'Coins', 'Streak'];
    head.innerHTML = cols.map((c, i) => `<th${i === 0 ? ' style="width:64px"' : i > 1 ? ' class="num"' : ''}>${c}</th>`).join('');
    note.textContent = daily ? "Fastest finishes of today's maze" : type === 'weekly' ? 'Top 3 win coins on Monday' : 'Top 50';
    rows.innerHTML = `<tr><td colspan="${cols.length}" class="empty">Loading…</td></tr>`;
    try {
      const data = await App.api(`/api/leaderboard?type=${encodeURIComponent(type)}&limit=50`);
      if (!data.leaderboard.length) {
        rows.innerHTML = `<tr><td colspan="${cols.length}" class="empty">${daily ? "Nobody has finished today's maze yet." : 'No players yet.'}</td></tr>`;
        return;
      }
      rows.innerHTML = data.leaderboard.map((p) => `
        <tr class="${p.username === me ? 'is-me' : ''}">
          <td><span class="rank rank-${p.rank}">${p.rank}</span></td>
          <td>${player(p)}</td>
          <td class="num">${p.level}</td>
          ${daily ? `<td class="num">${fmtTime(p.duration_seconds)}</td>` : `
          <td class="num">${p.xp.toLocaleString()}</td>
          <td class="num">${p.coins.toLocaleString()}</td>
          <td class="num">${p.streak}</td>`}
        </tr>`).join('');
    } catch (e) {
      rows.innerHTML = `<tr><td colspan="${cols.length}" class="empty">Could not load the leaderboard.</td></tr>`;
    }
  }

  tabs.forEach((t) => t.addEventListener('click', () => {
    history.replaceState(null, '', `?type=${t.dataset.type}`);
    load(t.dataset.type);
  }));
  const initial = new URLSearchParams(location.search).get('type');
  load(TYPES.includes(initial) ? initial : 'xp');
})();
