# MazeQuest - Quick Functions Reference 🚀

Presentation को लागि झट्टै हेर्न सकिने functions list।

---

## 🎮 GAME.HTML (31 Functions)

### Maze & Rendering (7)
| Function | काम |
|----------|-----|
| `generateMaze()` | DFS algorithm ले maze बनाउँछ |
| `carve(x,y)` | Recursive - walls हटाउँछ |
| `shuffle(array)` | Random order arrange गर्छ |
| `generateGates()` | Quiz gates place गर्छ |
| `drawMaze()` | Canvas मा maze render गर्छ |
| `drawPlayer()` | Player draw गर्छ |
| `drawEnd()` | Exit flag draw गर्छ |

### Game Logic (8)
| Function | काम |
|----------|-----|
| `checkGate()` | Gate collision detect गर्छ |
| `openQuestion()` | Quiz popup देखाउँछ |
| `checkAnswer()` | Answer verify गर्छ |
| `checkWin()` | Level complete check गर्छ |
| `levelComplete()` | Next level prepare गर्छ |
| `handleKeyPress()` | Arrow keys handle गर्छ |
| `startGame()` | Game loop start गर्छ |
| `pauseGame()` | Game pause गर्छ |

### Power-ups (4)
| Function | काम |
|----------|-----|
| `useTimeFreeze()` | Timer freeze गर्छ |
| `useHint()` | Question hint दिन्छ |
| `useSkipGate()` | Gate skip गर्छ |
| `useScoreDoubler()` | Rewards double गर्छ |

### Shop (3)
| Function | काम |
|----------|-----|
| `showShop()` | Shop modal खोल्छ |
| `buyPowerUp()` | Single power-up किन्छ |
| `buyStarterPack()` / `buyMegaPack()` | Bundle किन्छ |

### User Data (3)
| Function | काम |
|----------|-----|
| `saveUser()` | Data save trigger गर्छ |
| `updateHUD()` | HUD update गर्छ |
| `loadUserFromDB()` | Database बाट load गर्छ |

### Backend Sync (5)
| Function | काम |
|----------|-----|
| `checkBackend()` | Backend check गर्छ |
| `syncProfileNow()` | Immediate sync गर्छ |
| `scheduleSync()` | Debounced sync (800ms) |
| `startServerSession()` | Game session start गर्छ |
| `endServerSession()` | Game session end गर्छ |

### Init (1)
| Function | काम |
|----------|-----|
| `initGame()` | Game initialize गर्छ |

---

## 🏠 DASHBOARD.HTML (21 Functions)

### Profile (6)
| Function | काम |
|----------|-----|
| `updateProfileInfo()` | Profile display update |
| `openProfilePanel()` | Edit modal खोल्छ |
| `closeProfilePanel()` | Modal बन्द गर्छ |
| `saveProfilePanel()` | Changes save गर्छ |
| `handleImageUpload()` | Avatar upload गर्छ |
| `handlePanelImageUpload()` | Panel मा upload गर्छ |

### Dashboard (1)
| Function | काम |
|----------|-----|
| `updateDashboard()` | सबै stats update गर्छ |

### Sync (3)
| Function | काम |
|----------|-----|
| `syncProfile()` | Database sync गर्छ |
| `loadProfileFromServer()` | Database बाट load |
| `checkBackend()` | Connection test गर्छ |

### Rewards (4)
| Function | काम |
|----------|-----|
| `claimBonus()` | Daily bonus claim |
| `openSpinWheel()` | Spin modal खोल्छ |
| `closeSpinWheel()` | Modal बन्द गर्छ |
| `spinWheel()` | Lucky spin execute |

### Leaderboard (2)
| Function | काम |
|----------|-----|
| `loadLeaderboard(type)` | Leaderboard load गर्छ |
| `escapeHtml(text)` | XSS prevention |

### UI (3)
| Function | काम |
|----------|-----|
| `showPopup(msg)` | Toast notification |
| `toggleDarkMode()` | Theme toggle गर्छ |
| `toggleMusic()` | Audio mute/unmute |

### Navigation (2)
| Function | काम |
|----------|-----|
| `startGame(difficulty)` | Game page जान्छ |
| `logout()` | Logout गर्छ |

### Init (1)
| Function | काम |
|----------|-----|
| `init()` (IIFE) | Dashboard initialize |

---

## 🔧 PHP BACKEND (9 Functions)

### Authentication (3)
| File | काम |
|------|-----|
| `login.php` | User login process |
| `logout.php` | Session destroy गर्छ |
| `register.php` | New user create गर्छ |

### Profile (2)
| File | काम |
|------|-----|
| `user_profile.php` | User data fetch गर्छ |
| `sync_profile.php` | User data update गर्छ |

### Game Data (2)
| File | काम |
|------|-----|
| `leaderboard.php` | Rankings generate गर्छ |
| `game_session_api.php` | Sessions manage गर्छ |

### Database (1)
| Class/Method | काम |
|--------------|-----|
| `Database::getConnection()` | PDO connection return |

### Config (1)
| File | काम |
|------|-----|
| `config.php` | Database credentials |

---

## 🎯 Function Statistics

```
Frontend JavaScript: 52 functions
├─ Game Logic:      31 functions
└─ Dashboard:       21 functions

Backend PHP:         9 APIs
├─ Auth:             3 APIs
├─ Profile:          2 APIs
├─ Game:             2 APIs
├─ Database:         1 class
└─ Config:           1 file

Total:              61 functions/endpoints
```

---

## 🔑 Most Important Functions (Top 10)

### For Presentation:

1. **`generateMaze()`** - DFS maze generation algorithm
2. **`syncProfile()`** - Database-first architecture core
3. **`loadLeaderboard()`** - Real-time ranking system
4. **`checkAnswer()`** - Quiz validation logic
5. **`claimBonus()`** - Daily reward system
6. **`useTimeFreeze()`** - Power-up example
7. **`sync_profile.php`** - Backend data sync API
8. **`leaderboard.php`** - Rankings API
9. **`user_profile.php`** - User data API
10. **`initGame()`** - Game initialization

---

## 💡 Key Algorithms

### 1. DFS Backtracking (Maze)
```
generateMaze() → carve(x,y) → recursive path creation
Perfect maze guaranteed!
```

### 2. Debounced Sync
```
User action → scheduleSync() → wait 800ms → syncProfileNow()
Optimized database writes!
```

### 3. Random Gate Placement
```
Loop cells → if(random < 0.07) → place gate
7% density distribution!
```

### 4. Leaderboard Ranking
```
SELECT * FROM users ORDER BY xp DESC LIMIT 10
Dynamic SQL ordering!
```

---

## 📊 Architecture Flow

```
User Action (Frontend)
    ↓
JavaScript Function
    ↓
API Call (Fetch)
    ↓
PHP Backend
    ↓
Database (MySQL)
    ↓
JSON Response
    ↓
Frontend Update
```

---

## ✅ Presentation Tips

**जब functions को बारे बोल्नु हो:**

1. **Maze Generation** - "DFS backtracking algorithm use गरेर unique mazes generate हुन्छ"

2. **Database Sync** - "हरेक action database मा immediate sync हुन्छ, localStorage use गर्दैनौं"

3. **Leaderboard** - "Real-time rankings चार categories मा - XP, Coins, Level, Streak"

4. **Power-ups** - "चार types - Time Freeze, Hint, Skip, Score Doubler"

5. **Security** - "सबै APIs मा PDO prepared statements र password hashing छ"

**Demo गर्दा देखाउने:**
- Maze generation (refresh गर्दा नयाँ maze)
- Quiz gates (answer गरेर gate खुल्ने)
- Leaderboard (tabs change गरेर हेर्ने)
- Daily bonus (claim गर्ने)
- Profile sync (coins change भएपछि refresh)

---

यो quick reference presentation गर्दा सजिलो हुनेछ! 🎯
