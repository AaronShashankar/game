# MazeQuest — Algorithms

This document explains every non-trivial algorithm in MazeQuest: how mazes are built, how gates and coins are placed, how questions are chosen, how answers are scored, and how rankings and rewards are settled. Code references point to the files where each algorithm lives. All constants come from [`src/Services/GameRules.php`](../src/Services/GameRules.php).

| # | Algorithm | Runs on | File |
|---|---|---|---|
| 1 | Seeded pseudo-random numbers (mulberry32) | browser | `public/assets/js/game.js` |
| 2 | Maze generation (randomised depth-first search) | browser | `game.js · generateMaze` |
| 3 | Solution path (breadth-first search) | browser | `game.js · solutionPath` |
| 4 | Gate placement (even spacing on the path) | browser | `game.js · placeGates` |
| 5 | Pickup placement (seeded shuffle of dead ends) | browser | `game.js · placePickups` |
| 6 | Fog of war | browser | `game.js · drawMaze` |
| 7 | Question selection (priority with fallbacks) | server | `RunService::pickQuestion`, `QuestionRepository` |
| 8 | Answer validation and grading | server | `RunService::answer` |
| 9 | Scoring formulas | server | `GameRules` |
| 10 | Level curve | server | `GameRules::levelForXp` |
| 11 | Spaced repetition (Leitner boxes) | server | `LearningService`, `ReviewRepository` |
| 12 | Subject mastery | server | `QuestionRepository::masteryFor` |
| 13 | Check-in streak with freezes | server | `RewardService::checkin` |
| 14 | Spin wheel | server | `RewardService::spin` |
| 15 | Daily maze seed and ranking | server | `DailyService`, `RunRepository` |
| 16 | Weekly prize settlement | server | `WeeklyRewardService` |
| 17 | Challenge share codes | server | `ChallengeService` |
| 18 | Achievement evaluation | server | `AchievementService` |
| 19 | Idempotent rewards (ledger keys) | server + DB | `ProgressionService`, `ledger` |

---

## 1. Seeded pseudo-random numbers — mulberry32

The server issues a 31-bit seed, `random_int(1, 2_147_483_647)`, which comes from PHP's cryptographically secure generator. The browser turns the seed into a deterministic stream of numbers in [0, 1):

```js
function mulberry32(seed) {
  return function () {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
```

- **Why mulberry32:**
  - It has 32 bits of state, needs no dependencies, and gives a good spread for games.
  - It produces identical output in every JavaScript engine, because it uses only 32-bit integer operations.
  - That last property is what makes a **seed a complete description of a maze**.
- **Independent streams:** each use XORs the seed with a constant, so the same seed gives several unrelated sequences:

  | Use | Seed |
  |---|---|
  | Maze | `seed` |
  | Pickups | `seed ^ 0x5bd1e995` |
  | Space-theme stars | `seed ^ 0x2545f491` |

- **Not for security.** Nothing secret depends on it. Share codes and session IDs use the server's CSPRNG.

## 2. Maze generation — randomised depth-first search

The maze is an `n × n` grid (n = 10, 15 or 20). Each cell starts with all four walls. An iterative depth-first search, also called the recursive backtracker, carves passages:

```text
stack ← [(0,0)];  visited(0,0) ← true
while stack not empty:
    (x,y) ← top of stack
    neighbours ← shuffle([up, down, left, right], rand)      # Fisher–Yates with mulberry32
    next ← first neighbour inside the grid and not visited
    if none: pop stack; continue
    remove the wall between (x,y) and next (both sides)
    visited(next) ← true; push next
open the left wall of the start (0,0) and the right wall of the exit (n−1,n−1)
```

**Properties:**

- **Perfect maze.** The carved passages form a spanning tree of the grid. Every cell is reachable, and there is **exactly one path** between any two cells. This is what makes gate placement fair: the player cannot route around a gate.
- **Texture.** DFS mazes have long, winding corridors and relatively few dead ends, compared with Prim's or Kruskal's algorithms. That makes the route feel like exploration.
- **Iterative.** The explicit stack avoids recursion-depth limits on 20 × 20 mazes.
- **Complexity:** O(n²) time and memory. Every cell is pushed and popped once; each step checks 4 neighbours.

## 3. Solution path — breadth-first search

Gates must sit on the route to the exit, so the client finds that route with BFS from the start:

```text
prev ← {start: null};  queue ← [start]
while queue not empty:
    c ← dequeue; if c = exit: break
    for each open side of c leading to an unvisited cell d:
        prev[d] ← c; enqueue d
path ← walk prev[] back from the exit, reversed
```

In a perfect maze, BFS returns the unique path. BFS (rather than DFS) also guarantees the shortest path in general.

**Complexity:** O(n²).

## 4. Gate placement — even spacing on the path

```text
inner ← path without its first two and last two cells     # keep gates away from start and exit
for k in 1..N:                                            # N = 3, 5 or 7 gates
    gate k ← inner[ floor(k · |inner| / (N + 1)) ]
```

- **Spacing:** gates split the inner path into N + 1 roughly equal stretches.
- **Order:** gate numbers increase from start to exit, matching the `gate_no` rows the server created for the run.
- **Always enough room:** the shortest possible path in an n × n grid is 2n − 1 cells, so `inner` has at least 2n − 5 cells. That is 15 for the 10 × 10 Easy maze, which leaves room for 3 gates.
- **Already-opened gates:** gates the server reports as `cleared` or `skipped` are drawn open. This is how a resumed daily maze keeps its progress.

## 5. Pickup placement — seeded shuffle of dead ends

```text
deadEnds ← every cell with exactly 3 walls, except start and exit
rand ← mulberry32(seed XOR 0x5bd1e995)
Fisher–Yates shuffle deadEnds with rand
pickups ← first K cells                                   # K = 3, 4 or 5 by difficulty
```

- **Why dead ends:** reaching a coin means leaving the solution path, so collecting is a choice between time and coins.
- **Determinism:** the same seed places coins in the same cells, so replays, challenges and the daily maze are fair.
- **Server side:** each pickup has a number from 1 to K.
  - Collecting it sets bit `K−1` of `runs.pickups_mask` with `UPDATE … SET pickups_mask = pickups_mask | bit WHERE (pickups_mask & bit) = 0`. This is idempotent.
  - On completion the server counts the set bits and pays 5 coins each. An abandoned run pays nothing.
- **Edge case:** if a maze has fewer dead ends than K, fewer pickups are placed. This doesn't happen in practice, because DFS mazes of these sizes have well over 5 dead ends.

## 6. Fog of war (Hard)

After drawing the maze, the canvas is covered with a radial gradient centred on the player. It is transparent within about 35% of the radius and almost opaque (97%) at 3.5 cells. The hint highlight is drawn after the fog, so it stays visible. Fog is a display rule only. The server doesn't know about it.

## 7. Question selection

When the player walks into a closed gate, the browser calls `POST /api/runs/question`. The server locks the run and the gate, then chooses the question:

```text
if gate already has a served question:            # asking again can't "shop" for an easier one
    return that question
attempt ← gate.attempts + 1
if run.mode = challenge:
    q ← the question the friend was asked at (this gate, this attempt)      # from run_answers of the source run
    if q exists and is active: return q
if run.mode = daily:
    return pickSeeded(subject, difficulty, run, seed)
if run.mode = normal and run.reviews_served < 2:
    q ← pickDueReview(user, subject, run)
    if q: run.reviews_served += 1; return q (is_review = true)
return pickRandom(subject, difficulty, run)
```

Every picker shares two rules:

1. **No repeats in a run.** It excludes questions already answered in this run (`run_answers`) or currently assigned to another gate (`run_gates`).
2. **Graceful fallback.** If nothing unused is left, it picks any active question in the subject.

| Picker | Ordering (SQL) | Effect |
|---|---|---|
| `pickRandom` | `ORDER BY (difficulty = :maze_difficulty) DESC, RAND()` | Questions tagged with the maze's difficulty first, random within each group. A Hard maze falls back to Medium and Easy questions once the Hard ones run out |
| `pickSeeded` | `ORDER BY (difficulty = :d) DESC, MD5(CONCAT(:seed, ':', id))` | A deterministic shuffle keyed by the day's seed. Every player gets the same order, as long as the question bank isn't edited during the day |
| `pickDueReview` | `WHERE due_at <= NOW() … ORDER BY due_at LIMIT 1` | The most overdue review question that fits the subject (any subject in Mixed Mode) |

**Complexity:** each pick sorts the subject's active questions. That is O(Q log Q) with Q ≤ 145 today, which is trivial. Past roughly 10⁴ questions, replace `ORDER BY RAND()` with a stored random key.

**Wrong answers change the question.** After a wrong answer, the gate's `question_id` is cleared, so the next attempt draws a different question. That is why the server can safely reveal the correct option and explanation straight away.

## 8. Answer validation and grading

`POST /api/runs/answer {run_id, gate_no, attempt_no, option}` is processed in one transaction, in this order:

| Step | Check | Failure |
|---|---|---|
| 1 | Run exists, belongs to the player, is `active` (row locked) | 404 / 409 `RUN_NOT_ACTIVE` |
| 2 | **Idempotency:** is there already a `run_answers` row for (run, gate, attempt)? If yes, return the stored outcome; nothing is paid again | — |
| 3 | Gate number is in range and the gate is `pending` (row locked) | 422 `INVALID_GATE` / 409 `GATE_NOT_PENDING` |
| 4 | Deadline not passed (3 s grace) | 409 `EXPIRED` |
| 5 | A question was served for this gate | 409 `NO_QUESTION_SERVED` |
| 6 | `attempt_no = gate.attempts + 1` | 409 `WRONG_ATTEMPT` |
| 7 | At least **1 second** since the question was served (millisecond `DATETIME(3)`, DB clock) | 409 `TOO_FAST` |
| 8 | Option index within the question's options | 422 `INVALID_OPTION` |
| 9 | Grade: `option = answer_idx` | — |

Failures marked as suspicious (`TOO_FAST`, `WRONG_ATTEMPT`, `NO_QUESTION_SERVED`, `INVALID_GATE`, `INVALID_OPTION`, plus `INVALID_PICKUP` and `GATES_PENDING` elsewhere) are written to `security_log` **after** the rollback, so the log entry is kept.

- **Correct answer:**
  - Pay the gate reward (§9).
  - Mark the gate `cleared`.
  - `combo + 1`, update `best_combo`, turn off the doubler.
- **Wrong answer:**
  - Take the difficulty penalty (never below a balance of 0).
  - `attempts + 1`, clear the gate's question, reset `combo` to 0.
- **Either way:**
  - Insert the `run_answers` row.
  - Update the player's correct or wrong count.
  - Update the review box (§11).
  - Check achievements (§18).

The client waits until at least 1.1 s after showing the question before it sends the answer. It also shuffles the options for display (`sort(() => Math.random() − 0.5)`) but always sends the **original** index.

## 9. Scoring formulas

### Per gate (correct answer)

```text
gateCoins(attempt, combo, doubled):
    if attempt ≥ 3: return 0
    coins ← 10 + min((combo − 1) × 2, 10)       # combo counts this answer, so the first is +0
    if attempt = 2: coins ← floor(coins / 2)
    return doubled ? coins × 2 : coins

gateXp(attempt, doubled) = attempt ≥ 3 ? 0 : (doubled ? 10 : 5)
```

| Combo | 1 | 2 | 3 | 4 | 5 | 6+ |
|---|---|---|---|---|---|---|
| Coins (1st try) | 10 | 12 | 14 | 16 | 18 | 20 |

### Wrong answer

The penalty is 0 / 5 / 10 coins (Easy / Medium / Hard). The amount actually taken is `min(penalty, current coins)`, so a balance never goes negative.

### Completion

```text
mult       ← 1.0 / 1.5 / 2.0                    (Easy / Medium / Hard)
timeBonus  ← floor(secondsLeft / 5)             (0 on Easy, which has no timer)
coins      ← round(50 × mult) + timeBonus
xp         ← round(20 × mult)
pickups    ← popcount(pickups_mask) × 5
daily      ← +50 coins, +20 XP                  (daily maze only)
```

Completion is refused unless every gate is `cleared` or `skipped`, at least `min_seconds` (6 / 10 / 15 s) have passed since the start, and the deadline hasn't expired.

**Worked example.** A Medium maze has 5 gates. The player answers all of them correctly on the first try, finishes with 90 s left, and collects all 4 coins:

| Part | Coins | XP |
|---|---|---|
| Gates (combo 1–5): 10 + 12 + 14 + 16 + 18 | 70 | 25 |
| Completion: round(50 × 1.5) | 75 | 30 |
| Time bonus: floor(90 / 5) | 18 | — |
| Pickups: 4 × 5 | 20 | — |
| **Total** | **183** | **55** |

## 10. Level curve

XP is a lifetime total. Reaching level L needs 100 × (1 + 2 + … + (L − 1)) XP:

```text
xpForLevel(L) = 50 · L · (L − 1)
levelForXp(xp) = floor( (1 + √(1 + 8·xp/100)) / 2 )        # the inverse, from solving 50L² − 50L − xp = 0
```

| Level | 1 | 2 | 3 | 4 | 5 | 10 | 20 |
|---|---|---|---|---|---|---|---|
| Total XP | 0 | 100 | 300 | 600 | 1,000 | 4,500 | 19,000 |

The level is recomputed inside `ProgressionService::credit` whenever XP changes, so it can't drift from XP.

## 11. Spaced repetition — Leitner boxes

Each player has one `question_reviews` row for each question they are still learning.

```text
on answer(user, question, correct):
    row ← review row (locked) or none
    if not correct:
        upsert row: box ← 1, due_at ← now                 # can come back in the very next maze
        return
    if row is none: return                                # never missed: not being reviewed
    days ← INTERVAL[row.box]                              # 1, 3, 7, 14, 30 for boxes 1–5
    if days is none: delete row                           # past the last box: learned
    else: row.box ← row.box + 1; row.due_at ← now + days
```

```text
 wrong ─▶ [box 1] ─ok, 1d─▶ [box 2] ─ok, 3d─▶ [box 3] ─ok, 7d─▶ [box 4] ─ok, 14d─▶ [box 5] ─ok, 30d─▶ (learned)
            ▲                                                                             │
            └──────────────────────── any wrong answer ───────────────────────────────────┘
```

- **Serving.** Up to 2 due reviews per normal maze, the most overdue first, and only from the maze's subject (any subject in Mixed Mode). Daily and challenge mazes don't serve reviews, so that every player gets the same questions.
- **Known simplification.** A correct answer to a tracked question promotes it even if it wasn't due yet, which happens when it comes up in a normal draw.
- **Cost.** O(1) per answer (primary-key lookup). Due lookups use the index `idx_due (user_id, due_at)`.

## 12. Subject mastery

For each subject:

```text
known    ← distinct questions this player has answered correctly at least once
total    ← active questions in the subject
mastery  ← floor(100 × min(known, total) / total)
accuracy ← round(100 × correct answers / all answers)       (null if none)
```

It is computed with one grouped query over `run_answers ⋈ runs ⋈ questions`, plus one count per subject.

## 13. Check-in streak with streak freezes

```text
if last_checkin = today: already claimed
elif last_checkin = yesterday: streak ← streak + 1
elif last_checkin exists and streak > 0:
    missed ← days between last_checkin and today − 1
    if missed ≤ freezes held:
        use `missed` freezes; streak ← streak + 1           # the gap is forgiven
    else:
        streak ← 1                                          # freezes are kept
else: streak ← 1
coins ← 100 + (streak > 1 ? 10 × streak : 0)
credit once with ledger key ('checkin', today)
```

All dates come from the database (`CURDATE()`). A player can hold at most 2 freezes, which is enforced at purchase time.

## 14. Spin wheel

- **Limits:** at most 3 spins per day, counted from today's `spin` ledger rows. Each spin costs 5 coins.
- **Result:** the segment is `random_int(0, 5)`, which is uniform and cryptographically secure. The browser only animates the wheel to the segment the server returns.
- **Expected value per spin:** (10 + 25 + 50) / 6 ≈ 14.2 coins, plus a ½ chance of a power-up.
- **Why the cap:** the original wheel had no limit, which made it an infinite coin machine.

## 15. Daily maze — seed and ranking

- **Seed.**
  - `INSERT IGNORE INTO daily_mazes (day, seed) VALUES (CURDATE(), random)`, then read the row back.
  - If two first requests of the day race, the primary key on `day` lets exactly one insert win. Both then read the same seed.
- **One attempt.**
  - `UNIQUE(user_id, daily_date)` on `runs` enforces it. Normal runs have `daily_date = NULL` and don't collide.
  - Starting the daily maze again while its run is active resumes it. The clock keeps running.
  - Starting it after finishing returns `DAILY_DONE`.
- **Ranking.**
  - Completed daily runs are ordered by `duration_seconds ASC`. Ties go to the earlier `completed_at`, then the lower run id.
  - A player's rank is `1 + (number of runs strictly better by that order)`.
  - The index `idx_daily_rank (daily_date, status, duration_seconds)` limits the scan to that day's finished runs.

## 16. Weekly prize settlement

There is no scheduler. Settlement is triggered by `GET /api/me` or by the weekly leaderboard:

```text
week ← Monday of last week (DB clock)
if weekly_settlements has week: return                          # fast path, no transaction
BEGIN
  INSERT IGNORE weekly_settlements(week)                        # PK = lock; only one request wins
  if no row inserted: COMMIT; return
  top ← SUM(ledger.xp_delta) per player for [week, week + 7 days),
        excluding opening_balance and admin_grant, active players only,
        HAVING xp > 0, ORDER BY xp DESC, user_id ASC, LIMIT 3
  for rank r, player p in top:
      INSERT weekly_rewards(week, r, p, xp, prize[r])
      credit(p, prize[r] coins, reason 'weekly_prize', ref week)   # 300 / 200 / 100, paid once
COMMIT
```

Only the week that just ended is ever settled. If nobody visits for a whole week, the week before is skipped. That keeps the job's cost bounded and predictable.

## 17. Challenge share codes

- **Alphabet:** `ABCDEFGHJKMNPQRSTUVWXYZ23456789` has 31 characters. It leaves out 0/O, 1/I/L, so codes survive being read aloud.
- **Length 8:** 31⁸ ≈ 8.5 × 10¹¹ possible codes. Each character is chosen with `random_int` (CSPRNG).
- **Uniqueness:** `UPDATE IGNORE runs SET share_code = ? WHERE id = ? AND share_code IS NULL`, with a unique index on `share_code`. A collision is retried up to 5 times. Even with a million codes already issued, a new code collides with probability about 1.2 × 10⁻⁶, and that one retry fixes it.
- **Replaying a challenge:**
  - The friend's run copies the seed, difficulty, subject and gate count.
  - For each (gate, attempt), it serves the question the original player was asked (§7).
  - Times are compared on completion: `won = your_seconds < their_seconds`.

## 18. Achievement evaluation

`AchievementService::check(user, run?, outcome?)` runs after an answer, a completed run, and a check-in. It evaluates only the rules the current event can satisfy. A level reached through another reward, such as a weekly prize, is unlocked at the player's next answer, completed run or check-in.

| Event | Rules evaluated |
|---|---|
| Any check (after an answer, a completed run, or a check-in) | level 5, level 10, 500 coins held, 10-day streak, 100 correct answers |
| With a run (after an answer) | + best combo ≥ 5 |
| With a completed run | + first clear, flawless (0 wrong, 0 skipped), Hard clear, under 90 s on Medium or Hard, daily maze, challenge won, a maze finished in every subject |

Each rule that is met tries `INSERT IGNORE INTO user_achievements (user_id, code)`. The primary key makes unlocking idempotent, and only rows actually inserted are returned to the UI as new.

## 19. Idempotent rewards — ledger keys

Every coin and XP change goes through `ProgressionService`:

```text
credit(user, coins, xp, reason, ref):
    if INSERT IGNORE ledger(user, coins, xp, reason, ref) inserted no row: return false    # already paid
    lock users row; level ← levelForXp(xp + Δxp)
    UPDATE users SET coins += Δcoins, xp += Δxp, level = level
```

| Reason | Ref | Paid at most once per… |
|---|---|---|
| `signup` | `starter` | account |
| `gate` | `run:{id}:gate:{n}` | gate of a run |
| `wrong_answer` | `run:{id}:gate:{n}:try:{k}` | attempt |
| `complete` | `run:{id}` | run |
| `pickups` | `run:{id}` | run |
| `daily` | `{date}` | day |
| `checkin` | `{date}` | day |
| `spin` / `spin_prize` | `{date}:{n}` | spin number of the day |
| `purchase` | `{item}:{random}` | purchase (random ref, since buying twice is allowed) |
| `weekly_prize` | `{week_start}` | week |
| `admin_grant` | `create` | account created by an admin |
| `opening_balance` | `migration-002` | migrated legacy account |

Spending uses `UPDATE users SET coins = coins − ? WHERE id = ? AND coins >= ?`, so a balance cannot go negative even under concurrent requests.

**Invariant, checked by the tests:** for every player, `users.coins = SUM(ledger.coins_delta)` and `users.xp = SUM(ledger.xp_delta)`.

## Complexity summary

| Operation | Time | Notes |
|---|---|---|
| Generate a maze | O(n²) | n ≤ 20, so at most 400 cells, under a millisecond |
| BFS path, gates, pickups | O(n²) | once per run |
| Draw a frame | O(n²) | canvas, about 60 fps |
| Pick a question | O(Q log Q) | Q = active questions in the subject (≤ 145) |
| Grade an answer | O(1) indexed lookups + a constant number of writes | one transaction |
| Daily rank | O(F) | F = today's finishers, using an index range |
| Weekly settlement | O(L) once a week | L = last week's ledger rows |
| Mastery | O(A) | A = the player's answers |
