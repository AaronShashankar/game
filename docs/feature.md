# MazeQuest — Features

Every feature in the current build, with the exact rules and numbers the server applies. All numbers come from [`src/Services/GameRules.php`](../src/Services/GameRules.php) unless noted, so changing them there changes the game and the copy on the pages.

**Contents:**
1. [Accounts and sessions](#1-accounts-and-sessions)
2. [Core gameplay](#2-core-gameplay)
3. [Game modes](#3-game-modes)
4. [Power-ups and pickups](#4-power-ups-and-pickups)
5. [Learning](#5-learning)
6. [Progression and economy](#6-progression-and-economy)
7. [Shop and cosmetics](#7-shop-and-cosmetics)
8. [Competition and social](#8-competition-and-social)
9. [Achievements](#9-achievements)
10. [Player pages](#10-player-pages)
11. [Administration](#11-administration)
12. [Quality features](#12-quality-features)
13. [Not implemented](#13-not-implemented)
14. [Feature-to-API map](#14-feature-to-api-map)

---

## 1. Accounts and sessions

| Feature | Rules |
|---|---|
| **Registration** | Username 3–30 characters (`A–Z a–z 0–9 _ . -`), valid email, password ≥ 6 characters and confirmed. Username and email must be unique. Starts with **100 coins** and **3 of each power-up**. Signs the player straight in |
| **Player login** | Email + password. Throttled to **10 failed attempts per IP per 15 minutes**. Deactivated accounts are refused |
| **Return to the page you wanted** | Opening a player page while signed out goes to `/user?next=<page>`. After login or sign-up the player lands back there. Only same-site paths are accepted |
| **Admin login** | Separate page (`/admin`), separate `admin` table and session key |
| **Profile** | Change username, email and avatar (image URL, or an uploaded PNG/JPEG/GIF/WebP/SVG ≤ ~200 KB) |
| **Password change** | Requires the current password. Players need ≥ 6 characters, admins ≥ 8. **Signs out every other device**; the current one stays signed in. Resetting an admin password with `create_admin.php` signs that admin out everywhere |
| **Session check** | Every protected page and API call re-checks the account. A deleted or deactivated player, a deleted admin, or a session from before a password change is signed out on the next request (APIs return 401, pages go to the login page) |
| **Logout** | Destroys the session and the cookie |
| **Themes** | Light and dark. Follows the OS setting until the player picks one; the choice is remembered in the browser |

## 2. Core gameplay

The player walks from the top-left entrance to the bottom-right exit. Numbered **gates** block the only path. Walking into a gate opens a multiple-choice question, and a correct answer opens the gate.

| Difficulty | Maze | Gates | Timer | Wrong-answer penalty | Fog of war | Coin pickups | Reward multiplier | Minimum finish time |
|---|---|---|---|---|---|---|---|---|
| **Easy** | 10 × 10 | 3 | none | 0 | no | 3 | × 1.0 | 6 s |
| **Medium** | 15 × 15 | 5 | 150 s | 5 coins | no | 4 | × 1.5 | 10 s |
| **Hard** | 20 × 20 | 7 | 240 s | 10 coins | radius 3.5 cells | 5 | × 2.0 | 15 s |

- **Subjects:**
  - Mathematics, Science, General Knowledge, History and English Vocabulary.
  - **Mixed mode** draws from all of them.
- **Controls:**
  - Arrow keys or WASD.
  - Swipe on the maze, or the on-screen arrow pad, which appears on touch screens. Holding a direction repeats the move.
  - Keys `1`–`4` answer a question, or use a power-up when no question is open.
  - `Enter`, `Space` or `Esc` closes the answer feedback.
- **Answer feedback:**
  - After answering, the correct option is highlighted, with a short explanation.
  - A correct answer closes the question on its own: after 1.8 s if there is an explanation, 0.9 s if not.
  - A wrong answer waits for **Continue**.
- **Retries:** the next attempt at the same gate gets a **different question**. The first try pays full, the second pays half, and the third or later pays nothing.
- **Three wrong answers in a row** send the player back to the start. This rule runs in the browser.
- **Timer:**
  - When the time runs out, the player goes back to the start with a fresh clock.
  - Cleared gates stay cleared, and the combo resets.
- **Exit:** reaching the exit with every gate cleared or skipped completes the maze.
- **Run summary:**
  - Time, accuracy, best combo and coins found.
  - An itemised list of rewards: gates, completion, time bonus, pickups, daily bonus.
  - Any level-up and new achievements.
  - **Worth another look:** every missed question with your answer, the right answer and the explanation.
  - The daily rank, or the result against the challenger.
  - Buttons: Next maze, Replay (same layout), Challenge a friend, Back to dashboard.
- **Sound:** short synthesized effects for gate, correct, wrong, pickup, power-up, timeout and completion. A mute button is remembered per device.
- **Restart and replay:** replays the same maze layout as a new run, using the same seed.

## 3. Game modes

| Mode | How to start | What is special |
|---|---|---|
| **Normal** | Choose difficulty and subject (`/choose-subject`) | Random layout. Questions prefer the maze's difficulty. Up to 2 due **review** questions are mixed in |
| **Daily maze** | Dashboard → *Today's maze* (`/game?daily=1`) | The same Medium, mixed-subject maze for everyone that day, with the same questions in the same order. **One attempt**: leaving and coming back resumes it, and the clock keeps running. Finishing pays **+50 coins, +20 XP**. Ranked by time on the daily leaderboard. Can't be replayed or shared |
| **Challenge** | A friend's link (`/challenge?code=XXXXXXXX`) | Same layout, difficulty and subject as the friend's finished maze. Each gate asks the question the friend was asked at that attempt. The friend's time is shown during the run and compared at the end. Can't challenge your own maze |

## 4. Power-ups and pickups

| Power-up | Key | Effect | Shop price |
|---|---|---|---|
| **Time Freeze** | 1 | Stops the clock for 10 s (extends the deadline). Not usable on Easy, which has no timer; the item isn't used up | 25 |
| **Hint Key** | 2 | Highlights the nearest closed gate (visible through fog) | 20 |
| **Skip Gate** | 3 | Opens the nearest closed gate with no reward | 30 |
| **Score Doubler** | 4 | Doubles the coins and XP of the next correct answer | 40 |

The server checks a power-up before using one up, so a rejected use (for example, a time freeze while frozen) costs nothing.

**Coin pickups:**
- Coins sit in dead ends: 3, 4 or 5 per maze. Reaching one means leaving the main path.
- Each pays **5 coins**, credited only when the maze is **completed**.

## 5. Learning

| Feature | Rules |
|---|---|
| **Question bank** | 145 questions (71 Easy, 52 Medium, 22 Hard), each with a one-line explanation |
| **Difficulty matching** | A maze asks questions tagged with its own difficulty first; when those run out it uses the others |
| **No repeats in a maze** | A question isn't asked twice in the same run while unused ones remain |
| **Spaced repetition** | A missed question goes into review box 1 and is due straight away. Each later correct answer moves it up a box, due again in **1, 3, 7, 14, then 30 days**. After that it counts as learned. Any wrong answer sends it back to box 1. Review questions show a **Review** tag |
| **Subject mastery** | Per subject: the share of its questions answered correctly at least once, plus overall accuracy. Shown on the profile and the dashboard |
| **Learning card** | The dashboard shows how many reviews are due and how many questions are being learned |

## 6. Progression and economy

| Feature | Rules |
|---|---|
| **Gate reward** | 10 coins + combo bonus (+2 per answer in a row after the first, max +10) and 5 XP. Half on the second try, nothing after that. ×2 with Score Doubler |
| **Completion reward** | round(50 × multiplier) coins + **time bonus** (1 coin per 5 s left), and round(20 × multiplier) XP |
| **Levels** | XP is lifetime. Level L starts at 50·L·(L−1) XP: level 2 = 100, 3 = 300, 5 = 1,000, 10 = 4,500 |
| **Daily check-in** | 100 coins, plus 10 × streak when the streak is above 1. Once per day |
| **Streak freeze** | Each held freeze covers one missed day at the next check-in. If the player doesn't hold enough to cover the gap, the streak resets to 1 and the freezes are kept |
| **Lucky spin** | 5 coins per spin, **3 spins a day**. Six equal segments: 10, 25 or 50 coins, or a Time Freeze, Hint Key or Skip Gate. The server picks the segment |
| **XP chart** | The dashboard shows XP earned per day over the last 7 days |
| **Ledger** | Every coin and XP change is recorded with a reason. Balances can't go negative or be set from the browser |

## 7. Shop and cosmetics

| Item | Kind | Price | Notes |
|---|---|---|---|
| Time Freeze, Hint Key, Skip Gate, Score Doubler | power-up | 25 / 20 / 30 / 40 | |
| Power-Up Pack | pack | 100 | 1 of each power-up |
| Mega Pack | pack | 250 | 3 of each power-up |
| Streak Freeze | perk | 50 | Hold up to 2 |
| Blue Skin, Red Hero | skin | 200 / 350 | Player colour on the board |
| Forest Theme, Space Theme | theme | 300 / 450 | Maze palette (Space adds a star field) |
| Speed Boost, Shield | power-up | — | Listed as "coming soon", not on sale |

- **Pricing:** prices always come from the database, never from the request.
- **Cosmetics:**
  - Can be bought once and are **equipped automatically** when bought.
  - The shop has **Equip / Unequip**. Only owned items can be equipped.
- **In-game shop:** a quick shop during a maze for buying power-ups without leaving the game.

## 8. Competition and social

| Feature | Rules |
|---|---|
| **Leaderboards** | Public (no login needed). Boards: **XP, Coins, Level, Streak, This week** (XP earned since Monday) and **Today's maze** (fastest daily finish). Inactive players are hidden. Up to 100 rows |
| **Weekly prizes** | Last week's top 3 by XP earned get **300 / 200 / 100 coins**. Admin grants and migrated balances don't count. Paid once, the first time anyone loads their dashboard or the weekly board in the new week. The dashboard shows last week's winners |
| **Challenge a friend** | Every completed maze (except the daily) gets an 8-character link. The public challenge page shows the challenger, difficulty, gates and time to beat. Guests are sent to register or log in, then straight into the game |
| **Daily leaderboard** | Shown on the dashboard (top times and your rank) and on the leaderboard page |

## 9. Achievements

13 achievements, checked by the server after answers, completed mazes and check-ins:

| Achievement | Requirement |
|---|---|
| First steps | Finish your first maze |
| Flawless | Finish a maze without a wrong answer or a skip |
| On a roll | 5 correct answers in a row in one maze |
| Into the fog | Finish a Hard maze |
| Speedrunner | Finish a Medium or Hard maze in under 90 s |
| Daily explorer | Finish a daily maze |
| Challenger | Beat a friend's time on their maze |
| All-rounder | Finish a maze in every subject |
| Scholar | Answer 100 questions correctly |
| Rising star | Reach level 5 |
| Veteran | Reach level 10 |
| Rich explorer | Hold 500 coins at once |
| Dedicated | Keep a 10-day check-in streak |

New unlocks appear as toasts. The profile and dashboard show the full list with unlock dates.

## 10. Player pages

| Page | URL | Contents |
|---|---|---|
| Landing | `/` | What the game is, features, how it works, sign-up |
| How to play | `/howtoplay` | Steps, rewards table, power-ups, difficulty table, more ways to play |
| Leaderboard | `/leaderboard` | All boards (public) |
| Login / Register | `/user`, `/register` | Forms, keeping `?next=` |
| Dashboard | `/dashboard` | Start a game, daily rewards (check-in, spin, streak freezes), today's maze, learning, weekly prizes, XP chart, achievements, top players |
| Choose subject | `/choose-subject` | Difficulty and subject picker |
| Game | `/game` | Maze, HUD (coins, level, XP, combo, timer, gates, coins found), power-ups, question modal, summary, in-game shop |
| Shop | `/shop` | Power-ups, packs, perks, cosmetics with Equip/Unequip |
| Profile | `/profile` | Progress, account, password, inventory, subject mastery, achievements |
| Challenge | `/challenge?code=` | Public challenge landing page |

The app shell has a sidebar on desktop and a bottom tab bar on phones. Layouts work down to 390 px wide.

## 11. Administration

| Tab | Features |
|---|---|
| **Overview** | Players, coins in circulation, active players (24 h), average level, total XP, completed mazes, gates opened, correct answers; recent activity feed |
| **Players** | List of all players with stats; **add a player** with starting coins (0–1,000,000) and level (1–100), recorded as an `admin_grant`; **delete** a player |
| **Subjects** | Add a subject (name, optional description, icon); it starts **hidden**. **Show** it once it has at least 7 active questions (enough for a Hard maze without repeats); **hide** it to take it out of play, including Mixed mode and the daily maze. **Rename** (questions and past runs follow). **Delete** only while it has no questions. Per-subject question counts by difficulty; **Add question** opens the editor with the subject chosen |
| **Questions** | Search and filter by subject, difficulty and status; answer counts and accuracy per question; **create and edit** (an existing subject, 2–4 options, the correct option, explanation, difficulty; **Save and add another** keeps the subject and difficulty); **archive and restore**; **delete** one question or **select several** (or all shown) and delete them together — deleting is permanent and drops the question from past game summaries, so archiving is offered as the way to keep history; **CSV import** (all-or-nothing, row-level errors, duplicates skipped, ≤ 500 KB / 1,000 rows) with a downloadable template |
| **Analytics** | Average game length, answer success rate, most popular subject, daily active players, completion rate, the 5 hardest questions (≥ 3 answers) |
| **Security** | The last 100 security events: logins, failures, rejected game events, with IP |
| **Data** | JSON export of players, runs, questions and items; admin password change |

Admins are created only from the command line (`database/create_admin.php`). The script refuses to overwrite an admin whose email or username doesn't match exactly.

## 12. Quality features

- **Cheat resistance:**
  - The server decides every reward.
  - Answers sent less than 1 s after the question was served are rejected.
  - Completion needs every gate cleared and a minimum time.
  - Suspicious requests are logged.
- **Idempotent actions:** retrying an answer, a pickup, a check-in or a reward after a network error never pays twice.
- **Accessibility:** the game can be played entirely by keyboard. Answer feedback and toasts are announced (`aria-live`), and focus is visible. Answer results use an icon and words as well as colour.
- **Responsive:** phone, tablet and desktop layouts; high-DPI canvas.
- **Automated tests:** 36 integration tests against a throwaway database (`docker compose exec -T web php tests/run.php`).

## 13. Not implemented

| Item | State |
|---|---|
| Speed Boost, Shield power-ups | In the catalogue as "coming soon"; no game logic |
| Referrals | Removed from the UI (never implemented) |
| Password reset by email, email verification | Not implemented |
| Server-side movement validation | Not implemented (see [systemdesign.md §12](systemdesign.md#12-review-findings)) |
| Limit on challenge retries | A friend can retry a challenge as often as they like |
| Saving the player's position in a resumed daily maze | Cleared gates and coins are kept; the position restarts at the entrance |

## 14. Feature-to-API map

| Feature | Endpoints |
|---|---|
| Accounts | `POST /api/auth/register`, `/api/auth/login`, `/api/auth/admin-login`, `/api/auth/logout`, `/api/auth/password` |
| Profile, dashboard data | `GET/POST /api/me` |
| Daily rewards | `POST /api/checkin`, `POST /api/spin` |
| Shop | `GET /api/shop`, `POST /api/shop/buy`, `POST /api/shop/equip` |
| Daily maze status | `GET /api/daily` |
| Leaderboards | `GET /api/leaderboard?type=xp|coins|level|streak|weekly|daily&limit=` |
| Game runs | `POST /api/runs/start` (`mode` = normal / daily / challenge), `/question`, `/answer`, `/powerup`, `/pickup`, `/complete`, `/timeout` |
| Admin | `GET /api/admin/stats`, `/users`, `/activity`, `/security-logs`, `/analytics`, `/export`, `/questions`, `/subjects`; `POST /api/admin/users`, `/users/delete`, `/password`, `/questions`, `/questions/status`, `/questions/import`, `/questions/delete`, `/subjects`, `/subjects/visibility`, `/subjects/delete` |
