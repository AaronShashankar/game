// Leaderboard page: tabs switch the ranking; your own row is highlighted.
(function () {
  const rows = document.getElementById('leaderRows');
  const tabs = document.querySelectorAll('[data-type]');

  async function load(type) {
    tabs.forEach((t) => t.classList.toggle('is-active', t.dataset.type === type));
    document.getElementById('xpHead').textContent = type === 'weekly' ? 'XP this week' : 'XP';
    try {
      const data = await App.api(`/api/leaderboard?type=${encodeURIComponent(type)}&limit=50`);
      if (!data.leaderboard.length) {
        rows.innerHTML = '<tr><td colspan="6" class="empty">No players yet.</td></tr>';
        return;
      }
      rows.innerHTML = data.leaderboard.map((p) => `
        <tr class="${p.username === window.CurrentPlayer ? 'is-me' : ''}">
          <td><span class="rank rank-${p.rank}">${p.rank}</span></td>
          <td><span class="row" style="gap:10px"><img class="avatar" style="width:28px;height:28px" src="${App.escape(App.avatar(p.avatar, p.username))}" alt="">
            <strong style="font-weight:500">${App.escape(p.username)}</strong>
            ${p.username === window.CurrentPlayer ? '<span class="chip chip-accent">You</span>' : ''}</span></td>
          <td class="num">${p.level}</td>
          <td class="num">${p.xp.toLocaleString()}</td>
          <td class="num">${p.coins.toLocaleString()}</td>
          <td class="num">${p.streak}</td>
        </tr>`).join('');
    } catch (e) {
      rows.innerHTML = '<tr><td colspan="6" class="empty">Could not load the leaderboard.</td></tr>';
    }
  }

  tabs.forEach((t) => t.addEventListener('click', () => load(t.dataset.type)));
  load('xp');
})();
