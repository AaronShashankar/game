// Public leaderboard page.
async function loadLeaderboard(type = 'xp') {
  const rows = document.getElementById('leaderRows');
  const row = (rank, player, xp, coins) =>
    `<div class="leader-row"><div class="rank">${rank}</div><div class="player">${player}</div>` +
    `<div class="xp">${xp}</div><div class="coins">${coins}</div></div>`;
  try {
    const data = await App.api(`/api/leaderboard?type=${encodeURIComponent(type)}&limit=50`);
    rows.innerHTML = '';
    if (!data.leaderboard.length) {
      rows.innerHTML = row('-', 'No players yet', '-', '-');
      return;
    }
    data.leaderboard.forEach((p, idx) => {
      const div = document.createElement('div');
      div.className = `leader-row ${['top1', 'top2', 'top3'][idx] || ''}`;
      const avatar = `<img src="${App.escape(App.avatar(p.avatar, p.username))}" alt="" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:8px;">`;
      div.innerHTML = `
        <div class="rank">${p.rank}</div>
        <div class="player">${avatar}${App.escape(p.username)}</div>
        <div class="xp">${p.xp}</div>
        <div class="coins">💰 ${p.coins}</div>`;
      rows.appendChild(div);
    });
  } catch (e) {
    rows.innerHTML = row('-', 'Could not load leaderboard', '-', '-');
  }
}

document.addEventListener('DOMContentLoaded', () => loadLeaderboard('xp'));
