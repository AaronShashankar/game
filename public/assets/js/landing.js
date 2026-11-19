// Landing page: show the top 5 players.
(function () {
  const list = document.getElementById('leader-list-landing');
  if (!list) return;

  function showMessage(text) {
    list.innerHTML = '';
    const li = document.createElement('li');
    li.textContent = text;
    list.appendChild(li);
  }

  showMessage('Loading…');
  App.api('/api/leaderboard?type=xp&limit=5')
    .then((data) => {
      if (!data.leaderboard.length) return showMessage('No players yet. Be the first!');
      list.innerHTML = '';
      data.leaderboard.forEach((p) => {
        const li = document.createElement('li');
        const name = document.createElement('strong');
        name.textContent = p.username;   // user-controlled: textContent, never innerHTML
        li.appendChild(name);
        li.appendChild(document.createTextNode(` · Level ${p.level} · ${p.xp} XP`));
        list.appendChild(li);
      });
    })
    .catch(() => showMessage('Leaderboard unavailable right now.'));
})();
