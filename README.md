# MazeQuest

An educational maze game. Walk through a maze, answer a quiz question at each gate to open it, and reach the exit. Correct answers earn coins and XP. Players level up, keep daily streaks, buy power-ups, and compete on leaderboards.

The **server** owns every reward. The browser draws the maze and sends what the player did (which gate, which answer, which power-up). The server checks it, scores it and records it in a ledger. A player cannot give themselves coins or XP from the browser console. See [docs/IMPLEMENTATION_REPORT.md](docs/IMPLEMENTATION_REPORT.md) for the design and the reasons behind it.

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
│   ├── migrations/        # 001_core_schema, 002_migrate_legacy_data, 003_seed_…
│   └── seeds/questions.json
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
- Database (for a GUI client): `127.0.0.1:3307`, user `maze`, password `maze`, db `mazequest`

Code is mounted live, so edits show up on refresh. Restarting the `web` container applies new migrations (`docker compose restart web`). `docker compose down` stops everything and keeps the data; `docker compose down -v` also wipes the database.

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
| `/dashboard` · `/choose-subject` · `/game` · `/shop` · `/profile` | logged-in players |
| `/admin` → `/admin-panel` | admins (separate `admin` table and session key) |

The JSON API is listed in the report.

## Changing the game

Difficulty sizes, timers, gate counts, rewards, penalties, spin limits and power-up prices are all in **[src/Services/GameRules.php](src/Services/GameRules.php)**. The client reads difficulties from the server, so a change there applies everywhere. Questions are in the `questions` table (seeded from `database/seeds/questions.json`).
