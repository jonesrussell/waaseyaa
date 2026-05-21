---
work_package_id: WP05
title: Wrap-up
dependencies:
- WP01
- WP02
- WP03
- WP04
requirement_refs:
- NFR-004
- C-002
- C-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T028
- T029
- T030
- T031
history:
- date: '2026-05-20T23:57:13Z'
  event: created
authoritative_surface: docs/specs/
execution_mode: code_change
owned_files:
- docs/specs/agent-executor.md
- CHANGELOG.md
tags: []
---

# WP05 — Wrap-up

**Closes**: #1509, #1510, #1511, #1513 (via merge commit `Closes #N` footer per C-002)
**Depends on**: WP01, WP02, WP03, WP04
**Implement command**: `spec-kitty agent action implement WP05 --agent <name>`

## Objective

Update `docs/specs/agent-executor.md` with the new exception hierarchy and event dispatch contract, add the `CHANGELOG.md` `[Unreleased]` entry, run `composer verify` to confirm the gate is green, and verify all SC-001..SC-008 success criteria pass.

## Context

This is a documentation + verification WP. No new source code is written here. The spec files that need updating are:

- `docs/specs/agent-executor.md` — the canonical spec for the agent executor subsystem. Must document the new typed exception hierarchy (FR-001..FR-003), event dispatch contract (FR-004..FR-005), broadcaster consolidation (FR-006..FR-007), and `--watch` implementation (FR-008..FR-009).
- `CHANGELOG.md` — follow the `[Unreleased]` pattern per `feedback_changelog_release_workflow.md` memory note.

The `composer verify` gate now includes `bin/check-openapi` (added in WP02/T018). All four CI gates must pass:
- PHPStan (dead-code baseline)
- CS-Fixer (formatting)
- `bin/check-package-layers` (layer architecture)
- `bin/check-openapi` (OpenAPI document validity)
- `bin/check-composer-policy` (Composer manifest policy)
- `bin/check-dead-code` (dead-code gate)

## Subtasks

### T028 — Update `docs/specs/agent-executor.md`

**File**: `docs/specs/agent-executor.md`

**Purpose**: The spec must reflect the state of the codebase after this mission. Stale specs cause future agents to generate code conflicting with current behaviour (CLAUDE.md gotcha).

**Steps**:
1. Open `docs/specs/agent-executor.md`. (Also check `docs/specs/ai-integration.md` — if that file covers the agent executor, update it too.)
2. Add or update the following sections:

   **Exception Hierarchy section**:
   ```markdown
   ## Provider Exception Hierarchy
   
   All AI provider exceptions extend `Waaseyaa\AI\Agent\Provider\ProviderException`
   (abstract, extends `\RuntimeException`):
   
   | Class | HTTP trigger | Retry behaviour |
   |---|---|---|
   | `RateLimitException` | 429 | Retried with backoff per FR-025 budget |
   | `TransportException` | 5xx, network errors | Retried per FR-025 budget |
   | `ClientErrorException` | 4xx non-429 | Re-thrown immediately, no retry |
   
   `AnthropicProvider` and `OpenAiCompatibleProvider` throw these typed exceptions
   for all HTTP outcomes. Bare `\RuntimeException` is not used for HTTP status codes.
   ```

   **Event Dispatch Contract section**:
   ```markdown
   ## Lifecycle Event Dispatch
   
   `AgentExecutor` dispatches these events via `EventDispatcherInterface` (L0):
   
   | Event | Dispatch point | Owner |
   |---|---|---|
   | `AgentRunStarted` | Entry of run loop | `AgentExecutor` |
   | `AgentRunIterationCompleted` | End of each iteration | `AgentExecutor` |
   | `AgentRunProviderCallCompleted` | After provider call returns | `AgentExecutor` |
   | `AgentRunToolCallObserved` | Per tool call in the loop | `AgentExecutor` |
   | `AgentRunTerminated` (normal) | Normal run completion | `AgentExecutor` |
   | `AgentRunTerminated` (abnormal) | Supervisor kill / pre-executor cancel | `RunAgentHandler` |
   
   Dispatch is best-effort: listener exceptions are logged via `LoggerInterface` and
   do not abort the run. Exactly one `AgentRunTerminated` fires per run.
   ```

   **Broadcaster section** (update):
   ```markdown
   ## Broadcaster
   
   `AgentRunBroadcaster` is the sole implementation of `AgentRunBroadcasterInterface`.
   `BroadcastStorageAdapter` was removed in mission `agent-executor-v1-1-audit-followups`.
   `AgentRunBroadcasterServiceProvider` is the canonical binding.
   ```

   **CLI --watch section**:
   ```markdown
   ## CLI --watch
   
   `bin/waaseyaa ai:run "<prompt>" --watch` attaches an SSE consumer to
   `/broadcast?channels=agent.run.<id>` via `StreamHttpClient`. Events are printed
   to stdout as they arrive. The command exits cleanly on `terminated` event.
   SIGINT (Ctrl-C) closes the stream; the server-side run continues.
   ```

3. Update the `<!-- Spec reviewed YYYY-MM-DD - reason -->` stamp at the top or bottom of the file to today's date.

**Validation**:
- [ ] All four new/updated sections present in the spec
- [ ] Review stamp updated
- [ ] `tools/drift-detector.sh` does not flag the spec as stale (or the stamp suppresses it)

---

### T029 — Add `CHANGELOG.md` `[Unreleased]` entry

**File**: `CHANGELOG.md`

**Purpose**: Document the mission's changes for operators. The `[Unreleased]` section is promoted to a version heading by `release-cut.yml` at tag time — do NOT add a version heading manually.

**Steps**:
1. Open `CHANGELOG.md`.
2. Find the `## [Unreleased]` section.
3. Add entries under the appropriate subsections (`### Fixed`, `### Changed`, `### Added`):

   ```markdown
   ### Fixed
   - **ai-agent**: `AgentExecutor::callProviderWithRetry` now correctly retries only
     transient errors (`TransportException`, `RateLimitException`); 4xx non-429
     `ClientErrorException` re-throws immediately without burning retry budget (#1509).
   - **ai-agent**: `AgentExecutor` and `RunAgentHandler` now dispatch all five
     `AgentRunTelemetryListener` domain events at their lifecycle points; the
     observability listener was previously wired but received no events (#1510).
   - **ai-agent**: Removed `BroadcastStorageAdapter`; `AgentRunBroadcaster` is the
     sole broadcaster implementation; `pending_approval` OpenAPI shape aligned with
     broadcaster emission (#1511).
   - **cli**: `bin/waaseyaa ai:run --watch` is now a working SSE consumer; prior
     implementation was a stub that printed a single message and exited (#1513).
   
   ### Added
   - **api**: `packages/api/openapi.yaml` bootstrapped as the canonical OpenAPI 3.1.0
     document for the Waaseyaa Framework API.
   - **api**: `bin/check-openapi` lint script added to `composer verify` gate (NFR-004).
   - **ai-agent**: Typed provider exception hierarchy — `ProviderException` (abstract),
     `TransportException`, `ClientErrorException`; `RateLimitException` now extends
     `ProviderException` (FR-001/FR-002).
   ```

**Validation**:
- [ ] Entries are in `[Unreleased]` (NOT under a version heading)
- [ ] All four closed issues referenced by number
- [ ] `CHANGELOG.md` is valid Keep-a-Changelog format

---

### T030 — Run `composer verify` and confirm green

**Purpose**: C-004 requires `composer verify` to be green on the merge commit. This step is the explicit gate check.

**Steps**:
1. Run `composer verify` from the project root.
2. If any step fails:
   - PHPStan: check for new dead-code findings on added classes; add `@api` annotation or update baseline.
   - CS-Fixer: run `composer cs-fix` then re-check.
   - `bin/check-openapi`: check `packages/api/openapi.yaml` for syntax errors.
   - `bin/check-package-layers`: check for any upward import introduced in this mission.
   - `bin/check-dead-code`: new classes may need `@api` if not yet wired.
3. Fix all failures and commit before merging.

**Validation**:
- [ ] `composer verify` exits 0
- [ ] All sub-checks pass (PHPStan, CS, package layers, OpenAPI, dead-code, Composer policy)

---

### T031 — Verify SC-001..SC-008 success criteria

**Purpose**: Explicit gate check for all success criteria defined in spec.md.

**Steps**:
Check each criterion:

| SC | Criterion | Verification |
|---|---|---|
| SC-001 | `--watch` prints live events and terminates cleanly | Smoke test in WP04/T027 recorded as PASS |
| SC-002 | All five lifecycle events received by telemetry listener | `AgentRunObservabilityTest::dispatchesAllFiveLifecycleEvents` passes |
| SC-003 | 4xx non-429 does not consume retry budget | `AgentExecutorRetryTest::clientErrorRethrownImmediately` passes |
| SC-004 | 5xx retried to budget | `AgentExecutorRetryTest::transportErrorRetriedToBudget` passes |
| SC-005 | `BroadcastStorageAdapter` does not exist | `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ] && echo PASS` |
| SC-006 | OpenAPI `pending_approval` shape matches broadcaster | `bin/check-openapi` passes |
| SC-007 | `composer verify` green on merge commit | CI status check passes |
| SC-008 | Issues #1509, #1510, #1511, #1513 close on merge | `Closes #1509`, `Closes #1510`, `Closes #1511`, `Closes #1513` in merge commit footer |

For each failing SC: trace back to the responsible WP and verify the implementation.

**Merge commit footer** (C-002 — must be present):
```
Closes #1509
Closes #1510
Closes #1511
Closes #1513
```

**Validation**:
- [ ] All 8 SCs verified as PASS
- [ ] Merge commit footer contains all four `Closes #N` lines
- [ ] No CI checks bypassed (C-006)

---

## Branch Strategy

**Planning/base branch**: `main`
**Merge target**: `main`
**Execution**: Worktree allocated per `lanes.json`. This is the final lane — depends on all prior WPs.

## Definition of Done

- [ ] `docs/specs/agent-executor.md` updated with exception hierarchy + event dispatch + broadcaster + `--watch` sections
- [ ] Review stamp in spec file updated
- [ ] `CHANGELOG.md` has `[Unreleased]` entries for all four closed issues
- [ ] `composer verify` exits 0
- [ ] SC-001..SC-008 all verified PASS
- [ ] Merge commit footer contains `Closes #1509`, `Closes #1510`, `Closes #1511`, `Closes #1513`

## Risks

| Risk | Mitigation |
|---|---|
| `composer verify` fails on dead-code for new classes | New concrete `final` classes that are thrown/caught are not dead; if PHPStan flags them, add `@api` or check why they're not detected as throw targets |
| Spec file is actually `docs/specs/ai-integration.md` | Check both files; update the one that covers `AgentExecutor` |
| Merge commit auto-closes issues before CI green | The PR should have `Closes #N` in the body; GitHub auto-closes on PR merge, not individual commits — confirm with PR template |

## Reviewer Guidance

1. Run `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ] && echo PASS`.
2. Check that `CHANGELOG.md` entries are in `[Unreleased]` and not under a version heading.
3. Verify `docs/specs/agent-executor.md` accurately describes the post-merge state.
4. Confirm merge commit (or PR body) contains all four `Closes #N` footers.

## Activity Log

- 2026-05-21T01:28:56Z – unknown – AI pipeline spec documents exception hierarchy + event dispatch + --watch SSE; CHANGELOG bullet covers all 4 WPs; composer verify green (11 pre-existing check-symfony-imports violations, none from this mission)
