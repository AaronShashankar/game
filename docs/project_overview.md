# MazeQuest — Project Overview

> **Status:** working web application, run locally with Docker. Not yet deployed to a public host.
> **Review date:** 2026-11-20 · **Code size:** about 9,000 lines (PHP, JavaScript, CSS, views) · **Automated tests:** 29 passing

## 1. What MazeQuest is

MazeQuest is an educational browser game. The player walks a maze toward the exit, and every gate on the path asks a multiple-choice quiz question. A correct answer opens the gate and pays coins and XP. A wrong answer costs coins and shows the right answer with a short explanation. That question then comes back in later mazes until the player knows it.

Around the core loop there is a light progression system: levels, a daily check-in streak, a shop with power-ups and cosmetics, achievements, a shared daily maze, weekly prizes and "challenge a friend" links. An admin panel lets staff manage players and the question bank.

## 2. Goals

| Goal | How the project meets it |
|---|---|
| **Learning that sticks** | Answers are explained; missed questions return on a spaced-repetition schedule (Leitner boxes); per-subject mastery is tracked |
| **Fun, short sessions** | A maze takes 1–4 minutes; combos, pickups, sound, themes and a run summary make each run feel complete |
| **Reasons to come back** | Daily maze, daily check-in with streaks (and streak freezes), weekly top-3 prizes, achievements, friend challenges |
| **Fair and cheat-resistant** | The server decides every reward; the browser only reports what the player did. Every coin and XP change is in a ledger |
| **Simple to run** | Plain PHP, no framework, no Composer. One `docker compose up` starts everything |

## 3. Who uses it

| Actor | What they do |
|---|---|
| **Guest** | Reads the landing and how-to-play pages, sees public leaderboards, opens a challenge link, registers or logs in |
| **Player** | Plays mazes (normal, daily, challenge), checks in, spins the wheel, buys and equips items, reviews progress, challenges friends |
| **Administrator** | Monitors stats and the security log, adds or removes players, edits and imports questions, exports data |

The system's boundary is shown in the [context diagram](diagrams/svg/context-diagram.svg).

## 4. Technology

| Layer | Choice |
|---|---|
| Server | PHP 8.1+ (tested on 8.3), no framework; small PSR-4 autoloader |
| Database | MySQL 5.7+ / MariaDB 10.4+ (tested on MariaDB 11), InnoDB, accessed through PDO with native prepared statements |
| Web server | Apache with `mod_rewrite`; `public/` is the only web-reachable folder |
| Client | Server-rendered PHP views plus vanilla JavaScript (one script per page), HTML canvas for the maze, WebAudio for sound |
| Charts | Chart.js 4.4.1 (dashboard XP chart) |
| Dev environment | Docker Compose: `web` (php:8.3-apache), `db` (mariadb:11), `phpmyadmin` |
| Tests | Custom HTTP test runner (`tests/run.php`) against a throwaway `mazequest_test` database |

## 5. Repository layout

```text
public/            web root: index.php (front controller), assets/{css,js,images}
src/               PHP source, namespace App\
  Http/            Router, Request, Auth (session), Controllers/
  Services/        game rules and business logic (RunService, ProgressionService, …)
  Repositories/    every SQL query
  Support/         Database, ApiError, helpers
  routes.php       every URL with its guard
views/             PHP templates: landing, layouts/, user/, admin/
database/          migrate.php, create_admin.php, migrations/001–004, seeds/questions.json
tests/             run.php, lib/TestKit.php, A_…F_*Test.php
docker/            Dockerfile, entrypoint (runs migrations on start)
docs/              this documentation set, diagrams/, IMPLEMENTATION_REPORT.md, legacy/
```

## 6. Running it

```bash
docker compose up -d --build
docker compose exec web php database/create_admin.php admin@example.com admin 'choose-a-password'
docker compose exec -T web php tests/run.php     # optional: run the test suite
```

| URL | What |
|---|---|
| http://localhost:8080 | the game (register at `/register`) |
| http://localhost:8080/admin | admin login |
| http://localhost:8082 | phpMyAdmin |

Full setup, including XAMPP/LAMP, is in the [README](../README.md).

## 7. Key numbers

| | |
|---|---|
| Questions | 145 across 5 subjects (Mathematics 30, General Knowledge 30, Science 29, History 28, English Vocabulary 28); 71 Easy, 52 Medium, 22 Hard |
| Difficulties | Easy 10×10 / 3 gates / no timer · Medium 15×15 / 5 gates / 150 s · Hard 20×20 / 7 gates / 240 s with fog |
| Shop | 13 items (11 on sale): 4 power-ups, 2 packs, 2 skins, 2 themes, 1 perk (streak freeze) |
| Achievements | 13 |
| Database tables | 16 in use (+ `legacy_*` tables kept from the old schema) |
| Routes | 34 JSON API routes + 13 page routes |

## 8. Where it came from

MazeQuest started as a prototype in which the browser kept score itself: coins and XP were saved by the client, so they could be edited from the console. The project was then rebuilt in four steps, each documented in [IMPLEMENTATION_REPORT.md](IMPLEMENTATION_REPORT.md):

1. **Server-authoritative core.** Layered architecture, rewards decided by the server, a ledger of every coin and XP change, and migrations from the old schema.
2. **UI redesign.** One design system, light and dark themes.
3. **Learning and engagement.** Spaced repetition, the daily maze, challenges, achievements, weekly prizes and the admin question editor.
4. **This documentation set and system review.**

## 9. Documentation map

| Document | Read it for |
|---|---|
| [project_overview.md](project_overview.md) | this page: what, who, how to run |
| [feature.md](feature.md) | every feature, with the exact rules and numbers |
| [architecture.md](architecture.md) | layers, components, request lifecycle, design decisions |
| [systemdesign.md](systemdesign.md) | requirements, context and data-flow diagrams (DFD 0/1/2), flowchart, API, security, scale, and the review findings |
| [database.md](database.md) | ER diagram, every table, keys, indexes, integrity rules, migrations |
| [algorithm.md](algorithm.md) | maze generation, path finding, question selection, spaced repetition, scoring, rankings |
| [diagrams/](diagrams/README.md) | all diagrams as SVG and PNG, with Mermaid sources |
| [IMPLEMENTATION_REPORT.md](IMPLEMENTATION_REPORT.md) | the history of the changes and the reasons for them |
