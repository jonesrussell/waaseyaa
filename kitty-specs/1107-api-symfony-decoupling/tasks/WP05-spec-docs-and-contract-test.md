---
work_package_id: WP05
title: Spec docs and Symfony-import contract test
dependencies:
- WP02
- WP03
- WP04
requirement_refs:
- FR-005
- FR-006
- C-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
assignee: claude
agent: "claude"
history: []
authoritative_surface: docs/specs/
execution_mode: code_change
owned_files:
- docs/specs/api-layer.md
- docs/specs/jsonapi.md
- docs/specs/http-entry-point.md
- docs/specs/middleware-pipeline.md
- docs/specs/infrastructure.md
- packages/api/tests/Contract/SymfonyImportBoundaryTest.php
tags: []
shell_pid: "650851"
---

# WP05 — Spec docs and contract test

## Goal

Update five spec docs to record the new boundary. Add a contract test that
asserts a sample app controller produces a JSON:API response without
importing any `Symfony\` class. Annotate anchor `#1107` with merged-commit
references. File the deferred follow-up issue per ratified C-005 (b).

## Acceptance Criteria

- **FR-005**: `docs/specs/api-layer.md`, `docs/specs/jsonapi.md`,
  `docs/specs/http-entry-point.md`, `docs/specs/middleware-pipeline.md`,
  `docs/specs/infrastructure.md` each reference the new
  `Waaseyaa\Foundation\Http\Request`, `Waaseyaa\Api\Http\JsonApiResponse`,
  and `Waaseyaa\Foundation\Event\EventDispatcherInterface`. The
  charter-vs-body framing tightens to Path R-narrow language.
- **FR-006**: `packages/api/tests/Contract/SymfonyImportBoundaryTest.php`
  defines a sample controller (or fixture) and asserts via reflection /
  source scan that no `Symfony\` class is imported; the controller still
  produces a valid JSON:API response.
- `infrastructure.md` adds the C-003 explicit framework-internal contract
  note (DomainEvent extends Symfony Event; only dispatcher abstracted).
- Anchor `#1107` body annotated with merged-commit references for WP02-WP05.
- New GitHub issue filed: "enforce Symfony-import boundary across consumer
  code" referencing this mission's merged commits, allowlist scope
  (Foundation, Routing, API internals, Validation, CLI), and the soft-rot
  tradeoff acknowledgment.

## Subtasks

- [ ] T012 — Edit the five spec docs. Each gets a brief section pointing to
  the new types and the boundary intent. `infrastructure.md` carries the
  longest update (C-003 contract note + the routing-still-Symfony
  acknowledgement per Path R-narrow).
- [ ] T013 — Add `SymfonyImportBoundaryTest` in
  `packages/api/tests/Contract/`. Use a sample controller fixture under
  `packages/api/tests/Fixtures/` with NO `use Symfony\` lines; the test
  asserts source contents.
- [ ] T014 — Annotate `#1107` (via `gh`) with merged commits; file the
  follow-up "enforce-symfony-import-boundary" issue with the allowlist
  scope and the WP02-WP05 commit references.

## Test strategy

- Contract test in `packages/api/tests/Contract/` uses
  `#[CoversNothing]`. Asserts via `file_get_contents()` + regex that the
  sample controller fixture contains no `use Symfony\` line, AND
  programmatically constructs the controller and verifies the JSON:API
  response shape.

## Verification

- `./vendor/bin/phpunit --testsuite Integration` green (the contract test
  may be classified Integration depending on harness).
- `tools/drift-detector.sh` reports no drift after spec doc updates.
- `gh issue view 1107` shows the merged-commit annotation block.
- `gh issue list --state open --search "enforce Symfony-import boundary"`
  returns the new issue.

## Definition of Done

- Five spec docs updated.
- Contract test green.
- Anchor `#1107` annotated.
- Follow-up issue filed.
- WP05 lane merged back into main.
- Mission accepts.

## Risks

- **Spec divergence**: edits to five docs raise drift risk. Mitigation:
  run `tools/drift-detector.sh` before merge.
- **Sample controller drift**: if the fixture controller imports
  Symfony for a constructor type-hint that survives the API migration,
  the test must accept that as scoped (request-handler return value only,
  not constructor wiring).

## Reviewer guidance

- Confirm `infrastructure.md` carries the C-003 + Path R-narrow notes.
- Confirm the contract test fixture is genuinely Symfony-free (review
  source; not just regex).
- Confirm anchor and follow-up issue are wired.

## Activity Log

- (To be appended by the implementer.)
- 2026-05-03T16:01:53Z – claude – shell_pid=650851 – Started review via action command
- 2026-05-03T16:02:11Z – claude – shell_pid=650851 – Self-review passed. T012: 5 spec docs updated (api-layer, jsonapi, http-entry-point, middleware-pipeline, infrastructure) — drift D4 closed in infrastructure.md. T013: SymfonyImportBoundaryTest 2/2 green (6 assertions); fixture controller imports zero Symfony namespaces. T014: anchor #1107 annotated (comment 4366576551); follow-up issue #1374 filed (enforce Symfony-import boundary). Unit suite 6390/6390. All gates clean.
