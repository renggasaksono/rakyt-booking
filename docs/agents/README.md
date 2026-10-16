# Agents

How AI agents (and the humans steering them) work on Rakyt Booking.
The binding rules are in the root [`AGENTS.md`](../../AGENTS.md); this folder holds the longer playbooks.

| File | Purpose |
|---|---|
| [`workflow.md`](workflow.md) | The loop for picking up, building and closing a piece of work |
| [`roles.md`](roles.md) | Agent roles (planner, builder, reviewer, docs keeper) and what each owns |
| [`prompts.md`](prompts.md) | Reusable prompts for common tasks |

## Adding to this folder

When you notice an agent repeating the same mistake, fix it at the right level:

- A rule every task needs → add a line to `AGENTS.md` (keep it short).
- A procedure for a kind of task → a section in `workflow.md` or a prompt in `prompts.md`.
- A tool-specific setting (e.g. Claude Code skills, Cursor rules) → that tool's own folder (`.claude/`, `.cursor/`), pointing back here rather than duplicating.
