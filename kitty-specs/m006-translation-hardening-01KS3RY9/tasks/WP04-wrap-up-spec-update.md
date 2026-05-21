---
work_package_id: WP04
title: Wrap-up and spec update
dependencies:
- WP01
- WP02
- WP03
requirement_refs:
- C-003
- C-005
- C-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T013
- T014
- T015
agent: "claude:sonnet:implementer:implementer"
shell_pid: "782878"
history:
- date: '2026-05-20T23:57:09Z'
  author: tasks-materializer
  note: Initial WP file generated
authoritative_surface: docs/specs/
execution_mode: planning_artifact
mission_slug: m006-translation-hardening-01KS3RY9
owned_files:
- docs/specs/entity-storage-two-axis.md
- CHANGELOG.md
tags: []
---

# WP04 — Wrap-up and spec update

**Mission**: m006-translation-hardening-01KS3RY9
**Closes**: SC-007 (spec update); C-003 (CHANGELOG + issue close references)
**Priority**: implement last — depends on WP01 + WP02 + WP03 all merged and green

## Objective

Document the new access-gate convention and BCP-47 regex constant in the canonical
two-axis spec, write the CHANGELOG entry covering all three closed issues, and
confirm `composer verify` is green on the merge commit.

## Context

- **Spec file**: `docs/specs/entity-storage-two-axis.md` — the canonical home for
  the translation storage contract per plan.md §Assumptions item 5.
- **CHANGELOG format**: `[Unreleased]` section, Keep-a-Changelog format.
  Per `feedback_changelog_release_workflow.md`: add bullets under `[Unreleased]` only;
  release-cut.yml promotes them at tag time.
- **Issue close references**: C-003 requires `Closes #1445`, `Closes #1446`, `Closes #1447`
  in the merge commit footer. These may be on the PR body of the final squash-merge PR,
  or in the commit message — confirm which surfaces GitHub honours for auto-close in this repo.
  Per CLAUDE.md memory `feedback_partial_fix_closes_footer.md`: `Closes #N` in a commit
  footer survives squash-merge and auto-closes.

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- Implement from the workspace `spec-kitty agent action implement WP04 --agent <name>` allocates.
- This WP cannot start until WP01, WP02, and WP03 are all merged to `main`.

## Implementation Command

```bash
spec-kitty agent action implement WP04 --agent claude:sonnet
```

---

## Subtask T013 — Update `docs/specs/entity-storage-two-axis.md`

**Purpose**: Document the translation access-gate convention (FR-002 per-method ability
mapping) and the BCP-47 regex constant reference so future implementors find this
information without spelunking source code (SC-007).

**File**: `docs/specs/entity-storage-two-axis.md`

**Steps**:

1. Read the current file to locate the best insertion point. The new section should go
   **after** any existing "Translation storage" or "Schema" section, and **before** any
   "Migration" or "Upgrade" section if present. If the file has a table of contents,
   add the new section to it.

2. Add a section `## Translation access-gate convention` with the following content
   (adapt formatting to match the existing doc style):

```markdown
## Translation access-gate convention

### Per-method ability mapping (FR-002)

`TranslationController` enforces the same ability namespace as the parent entity's CRUD
surface. No parallel `view_translation` / `create_translation` abilities exist.

| HTTP method | Controller method | Ability checked |
|---|---|---|
| GET | `index` | `view` |
| GET | `show` | `view` |
| POST | `store` | `create` |
| PATCH | `update` | `update` |
| DELETE | `destroy` | `delete` |

The check is performed via `EntityAccessHandler::check($entity, $operation, $account)`
where `$entity` is the **parent entity** (not the translation object). Per-langcode
policies (e.g. "user X can edit `fr` but not `de`") are out of scope for v1.

The account is read from the request via `$request->attributes->get('_account')`.
Do not use `'account'` (no underscore) — that key is not set by `SessionMiddleware`.

### Anonymous account behaviour

`AnonymousUser` (id: 0) passes through the same `EntityAccessHandler::check()` pipeline
as authenticated users. Translation reads are not implicitly public; they respect the
entity type's access policy. Callers must not special-case the anonymous account.

### BCP-47 langcode validation

The canonical BCP-47-tolerant regex is:

```
Waaseyaa\Entity\LangcodeValidator::BCP47_PATTERN
```

Pattern: `/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/`

Accepts: language (2-8 alpha) + optional script (4 alpha) + optional region (2 alpha / 3 digits).
Examples: `en`, `en-US`, `zh-Hant`, `zh-Hant-TW`, `fr-CA`.
Rejects: injection payloads, empty string, control characters, whitespace, variant subtags.

Any code that validates langcodes (CLI generators, API controllers, importers) MUST
import this constant rather than re-deriving the pattern (NFR-002).
```

3. If `docs/specs/entity-storage-two-axis.md` already has a section that covers access
   or security, merge the new content into that section rather than creating a duplicate.

4. Add a `<!-- Spec reviewed 2026-05-20 - M-C WP04 adds access-gate convention and BCP-47 regex -->` 
   comment near the top or in the relevant section to pacify the drift detector
   (per `feedback_drift_detector_review_stamp.md` memory).

**Validation**:
- [ ] The spec file compiles cleanly (no broken Markdown table formatting).
- [ ] The ability-mapping table is present and correct.
- [ ] `LangcodeValidator::BCP47_PATTERN` is referenced with its FQCN.
- [ ] The `_account` vs `account` gotcha is documented.

---

## Subtask T014 — Add `CHANGELOG.md` `[Unreleased]` entry

**Purpose**: Record the mission's changes in the changelog so the release-cut script
can promote them to a version heading at tag time.

**File**: `CHANGELOG.md`

**Steps**:

1. Locate the `## [Unreleased]` section at the top of `CHANGELOG.md`.

2. Add bullets under the appropriate subsection (`### Fixed` is most appropriate for
   these security/hardening items; use `### Added` for new public capabilities if preferred):

```markdown
### Fixed
- fix(api): `TranslationController` now enforces entity access policy on all 5 translation
  endpoints; unauthenticated or unauthorized requests return 403 Forbidden (Closes #1445)
- fix(cli): `AddTranslationsMigrationGenerator` validates `--default-langcode` and
  `--add-translations` entries against a canonical BCP-47 regex before writing any file;
  injection payloads are rejected with a clear error (Closes #1446)
- fix(entity): `TranslatableInterface` now declares `fieldLangcode(string $fieldName): ?string`,
  giving non-trait implementors compile-time enforcement of the contract (Closes #1447)

### Added
- feat(entity): `LangcodeValidator` class with `BCP47_PATTERN` constant — single canonical
  BCP-47-tolerant regex for all langcode validation in the framework
```

3. Adjust the exact wording to match the project's existing CHANGELOG voice. Look at
   two or three recent `[Unreleased]` entries for the style.

4. Do **not** add `Closes #N` to the CHANGELOG body itself if the project convention
   excludes issue refs from the changelog (check existing entries). Put `Closes #N` in
   commit/PR footers, not necessarily in the bullet text.

**Validation**:
- [ ] `[Unreleased]` section has entries for all three issues.
- [ ] Bullets are under the correct subsection (`### Fixed` or `### Changed`).
- [ ] No duplicate entries.

---

## Subtask T015 — Run `composer verify` and confirm green

**Purpose**: Prove the merge commit is releasable (C-005). Document the green result in
the commit message.

**Steps**:

1. From the repository root, run:
```bash
composer verify
```
This runs: `cs-check`, `phpstan`, `phpunit`, `check-dead-code`, `check-composer-policy`,
`check-package-layers` in sequence.

2. If any check fails, fix the issue before committing WP04. Common issues at this stage:
   - **cs-check**: run `composer cs-fix` to auto-fix; then run cs-check again to confirm.
   - **phpstan**: address any new findings introduced by WP01/WP02/WP03 changes.
   - **check-dead-code**: if `LangcodeValidator::validate()` or `TranslationController::forbiddenDocument()` appear as "dead code", add `@api` to the relevant class or mark the method as `@internal` and ensure it is called — it is called from test code, which should satisfy the detector. Check `tools/phpstan/WaaseyaaEntrypointProvider.php` if needed.
   - **check-package-layers**: confirm no new upward layer imports were introduced.

3. When all checks pass, include a note in the WP04 commit message:
```
chore(M-C): wrap-up — spec update, changelog, composer verify green

- docs/specs/entity-storage-two-axis.md: translation access-gate convention + BCP-47 ref
- CHANGELOG.md: [Unreleased] entries for #1445 #1446 #1447
- composer verify: all checks pass

Closes #1445
Closes #1446
Closes #1447
```

**Note on `Closes #N`**: The three `Closes` lines must appear in the **merge commit** body
or footer to trigger GitHub auto-close. If the squash-merge PR body doesn't carry them,
add them to the commit message. See memory `feedback_partial_fix_closes_footer.md`.

**Validation**:
- [ ] `composer verify` exits 0.
- [ ] Commit message contains `Closes #1445`, `Closes #1446`, `Closes #1447`.
- [ ] All three GitHub issues close on merge.

---

## Definition of Done

- [ ] `docs/specs/entity-storage-two-axis.md` has §"Translation access-gate convention" with ability-mapping table and `LangcodeValidator::BCP47_PATTERN` reference.
- [ ] `CHANGELOG.md` has `[Unreleased]` bullets for #1445, #1446, #1447.
- [ ] `composer verify` exits 0 on the WP04 commit.
- [ ] Commit/PR footer contains `Closes #1445`, `Closes #1446`, `Closes #1447`.
- [ ] Drift detector stamp added to `entity-storage-two-axis.md`.

## Risks

| Risk | Mitigation |
|------|------------|
| `composer verify` fails due to WP01/WP02/WP03 regressions | Fix in the relevant WP branch first; WP04 should not carry code fixes |
| `entity-storage-two-axis.md` has a conflicting section | Read the full file before editing; merge rather than duplicate |
| GitHub does not auto-close on squash-merge | Verify by checking issues after merging; re-close manually if needed |
| Dead-code detector flags `LangcodeValidator` | Add `@api` to the class if it's a public utility (it is — multiple packages will use it); or confirm test-only use satisfies the detector |

## Reviewer Guidance

- Verify the ability-mapping table in the spec matches FR-002 exactly.
- Confirm `LangcodeValidator::BCP47_PATTERN` is referenced by its full FQCN.
- Confirm all three `Closes #N` lines are in the commit/PR body.
- Confirm no source code changes are in this WP — only docs and changelog.

## Activity Log

- 2026-05-21T01:00:23Z – claude:sonnet:implementer:implementer – shell_pid=782878 – Started implementation via action command
- 2026-05-21T01:08:30Z – claude:sonnet:implementer:implementer – shell_pid=782878 – Routes wired; DI complete; spec updated; CHANGELOG bullet added
