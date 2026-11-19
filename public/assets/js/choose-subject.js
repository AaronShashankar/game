// Subject picker: forwards to the game with ?difficulty=&subject=.
const diff = (new URLSearchParams(location.search).get('difficulty') || 'easy').toLowerCase();

document.addEventListener('DOMContentLoaded', () => {
  const diffText = document.getElementById('difficultyText');
  diffText.textContent = `Difficulty: ${diff.toUpperCase()}`;
  diffText.style.color = { easy: '#4CAF50', medium: '#FF9800', hard: '#F44336' }[diff] || '';
});

function goBack() {
  location.href = App.url('dashboard');
}

function selectSubject(subject) {
  location.href = `${App.url('game')}?difficulty=${encodeURIComponent(diff)}&subject=${encodeURIComponent(subject)}`;
}
