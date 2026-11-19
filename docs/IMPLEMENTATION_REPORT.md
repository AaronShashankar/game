# MazeQuest: restructure and server-authoritative progression

**Date:** 2026-11-19 · **Base commit:** `550310a` · **Status:** implemented and tested locally. Not committed yet.

## 1. Summary

MazeQuest was a set of standalone PHP pages and `api/*.php` scripts. The browser decided how many coins and how much XP a player had, then saved those numbers to the server. Anyone with the browser console could give themselves unlimited coins, levels and leaderboard rank. Parts of the admin API also worked without an admin login.

This work did two things:

1. **Restructured the codebase.** There is now a single front controller in `public/`. The application code lives in `src/` and is split into layers: Http → Services → Repositories. Templates are in `views/`. Configuration comes from `.env`. Database changes are numbered migrations that only run from the command line.
2. **Made the server the authority on progression.** Each game is a server-side *run*. The server picks the maze seed, serves questions without the answers, checks the answers, enforces time limits, and pays rewards through an append-only *ledger*. The browser only displays what the server returns.

All 52 end-to-end API checks pass. The main UI flows were tested in a real browser. A copy of the old database schema with sample data was migrated successfully.

## 2. Problems found in the original code

| # | Problem | Impact | Fix |
|---|---|---|---|
| 1 | `api/sync_profile.php` accepted `coins`, `xp`, `level` from the client | Any player could set any balance and level | Clients can no longer send balances. Every change goes through `ProgressionService` + `ledger`. |
| 2 | `api/admin_api.php` guarded only some actions (`require_admin()` was called per action) | Admin data was exposed to the public | Every `/api/admin/*` route is guarded with `'admin'` at the router level |
| 3 | `Routes/api.php` / `api/*.php` reachable directly; `test_*.php` files in the web root | Unauthenticated scripts and DB diagnostics were exposed | Only `public/` is served. Test scripts were removed. |
| 4 | Players and admins shared one session `user_id` key, and IDs came from two tables | Player #1 and admin #1 could be mistaken for each other | Separate `player_id` / `admin_id` session keys. The session is regenerated on login. |
| 5 | Spin wheel had no limit | Endless free coins | Costs 5 coins, at most 3 spins per day, and the server picks the prize |
| 6 | Quiz answers were in the client JS; rewards were paid on the client | Every gate could be passed without answering | Questions are served without `answer_idx`. Answers are checked on the server. |
| 7 | Admin analytics were hard-coded or random numbers | Admins saw invented numbers | Analytics are now calculated from `runs` / `run_answers` |
| 8 | Duplicate element IDs on the admin password fields | Changing the admin password used the wrong field | IDs renamed (`newAdminPassword`, `newUserPassword`) |
| 9 | Fake 2FA / session-timeout settings and "Delete all users / Reset all data" buttons in the admin panel | The settings did nothing, and the buttons could wipe all data in one click | Removed. A note explains what is actually enforced. |
| 10 | Seeded default credentials (`admin/admin`, `user/user`) | Anyone could log in with a known password | No default accounts. Admins are created with `php database/create_admin.php`. |
| 11 | No login throttling | Passwords could be guessed by brute force | Locked after 10 failures per IP per 15 minutes. Successes and failures are written to `security_log`. |
| 12 | Leaderboard/landing/dashboard pages referred to files that no longer existed | Pages returned 500 errors or rendered half-empty | Fixed during the move to `views/` |

## 3. New architecture

### 3.1 Request flow

```text
Browser ──► /.htaccess ──► public/.htaccess ──► public/index.php
                                                   │  bootstrap: autoload, .env, helpers, session
                                                   ▼
                                             Http\Router  (exact path + method, guard: public/player/admin)
                                                   │
                         ┌─────────────────────────┴─────────────────────────┐
                    page route                                          /api/* route
                 view('user/game', …)                         Controller → Service → Repository → PDO
                    (HTML)                                   {"success":true, …} | {"success":false,"code","message"}
```

- **Controllers** (`src/Http/Controllers`) read the `Request`, call one service method and return an array.
- **Services** (`src/Services`) hold all the game rules. `GameRules.php` keeps every tunable number in one place.
- **Repositories** (`src/Repositories`) are the only place SQL is written. All queries are prepared statements with native prepares.
- **Errors**: services throw `ApiError(status, code, message)`. The router turns it into JSON. Unexpected exceptions are logged and returned as a generic 500. Details are added only when `APP_DEBUG=true`.
- **Guards**: a guarded page redirects to the matching login page. A guarded API route returns 401, and the client's `App.api` then redirects to login.

### 3.2 Folder structure

```text
public/        web root: index.php, .htaccess, assets/{css,js,images}
src/           App\ namespace: bootstrap.php, routes.php, Http/, Services/, Repositories/, Support/
views/         landing.php, layouts/, user/, admin/
config/        app.php (reads .env)
database/      migrate.php, create_admin.php, migrations/, seeds/
docs/          this report; legacy/ = old docs (out of date)
```

Old folders were removed: `api/`, `Routes/`, `Config/`, `Utils/`, `Services/`, `Pages/`, `Components/`, `database/schema/`, and the root `index.php`. Their code was moved into the structure above or rewritten there.

## 4. Server-authoritative game runs

### 4.1 Lifecycle

```text
start ──► (question ──► answer)×N ──► complete
  │            ▲            │
  │            └─ wrong ────┘ (attempt 2, 3 …)
  ├─► powerup (timeFreeze | hint | skip | scoreDoubler)
  └─► timeout  (timer hit 0 → back to start, deadline reset)
```

1. **`POST /api/runs/start {difficulty, subject, replay_of?}`** creates a `runs` row. The server chooses a random `seed` (or reuses the seed of `replay_of` for a restart), sets `deadline_at` for timed difficulties, and creates `run_gates` 1..N. Any run the player still has open is marked `abandoned`.
2. The browser builds the maze from the seed (mulberry32 PRNG and an iterative DFS) and puts gates 1..N along the BFS solution path. The server never sends the maze itself, only the seed.
3. **`POST /api/runs/question {run_id, gate_no}`** picks a question that hasn't been used in this run yet, records `served_at`, and returns `{prompt, options}` **without the answer**.
4. **`POST /api/runs/answer {run_id, gate_no, attempt_no, choice}`** is rejected if it arrives less than 1 s after the question was served (`TOO_FAST`). It is **idempotent**: `(run_id, gate_no, attempt_no)` is unique, so a retried request gets the stored result back and is never paid twice. If the answer is correct, the gate is marked `cleared` and the ledger is credited.
5. **`POST /api/runs/complete {run_id}`** checks that every gate is cleared or skipped, that the run took at least `min_seconds`, and that the deadline plus 3 s grace hasn't passed. It then pays the completion reward and the time bonus.
6. **`POST /api/runs/timeout`** resets the deadline and clears the combo. It costs no coins. The difficulty's coin penalty is charged on each **wrong answer** instead (see §4.2).

Every run endpoint returns the current `state()`: `seconds_left`, `freeze_left`, `combo`, `doubler`, and the status of each gate. The client re-syncs its timer from that state, so the browser clock never decides anything.

### 4.2 Rules (`src/Services/GameRules.php`)

| | Easy | Medium | Hard |
|---|---|---|---|
| Maze size | 10 | 15 | 20 |
| Time limit | none | 150 s | 240 s |
| Gates | 3 | 5 | 7 |
| Wrong-answer penalty | 0 | 5 coins | 10 coins |
| Reward multiplier | ×1 | ×1.5 | ×2 |
| Minimum run time | 6 s | 10 s | 15 s |
| Fog of war | — | — | radius 3.5 |

- **Gate reward:** 10 coins + 5 XP. Answering consecutive gates correctly on the first try builds a combo worth +2 coins per step, up to +10. The **2nd attempt pays half; the 3rd attempt or later pays nothing.** Guessing through all four options is never worth it. A test bot that only guessed earned 5 coins from gates on Easy.
- **Score Doubler** doubles the next gate reward. **Time Freeze** adds 10 s to the server deadline and sets `freeze_until`. **Skip** opens the nearest gate without paying a reward. **Hint** only uses up the item; the browser highlights the nearest gate. A power-up is taken from the inventory only after the action has been validated.
- **Completion:** 50 coins + 20 XP, multiplied by the difficulty multiplier, plus `seconds_left / 5` as a time bonus.
- **Levels:** XP is a lifetime total. `level = floor((1 + sqrt(1 + 8·xp/100)) / 2)`, and level L starts at `50·L·(L−1)` XP.
- **Daily check-in:** 100 coins + 10 × streak days, once per DB calendar day. **Spin:** costs 5 coins, at most 3 per day, prize chosen by the server.
- **Badges:** Level 5, Rich Explorer (≥500 coins), 10 Day Streak.

### 4.3 Anti-cheat logging

A run request rejected for a reason that suggests tampering (`TOO_FAST`, `GATES_PENDING`, `WRONG_ATTEMPT`, `NO_QUESTION_SERVED`, `INVALID_GATE`, `INVALID_OPTION`) is written to `security_log` as `run_rejected:<CODE>`. The log entry is written **after** the transaction rolls back, so the rollback can't erase it. Admins can see these entries in the Security tab.

## 5. Data model

| Table | Purpose |
|---|---|
| `users` | Players only. Holds `coins`, lifetime `xp`, `level`, `streak`, `last_checkin`, `is_active`, `avatar` (MEDIUMTEXT) |
| `admin` | Admin accounts (separate table and session key) |
| `questions` | `subject`, `difficulty`, `prompt`, `options` (JSON), `answer_idx`, `explanation`, `active`, `updated_at` (NULL = untouched seed). 145 seeded |
| `runs` | One game: `seed`, `difficulty`, `subject`, `status` (active/completed/abandoned), `deadline_at`, `freeze_until`, `combo`, `doubler`, `coins_earned`, `xp_earned`. Since 004 also `mode` (normal/daily/challenge), `daily_date`, `challenge_of`, `share_code`, `pickup_count`, `pickups_mask`, `best_combo`, `reviews_served`, `duration_seconds` |
| `run_gates` | Per-gate state: `question_id`, `served_at` (ms), `status` (pending/cleared/skipped), `attempts` |
| `run_answers` | Every answer attempt. `UNIQUE(run_id, gate_no, attempt_no)` makes answers idempotent |
| `ledger` | Every coin/XP change. `UNIQUE(user_id, reason, ref)` means each reward (e.g. `gate` + `run:12:gate:3`, `checkin` + `2026-11-19`) is paid at most once |
| `items`, `inventory` | Shop catalogue (power-ups, cosmetics, and the `streakFreeze` perk) and what each player owns. `users.equipped_skin` / `equipped_theme` hold what's equipped |
| `user_achievements` | Achievement codes unlocked (unique per user and code). Replaces `user_badges`, which was renamed `legacy_user_badges` |
| `question_reviews` | Spaced-repetition state per player and question: `box` (1–5) and `due_at` |
| `daily_mazes` | One row per date: the shared seed of that day's maze |
| `weekly_settlements`, `weekly_rewards` | Which weeks have had prizes paid out, and who won what |
| `security_log` | Login successes and failures, rejected runs |
| `schema_migrations` | Which migration files have been applied |

`users.coins` / `users.xp` are a cached total of the ledger. They are only changed inside the same transaction that writes the ledger row. Coins are spent with `UPDATE … WHERE coins >= ?`, so a balance can never go negative, even with concurrent requests.

## 6. API reference

All endpoints return JSON. A successful response is `{"success": true, …}`. An error is `{"success": false, "code": "…", "message": "…"}`, sent with the matching HTTP status (401/403/404/405/409/422/429/500).

| Method | Path | Guard | Notes |
|---|---|---|---|
| POST | `/api/auth/register` | — | Username 3–30 chars `[A-Za-z0-9_.-]`. Starts with 100 coins and 3 of each power-up |
| POST | `/api/auth/login` · `/api/auth/admin-login` | — | Throttled per IP |
| POST | `/api/auth/logout` | — | |
| POST | `/api/auth/password` | player | Requires the current password |
| GET | `/api/leaderboard?type=xp\|coins\|level\|streak\|weekly\|daily&limit=` | — | `weekly` = XP from the ledger since Monday (admin grants excluded). `daily` = today's maze by time |
| GET / POST | `/api/me` | player | GET: profile, wallet, 7-day XP chart. POST: only username, email and avatar can be changed |
| POST | `/api/checkin` · `/api/spin` | player | |
| GET | `/api/shop` · POST `/api/shop/buy` | player | Price comes from the DB. Cosmetics can be bought only once, and are equipped when bought. Streak freezes are capped at 2 |
| POST | `/api/shop/equip` | player | `{kind: skin\|theme, code\|null}`. Only owned items |
| GET | `/api/daily` | player | Today's maze: whether you've played it, your time and rank |
| POST | `/api/runs/{start,question,answer,powerup,pickup,complete,timeout}` | player | See §4 and §10. `start` takes `mode`: `normal` (difficulty, subject), `daily`, or `challenge` (code) |
| GET | `/api/admin/{stats,users,activity,security-logs,analytics,export}` | admin | |
| POST | `/api/admin/users` · `/api/admin/users/delete` · `/api/admin/password` | admin | Coins given by an admin are recorded in the ledger as `admin_grant` |
| GET / POST | `/api/admin/questions` | admin | GET: search and filter, with answer counts and accuracy. POST: create or edit (`id`) |
| POST | `/api/admin/questions/status` · `/api/admin/questions/import` | admin | Archive or restore a question. Import CSV text (see the README for the format) |

## 7. Migrating an existing database

`php database/migrate.php` runs every pending file in `database/migrations/` and records each one in `schema_migrations`. It refuses to run from a web request.

- **001_core_schema** creates the new tables and changes an older `users` table in place (adds columns, widens `avatar`).
- **002_migrate_legacy_data**:
  - Converts the old per-level XP into a lifetime total (`50·L·(L−1) + xp`) and recalculates the level.
  - Writes an `opening_balance` ledger row for each existing user.
  - Gives each user the starter power-ups.
  - Deactivates `users` rows with `role='admin'`, because admins now live in the `admin` table.
  - **Renames** old tables to `legacy_*` (leaderboard, player, game_session(s), maze, games, rewards, user_achievements). It never drops them.
- **003_seed_questions_and_items** adds the 34 questions (only if the table is empty) and upserts the 12 shop items by `code`.
- **004_learning_and_engagement** adds the columns and tables for §10:
  - It upserts the question seed by prompt, and refreshes a seeded question only while `updated_at IS NULL`, so admin edits are never overwritten. On the dev database this added 111 questions and refreshed 34.
  - It rewords 4 old prompts in place, so their answer history keeps the same question id.
  - It converts old badges to achievement codes.
  - It equips skins that players already own.
  - It turns on the Forest and Space themes and adds the streak freeze item.

**Test result:** a database built from the original `mazequest.sql`, with a level-3 player holding 40 XP and 120 coins plus an old admin row, was migrated. The player ended up with 340 XP, level 3, 120 coins, an `opening_balance` ledger row and 3 of each power-up. The admin row was set to `is_active=0`, and all 6 old tables present were renamed. A second run printed "Nothing to migrate."

## 8. Testing

Tests ran against MariaDB 11 and PHP 8.3 + Apache in Docker.

**Committed suite (since §10): `tests/`, 29/29 passing.** Run it with `docker compose exec -T web php tests/run.php`. Each run:
- builds a fresh `mazequest_test` database from the migrations;
- starts PHP's built-in server against that database;
- runs the tests over HTTP, using a cookie-jar client;
- after each test that moves coins or XP, checks that the ledger still adds up to the player's balance.

It covers game rules, auth guards and the `?next=` redirect, runs (answers, reveal and explanation, pickups, summary, reviews, difficulty preference, mastery), the daily maze, challenges, streak freezes, cosmetics, weekly prizes, achievements, and the admin question editor and CSV import.

The section below describes the earlier one-off API harness, which was not committed.

**API tests: 52/52 passing, in two consecutive runs.** They cover:

- Registration, login and throttling, and guards. A player calling an admin endpoint gets 401, and so does a guest calling a player endpoint.
- Answers: rejected when too fast, idempotent retries, the 2nd/3rd attempt reward rules, rejected answers for unknown gates or options.
- Completing with gates still pending; completing faster than the difficulty minimum.
- Deadline expiry and timeout reset; Time Freeze extending the deadline.
- Each power-up is consumed exactly once.
- Replay reuses the seed and creates a new run.
- Check-in is paid once per day; the spin is capped at 3 per day.
- Shop: insufficient coins, cosmetics bought only once.
- Profile update can't change coins/XP.
- Rejected runs show up in the security log.
- Admin stats, users, analytics and export.

**UI flows tested in a browser:**

- Register, then the dashboard (data, check-in, spin wheel lands on the prize the server chose).
- A full Easy game played by a bot that guesses randomly.
- A Hard game using fog, all four power-ups, restart (same maze) and next level.
- Shop purchase, profile and avatar upload, leaderboard.
- Admin login, then every admin tab.

No console errors except a missing favicon and one deliberate 409 test response.

## 9. UI redesign (2026-11-20)

All pages were rebuilt on one design system, [public/assets/css/app.css](../public/assets/css/app.css). It replaces 12 stylesheets plus the inline `<style>` blocks.

- **Colour, 60 · 30 · 10:**
  - 60%: a neutral background (`--bg`).
  - 30%: surfaces, borders and text (`--surface`, `--text`).
  - 10%: one indigo accent (`--accent`). It is used only for primary actions, active navigation, progress and the player in the maze.
  - Green, red and amber appear only as small feedback (toasts, timer, chips).
  - Light and dark themes share the same tokens. Dark mode follows the OS setting until the player picks one.
- **Type and icons:** Inter. One line-icon set (`views/layouts/icons.php`, used through `icon()` / `App.icon()`) replaces the emoji.
- **Layouts:** public pages share a header and footer. Logged-in pages share an app shell: sidebar, top bar with a live coin balance, and a bottom tab bar on phones. The admin panel uses the same shell.
- **Behaviour changes:**
  - Signing up logs you straight in.
  - The dashboard shows today's real check-in and spin status (`/api/me` now returns `daily`).
  - Profile editing, avatar upload and password change all live on the Profile page. Password change asks for the current password in a form field instead of a browser prompt.
  - The game has keyboard shortcuts: `1`–`4` answer a question, or use a power-up when no question is open.
  - The in-game shop reads prices from the server.
  - The canvas follows the theme and draws sharply on high-DPI screens.
- **Removed because they didn't work or weren't real:**
  - The dashboard's "Weekly Challenge" and external background music.
  - The admin "Game Appearance" settings, whose save buttons called functions that didn't exist.
  - The always-green "System Health" panel.
  - The referral section, which was never implemented.
- **`database/create_admin.php`** now refuses to touch an admin whose email or username doesn't match exactly. Before, a username clash silently reset another admin's password.

## 10. Learning & engagement features (2026-11-20)

### 10.1 Learning
- **Question bank.** Grew from 34 to 145 questions, each with a difficulty and a one-line explanation:
  - By subject: Mathematics 30, General Knowledge 30, Science 29, History 28, English Vocabulary 28.
  - By difficulty: 71 Easy, 52 Medium, 22 Hard.
  - A normal maze picks, in order: up to 2 due reviews; then questions of the maze's difficulty; then any active question in the subject.
- **Bug fixed in the original data.** "What is 5² + 3³?" had `34` marked correct, which is 5² + 3². The real answer, 52, was not among the options. It is fixed in the seed and in the migrated database.
- **Correct answer after a wrong one.** The server reveals `correct_idx` and the explanation only after an answer is recorded:
  - After a correct answer, the next gate draws a fresh question.
  - After a wrong one, the gate's question is cleared, so the next attempt draws a different question.
  - Either way, revealing the answer never lets the player pass a gate with it.
- **Spaced repetition (Leitner).**
  - A wrong answer puts the question in box 1, due now.
  - A correct answer on a tracked question moves it up a box, due again in 1, 3, 7, 14, then 30 days.
  - After the last box the question is considered learned and is dropped.
  - Due reviews are served first, at most 2 per maze, and only from the chosen subject (or any subject in Mixed Mode).
- **Mastery.** Per subject: the share of active questions the player has answered correctly at least once, plus overall accuracy. Shown on the profile and the dashboard.
- **Admin question editor.**
  - Search, filter by subject, difficulty and status, and see per-question answer counts and accuracy.
  - Create and edit questions, and archive them. Questions are never deleted, because answers refer to them.
  - CSV import is all-or-nothing, with row-level errors.

### 10.2 Gameplay
- **Daily maze.**
  - One Medium, Mixed Mode maze per day. The seed and question order (`MD5(seed:id)`) are the same for everyone.
  - One attempt per player (`UNIQUE(user_id, daily_date)`).
  - Leaving and coming back resumes the same run.
  - Finishing pays a bonus of +50 coins and +20 XP.
  - It has its own leaderboard, ranked by time.
- **Coin pickups.**
  - Each maze puts 3, 4 or 5 coins in dead ends, depending on difficulty.
  - Their positions come from the seed, so the client places them.
  - Each one collected is recorded in a bitmask on the run, which makes collecting idempotent.
  - They pay 5 coins each, only when the run is completed, so an abandoned or timed-out run pays nothing.
- **Run summary:**
  - time, accuracy, best combo, coins found, and an itemised list of rewards;
  - the questions the player missed, with the correct answers;
  - daily rank, or the result against the challenger.
- **Themes and skins.** Forest and Space change the maze palette (Space adds a star field). Skins change the player's colour. Buying one equips it, and the shop has Equip/Unequip.
- **Touch arrow pad.** Shown on coarse pointers. Holding a direction repeats the move every 150 ms. Swipe still works.
- **Sound.** Short WebAudio tones, so there are no files to download. The mute choice is saved per device.

### 10.3 Retention
- **13 achievements.** First clear, Flawless, Combo ×5, Hard clear, Speedrunner, Daily player, Challenge won, All-rounder, Scholar, Level 5/10, Rich, 10-day streak. They are checked after answers, completions, check-ins and level-ups, and newly unlocked ones show as toasts.
- **Challenge a friend.**
  - Every completed run except the daily maze gets an 8-character share code. The daily maze has none, because the link would give away that day's maze and questions. The code avoids look-alike characters such as 0/O and 1/I.
  - `/challenge?code=` is public. Guests are sent to register or log in, then straight into the game (`?next=`, same-site paths only).
  - The challenger's run copies the seed, difficulty and subject, and serves the same question at each gate and attempt.
- **Weekly prizes.**
  - Last week's top 3 by XP get 300, 200 and 100 coins.
  - XP counts ledger entries, excluding opening balances and admin grants.
  - Prizes are settled lazily, the first time in a new week that anyone loads their profile/dashboard data (`/api/me`) or the weekly leaderboard. An `INSERT IGNORE` into `weekly_settlements` works as the lock, and the ledger key `weekly_prize` + week start stops double payment.
- **Streak freeze.**
  - A 50-coin perk. A player can hold at most 2.
  - At check-in, each missed day uses one freeze. If the player doesn't hold enough to cover the gap, the streak resets and their freezes are kept.

### 10.4 Trust model for the new parts
- **Pickup positions are computed on the client.** The server only checks that a pickup number is in range for the difficulty. It does not check that the player actually walked there (see §4: movement isn't validated). The payout is small and capped, and only paid on a completed run.
- **Challenge and daily runs go through the same answer-timing and minimum-duration checks as normal runs.**
- **The daily maze can be played only once.** Replaying it is refused with `DAILY_DONE`.
- **A challenge earns the same rewards as any other maze of that difficulty.** Retrying one gives no extra payout.

## 11. Known limitations / follow-ups

- **Movement is not validated.** The server enforces the order of gates, answers and timing. It doesn't replay the player's path through the maze. A modified client could skip walking, but it still has to answer every question correctly and wait at least the minimum run time. Server-side path replay would be the next step if needed.
- **Question bank is still modest:** 145 questions, and only 22 of them are Hard. Hard mazes fall back to Medium or Easy questions once the Hard ones in a subject are used up. Admins can now add more from the panel.
- **Not implemented:** referrals, Speed Boost and Shield.
- **Resuming the daily maze starts the player back at the entrance.** Cleared gates and collected coins are kept. Only the position on the board isn't saved.
- **Challenges with more attempts than the original.** If the friend needs more attempts at a gate than the challenger did, the extra attempts get ordinary random questions.
- **Challenges can be retried.** The friend can accept the same challenge again and again, and so learn its questions before setting a winning time. Capping it at one attempt per player and challenge would need a unique key, as the daily maze has.
- **Forest and Space themes ignore dark mode.** They use their own fixed palettes.
- **Correct answers can promote questions early.** A correct answer to a tracked question moves it up a box even if it wasn't due yet, which happens when it comes up in a normal draw.
- **Only tested in Docker** (MariaDB 11 / PHP 8.3). It has not been run on a real XAMPP install. The minimum versions in the README are inferred, not tested.
- **`legacy_*` tables** are kept for safety. Drop them by hand once you're sure the migrated data is correct.
- **`docs/legacy/`** describes the old `api/*.php` functions and is out of date.
- **No CI yet.** The test suite exists (§8) but nothing runs it automatically. A GitHub Actions job using `docker compose` would be the next step.
- Avatars are stored as data URLs in the DB (≤300 KB). Move them to file or object storage if there will be many users.

setup admin password
- docker compose exec web php database/create_admin.php aaronshasankar@gmail.com admin 'your-new-password'

