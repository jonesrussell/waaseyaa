---
work_package_id: WP05
title: Docs + bypass audit + CHANGELOG
dependencies:
- WP04
requirement_refs:
- NFR-005
- NFR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T022
- T023
- T024
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "481899"
history:
- date: '2026-05-18T23:44:03Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: docs/security/sql-entity-query-access-check-bypass-audit.md
execution_mode: planning_artifact
owned_files:
- docs/specs/access-control.md
- docs/security/sql-entity-query-access-check-bypass-audit.md
- CHANGELOG.md
tags: []
---

# WP05 — Docs + bypass audit + CHANGELOG

## Objective

Document the mission's outcome:

1. Refresh `docs/specs/access-control.md` so the canonical access-control spec reflects query-layer enforcement.
2. File the per-call-site bypass audit so future agents (and future you) know which `accessCheck(false)` calls are legitimate vs. which are tech debt.
3. Add CHANGELOG `[Unreleased]` entries that describe the change for v1 release notes.

Stub removal happens in WP02 (it's an inline edit during the `execute()` rewrite); this WP only handles narrative docs.

## Context

- Spec FRs in scope: covered by WP01-WP04. WP05 is documentation.
- Spec SCs: SC-008 (framework health gates green on the final PR).
- Constraint: per CLAUDE.md "Meta" gotcha, refactoring a subsystem MUST update the relevant `docs/specs/` file.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T022 — Update `docs/specs/access-control.md`

**Purpose:** The access-control spec is the canonical document. After this mission lands, query-layer enforcement is part of the access surface and must be documented.

**Steps:**

1. Open `docs/specs/access-control.md`.
2. Locate (or add) a section on enforcement layers. The spec should describe:
   - **Route layer**: `AccessChecker::check(Route, AccountInterface)` — runs in `AuthorizationMiddleware`.
   - **Entity layer (handler)**: `EntityAccessHandler::check(EntityInterface, $operation, AccountInterface)` — runs in controllers when handling a specific entity instance (e.g. GET `/api/node/{id}`).
   - **Entity layer (query) ← NEW**: `SqlEntityQuery::execute()` runs per-row `EntityAccessHandler::check($entity, 'view', $account)` for every candidate row. Bypass via `accessCheck(false)` for system contexts; throws `MissingQueryAccountException` when the bypass is not set and no account is bound.
   - **Field layer**: `EntityAccessHandler::filterFields(...)` — runs in the serializer.
3. Update the spec's "Access result semantics" section if necessary to reflect that **at the query layer**, `allowed` + `neutral` both admit a row (open-by-default at entity level), while `forbidden` drops it. This is consistent with the existing entity-handler shape.
4. Add a `<!-- Spec reviewed YYYY-MM-DD - {description} -->` stamp at the top of the file describing this mission's edit (per the drift-detector convention noted in MEMORY).
5. Cross-link to `docs/security/sql-entity-query-access-check-bypass-audit.md` so readers find the per-call-site audit.

**Files:**
- `docs/specs/access-control.md` (EDIT)

**Validation:**
- [ ] New enforcement-layers section accurately describes the four layers.
- [ ] Spec stamp added.
- [ ] cs-check skips (markdown file); drift detector quiet.

---

## Subtask T023 — File `docs/security/sql-entity-query-access-check-bypass-audit.md`

**Purpose:** A single document that names every `accessCheck(false)` call site remaining after WP03, classifies it as "legitimate system context" or "tech-debt to revisit", and provides the grep command future audits can run.

**Steps:**

1. Create `docs/security/sql-entity-query-access-check-bypass-audit.md`.
2. Use this template (adapt counts and call sites based on WP03's actual outcomes — the grep command at the bottom is the authoritative way to re-discover them):

   ```markdown
   # SqlEntityQuery `accessCheck(false)` Bypass Audit

   **Status:** Living document. Updated whenever a new `accessCheck(false)` call site lands.
   **Last full audit:** 2026-05-18 (mission `sql-entity-query-access-checking-01KRYP15`).

   ## Why this document exists

   After mission `sql-entity-query-access-checking-01KRYP15` landed, `SqlEntityQuery::accessCheck(true)` became the default and the no-op stub was replaced with a real per-row filter against `EntityAccessHandler::check('view', ...)`. The `accessCheck(false)` opt-out is preserved for system contexts that legitimately need to see every row — index warmers, validators, internal lookups — but every such call site must be documented here.

   ## Current call sites

   | File | Line | Justification | Last reviewed |
   |---|---|---|---|
   | `packages/oidc/src/ClientRegistry/OidcClientSeeder.php` | 123 | Pre-auth client registry lookup; no `_account` available at boot. | 2026-05-18 |
   | `packages/oidc/src/ClientRegistry/OidcClientLookup.php` | 28 | Pre-auth client registry lookup. | 2026-05-18 |
   | `packages/relationship/src/RelationshipValidator.php` | 272 | Referential-integrity check spans access boundaries — a user can't be allowed to violate FK constraints because they can't see the referenced entity. | 2026-05-18 |
   | `packages/relationship/src/RelationshipDeleteGuardListener.php` | 36, 41 | Same — listener runs inside a system delete transaction. | 2026-05-18 |
   | `packages/ai-vector/src/SemanticIndexWarmer.php` | 282 | Background index-warming job; needs to see all entities to build the embedding store. | 2026-05-18 |
   | `packages/workflows/src/DomainValidationListener.php` | 133 | Workflow validator runs inside entity-save transaction; needs unrestricted read. | 2026-05-18 |
   | *(plus any genealogy service call sites WP03 determined are system context)* | — | — | 2026-05-18 |

   ## Removed during mission (formerly `accessCheck(false)`)

   These call sites were classified as user-facing and switched to `setAccount($account)`:
   - `packages/graphql/src/Resolver/EntityResolver.php:65` (count query) — was leaking unfiltered cardinality.
   - `packages/graphql/src/Resolver/EntityResolver.php:81` (main query) — was returning rows the user couldn't access.
   - `packages/ai-vector/src/SearchController.php:173` and `:303` — user-facing semantic search.

   ## How to audit

   To regenerate this list:

   ```bash
   grep -rn "accessCheck(false)" packages/ --include="*.php" | grep -v "/tests/"
   ```

   For each result, decide:
   - **Keep**: system context — runs without a user, or genuinely needs to see all rows. Add a comment at the call site if missing, and add a row to the table above.
   - **Switch**: it's user-facing — replace with `->setAccount($account)`. The account source is request-specific; mirror the pattern from `JsonApiController` (`_account` request attribute) or GraphQL context.

   ## Future automation

   A CI grep gate is a candidate follow-up to enforce that no new `accessCheck(false)` lands without an audit-doc update. Not implemented in v1.
   ```

3. Replace the placeholder call sites with whatever WP03 actually landed (some genealogy sites may have been classified as user-context and bind accounts instead; verify by grepping `accessCheck(false)` after WP03 merge).

**Files:**
- `docs/security/sql-entity-query-access-check-bypass-audit.md` (NEW)

**Validation:**
- [ ] Every remaining `accessCheck(false)` in `packages/*/src/` is in the table.
- [ ] Every site has a one-line justification.
- [ ] The "Removed during mission" section names the formerly-bypassed sites (read commit history if needed).

---

## Subtask T024 — `CHANGELOG.md` `[Unreleased]` bullets

**Purpose:** Surface the mission in v1 release notes.

**Steps:**

1. Open `CHANGELOG.md` at the top, under the `[Unreleased]` heading.
2. Add bullets per Keep-a-Changelog convention:

   ```markdown
   ### Added
   - `Waaseyaa\EntityStorage\Exception\MissingQueryAccountException` — thrown by `SqlEntityQuery::execute()` when access checking is enabled but no account is bound. Fail-closed default. #1495
   - `EntityQueryInterface::setAccount(?AccountInterface): static` — bind the account used for per-row access filtering. All implementations of `EntityQueryInterface` must honour this method.
   - Per-row access enforcement at the query layer. `SqlEntityQuery::execute()` now consults `EntityAccessHandler::check($entity, 'view', $account)` for every candidate row and drops `forbidden` results. Default state is `accessCheck(true)`.

   ### Changed
   - `SqlEntityQuery::accessCheck(bool)` is no longer a no-op stub. Default `true` enables the per-row filter described above; `false` is preserved as an explicit, audited bypass for system contexts.
   - GraphQL `EntityResolver`, `JsonApiController` index endpoints, and `ai-vector` `SearchController` now bind the request's authenticated account into the query and return access-filtered results. Previously these surfaces leaked unfiltered cardinality and rows.

   ### Security
   - **Closes a v1 security gap.** Before this release, `SqlEntityQuery::accessCheck()` was a no-op — every entity query path returned all candidate rows regardless of the requesting account. After this release, query results are filtered at source by the existing `EntityAccessHandler` pipeline. See `docs/security/sql-entity-query-access-check-bypass-audit.md` for the per-call-site audit of remaining `accessCheck(false)` opt-outs.
   ```

3. Reference issue **#1495** (the original decision issue this mission resolved).

**Files:**
- `CHANGELOG.md` (EDIT — append under `[Unreleased]`)

**Validation:**
- [ ] Three sections (Added, Changed, Security) all present.
- [ ] #1495 referenced.
- [ ] CHANGELOG renders correctly; release-cut workflow will promote the `[Unreleased]` content at tag time.

---

## Definition of Done

- [ ] T022..T024 checkboxes flipped.
- [ ] `docs/specs/access-control.md` updated with enforcement-layers section + spec stamp.
- [ ] `docs/security/sql-entity-query-access-check-bypass-audit.md` filed with every remaining `accessCheck(false)` call site classified.
- [ ] CHANGELOG `[Unreleased]` carries Added / Changed / Security bullets.
- [ ] All gates green: drift detector quiet, cs-check (markdown skipped).

## Risks & mitigations

1. **Audit doc bit-rots.** *Mitigation:* the doc names the grep command; an inline note documents that anyone adding `accessCheck(false)` MUST update this file. A future CI gate could enforce this.
2. **Spec stamp wrong format.** *Mitigation:* match the format used by existing stamps in the same file or in `docs/specs/infrastructure.md` (see the recent agent-executor mission's edit).

## Reviewer guidance

- For T022: verify the spec describes all FOUR enforcement layers (route, entity-handler, entity-query, field) — the entity-query layer is the new one.
- For T023: cross-check the table against `grep -rn "accessCheck(false)" packages/ --include="*.php" | grep -v "/tests/"`. Every match in the grep should be in the table, and every entry in the table should match a grep result.
- For T024: confirm #1495 is referenced and the Security section names the gap that was closed (the no-op stub).

## Implementation command

```
spec-kitty agent action implement WP05 --agent <name>
```

## Activity Log

- 2026-05-19T01:28:08Z – claude:sonnet:implementer:implementer – shell_pid=479732 – Started implementation via action command
- 2026-05-19T01:35:27Z – claude:sonnet:implementer:implementer – shell_pid=479732 – Ready for review: access-control spec updated with 4-layer enforcement section + spec stamp; bypass-audit doc filed (16 unconditional system-context sites + 8 conditional-fallback sites + 10 'removed during mission' entries reflecting WP03's sweep); CHANGELOG [Unreleased] carries Added (3), Changed (2), Security (1) bullets referencing #1495; drift detector quiet
- 2026-05-19T01:36:00Z – claude:opus-4-7:reviewer:reviewer – shell_pid=481899 – Started review via action command
