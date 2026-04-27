# Tasks: Greenfield Removal Directive (Charter Amendment)

**Mission**: `charter-greenfield-directive-01KQ7MN5`
**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md) · **Quickstart**: [quickstart.md](quickstart.md)
**Branch contract**: planning base `main` → merge target `main`

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Replace DIR-001 alpha-phase removal sub-bullet with cross-reference to DIR-003 in `charter.md` | WP01 | — |
| T002 | Add DIR-003 (Greenfield Removal Policy) as a third top-level numbered item in `charter.md` § Project Directives | WP01 | — |
| T003 | Run `spec-kitty charter sync --force` and verify `directives.yaml` contains DIR-003 with the expected description | WP01 | — |
| T004 | Verify compact charter context output via `spec-kitty charter context --action specify --json` lists `DIR-003` in the `Directives:` line | WP01 | — |
| T005 | Verify sync idempotency: re-run `charter sync --force`, confirm zero diff in `directives.yaml` | WP01 | — |
| T006 | Add CHANGELOG.md entry under `## [Unreleased]` describing the directive hoist and citing the mission ID | WP01 | — |

All 6 subtasks belong to a single work package: WP01 — Hoist DIR-003 and verify.

This mission is intentionally small and linear. There is no foundational/setup phase, no parallelizable concerns, and no separate verification or polish phase — verification is interleaved with the edits in WP01 because each step's correctness depends on the previous step's outcome. Splitting would create artificial WP boundaries that fragment a tightly coupled edit-sync-verify-commit cycle.

## Work Packages

### WP01 — Hoist DIR-003 (Greenfield Removal Policy) and verify

**Goal**: Hoist the existing alpha-phase greenfield-removal sub-bullet from DIR-001 into its own top-level directive (DIR-003), regenerate `directives.yaml`, verify the directive surfaces in compact charter context, and add a CHANGELOG entry — all in one focused linear pass.

**Priority**: P1 (only WP in the mission; no MVP/polish split needed).

**Independent test (Definition of Done)**:
1. `spec-kitty charter context --action specify --json | jq -r '.text'` contains `DIR-003` in the `Directives:` line.
2. `.kittify/charter/directives.yaml` contains a `DIR-003` entry whose `description` includes the strings "no shims", "@deprecated", "Legacy", "no matter the cost".
3. Running `spec-kitty charter sync --force` a second time produces no diff in `directives.yaml`.
4. `CHANGELOG.md` has an `## [Unreleased]` (or equivalent next-release) section with an entry describing the hoist.
5. Combined diff across `charter.md`, `directives.yaml`, `CHANGELOG.md` is ≤ 100 lines.
6. No file under `packages/` is modified.

**Included subtasks**:

- [ ] T001 Replace DIR-001 alpha-phase removal sub-bullet with cross-reference (WP01)
- [ ] T002 Add DIR-003 as a third top-level numbered item in charter.md (WP01)
- [ ] T003 Run `spec-kitty charter sync --force` and verify directives.yaml (WP01)
- [ ] T004 Verify compact charter context lists DIR-003 (WP01)
- [ ] T005 Verify idempotent sync (WP01)
- [ ] T006 Add CHANGELOG.md entry (WP01)

**Implementation sketch**: follow [quickstart.md](quickstart.md) verbatim — Steps 1 through 7 map 1:1 to T001–T006.

**Parallel opportunities**: none — the steps are linearly dependent (sync depends on edits; verification depends on sync; CHANGELOG entry should reflect the actual completed change).

**Dependencies**: none. This WP depends only on a clean `main` branch and `spec-kitty charter status --json` returning `synced` as a baseline.

**Risks**:
- `spec-kitty charter sync --force` could fail if the numbered list under `## Project Directives` is malformed. Mitigation: verify the list is well-formed (1. 2. 3.) before sync; if sync errors, revert and re-edit.
- `directives.yaml` description capture could clip the directive text if a stray sub-bullet is introduced. Mitigation: keep DIR-003 as a single paragraph (no nested bullets, no continuation paragraph), per research.md Q3.
- Severity stays `warn` regardless of intent (research.md Q1); this is an accepted limitation, not a risk to remediate in this mission.

**Estimated prompt size**: ~280 lines (6 subtasks × ~45 lines each). Within ideal range.

## MVP scope

WP01 is the entire mission. Merge unblocks: (a) future missions can reference `DIR-003` by ID, (b) the in-flight Single-Entity Work Surface mission can simplify its constraint C-010 to a `See DIR-003` reference in a follow-up touch.
