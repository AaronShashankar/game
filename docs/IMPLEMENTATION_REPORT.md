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
  └─► timeout  (timer hit 0 → penalty, deadline reset)
```

1. **`POST /api/runs/start {difficulty, subject, replay_of?}`** creates a `runs` row. The server chooses a random `seed` (or reuses the seed of `replay_of` for a restart), sets `deadline_at` for timed difficulties, and creates `run_gates` 1..N. Any run the player still has open is marked `abandoned`.
2. The browser builds the maze from the seed (mulberry32 PRNG and an iterative DFS) and puts gates 1..N along the BFS solution path. The server never sends the maze itself, only the seed.
3. **`POST /api/runs/question {run_id, gate_no}`** picks a question that hasn't been used in this run yet, records `served_at`, and returns `{prompt, options}` **without the answer**.
4. **`POST /api/runs/answer {run_id, gate_no, attempt_no, choice}`** is rejected if it arrives less than 1 s after the question was served (`TOO_FAST`). It is **idempotent**: `(run_id, gate_no, attempt_no)` is unique, so a retried request gets the stored result back and is never paid twice. If the answer is correct, the gate is marked `cleared` and the ledger is credited.
5. **`POST /api/runs/complete {run_id}`** checks that every gate is cleared or skipped, that the run took at least `min_seconds`, and that the deadline plus 3 s grace hasn't passed. It then pays the completion reward and the time bonus.
6. **`POST /api/runs/timeout`** applies the difficulty's penalty, resets the deadline and clears the combo.

Every run endpoint returns the current `state()`: `seconds_left`, `freeze_left`, `combo`, `doubler`, and the status of each gate. The client re-syncs its timer from that state, so the browser clock never decides anything.

### 4.2 Rules (`src/Services/GameRules.php`)

| | Easy | Medium | Hard |
|---|---|---|---|
| Maze size | 10 | 15 | 20 |
| Time limit | none | 150 s | 240 s |
| Gates | 3 | 5 | 7 |
| Timeout penalty | 0 | 5 coins | 10 coins |
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
| `questions` | `subject`, `prompt`, `options` (JSON), `answer_idx`, `active` (34 seeded) |
| `runs` | One game: `seed`, `difficulty`, `subject`, `status` (active/completed/abandoned), `deadline_at`, `freeze_until`, `combo`, `doubler`, `coins_earned`, `xp_earned` |
| `run_gates` | Per-gate state: `question_id`, `served_at` (ms), `status` (pending/cleared/skipped), `attempts` |
| `run_answers` | Every answer attempt. `UNIQUE(run_id, gate_no, attempt_no)` makes answers idempotent |
| `ledger` | Every coin/XP change. `UNIQUE(user_id, reason, ref)` means each reward (e.g. `gate` + `run:12:gate:3`, `checkin` + `2026-11-19`) is paid at most once |
| `items`, `inventory` | Shop catalogue (12 items, 4 marked "coming soon") and what each player owns |
| `user_badges` | Badges earned (unique per user and badge) |
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
| GET | `/api/leaderboard?type=xp\|coins\|level\|streak\|weekly&limit=` | — | `weekly` = XP from the ledger since Monday |
| GET / POST | `/api/me` | player | GET: profile, wallet, 7-day XP chart. POST: only username, email and avatar can be changed |
| POST | `/api/checkin` · `/api/spin` | player | |
| GET | `/api/shop` · POST `/api/shop/buy` | player | Price comes from the DB. Cosmetics can be bought only once |
| POST | `/api/runs/{start,question,answer,powerup,complete,timeout}` | player | See §4 |
| GET | `/api/admin/{stats,users,activity,security-logs,analytics,export}` | admin | |
| POST | `/api/admin/users` · `/api/admin/users/delete` · `/api/admin/password` | admin | Coins given by an admin are recorded in the ledger as `admin_grant` |

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

**Test result:** a database built from the original `mazequest.sql`, with a level-3 player holding 40 XP and 120 coins plus an old admin row, was migrated. The player ended up with 340 XP, level 3, 120 coins, an `opening_balance` ledger row and 3 of each power-up. The admin row was set to `is_active=0`, and all 6 old tables present were renamed. A second run printed "Nothing to migrate."

## 8. Testing

Tests ran against MariaDB 11 and PHP 8.3 + Apache in Docker.

**API tests: 52/52 passing, in two consecutive runs.** They cover:

- Registration, login and throttling, and guards. A player calling an admin endpoint gets 401, and so does a guest calling a player endpoint.
- Answers: rejected when too fast, idempotent retries, the 2nd/3rd attempt reward rules, rejected answers for unknown gates or options.
- Completing with gates still pending; completing faster than the difficulty minimum.
- Deadline + timeout penalty; Time Freeze extending the deadline.
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

## 9. Known limitations / follow-ups

- **Movement is not validated.** The server enforces the order of gates, answers and timing. It doesn't replay the player's path through the maze. A modified client could skip walking, but it still has to answer every question correctly and wait at least the minimum run time. Server-side path replay would be the next step if needed.
- **Question bank is small**: 34 questions, so Mixed Mode draws from those 34. *(I said "68" earlier in our conversation. That was wrong.)* Admins can't edit questions from the panel yet.
- **Not implemented:** referrals, maze themes (Forest/Space are listed as "coming soon"), Speed Boost and Shield.
- **Only tested in Docker** (MariaDB 11 / PHP 8.3). It has not been run on a real XAMPP install. The minimum versions in the README are inferred, not tested.
- **`legacy_*` tables** are kept for safety. Drop them by hand once you're sure the migrated data is correct.
- **`docs/legacy/`** describes the old `api/*.php` functions and is out of date.
- No automated tests are committed to the repo. The API test script was a one-off harness. Adding PHPUnit and a CI job would be the obvious next step.
- Avatars are stored as data URLs in the DB (≤300 KB). Move them to file or object storage if there will be many users.
