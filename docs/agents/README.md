# Agents

How AI agents (and the humans steering them) work on Rakyt Booking.
The binding rules are in the root [`AGENTS.md`](../../AGENTS.md); this folder holds the longer playbooks.

| File | Purpose |
|---|---|
| [`workflow.md`](workflow.md) | The loop for picking up, building and closing a piece of work |
| [`roles.md`](roles.md) | Agent roles (planner, builder, reviewer, docs keeper) and what each owns |
| [`prompts.md`](prompts.md) | Reusable prompts for common tasks |
| [`../../.claude/agents/`](../../.claude/agents/) | Claude Code subagent definitions for each role |

## Claude Code subagents

Each role in `roles.md` has a matching subagent in [`.claude/agents/`](../../.claude/agents/):

| Subagent | Tools | Invoke with |
|---|---|---|
| `planner` | Read, search, edit (instructed: `docs/` only), web | "Use the planner agent to make F-003 ready" |
| `builder` | Read, search, edit, Bash | "Use the builder agent to build F-009" |
| `reviewer` | Read, search, Bash — **no Edit/Write** | "Use the reviewer agent on feat/F-009-tenancy" |
| `docs-keeper` | Read, search, edit, Bash | "Use the docs-keeper agent for a health check" |

Only the reviewer's restriction is enforced by tool access; the planner's and docs keeper's "docs only"
limits are instructions. Run the reviewer in a fresh session from the builder's so it judges the diff, not its own memory.
Other tools (Cursor, Codex, Gemini) can use the same role by pointing them at the subagent file as a prompt.

## Adding to this folder

When you notice an agent repeating the same mistake, fix it at the right level:

- A rule every task needs → add a line to `AGENTS.md` (keep it short).
- A procedure for a kind of task → a section in `workflow.md` or a prompt in `prompts.md`.
- A tool-specific setting (e.g. Claude Code skills, Cursor rules) → that tool's own folder (`.claude/`, `.cursor/`), pointing back here rather than duplicating.
