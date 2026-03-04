# Waaseyaa Agent Notes

This file is intentionally lightweight and stays in sync with [CLAUDE.md](CLAUDE.md).

## Canonical Source
- `CLAUDE.md` is the authoritative, detailed instruction set for architecture, workflows, and gotchas.
- If guidance here conflicts with `CLAUDE.md`, follow `CLAUDE.md`.

## Codified Context Workflow
- Tier 1: `CLAUDE.md` (always-on constitution and orchestration table)
- Tier 2: `skills/waaseyaa/` (domain skills loaded on demand)
- Tier 3: `docs/specs/` (deep subsystem specs)

When MCP tools are available, prefer:
- `waaseyaa_search_specs` to find impacted specs
- `waaseyaa_get_spec` to load the exact subsystem spec before edits

## Practical Rules
- Respect layer boundaries and access-control semantics from `CLAUDE.md`.
- Treat `HttpKernel` and `ConsoleKernel` as composition roots; test via seams/integration points.
- Keep changes paired with tests and update relevant `docs/specs/` when architecture shifts.

## Sync Policy
- Update this file only to keep pointers accurate.
- Put operational detail changes in `CLAUDE.md` (not duplicated here).
