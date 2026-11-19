# MazeQuest — Diagrams

Every diagram exists in three forms:

| Folder | Use it for |
|---|---|
| [`src/`](src/) | **Source** (Mermaid `.mmd`). Edit these |
| [`svg/`](svg/) | Embedding in Markdown and web pages (sharp at any zoom; white background so they read in dark mode) |
| [`png/`](png/) | Pasting into Word, Google Docs or slides (rendered at 2× resolution) |

## Index

| Diagram | What it shows | Files |
|---|---|---|
| **Context diagram** | MazeQuest as one process with its three external entities | [svg](svg/context-diagram.svg) · [png](png/context-diagram.png) · [src](src/context-diagram.mmd) |
| **DFD level 0** | The 7 main processes and 11 data stores | [svg](svg/dfd-0.svg) · [png](png/dfd-0.png) · [src](src/dfd-0.mmd) |
| **DFD level 1 — 1.0** Accounts and sessions | register, log in, profile, log out | [svg](svg/dfd-1-1-accounts.svg) · [png](png/dfd-1-1-accounts.png) · [src](src/dfd-1-1-accounts.mmd) |
| **DFD level 1 — 2.0** Play a maze run | start, question, answer, power-up, pickup, timeout, complete | [svg](svg/dfd-1-2-play.svg) · [png](png/dfd-1-2-play.png) · [src](src/dfd-1-2-play.mmd) |
| **DFD level 1 — 3.0** Progression and daily rewards | credit once, spend, check-in, spin, wallet | [svg](svg/dfd-1-3-progression.svg) · [png](png/dfd-1-3-progression.png) · [src](src/dfd-1-3-progression.mmd) |
| **DFD level 1 — 4.0** Shop and inventory | catalogue, buy, equip | [svg](svg/dfd-1-4-shop.svg) · [png](png/dfd-1-4-shop.png) · [src](src/dfd-1-4-shop.mmd) |
| **DFD level 1 — 5.0** Track learning | review boxes, due reviews, mastery | [svg](svg/dfd-1-5-learning.svg) · [png](png/dfd-1-5-learning.png) · [src](src/dfd-1-5-learning.mmd) |
| **DFD level 1 — 6.0** Competition and achievements | leaderboards, daily maze, weekly prizes, challenges, achievements | [svg](svg/dfd-1-6-competition.svg) · [png](png/dfd-1-6-competition.png) · [src](src/dfd-1-6-competition.mmd) |
| **DFD level 1 — 7.0** Administer the system | stats, players, questions, CSV, security log, export | [svg](svg/dfd-1-7-admin.svg) · [png](png/dfd-1-7-admin.png) · [src](src/dfd-1-7-admin.mmd) |
| **DFD level 2 — 2.2** Serve a question | question selection priority | [svg](svg/dfd-2-2-serve-question.svg) · [png](png/dfd-2-2-serve-question.png) · [src](src/dfd-2-2-serve-question.mmd) |
| **DFD level 2 — 2.3** Grade an answer | idempotency, validation, scoring | [svg](svg/dfd-2-3-grade-answer.svg) · [png](png/dfd-2-3-grade-answer.png) · [src](src/dfd-2-3-grade-answer.mmd) |
| **DFD level 2 — 2.7** Complete a run | validation, rewards, summary, share code, ranking | [svg](svg/dfd-2-7-complete-run.svg) · [png](png/dfd-2-7-complete-run.png) · [src](src/dfd-2-7-complete-run.mmd) |
| **ER diagram** | All 16 tables with keys and relationships | [svg](svg/er-diagram.svg) · [png](png/er-diagram.png) · [src](src/er-diagram.mmd) |
| **System flowchart** | A player's journey through one maze, with the server's checks | [svg](svg/system-flowchart.svg) · [png](png/system-flowchart.png) · [src](src/system-flowchart.mmd) |
| **Component diagram** | Layers inside the app | [svg](svg/architecture-components.svg) · [png](png/architecture-components.png) · [src](src/architecture-components.mmd) |
| **Deployment diagram** | The Docker Compose setup | [svg](svg/deployment.svg) · [png](png/deployment.png) · [src](src/deployment.mmd) |
| **Sequence: answering a gate** | Browser ↔ server ↔ database for one gate | [svg](svg/sequence-answer.svg) · [png](png/sequence-answer.png) · [src](src/sequence-answer.mmd) |

## DFD notation

| Shape | Meaning |
|---|---|
| Grey rectangle | External entity (Guest, Player, Administrator) |
| Blue rounded box, numbered | Process (1.0 … 7.0 at level 0; 2.1 … at level 1; 2.3.1 … at level 2) |
| Blue dashed box | A process from another part of the system, shown for context |
| Orange cylinder | Data store (D1 users … D12 PHP sessions) |
| Labelled arrow | Data flow; a two-headed arrow means the process both reads and writes |

The level-numbering follows the usual convention: the **context diagram** shows the whole system as a single process (0). **Level 0** breaks it into the major processes, **level 1** breaks each of those down, and **level 2** goes one step further for the three processes that decide correctness: serving a question, grading an answer, and completing a run.

## Editing and re-rendering

The `.mmd` files are standard [Mermaid](https://mermaid.js.org/) (v11). Paste any of them into the [Mermaid Live Editor](https://mermaid.live) to edit them visually. The DFDs start with a `layout: elk` front-matter block. They render best with the ELK layout engine (`@mermaid-js/layout-elk`), which routes the many flows orthogonally. Without ELK, Mermaid falls back to its default layout.

To regenerate the SVG and PNG files with the Mermaid CLI:

```bash
npm i -g @mermaid-js/mermaid-cli
for f in docs/diagrams/src/*.mmd; do
  n=$(basename "$f" .mmd)
  mmdc -i "$f" -o "docs/diagrams/svg/$n.svg" -b white
  mmdc -i "$f" -o "docs/diagrams/png/$n.png" -b white -s 2
done
```

If your Mermaid CLI doesn't include the ELK layout, the DFDs come out in the default layout. They are still correct, just more tangled. The checked-in images were rendered in a browser with Mermaid 11.4.1 plus `@mermaid-js/layout-elk` 0.1.7, the `neutral` theme, and plain-text (non-HTML) labels, so they also display outside browsers.
