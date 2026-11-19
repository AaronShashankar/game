// Landing page: the top 5 players.
(function () {
  const list = document.getElementById('leader-list-landing');
  if (!list) return;

  App.api('/api/leaderboard?type=xp&limit=5')
    .then((data) => {
      if (!data.leaderboard.length) {
        list.innerHTML = '<div class="empty">No players yet — be the first.</div>';
        return;
      }
      list.innerHTML = data.leaderboard.map((p) => `
        <div class="list-row">
          <span class="rank rank-${p.rank}">${p.rank}</span>
          <img class="avatar" src="${App.escape(App.avatar(p.avatar, p.username))}" alt="">
          <div class="grow">
            <div class="list-title">${App.escape(p.username)}</div>
            <div class="list-meta">Level ${p.level}</div>
          </div>
          <div class="list-value">${p.xp.toLocaleString()} XP</div>
        </div>`).join('');
    })
    .catch(() => { list.innerHTML = '<div class="empty">Leaderboard unavailable right now.</div>'; });
})();
