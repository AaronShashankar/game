<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Choose Subject — MazeQuest</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body {
      background: linear-gradient(to bottom right, #f4e9ff, #fff7f7);
      min-height: 100vh;
      padding: 40px;
    }
    header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 25px;
    }
    header button {
      background: #f2f2f2;
      border: none;
      border-radius: 8px;
      padding: 6px 12px;
      cursor: pointer;
      font-weight: 600;
    }
    h1 {
      font-size: 24px;
      font-weight: 700;
    }
    .subject-container {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.05);
      padding: 40px;
      text-align: center;
    }
    .difficulty {
      font-weight: 600;
      margin-bottom: 8px;
      font-size: 16px;
    }
    .subtitle {
      color: #666;
      font-size: 14px;
      margin-bottom: 30px;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }
    .card {
      border: 2px solid #eee;
      border-radius: 12px;
      padding: 25px;
      text-align: left;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.06);
      border-color: #d9d9ff;
    }
    .icon {
      font-size: 28px;
      background: #f6f6ff;
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      margin-bottom: 10px;
    }
    h3 {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 4px;
    }
    p {
      font-size: 13px;
      color: #555;
    }
  </style>
</head>
<body>

  <header>
    <button onclick="goBack()">← Back</button>
    <h1>Choose Subject</h1>
  </header>

  <div class="subject-container">
    <p class="difficulty" id="difficultyText">Difficulty: EASY</p>
    <p class="subtitle">Select a subject to start your maze adventure</p>

    <div class="grid">
      <div class="card" onclick="selectSubject('Mathematics')">
        <div class="icon">🧮</div>
        <h3>Mathematics</h3>
        <p>Test your knowledge in mathematics</p>
      </div>

      <div class="card" onclick="selectSubject('Science')">
        <div class="icon">🧪</div>
        <h3>Science</h3>
        <p>Test your knowledge in science</p>
      </div>

      <div class="card" onclick="selectSubject('General Knowledge')">
        <div class="icon">🌐</div>
        <h3>General Knowledge</h3>
        <p>Test your knowledge in general knowledge</p>
      </div>

      <div class="card" onclick="selectSubject('History')">
        <div class="icon">📖</div>
        <h3>History</h3>
        <p>Test your knowledge in history</p>
      </div>

      <div class="card" onclick="selectSubject('English Vocabulary')">
        <div class="icon">🔤</div>
        <h3>English Vocabulary</h3>
        <p>Test your knowledge in english vocabulary</p>
      </div>

      <div class="card" onclick="selectSubject('Mixed Mode')">
        <div class="icon">🔀</div>
        <h3>Mixed Mode</h3>
        <p>Test your knowledge in mixed mode</p>
      </div>
    </div>
  </div>

  <script>
    // Get difficulty from query parameter
    const urlParams = new URLSearchParams(window.location.search);
    const diff = urlParams.get('difficulty') || 'easy';

    // Difficulty Display + Color
    const diffText = document.getElementById('difficultyText');
    diffText.textContent = `Difficulty: ${diff.toUpperCase()}`;
    if(diff === 'easy') diffText.style.color = '#4CAF50';
    if(diff === 'medium') diffText.style.color = '#FF9800';
    if(diff === 'hard') diffText.style.color = '#F44336';

    // Back button → dashboard
    function goBack() {
      window.location.href = '<?= url('dashboard') ?>';
    }

    // Subject click → open game page
    function selectSubject(subject) {
      window.location.href = `<?= url('game') ?>?difficulty=${diff}&subject=${encodeURIComponent(subject)}`;
    }
  </script>
</body>
</html>
