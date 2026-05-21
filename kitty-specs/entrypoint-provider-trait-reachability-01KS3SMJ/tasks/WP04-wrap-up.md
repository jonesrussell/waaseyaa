---
work_package_id: WP04
title: Wrap-up — Documentation and Changelog
dependencies:
- WP03
requirement_refs:
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T013
- T014
- T015
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "784400"
history:
- date: '2026-05-20T23:57:25Z'
  author: tasks-materializer
  note: Initial WP file created
authoritative_surface: CLAUDE.md
execution_mode: code_change
owned_files:
- CLAUDE.md
- CHANGELOG.md
tags: []
---

# WP04 — Wrap-up: Documentation and Changelog

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Pre-condition**: WP03 must be approved. Baseline regenerated and `composer verify` green.
- **Worktree**: Allocated from `lanes.json` at runtime. Run `spec-kitty agent action implement WP04 --agent <name>` to enter the lane.

## Objective

Update `CLAUDE.md` to document the trait `@api` propagation behavior (FR-005). Add a `CHANGELOG.md` `[Unreleased]` entry. Run final `composer verify` to confirm the mission's changes are cohesive and green.

---

## Subtask T013 — Update CLAUDE.md § "Dead code audits and intentional scaffolding"

**Purpose**: Document the trait-member `@api` propagation behavior so future contributors know `@api` on a trait's class docblock is sufficient — no per-trait registration needed. FR-005.

**Steps**:

1. Open `CLAUDE.md` and find the section `## Dead code audits and intentional scaffolding`.

2. Find the sub-section "Reflection-discovered entrypoints — auto-marked as used by `tools/phpstan/WaaseyaaEntrypointProvider.php`". It currently lists:
   - Classes carrying `#[PolicyAttribute]` or `#[AsMiddleware]`
   - FQCNs declared in `extra.waaseyaa.providers`
   - Classes under `\Ingestion\EntityMapper\` namespace
   - Implementors of `RouteProviderInterface`
   - Subclasses of `EntityBase` / `ContentEntityBase` plus their used traits
   - Classes with class-level `@api` PHPDoc

3. After the last bullet in that list (the `@api` PHPDoc bullet), add:

   ```
   - **Traits with class-level `@api`**: A trait carrying `@api` in its class docblock has all its properties and methods automatically recognized as used, via `WaaseyaaEntrypointProvider::isTraitWithApiPhpDoc()`. No per-trait registration is needed. This covers entity-supporting traits (e.g. `RevisionableEntityTrait`) and testing traits (e.g. `InteractsWithApi`, `RefreshDatabase`) alike.
   ```

   If the existing `@api` bullet already covers classes but not traits separately, add the trait note immediately after it as a nested clarification:

   ```
   - Classes whose FQCN sits under a `\Ingestion\EntityMapper\` namespace segment.
   - Implementors of `RouteProviderInterface`.
   - Subclasses of `EntityBase` / `ContentEntityBase`, plus the traits they `use` (members hydrated via `ReflectionProperty::setValue` and `ContentEntityBase::set()` are call-graph-invisible).
   - Classes carrying class-level `@api` PHPDoc (the canonical signal — covers extension points, public service facades, DTOs, the entire `packages/testing/src/` consumer surface).
   - **Traits carrying class-level `@api` PHPDoc**: All properties and methods of such a trait are recognized as used via `isTraitWithApiPhpDoc()`. No per-trait allowlist required. Works for entity traits (e.g. `RevisionableEntityTrait`) and testing traits (e.g. `InteractsWithApi`, `RefreshDatabase`) alike.
   ```

4. Also find the "### Marking intentional scaffolding" sub-section. It describes when to use `@api`. After the paragraph explaining what `@api` covers, add or extend a note about traits:

   If not already present, add after the existing `@api` description:
   > When adding `@api` to a **trait**, all members of that trait (properties and methods) are automatically marked as used by the provider. This is sufficient for traits that are hydrated via reflection (entity traits) or consumed as test-surface APIs (testing traits).

5. Run `composer cs-check` — CLAUDE.md is not subject to PHP style checks, but verify no accidental changes to neighboring PHP files.

6. No code logic changes in this subtask.

**Validation**:
- [ ] `CLAUDE.md` contains the new bullet describing `isTraitWithApiPhpDoc()`.
- [ ] The bullet names `RevisionableEntityTrait`, `InteractsWithApi`, `RefreshDatabase` as examples.
- [ ] The note clarifies "no per-trait registration needed."
- [ ] No other sections of CLAUDE.md accidentally changed.

---

## Subtask T014 — Add CHANGELOG.md [Unreleased] bullet

**Purpose**: Record the fix in the changelog so it appears in the next release notes. Per project convention: add to `[Unreleased]` only; release-cut.yml promotes to the version heading at tag time.

**Steps**:

1. Open `CHANGELOG.md` and find the `## [Unreleased]` section at the top of the entries.

2. Under the appropriate subsection (`### Fixed` or `### Changed`), add:

   ```
   - fix(dead-code): trait members with class-level `@api` now recognized as used by `WaaseyaaEntrypointProvider`; removes 31 false-positive baseline entries for `RevisionableEntityTrait`, `InteractsWithApi`, and `RefreshDatabase` (#1501)
   ```

   If there is no `### Fixed` subsection under `[Unreleased]`, add one:
   ```markdown
   ### Fixed
   - fix(dead-code): trait members with class-level `@api` now recognized as used by `WaaseyaaEntrypointProvider`; removes 31 false-positive baseline entries for `RevisionableEntityTrait`, `InteractsWithApi`, and `RefreshDatabase` (#1501)
   ```

3. The entry format follows the existing project convention (look at the last few releases in CHANGELOG.md for the exact bullet format and adapt if needed).

**Validation**:
- [ ] `CHANGELOG.md` has a new bullet under `[Unreleased]` § `Fixed`.
- [ ] The bullet references `#1501`.
- [ ] The bullet mentions all three trait names.
- [ ] No entries were accidentally removed or reordered.

---

## Subtask T015 — Final `composer verify` green

**Purpose**: Confirm the full suite is green on the mission's combined changes before handing off to spec-kitty review.

**Steps**:

1. Run:
   ```bash
   composer verify
   ```

2. Expected outcome: exits 0. The changes in this WP (CLAUDE.md + CHANGELOG.md) are documentation only — they should not affect any check. But running `composer verify` provides a final health check that confirms no merge artifact broke something.

3. If `composer verify` fails here:
   - Check `git log --oneline -5` to confirm this worktree has all prior WPs (WP01/WP02/WP03) merged.
   - If WP02's code is missing: the worktree was not rebased/merged from main after WP02 and WP03 landed. Stop and report to spec-kitty orchestrator.
   - If the failure is a flaky pre-existing test (unrelated to this mission): document it and ask spec-kitty reviewer for guidance.

4. Commit CLAUDE.md and CHANGELOG.md together:
   ```bash
   git add CLAUDE.md CHANGELOG.md
   git commit -m "$(cat <<'EOF'
   docs: document trait @api propagation in CLAUDE.md; add CHANGELOG entry

   Updates CLAUDE.md § "Dead code audits" to describe isTraitWithApiPhpDoc()
   and the trait @api propagation behavior. Adds CHANGELOG [Unreleased] entry
   for the 31-entry baseline reduction.

   Refs #1501
   EOF
   )"
   ```

**Validation**:
- [ ] `composer verify` exits 0.
- [ ] Commit includes both `CLAUDE.md` and `CHANGELOG.md`.
- [ ] `git log --oneline -1` shows the expected commit message.

---

## Definition of Done

- `CLAUDE.md` updated with trait `@api` propagation note in § "Dead code audits and intentional scaffolding".
- `CHANGELOG.md` `[Unreleased]` section has the fix bullet referencing `#1501` and all three trait names.
- `composer verify` exits 0.
- Both files committed.

## Risks

- Merge conflict on `CHANGELOG.md` if another PR also added `[Unreleased]` entries concurrently. Resolve by keeping both entries.
- Merge conflict on `CLAUDE.md` if another PR modified the dead-code section. Resolve by incorporating both changes.
- `composer verify` fails due to WP02/WP03 not being in this worktree's ancestry: stop and report, do not force the commit.

## Reviewer Guidance

- Confirm CLAUDE.md change is in the correct section (§ "Dead code audits") and is accurate — not overly broad, not too narrow.
- Confirm the new note names the specific method `isTraitWithApiPhpDoc()` so it can be found by grep.
- Confirm CHANGELOG entry is under `[Unreleased]` and not under a versioned heading.
- Confirm `composer verify` passed (check the worktree's CI run or local output).

## Activity Log

- 2026-05-21T00:58:40Z – claude:sonnet:implementer:implementer – shell_pid=778658 – Started implementation via action command
- 2026-05-21T00:59:49Z – claude:sonnet:implementer:implementer – shell_pid=778658 – CLAUDE.md 7th pattern documented; CHANGELOG bullet added; composer verify result: cs-check OK, phpstan OK, check-dead-code OK (baseline 66→13); check-symfony-imports 11 pre-existing violations (not a regression)
- 2026-05-21T01:01:06Z – claude:opus-4-7:reviewer:reviewer – shell_pid=784400 – Started review via action command
