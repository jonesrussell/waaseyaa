# Waaseyaa Agent Notes

This file is intentionally lightweight and stays in sync with [CLAUDE.md](CLAUDE.md).

## Canonical Source
- `CLAUDE.md` is the authoritative, detailed instruction set for architecture, workflows, and gotchas.
- If guidance here conflicts with `CLAUDE.md`, follow `CLAUDE.md`.

## Specs and Spec Kitty
- **Constitution:** `CLAUDE.md` (orchestration table, layers, checklists)
- **Skills:** `skills/waaseyaa/` (domain skills on demand); `waaseyaa:spec-maintenance` for edits to `docs/specs/**` and agent rules
- **Specs:** `docs/specs/` — read the relevant `.md` files directly (or `rg` under `docs/specs/`). There is no Waaseyaa spec MCP server in this repo.

Optional **Spec Kitty** workflow: see `CLAUDE.md` (`.kittify/`, CLI install). GitHub issues and milestones still govern tracked work per `docs/specs/workflow.md`.

## Practical Rules
- Respect layer boundaries and access-control semantics from `CLAUDE.md`.
- Treat `HttpKernel` and `ConsoleKernel` as composition roots; test via seams/integration points.
- Keep changes paired with tests and update relevant `docs/specs/` when architecture shifts.

## Sync Policy
- Update this file only to keep pointers accurate.
- Put operational detail changes in `CLAUDE.md` (not duplicated here).
