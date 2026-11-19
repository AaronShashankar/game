// Subject picker: choose a difficulty tab, then a subject → /game?difficulty=&subject=.
(function () {
  const { difficulties } = window.GameConfig;
  let difficulty = window.GameConfig.selected;

  function renderSpec() {
    const d = difficulties[difficulty];
    const chips = [
      `${d.size} × ${d.size} maze`,
      `${d.gates} gates`,
      d.time ? `${Math.floor(d.time / 60)}:${String(d.time % 60).padStart(2, '0')} time limit` : 'No timer',
      `×${d.mult} rewards`,
    ];
    if (d.fog) chips.push('Fog of war');
    document.getElementById('diffSpec').innerHTML = chips.map((c) => `<span class="chip">${c}</span>`).join('');
  }

  document.querySelectorAll('[data-difficulty]').forEach((tab) => {
    tab.addEventListener('click', () => {
      difficulty = tab.dataset.difficulty;
      document.querySelectorAll('[data-difficulty]').forEach((t) => {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-checked', t === tab ? 'true' : 'false');
      });
      history.replaceState(null, '', `?difficulty=${difficulty.toLowerCase()}`);
      renderSpec();
    });
  });

  document.querySelectorAll('[data-subject]').forEach((card) => {
    card.addEventListener('click', () => {
      location.href = `${App.url('game')}?difficulty=${encodeURIComponent(difficulty.toLowerCase())}&subject=${encodeURIComponent(card.dataset.subject)}`;
    });
  });

  renderSpec();
})();
