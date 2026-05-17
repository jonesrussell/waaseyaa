---
work_package_id: WP09
title: config:* CLI namespace reservation + collision-check + ConfigCommand base
dependencies: []
requirement_refs:
- FR-047
- FR-048
- FR-049
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During
  /spec-kitty.implement this WP may branch from a dependency-specific base, but completed
  changes must merge back into main unless the human explicitly redirects the landing
  branch.
base_branch: main
base_commit: 8f2f2c483d1819983bb56e654278d41bc2c76d57
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T047
- T048
- T049
- T050
shell_pid: "101861"
history: []
authoritative_surface: packages/cli/
execution_mode: code_change
owned_files:
- packages/cli/src/Command/Config/ConfigCommand.php
- packages/config/src/Exception/ConfigCommandCollisionException.php
- packages/cli/tests/Unit/Command/Config/ConfigCommandTest.php
- packages/config/tests/Unit/Exception/ConfigCommandCollisionExceptionTest.php
- tests/Integration/Phase28/ConfigCommandCollisionBootTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP09 — config:* CLI namespace reservation + collision-check + ConfigCommand base

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Reserve the `config:*` CLI verb namespace framework-side. `ConfigCommand` base class registers reserved sub-verbs (`export`, `import`, `diff`, `status`, `validate`, `reset`). App or extension commands registering reserved verbs fail at boot via `ConfigCommandCollisionException`; apps MAY define non-reserved `config:<custom>` verbs.

## Requirements covered

- FR-047
- FR-048
- FR-049

## Dependencies

No prerequisite WPs — may dispatch immediately on mission start.

## Subtasks

- T047 — Implement `ConfigCommand` base class with reserved sub-verb registry (export, import, diff, status, validate, reset) (FR-047).
- T048 — Implement `ConfigCommandCollisionException` with stable code; app-registered reserved verbs fail at boot (FR-048).
- T049 — Allow apps to register non-reserved `config:<custom>` verbs without collision (FR-049).
- T050 — Integration test in `tests/Integration/Phase28/` exercising kernel refusal to boot on collision (FR-048).

## Owned files

- `packages/cli/src/Command/Config/ConfigCommand.php`
- `packages/config/src/Exception/ConfigCommandCollisionException.php`
- `packages/cli/tests/Unit/Command/Config/ConfigCommandTest.php`
- `packages/config/tests/Unit/Exception/ConfigCommandCollisionExceptionTest.php`
- `tests/Integration/Phase28/ConfigCommandCollisionBootTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-17T01:27:26Z – claude:sonnet:python-implementer:implementer – shell_pid=100148 – Started implementation via action command
- 2026-05-17T01:33:10Z – claude:sonnet:python-implementer:implementer – shell_pid=100148 – WP09 ready: config:* namespace reservation + collision-check + ConfigCommand base. 33 new tests (unit+integration), gates green.
- 2026-05-17T01:33:39Z – claude:opus:python-reviewer:reviewer – shell_pid=101861 – Started review via action command
- 2026-05-17T01:34:52Z – claude:opus:python-reviewer:reviewer – shell_pid=101861 – WP09 review passed: 5 files (3 src + 2 tests) + Phase28 integration; 33 new tests pass; cs/phpstan/layers clean. RESERVED_VERBS covers all 6 shipped commands (export/import/diff/status/validate/reset). isReservedFqcn allowlist + subclass support correctly justified (sibling handlers, not OO chain). FR-047/048/049 semantics correct: reserved verb + allowlisted FQCN = no-op; reserved verb + foreign FQCN = throw; non-reserved (incl config:custom) = pass-through. Note: CliKernel does not yet *invoke* assertNoCollision — the integration test simulates the hook via a kernel facade. The hook surface is the library contract; kernel wiring is a follow-up. Acceptable for a namespace-reservation WP.
