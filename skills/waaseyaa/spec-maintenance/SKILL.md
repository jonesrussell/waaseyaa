---
name: waaseyaa-spec-maintenance
description: Use when editing docs/specs/, CLAUDE.md orchestration, or agent rules — keep subsystem specs aligned with code, run drift checks, and follow Spec Kitty + GitHub workflow precedence.
---

# Waaseyaa spec maintenance

## When to use

- Touching `docs/specs/**/*.md`, root `CLAUDE.md`, skeleton `CLAUDE.md`, or `.claude/rules/`
- Refactoring a subsystem and updating its enduring spec
- Auditing whether architecture docs match implementation

## Retrieving specs (no MCP)

Subsystem specs live in `docs/specs/`. Load them with the Read tool or search with ripgrep from the repo root, for example:

- `docs/specs/entity-system.md` — full file for the entity stack
- `rg -n "YourSymbol" docs/specs/` — find mentions across specs

The orchestration table in `CLAUDE.md` maps file patterns to spec paths — prefer that table over guessing filenames.

## Drift

After code changes that affect documented behaviour, update the relevant spec in the same PR when practical. Run:

```bash
bash tools/drift-detector.sh 5
```

Session hooks may run a shorter threshold; CI runs drift detection on pushes and PRs.

## Spec Kitty vs GitHub

This repo uses **Spec Kitty** for optional spec/plan/task-driven delivery (see `.kittify/` and [Spec Kitty](https://github.com/Priivacy-ai/spec-kitty)).

**Precedence:** GitHub issues and milestones remain the source of truth for tracked work (`docs/specs/workflow.md`). Use Spec Kitty missions, dashboard, and worktrees for structured implementation and review — they complement, not replace, issue triage and PR conventions.
