---
work_package_id: WP01
title: Hoist DIR-003 (Greenfield Removal Policy) and verify
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- NFR-001
- NFR-002
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-charter-greenfield-directive-01KQ7MN5
base_commit: 96cf74039d55458efd0c4bc387542e455fd76331
created_at: '2026-04-27T14:39:44.200315+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
shell_pid: '7560'
history:
- date: '2026-04-27'
  note: Generated from spec.md + plan.md + research.md + quickstart.md.
authoritative_surface: .kittify/charter/
execution_mode: code_change
mission_id: 01KQ7MN5TYE737EJN8EKFN9AEX
mission_slug: charter-greenfield-directive-01KQ7MN5
owned_files:
- .kittify/charter/charter.md
- .kittify/charter/directives.yaml
- CHANGELOG.md
tags: []
---

# WP01 — Hoist DIR-003 (Greenfield Removal Policy) and verify

## Objective

Hoist the existing alpha-phase greenfield-removal rule out of DIR-001's nested sub-bullets into its own top-level directive **DIR-003 (Greenfield Removal Policy)** so it surfaces in the compact charter context loaded by every `/spec-kitty.specify` and `/spec-kitty.plan` invocation. Regenerate `directives.yaml`, verify the directive is visible in compact context, confirm the regeneration is idempotent, and add a CHANGELOG entry.

This is a documentation-only amendment. **No `packages/` source code is modified.** No tests added. The "verification" steps run the spec-kitty CLI against the edited charter and inspect the output.

## Context (read first)

- **spec.md** — full statement of what this amendment changes and why. The author has codified "remove bad architecture, no matter the cost" as durable governance; this WP hoists the existing sub-rule into a stable, visible directive.
- **plan.md** — implementation plan, charter check, risk register.
- **research.md** — Phase 0 findings. Three load-bearing facts:
  1. **Severity is hardcoded `warn`** for all directives in Spec Kitty 3.1.6 (`specify_cli/charter/extractor.py:287`). The directive's binding force comes from its description text, not its severity field. Do **not** try to encode `severity: error` in `charter.md` — it will not be honored.
  2. **`spec-kitty charter sync --force`** is the canonical regeneration command. Do **not** run `charter generate --from-interview --force` for this amendment — that path is for interview-driven content regeneration, not structural reorgs.
  3. **DIR-003 must be a single numbered paragraph.** The extractor folds a numbered item's full body into its `description` field, but a malformed list (sub-bullets, orphaned paragraphs) can clip the description.
- **quickstart.md** — the exact editing recipe with verification commands. This WP follows that recipe verbatim. If you find yourself improvising, stop and re-read quickstart.md.

## Branch Strategy

- **Planning base**: `main`
- **Final merge target**: `main`
- The execution worktree path and branch are computed by `spec-kitty agent mission finalize-tasks` and recorded in `lanes.json`. Use `spec-kitty agent action implement WP01 --agent <name>` to enter the correct workspace; do not check out branches manually.

## Pre-flight

```bash
# From the lane workspace (not the project root).
spec-kitty charter status --json | jq -r '.status'
# Expected: "synced"
git status -uno
# Expected: clean (no uncommitted changes in charter or CHANGELOG before edits).
```

If `charter status` returns anything other than `synced`, stop and report — there is a pre-existing charter/yaml mismatch that must be resolved before this WP can proceed without conflating diffs.

## Subtasks

### T001 — Replace DIR-001 alpha-phase removal sub-bullet with cross-reference

**Purpose**: DIR-001 currently embeds the entire phase-dependent removal policy as a sub-clause. We retain the post-alpha (beta-and-beyond) deprecation rules in DIR-001 and replace the alpha sub-bullet with a one-line cross-reference to DIR-003.

**File**: `.kittify/charter/charter.md`

**Steps**:

1. Locate the `## Project Directives` section.
2. Inside item `1. Respect risk boundaries: Absolute non-negotiables:`, find the sub-section beginning `Public-API removal policy is phase-dependent:` (the original block is approximately 22 lines).
3. Replace the entire phase-dependent block with the following text (note: the post-alpha rules are preserved verbatim; only the alpha-phase sub-bullet is replaced with a cross-reference):

   ```markdown
   Public-API removal policy is phase-dependent. During alpha, see DIR-003
   (Greenfield Removal Policy). At beta entry and beyond, removals from
   the public API surface follow formal deprecation:
     - Use the `@deprecated <since-version> <reason and migration target>`
       PHPDoc tag at the symbol's declaration site.
     - Reference a target removal version (e.g., "since 1.2.0 — remove
       in 2.0.0") and a one-line migration note pointing at the
       replacement symbol or recipe.
     - Remain in place for at least two minor releases unless the
       symbol is security-critical, in which case removal cadence
       follows the security-advisory timeline.
     - Are listed under a `### Deprecated` heading in `CHANGELOG.md`
       for the release that introduces them.
   ```

4. Do **not** modify any other sub-bullet of DIR-001 (Drupal-runtime, composer-policy, service registration, no silent breaking changes, no skipping git hooks, no committing secrets — all preserved).

**Validation**:
- DIR-001 still has the same set of top-level sub-clauses (risk boundaries listed in the existing bullets).
- The "Public-API removal policy is phase-dependent" sub-section now leads with a single sentence cross-referencing DIR-003 for alpha, then continues with the unchanged beta-and-beyond rules.
- No other line in DIR-001 changed.

### T002 — Add DIR-003 as a third top-level numbered item in `charter.md` § Project Directives

**Purpose**: Create DIR-003 as a stable top-level directive with text that clearly forbids `@deprecated` wrappers, `Legacy*` namespaces, parallel `v2` interfaces, and "for backward compatibility" comments.

**File**: `.kittify/charter/charter.md`

**Steps**:

1. After the existing item `2. Keep documentation synchronized with workflow and behavior changes.` (and before the `## Reference Index` heading), add:

   ```markdown
   3. Greenfield Removal Policy: during alpha (current state), the
   greenfield principle applies. When a better pattern lands, the old one
   is removed outright. No deprecation window is required. Backwards-compat
   shims that retain known-bad patterns are forbidden. `@deprecated`
   wrappers, `Legacy*` namespaces, parallel `v2` interfaces, and "for
   backward compatibility" comments are not acceptable substitutes for
   deletion. Architecture quality is preferred over API stability for the
   duration of alpha. Breaking changes are still announced explicitly per
   DIR-001 (CHANGELOG.md entry, UPGRADING.md migration recipe) —
   communication discipline is preserved; compatibility debt is not.
   Severity is policy-binding regardless of the `severity: warn` field in
   `directives.yaml` (Spec Kitty 3.1.6 hardcodes severity for all
   directives synced from `charter.md`); the binding force of this
   directive comes from its text.
   ```

2. Keep DIR-003 as **one paragraph**. Do not introduce sub-bullets, code blocks, or continuation paragraphs inside item 3 — the extractor folds nested structure into the description field but breaks if the structure is malformed (research.md Q3).

**Validation**:
- The `## Project Directives` section now contains exactly three numbered items (1, 2, 3).
- DIR-003 mentions all four forbidden patterns: `@deprecated` wrappers, `Legacy*` namespaces, parallel `v2` interfaces, "for backward compatibility" comments.
- DIR-003 includes the phrase "no matter the cost" implicitly via "Architecture quality is preferred over API stability for the duration of alpha."

### T003 — Sync charter to YAML and verify directives.yaml

**Purpose**: Regenerate `.kittify/charter/directives.yaml` from the edited `charter.md`. Confirm DIR-003 appears with the expected description.

**Files**: `.kittify/charter/directives.yaml` (regenerated)

**Steps**:

1. Run from the lane workspace:
   ```bash
   spec-kitty charter sync --force
   ```
   `--force` is required because the post-edit hash differs from `stored_hash`. If the command errors, do **not** retry blindly — read the error, check the numbered list in `## Project Directives`, and fix any structural issues in `charter.md` before retrying.

2. Inspect `directives.yaml`:
   ```bash
   cat .kittify/charter/directives.yaml
   ```

3. Confirm:
   - Three entries: DIR-001, DIR-002, DIR-003.
   - DIR-003 has `id: DIR-003`.
   - DIR-003's `title` starts with `Greenfield Removal Policy:` (truncated at 50 chars by the extractor).
   - DIR-003's `description` contains the substrings: `no deprecation window`, `@deprecated`, `Legacy*`, `for backward compatibility`, `no matter the cost`.
   - DIR-003's `severity` is `warn` (expected — see research.md Q1; this is fine).
   - DIR-001 and DIR-002 are otherwise unchanged.

**Validation**:
- All three directive IDs present.
- All required substrings present in DIR-003's description.
- Severity values: DIR-001 `warn`, DIR-002 `warn`, DIR-003 `warn` (no change to DIR-001/DIR-002).

### T004 — Verify compact charter context lists DIR-003

**Purpose**: Confirm the primary win — DIR-003 surfaces in compact charter context, which is what every `/spec-kitty.specify` and `/spec-kitty.plan` invocation loads.

**Files**: none modified

**Steps**:

1. Run:
   ```bash
   spec-kitty charter context --action specify --json | jq -r '.text'
   ```

2. Confirm output is structurally:
   ```
   Governance:
     - Template set: software-dev-default
     - Paradigms: domain-driven-design
     - Directives: DIR-001, DIR-002, DIR-003
     - Tools: git, spec-kitty
   ```

3. Repeat for `--action plan`, `--action tasks` to confirm DIR-003 appears across all action contexts.

**Validation**:
- The literal substring `DIR-003` appears in the `Directives:` line of the `text` field for `specify`, `plan`, and `tasks` actions.

### T005 — Verify idempotent sync

**Purpose**: NFR-003 requires `spec-kitty charter sync` to be idempotent — running it twice in a row produces no second diff.

**Steps**:

1. Stage current `directives.yaml`:
   ```bash
   git add .kittify/charter/directives.yaml
   ```

2. Re-run sync:
   ```bash
   spec-kitty charter sync --force
   ```

3. Check diff:
   ```bash
   git diff .kittify/charter/directives.yaml
   ```

4. Expected: empty diff. The second sync produces no change. If a diff appears, investigate before proceeding — the sync is not deterministic for this input, and the WP needs to surface that as a finding rather than commit a non-idempotent state.

**Validation**:
- `git diff .kittify/charter/directives.yaml` is empty after the second sync.

### T006 — Add CHANGELOG.md entry

**Purpose**: DIR-001 requires CHANGELOG entries for breaking governance changes. Although this amendment changes no API, it changes a charter directive ID space, which is governance metadata downstream missions reference.

**File**: `CHANGELOG.md`

**Steps**:

1. Open `CHANGELOG.md`. If an `## [Unreleased]` (or equivalent next-release) section does not exist at the top, add one immediately below the introductory text.

2. Under the next-release section's `### Added` (create if missing), add:

   ```markdown
   ### Added
   - Charter directive **DIR-003 (Greenfield Removal Policy)** hoisted from
     a sub-bullet inside DIR-001 into its own top-level directive, so it
     surfaces in compact charter context loaded by every `/spec-kitty.specify`
     and `/spec-kitty.plan` invocation. No policy change — the alpha-phase
     greenfield removal rule was already charter law inside DIR-001. See
     `.kittify/charter/charter.md`. (mission `charter-greenfield-directive-01KQ7MN5`)
   ```

3. If the existing CHANGELOG already has unrelated `### Added` entries under the same release section, append to the existing list rather than creating a duplicate heading.

**Validation**:
- `CHANGELOG.md` contains a section for the next release with the DIR-003 entry.
- The mission slug `charter-greenfield-directive-01KQ7MN5` is referenced for traceability.

## Definition of Done (independent test)

All of the following must hold before requesting review:

- [ ] `.kittify/charter/charter.md` contains DIR-003 as the third top-level numbered item under `## Project Directives`, with no nested bullets/code-blocks inside item 3.
- [ ] DIR-001's alpha-phase removal sub-bullet has been replaced with a cross-reference to DIR-003; the post-alpha (beta+) rules are preserved verbatim.
- [ ] `.kittify/charter/directives.yaml` contains a DIR-003 entry (regenerated by `spec-kitty charter sync --force`, not hand-edited).
- [ ] `spec-kitty charter context --action specify --json | jq -r '.text'` includes `DIR-003` in the `Directives:` line. (Repeat for `plan` and `tasks` actions.)
- [ ] Re-running `spec-kitty charter sync --force` produces no further diff in `directives.yaml`.
- [ ] `CHANGELOG.md` has an `## [Unreleased]` (or equivalent next-release) entry describing the hoist.
- [ ] `git diff --stat` shows ≤ 100 changed lines combined across `charter.md`, `directives.yaml`, and `CHANGELOG.md`.
- [ ] `git status` shows no other modified files (no `packages/`, no `governance.yaml`, no `metadata.yaml`, no `references.yaml`, no `interview/`).

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| `charter sync` errors on malformed numbered list | Low | Verify `## Project Directives` has clean `1.`, `2.`, `3.` items before sync; revert and re-edit on error. |
| Description field clips DIR-003 text | Low | Keep DIR-003 as a single paragraph; no sub-bullets, no continuation paragraphs (research.md Q3). |
| `directives.yaml` non-idempotent (T005 fails) | Very low | Read the diff; if cosmetic (whitespace), re-sync; if structural, do not commit and surface as a finding. |
| Sync overwrites unrelated charter YAML files | Low | This is expected — `governance.yaml`, `metadata.yaml`, `references.yaml` may be touched by sync. Verify their diffs are limited to whitespace or hash-only changes. If substantive content changes, surface as a finding. |
| Severity expectation mismatch | Resolved | Severity stays `warn` for all directives — this is an accepted Spec Kitty 3.1.6 limitation (research.md Q1). |

## Reviewer guidance

A reviewer should verify:

1. **Spec drift**: research.md's findings (severity hardcoded, sync idempotency, single-paragraph DIR-003 requirement) are reflected in the actual edits — no `severity: error` claims, no nested bullets in DIR-003.
2. **No collateral damage**: `git diff --stat` shows changes only in `charter.md`, `directives.yaml`, `CHANGELOG.md`. If `governance.yaml` / `metadata.yaml` / `references.yaml` are touched, the diffs should be cosmetic (timestamps, hashes) — flag any substantive content change.
3. **Cross-reference correctness**: DIR-001's "Public-API removal policy" sub-clause now reads as a single coherent passage that points at DIR-003 for alpha and retains the formal-deprecation rules for beta+.
4. **DIR-003 text completeness**: the directive mentions all four forbidden patterns (`@deprecated`, `Legacy*`, `v2`, "for backward compatibility") and explicitly states architecture quality is preferred over API stability during alpha.
5. **Compact context**: independently run `spec-kitty charter context --action specify --json | jq -r '.text'` and confirm DIR-003 appears.
6. **CHANGELOG hygiene**: the entry cites the mission slug for traceability and is in the right release section.

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <agent-name>
```

(`--agent` selects the implementing agent profile — pick whichever the user prefers. The mission has no dependencies, so no preceding WPs need to land first.)
