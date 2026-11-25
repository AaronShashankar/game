# MazeQuest

An educational maze game. Walk through a maze, answer a quiz question at each gate to open it, and reach the exit. Correct answers earn coins and XP. Players level up, keep daily streaks, buy power-ups, and compete on leaderboards.

## Features

- **Learning:**
  - 145 questions across 5 subjects, each tagged Easy, Medium or Hard. Mazes prefer questions that match their difficulty.
  - After a wrong answer the game shows the correct answer and a short explanation.
  - Missed questions come back in later mazes on a spaced schedule (1, 3, 7, 14, 30 days).
  - The profile shows how well the player knows each subject.
- **Gameplay:**
  - A shared **daily maze**, with its own leaderboard.
  - Coins to find in dead ends.
  - A run summary covering time, accuracy, best combo and the questions worth another look.
  - Forest and Space themes and player skins, which can be equipped from the shop.
  - An on-screen arrow pad on touch screens.
  - Sound effects, with a mute button.
- **Retention:**
  - 13 achievements.
  - **Challenge a friend** links: the friend plays the same maze with the same questions.
  - Weekly prizes for the top 3 by XP.
  - Streak freezes that cover a missed check-in.
- **Admin:**
  - Players, analytics and security log.
  - **Subjects** you can add, rename, show or hide. New subjects stay hidden until you've written their questions.
  - A **question editor** with search, filters, per-question answer stats, archiving and CSV import.

The **server** owns every reward. The browser draws the maze and sends what the player did (which gate, which answer, which power-up). The server checks it, scores it and records it in a ledger. A player cannot give themselves coins or XP from the browser console. See [docs/IMPLEMENTATION_REPORT.md](docs/IMPLEMENTATION_REPORT.md) for the design and the reasons behind it.

## Documentation

| | |
|---|---|
| [docs/project_overview.md](docs/project_overview.md) | What MazeQuest is, who it's for, how to run it |
| [docs/feature.md](docs/feature.md) | Every feature with its exact rules |
| [docs/architecture.md](docs/architecture.md) | Layers, request lifecycle, design decisions |
| [docs/systemdesign.md](docs/systemdesign.md) | Requirements, context diagram, DFD 0/1/2, flowchart, API, security, scaling, review findings |
| [docs/database.md](docs/database.md) | ER diagram, tables, keys, invariants, migrations |
| [docs/algorithm.md](docs/algorithm.md) | Maze generation, question selection, spaced repetition, scoring |
| [docs/diagrams/](docs/diagrams/README.md) | All diagrams (SVG, PNG, Mermaid source) |
| [docs/IMPLEMENTATION_REPORT.md](docs/IMPLEMENTATION_REPORT.md) | History of the changes and why |

## Tech

- PHP 8.1+ (no framework, no Composer; small built-in autoloader)
- MySQL 5.7+ / MariaDB 10.4+ through PDO
- Apache with `mod_rewrite`
- Vanilla JS/CSS. Chart.js 4.4.1 from a CDN on the dashboard.

## Folder structure

```text
/
├── .htaccess              # forwards every request into public/
├── .env.example           # copy to .env (git-ignored)
├── config/app.php         # db, base_path, debug — read from .env
├── public/                # the only web-reachable folder
│   ├── index.php          # front controller
│   ├── .htaccess          # non-files → index.php
│   └── assets/{css,js,images}
├── src/                   # namespace App\
│   ├── bootstrap.php      # autoloader, .env loader, helpers
│   ├── routes.php         # every URL, with its guard (public / player / admin)
│   ├── Http/              # Router, Request, Auth (session), Controllers/
│   ├── Services/          # game rules and business logic (RunService, ProgressionService, …)
│   ├── Repositories/      # all SQL lives here
│   └── Support/           # Database, ApiError, helpers.php
├── views/                 # PHP templates: landing, layouts/, user/, admin/
├── database/
│   ├── migrate.php        # CLI: apply pending migrations
│   ├── create_admin.php   # CLI: create an admin account
│   ├── migrations/        # 001_core_schema … 004_learning_and_engagement
│   └── seeds/questions.json
├── tests/                 # API test suite: run.php + lib/TestKit.php + *Test.php
└── docs/                  # implementation report; legacy/ holds outdated docs
```

A request goes **Router → Controller → Service → Repository → DB**. Controllers only read input and return data. Rules live in services. SQL lives only in repositories.

## Quick start (Docker, recommended)

Needs only Docker Desktop. No PHP or MySQL on your machine.

```bash
docker compose up -d --build     # starts MariaDB + PHP/Apache, runs migrations
docker compose exec web php database/create_admin.php admin@example.com admin 'choose-a-password'
```

- Game: http://localhost:8080 (register a player at `/register`)
- Admin: http://localhost:8080/admin
- phpMyAdmin: http://localhost:8082 (logs in automatically as `maze`; the `mazequest` database is on the left)
- Database (for a GUI client): `127.0.0.1:3307`, user `maze`, password `maze`, db `mazequest`

Code is mounted live, so edits show up on refresh. Restarting the `web` container applies new migrations (`docker compose restart web`). `docker compose down` stops everything and keeps the data; `docker compose down -v` also wipes the database.

## Running the tests

```bash
docker compose exec -T web php tests/run.php                  # everything
docker compose exec -T web php tests/run.php --filter=daily   # only tests whose name matches
```

The runner never touches your data:
1. It drops and recreates a separate database, `mazequest_test`.
2. It applies every migration to it.
3. It starts PHP's built-in server on port 8099, pointed at that database.
4. It runs the tests in `tests/*Test.php` over HTTP.

Creating the database needs a MySQL account that may create databases. In Docker, `root`/`root` is used automatically. Elsewhere, set `TEST_DB_ADMIN_USER` and `TEST_DB_ADMIN_PASS`.

## Setup (XAMPP / LAMP)

1. Put the project in your web root, e.g. `htdocs/game`, and make sure `mod_rewrite` is enabled and `AllowOverride All` is set.
2. Create the database and configure it:
   ```bash
   cp .env.example .env        # edit DB_* if you're not on XAMPP's root/no-password defaults
   mysql -u root -e "CREATE DATABASE mazequest CHARACTER SET utf8mb4"
   ```
3. Run the migrations (CLI only; they can't be run from a browser):
   ```bash
   php database/migrate.php            # apply pending migrations
   php database/migrate.php --status   # show what's applied
   ```
   This is safe on an **existing** MazeQuest database. Old data is converted (see the report) and the old tables are renamed `legacy_*`. Nothing is dropped.
4. Create an admin (there are no default credentials):
   ```bash
   php database/create_admin.php admin@example.com admin 'a-strong-password'
   ```
5. Open `http://localhost/game/`. The base path is detected automatically. Set `BASE_PATH` in `.env` only if detection gets it wrong.

**Better for production:** point the Apache `DocumentRoot` directly at `public/`, so `src/`, `config/` and `.env` can't be reached even if `.htaccess` is ignored.

## Pages

| URL | Who |
|---|---|
| `/` · `/leaderboard` · `/howtoplay` | everyone |
| `/user` · `/register` | player login / sign-up |
| `/challenge?code=…` | everyone (landing page for a challenge link) |
| `/dashboard` · `/choose-subject` · `/game` · `/shop` · `/profile` | logged-in players (`/game?daily=1` is today's maze) |
| `/admin` → `/admin-panel` | admins (separate `admin` table and session key) |

The JSON API is listed in the report.

## Changing the game

Difficulty sizes, timers, gate counts, rewards, penalties, spin limits and power-up prices are all in **[src/Services/GameRules.php](src/Services/GameRules.php)**. The client reads difficulties from the server, so a change there applies everywhere. Achievements are listed in [src/Services/AchievementService.php](src/Services/AchievementService.php).

### Questions

To add a new subject (for example *Computer Software*), open **Subjects → Add subject**. It starts hidden from players. Use **Add question** on its row to write questions, then click **Show** once it has at least 7 active questions (enough for a Hard maze without repeats).

You can add and edit questions in the admin panel under **Questions**. Every question belongs to an existing subject. Questions are archived rather than deleted, because past answers refer to them. The starting set comes from `database/seeds/questions.json`.

A migration updates a seeded question only if no admin has edited it, so edits made in the panel are never overwritten.

**CSV import** (Questions → Import CSV) uses this header row:

```csv
subject,difficulty,prompt,option1,option2,option3,option4,answer,explanation
Science,Easy,What gas do plants take in?,Oxygen,Carbon dioxide,Nitrogen,,2,Plants use CO2 for photosynthesis.
```

- `subject` must already exist (create it on the Subjects tab). Case doesn't matter: `science` is saved as `Science`.
- `difficulty` is `Easy`, `Medium` or `Hard`.
- `answer` is the number of the correct option, from 1 to 4.
- Leave unused options empty. Each question needs at least 2 options.
- `explanation` is optional.
- Files can be up to 500 KB, with at most 1000 rows.
- The import is all-or-nothing. If any row is invalid, nothing is saved and every row error is listed.
- Rows that repeat an existing prompt in the same subject are skipped.
