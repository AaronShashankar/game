<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test Leaderboard</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
    button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; }
    button:hover { background: #0056b3; }
    pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 500px; overflow-y: auto; }
    .error { color: red; }
    .success { color: green; }
  </style>
</head>
<body>
  <h1>🔧 Leaderboard Test</h1>
  
  <div>
    <button onclick="testLeaderboard('xp')">Test XP Leaderboard</button>
    <button onclick="testLeaderboard('coins')">Test Coins Leaderboard</button>
    <button onclick="testLeaderboard('level')">Test Level Leaderboard</button>
    <button onclick="testLeaderboard('streak')">Test Streak Leaderboard</button>
  </div>

  <div>
    <h2>Direct API Test:</h2>
    <button onclick="testDirect()">Test Direct URL</button>
  </div>

  <h2>Result:</h2>
  <div id="result"></div>

  <h2>Raw Response:</h2>
  <pre id="raw"></pre>

  <script>
    async function testLeaderboard(type) {
      const resultDiv = document.getElementById('result');
      const rawDiv = document.getElementById('raw');
      
      resultDiv.innerHTML = '<p>Testing leaderboard type: <strong>' + type + '</strong>...</p>';
      rawDiv.textContent = 'Loading...';
      
      try {
        const url = `leaderboard.php?type=${type}&limit=10`;
        console.log('Fetching:', url);
        
        const res = await fetch(url, {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json'
          }
        });
        
        console.log('Response status:', res.status);
        console.log('Response headers:', [...res.headers.entries()]);
        
        const text = await res.text();
        console.log('Raw response:', text);
        rawDiv.textContent = text;
        
        try {
          const data = JSON.parse(text);
          
          if (data.success) {
            let html = '<p class="success">✅ Success! Found ' + data.total_players + ' players</p>';
            html += '<h3>Leaderboard Data:</h3>';
            
            if (data.leaderboard && data.leaderboard.length > 0) {
              html += '<ul>';
              data.leaderboard.forEach(player => {
                html += '<li>';
                html += '<strong>#' + player.rank + '</strong> ';
                html += player.username + ' - ';
                html += 'Level ' + player.level + ', ';
                html += player.xp + ' XP, ';
                html += player.coins + ' coins, ';
                html += player.streak + ' streak';
                html += '</li>';
              });
              html += '</ul>';
            } else {
              html += '<p>No players found in database</p>';
            }
            
            resultDiv.innerHTML = html;
          } else {
            resultDiv.innerHTML = '<p class="error">❌ Error: ' + (data.message || 'Unknown error') + '</p>';
          }
        } catch(jsonErr) {
          resultDiv.innerHTML = '<p class="error">❌ JSON Parse Error: ' + jsonErr.message + '</p>';
        }
        
      } catch(e) {
        resultDiv.innerHTML = '<p class="error">❌ Fetch Error: ' + e.message + '</p>';
        rawDiv.textContent = 'Error: ' + e.message;
      }
    }

    function testDirect() {
      const url = 'leaderboard.php?type=xp&limit=10';
      window.open(url, '_blank');
    }

    // Auto-test on load
    window.onload = function() {
      testLeaderboard('xp');
    };
  </script>
</body>
</html>
