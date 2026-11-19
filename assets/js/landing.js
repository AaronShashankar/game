// Landing page: show the top 5 players from the leaderboard API.
(function () {
  const list = document.getElementById('leader-list-landing');
  if (!list) return;

  const apiUrl = list.dataset.api;

  function showMessage(text) {
    list.innerHTML = '';
    const li = document.createElement('li');
    li.textContent = text;
    list.appendChild(li);
  }

  showMessage('Loading…');

  fetch(apiUrl, { credentials: 'same-origin' })
    .then((res) => res.json())
    .then((data) => {
      if (!data.success || !data.leaderboard || data.leaderboard.length === 0) {
        showMessage('No players yet. Be the first!');
        return;
      }
      list.innerHTML = '';
      data.leaderboard.forEach((p) => {
        const li = document.createElement('li');
        const name = document.createElement('strong');
        name.textContent = p.username;
        li.appendChild(name);
        li.appendChild(document.createTextNode(` · Level ${p.level} · ${p.xp} XP`));
        list.appendChild(li);
      });
    })
    .catch(() => showMessage('Leaderboard unavailable right now.'));
})();
