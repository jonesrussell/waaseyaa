# Archived Spec Kitty Missions

Missions in this directory are no longer active. They are preserved for planning history but excluded from "active missions" dashboards.

A mission belongs here if any of:

- **Implemented and merged**, but `status.json` was never closed out by `spec-kitty merge` (so it still shows up as active in `spec-kitty next`). Look for the squash-merge commit on `main` matching the mission slug.
- **Obsoleted** by a subsequent decision (e.g. a baseline version bump that subsumes the work).
- **Cancelled** in planning before any code landed.

Each archived directory should keep a one-line `ARCHIVED.md` at its root recording the reason and any pointer to the merged work (PR / commit / superseding mission).

## Current archived missions

- `php-8-5-upgrade-01KR8DN2` — implemented and merged via PR #1406 (WP01–WP06 on `main`). Status never closed by `spec-kitty merge` so the planning artifacts were still being picked up by mission triage.
- `php84-mechanical-modernization-01KR82KT` — squash-merged on `main` (commit `10e7ade5f`). Status not auto-closed.
- `php84-lazy-object-hydration-01KR82KZ` — planning-only; no implementation commits. Obsoleted by the `php-8-5-upgrade` mission raising the PHP floor to 8.5 (memory: 2026-05-10), which makes the original 8.4-framed scope stale. If the lazy-hydration feature is still wanted, re-scope under 8.5.
