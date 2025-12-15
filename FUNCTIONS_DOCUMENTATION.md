# MazeQuest - All Functions Documentation 📚

यो document मा project का सबै important functions र तिनीहरूको काम listed छ।

---

## 🎮 GAME.HTML - Game Functions

### **Maze Generation Functions**

#### 1. `generateMaze()`
**के गर्छ:** DFS backtracking algorithm use गरेर maze structure बनाउँछ
```javascript
- सबै cells मा walls initialize गर्छ (top, right, bottom, left)
- Recursive carving algorithm call गर्छ
- Start र end points create गर्छ
- Perfect maze generate गर्छ (guaranteed solution)
```

#### 2. `carve(x, y)` (Nested in generateMaze)
**के गर्छ:** Recursive function - walls हटाएर paths बनाउँछ
```javascript
- Current cell लाई visited mark गर्छ
- Neighbors लाई random order मा check गर्छ
- Unvisited neighbors सँग walls हटाउँछ
- Recursively अगाडि बढ्छ (DFS)
```

#### 3. `shuffle(array)` (Nested in generateMaze)
**के गर्छ:** Array elements लाई random order मा arrange गर्छ
```javascript
- Fisher-Yates shuffle algorithm use गर्छ
- Maze generation लाई random बनाउँछ
```

#### 4. `generateGates()`
**के गर्छ:** Maze मा quiz gates randomly place गर्छ
```javascript
- GATE_DENSITY (7%) अनुसार gates राख्छ
- Start र end cells मा gates राख्दैन
- Subject assign गर्छ
- opened: false initialize गर्छ
```

---

### **Rendering Functions**

#### 5. `drawMaze()`
**के गर्छ:** Canvas मा maze render गर्छ
```javascript
- Canvas clear गर्छ
- Background gradient apply गर्छ
- सबै walls draw गर्छ (top, right, bottom, left)
- Gates visualize गर्छ (orange = closed, blue = opened)
- Sparkle effects add गर्छ
- Player र End flag draw गर्छ
```

#### 6. `drawPlayer()`
**के गर्छ:** Player character canvas मा draw गर्छ
```javascript
- Player को position calculate गर्छ
- Circle shape draw गर्छ
- Gradient fill apply गर्छ
```

#### 7. `drawEnd()`
**के गर्छ:** Exit flag canvas मा draw गर्छ
```javascript
- End position मा flag icon
- Victory indicator
```

---

### **Game Logic Functions**

#### 8. `checkGate()`
**के गर्छ:** Player gate मा पुगेको check गर्छ
```javascript
- Current position मा gate छ कि check
- Unopened gate भेट्टाए popup देखाउँछ
- Question load गर्छ
- Game pause गर्छ
```

#### 9. `openQuestion(gate)`
**के गर्छ:** Quiz question popup देखाउँछ
```javascript
- Gate को subject अनुसार question select गर्छ
- Options display गर्छ
- Timer start गर्छ (optional)
- Event listeners attach गर्छ
```

#### 10. `checkAnswer(chosen, correct)`
**के गर्छ:** User को answer check गर्छ
```javascript
- Correct: coins + XP दिन्छ, gate खोल्छ
- Wrong: penalty apply गर्छ
- Stats update गर्छ (correct_answers/wrong_answers)
- Popup close गर्छ
- saveUser() call गर्छ
```

#### 11. `checkWin()`
**के गर्छ:** Player end point मा पुग्यो कि check
```javascript
- Position match गर्छ
- Level complete popup देखाउँछ
- Rewards calculate गर्छ
- Next level prepare गर्छ
```

#### 12. `levelComplete()`
**के गर्छ:** Level complete भएपछि actions
```javascript
- XP + Coins rewards दिन्छ
- Level up गर्छ (if threshold crossed)
- New maze generate गर्छ
- Player position reset गर्छ
- Timer reset गर्छ
```

---

### **Movement & Control Functions**

#### 13. `handleKeyPress(event)`
**के गर्छ:** Keyboard input handle गर्छ
```javascript
- Arrow keys detect गर्छ
- Wall collision check गर्छ
- Valid moves allow गर्छ
- Player position update गर्छ
- checkGate() call गर्छ
```

#### 14. `startGame()`
**के गर्छ:** Game loop start गर्छ
```javascript
- Timer start गर्छ
- Animation loop begin गर्छ
- Controls enable गर्छ
- Backend session start गर्छ
```

#### 15. `pauseGame()`
**के गर्छ:** Game temporarily pause गर्छ
```javascript
- Timer stop गर्छ
- Controls disable गर्छ
- Animation loop pause गर्छ
```

---

### **Power-up Functions**

#### 16. `useTimeFreeze()`
**के गर्छ:** Timer freeze गर्छ
```javascript
- countdown stop गर्छ
- 10 seconds को लागि freeze
- Power-up count decrease गर्छ
- Visual effect देखाउँछ
```

#### 17. `useHint()`
**के गर्छ:** Current question को hint देखाउँछ
```javascript
- Wrong options filter गर्छ
- 50-50 style hint
- Power-up count decrease गर्छ
```

#### 18. `useSkipGate()`
**के गर्छ:** Current gate skip गर्छ
```javascript
- Gate automatically open गर्छ
- No question popup
- Power-up count decrease गर्छ
```

#### 19. `useScoreDoubler()`
**के गर्छ:** Next answer को reward double गर्छ
```javascript
- scoreDoubler flag set गर्छ
- Next correct answer मा 2x coins/XP
- Power-up count decrease गर्छ
```

---

### **Shop Functions**

#### 20. `showShop()`
**के गर्छ:** Power-up shop display गर्छ
```javascript
- Shop modal open गर्छ
- Available power-ups देखाउँछ
- Prices display गर्छ
```

#### 21. `buyPowerUp(type, cost)`
**के गर्छ:** Power-up purchase गर्छ
```javascript
- Coins check गर्छ (sufficient?)
- Coins deduct गर्छ
- Power-up count increase गर्छ
- saveUser() call गर्छ
```

#### 22. `buyStarterPack()` / `buyMegaPack()`
**के गर्छ:** Bundle purchase गर्छ
```javascript
- Multiple power-ups एकैचोटि दिन्छ
- Discounted price
- Coins deduct गर्छ
```

---

### **User & Data Functions**

#### 23. `saveUser()`
**के गर्छ:** User data save गर्छ
```javascript
- scheduleSync() call गर्छ (debounced)
- Database मा save गर्न trigger गर्छ
```

#### 24. `updateHUD()`
**के गर्छ:** Game HUD update गर्छ
```javascript
- Coins display update
- XP display update
- Level display update
- Streak display update
- Power-up counts update
```

#### 25. `loadUserFromDB()`
**के गर्छ:** Database बाट user data load गर्छ
```javascript
- user_profile.php API call गर्छ
- Response parse गर्छ
- user object populate गर्छ
- Error handling गर्छ
```

---

### **Backend Sync Functions**

#### 26. `checkBackend()`
**के गर्छ:** Backend availability check गर्छ
```javascript
- Test API call गर्छ
- backendAvailable flag set गर्छ
- Connection status determine गर्छ
```

#### 27. `syncProfileNow()`
**के गर्छ:** Database मा immediate sync गर्छ
```javascript
- FormData prepare गर्छ
- sync_profile.php call गर्छ
- Success/failure handle गर्छ
- Console log गर्छ
```

#### 28. `scheduleSync()`
**के गर्छ:** Debounced sync schedule गर्छ
```javascript
- 800ms delay सँग sync
- Multiple rapid calls लाई batch गर्छ
- Performance optimize गर्छ
```

#### 29. `startServerSession()`
**के गर्छ:** Game session backend मा track गर्छ
```javascript
- game_session_api.php call गर्छ
- Session ID receive गर्छ
- Start time record गर्छ
```

#### 30. `endServerSession()`
**के गर्छ:** Game session end गर्छ
```javascript
- Score report गर्छ
- End time record गर्छ
- Session close गर्छ
```

---

### **Initialization Function**

#### 31. `initGame()`
**के गर्छ:** Game initialize गर्छ
```javascript
- Backend check गर्छ
- User data load गर्छ
- Maze generate गर्छ
- Gates place गर्छ
- Canvas render गर्छ
- Event listeners attach गर्छ
```

---

## 🏠 DASHBOARD.HTML - Dashboard Functions

### **Profile Functions**

#### 32. `updateProfileInfo()`
**के गर्छ:** Profile section update गर्छ
```javascript
- Username display गर्छ
- Email display गर्छ
- Avatar update गर्छ
```

#### 33. `openProfilePanel()`
**के गर्छ:** Profile edit panel खोल्छ
```javascript
- Modal display गर्छ
- Current values populate गर्छ
- Edit mode enable गर्छ
```

#### 34. `closeProfilePanel()`
**के गर्छ:** Profile panel बन्द गर्छ
```javascript
- Modal hide गर्छ
- Unsaved changes discard गर्छ
```

#### 35. `saveProfilePanel()`
**के गर्छ:** Profile changes save गर्छ
```javascript
- Input values collect गर्छ
- Validation गर्छ
- syncProfile() call गर्छ
- Success message देखाउँछ
```

#### 36. `handleImageUpload(event)`
**के गर्छ:** Avatar image upload गर्छ
```javascript
- File read गर्छ
- Base64 convert गर्छ
- Avatar update गर्छ
- syncProfile() call गर्छ
```

---

### **Dashboard Update Functions**

#### 37. `updateDashboard()`
**के गर्छ:** Dashboard stats update गर्छ
```javascript
- Coins display update
- Level display update
- XP display update
- Streak display update
- XP progress bar update
- Badges display update
```

---

### **Sync Functions**

#### 38. `syncProfile()`
**के गर्छ:** Database मा profile sync गर्छ
```javascript
- Backend availability check गर्छ
- FormData prepare गर्छ
- sync_profile.php POST request
- Success/error handle गर्छ
- User feedback provide गर्छ
```

#### 39. `loadProfileFromServer()`
**के गर्छ:** Database बाट profile load गर्छ
```javascript
- user_profile.php GET request
- Response parse गर्छ
- user object update गर्छ
- UI refresh गर्छ
```

#### 40. `checkBackend()`
**के गर्छ:** Backend connection test गर्छ
```javascript
- Test API call गर्छ
- Timeout handle गर्छ
- backendAvailable flag set गर्छ
```

---

### **Bonus & Rewards Functions**

#### 41. `claimBonus()`
**के गर्छ:** Daily bonus claim गर्छ
```javascript
- Last check-in date verify गर्छ
- Coins + XP award गर्छ
- Streak increment गर्छ
- lastCheckin update गर्छ
- syncProfile() call गर्छ
```

#### 42. `openSpinWheel()`
**के गर्छ:** Spin wheel modal खोल्छ
```javascript
- Modal display गर्छ
- Animation prepare गर्छ
```

#### 43. `closeSpinWheel()`
**के गर्छ:** Spin wheel बन्द गर्छ
```javascript
- Modal hide गर्छ
```

#### 44. `spinWheel()`
**के गर्छ:** Lucky spin execute गर्छ
```javascript
- 5 coins deduct गर्छ
- Random rotation calculate गर्छ
- Wheel animate गर्छ
- Reward determine गर्छ
- Reward apply गर्छ
- syncProfile() call गर्छ
```

---

### **Leaderboard Functions**

#### 45. `loadLeaderboard(type)`
**के गर्छ:** Leaderboard data load गर्छ
```javascript
- leaderboard.php API call गर्छ
- Type filter (xp/coins/level/streak)
- Response parse गर्छ
- HTML generate गर्छ
- Display update गर्छ
```

#### 46. `escapeHtml(text)`
**के गर्छ:** XSS prevention
```javascript
- HTML special characters escape गर्छ
- Security ensure गर्छ
```

---

### **UI Functions**

#### 47. `showPopup(msg)`
**के गर्छ:** Toast notification देखाउँछ
```javascript
- Message display गर्छ
- 2 seconds auto-hide
- Visual feedback provide गर्छ
```

#### 48. `toggleDarkMode()`
**के गर्छ:** Dark/Light theme toggle गर्छ
```javascript
- body class toggle गर्छ
- Theme preference save गर्छ
```

#### 49. `toggleMusic()`
**के गर्छ:** Background music mute/unmute
```javascript
- Audio play/pause गर्छ
- Button icon update गर्छ
```

---

### **Game Start Function**

#### 50. `startGame(difficulty)`
**के गर्छ:** Game page मा redirect गर्छ
```javascript
- Difficulty parameter pass गर्छ
- game.html navigate गर्छ
```

---

### **Authentication Function**

#### 51. `logout()`
**के गर्छ:** User logout गर्छ
```javascript
- logout.php call गर्छ
- Session destroy गर्छ
- login.html redirect गर्छ
```

---

### **Initialization Function**

#### 52. `init()` (IIFE - Immediately Invoked)
**के गर्छ:** Dashboard initialize गर्छ
```javascript
- Backend check गर्छ
- Profile data load गर्छ
- Dashboard update गर्छ
- Leaderboard load गर्छ
- URL parameters check गर्छ (openSpin flag)
```

---

## 🔧 PHP BACKEND - API Functions

### **Authentication APIs**

#### 53. `login.php`
**के गर्छ:** User login process गर्छ
```php
- POST data receive गर्छ
- Database query गर्छ
- Password verify गर्छ (password_verify)
- Session create गर्छ ($_SESSION)
- JSON response return गर्छ
```

#### 54. `logout.php`
**के गर्छ:** User logout process गर्छ
```php
- Session data clear गर्छ
- Session cookie destroy गर्छ
- session_destroy() call गर्छ
- Success response return गर्छ
```

#### 55. `register.php` (if exists)
**के गर्छ:** New user registration
```php
- Input validation गर्छ
- Password hash गर्छ (password_hash)
- Database INSERT गर्छ
- Auto-login गर्छ
```

---

### **Profile Management APIs**

#### 56. `user_profile.php`
**के गर्छ:** User profile data fetch गर्छ
```php
- Session check गर्छ (authenticated?)
- User ID extract गर्छ
- Database SELECT query
- User data fetch गर्छ
- JSON response return गर्छ
```

#### 57. `sync_profile.php`
**के गर्छ:** User data update गर्छ
```php
- Session authentication check
- POST data receive गर्छ
- Input validation गर्छ
- Database UPDATE query
- PDO prepared statements use गर्छ
- Success/error response return
```

---

### **Leaderboard API**

#### 58. `leaderboard.php`
**के गर्छ:** Leaderboard data generate गर्छ
```php
- Type parameter read गर्छ (xp/coins/level/streak)
- Database columns check गर्छ
- Dynamic ORDER BY query
- Top 10 players fetch गर्छ
- Rank assign गर्छ
- JSON array return गर्छ
```

---

### **Game Session API**

#### 59. `game_session_api.php`
**के गर्छ:** Game sessions manage गर्छ
```php
Actions:
- start_session: New session create गर्छ
- end_session: Session complete गर्छ, score record गर्छ
- my_sessions: User को sessions list गर्छ
- leaderboard: High scores fetch गर्छ
```

---

### **Database Connection**

#### 60. `config.php` - `Database::getConnection()`
**के गर्छ:** Database connection establish गर्छ
```php
- PDO instance create गर्छ
- Connection parameters set गर्छ
- Error mode set गर्छ (ERRMODE_EXCEPTION)
- Charset set गर्छ (utf8mb4)
- Connection return गर्छ
```

---

## 📊 Algorithm Summary

### **Maze Generation - DFS Backtracking**
```
1. Initialize: सबै cells मा walls
2. Pick start cell: (0,0)
3. Recursive carving:
   - Mark current as visited
   - Shuffle directions randomly
   - For each unvisited neighbor:
     - Remove wall between cells
     - Recurse to neighbor
   - Backtrack when stuck
4. Create entrance/exit
```

### **Gate Placement - Random Distribution**
```
1. Loop through all cells (except borders)
2. For each cell:
   - Generate random number
   - If < GATE_DENSITY (7%):
     - Place gate
     - Assign subject
     - Mark as unopened
```

### **Leaderboard Ranking - SQL ORDER BY**
```
1. Select user data (username, stats)
2. Order by selected metric DESC
3. Limit to top 10
4. Add rank number sequentially
5. Return as JSON array
```

### **Debounced Sync - Timer Pattern**
```
1. User action triggers save
2. Clear previous timer (if any)
3. Set new timer (800ms)
4. If another action within 800ms:
   - Cancel previous timer
   - Set new timer
5. After 800ms idle:
   - Execute sync
   - Send to database
```

---

## 🎯 Total Functions Count

**Frontend (JavaScript):**
- Game Functions: ~31
- Dashboard Functions: ~21
- **Total: ~52 functions**

**Backend (PHP):**
- API Endpoints: ~8
- Database Functions: ~1
- **Total: ~9 functions**

**Grand Total: ~61 main functions**

---

## 💡 Key Design Patterns

1. **MVC-like Separation**
   - Views: HTML/CSS
   - Controllers: JavaScript functions
   - Models: PHP APIs + MySQL

2. **API-First Architecture**
   - All data operations through APIs
   - JSON request/response
   - RESTful conventions

3. **Database-First**
   - Single source of truth
   - Real-time synchronization
   - No localStorage dependency

4. **Debouncing Pattern**
   - Optimize database writes
   - Reduce server load
   - Better performance

5. **Recursive Algorithms**
   - Maze generation (DFS)
   - Path finding
   - Clean, elegant code

---

यो comprehensive list presentation को लागि perfect छ! 🎉
