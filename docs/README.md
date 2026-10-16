# Rakyt Booking docs

The docs folder is the project's memory. Code says *what* the system does; these files say *why*, *what's next*,
and *what "done" means*. Agents and humans keep them current in the same PR as the code (see [AGENTS.md](../AGENTS.md)).

```
docs/
├── product/        Strategy and vocabulary — changes rarely
│   ├── blueprint.md          Product strategy, MVP scope, architecture, commercial model (v1.1)
│   ├── glossary.md           Domain terms, English ↔ Bahasa Indonesia
│   └── source/               Original .docx
├── design/         How it looks and reads
│   ├── README.md             Tokens, status set, CTA rule, screen index
│   └── mockups/              Hi-fi draft v2 (open the .html in a browser)
├── features/       What we build — one spec per feature (F-NNN)
├── roadmaps/       When — now / next / later + dated plans
├── decisions/      Why — ADRs (NNNN), append-only
└── agents/         How agents work here — workflow, roles, prompts
```

## How the pieces connect

```
blueprint ──► roadmap (P0/P1/P2, weeks) ──► feature specs (F-NNN) ──► code + tests
                    ▲                              │
                    └──── decisions (ADRs) ◄───────┘  any "why?" choice made along the way
```

- **Roadmap** lists features by ID and says what is in scope *now*.
- **Feature specs** hold scope, acceptance criteria, mockup references and open questions. Status lives in the spec's front matter and in the feature index.
- **Decisions** are referenced from specs and code comments (`// See ADR-0006`).

## Statuses

| Doc | Status values |
|---|---|
| Feature | `draft` → `ready` → `in-progress` → `done` (or `parked`) |
| Decision | `proposed` → `accepted` → `superseded by NNNN` (or `rejected`) |
| Roadmap item | `planned` · `in-progress` · `done` · `cut` |
