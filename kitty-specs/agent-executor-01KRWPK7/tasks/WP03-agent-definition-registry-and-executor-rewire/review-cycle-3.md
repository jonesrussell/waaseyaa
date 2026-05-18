---
affected_files: []
cycle_number: 3
mission_slug: agent-executor-01KRWPK7
reproduction_command:
reviewed_at: '2026-05-18T17:24:31Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP03
---

# WP03 Review — Cycle 1 (REJECTED)

**Mission:** `agent-executor-01KRWPK7`
**Work Package:** WP03 — AgentDefinition registry + AgentExecutor rewire (+ absorbed deletions)
**Reviewed commit:** `b39f788bd`
**Reviewer:** opus (lane-c review pass)
**Verdict:** REJECTED — hard gate failure + missing required test coverage.

---

## Scope reviewed

Original WP03 (T017–T023) + scope absorbed from WP01:
- `AgentDefinition` VO + `#[AsAgentDefinition]` + `AgentDefinitionRegistry` + manifest-compiler `agent_definitions` collector.
- `AgentResult` token/cost telemetry.
- `AgentExecutor` rewired onto `Waaseyaa\AI\Tools\ToolRegistryInterface`.
- HITL state machine (None / All / Interactive) + cancellation polling.
- Provider retry (3 attempts, exponential backoff, 30s cap).
- Legacy deletions: `AgentInterface`, in-memory `AgentAuditLog` VO, legacy `ToolRegistry[Interface]`, `packages/mcp/src/Tools/{Entity,Discovery,Traversal,Editorial}Tools.php`, `packages/ai-schema/src/Mcp/*`.

## Summary

The implementation is substantively correct on the executor itself: audit-row invariants are
honoured, the retry loop emits one `provider_call` per attempt, the HITL state machine reaches
`AwaitingApproval` and resumes via the outer-scope `$toolArgs` (no refetch), cancellation polling
runs at iteration entry **and** before tool dispatch (no provider-streaming poll), the transcript
truncation marker is idempotent (early-exit on `str_ends_with($transcript, '[truncated]')`), the
`AgentDefinition` VO/attribute/registry match the data-model, `PackageManifestCompiler` uses
**string FQCN** for Foundation L0 cleanliness, and the absorbed deletions are clean. PHPStan,
package-layer, composer-policy, dead-code, and external-consumers gates all pass.

However, the WP is **not approvable as-is** for two reasons:

1. **`composer cs-check` fails (hard gate).** Both new test files trip `php-cs-fixer` —
   `composer verify` is the project's published quality-gate target and this MUST be clean
   before approval (per CLAUDE.md "Code quality" section + dead-code-audit precedent).
2. **HITL-Interactive and transcript-truncation behaviour is implemented but untested.** The WP
   prompt explicitly required tests for all three HITL modes and for the transcript marker; only
   None/All are exercised. This leaves the most subtle codepath (the poll/timeout/approve loop,
   `pending_approval_call_id` write, and the resume continuation) unguarded by regression.

Required changes are mechanical and confined to test files plus one narrow executor refinement
(retry classification). No architectural rework needed.

---

## Numbered change requests

### CR1 — Fix `composer cs-check` failures (BLOCKER)

`composer cs-check` reports diff in both new test files. Run `composer cs-fix` and recommit.
Specific items flagged by php-cs-fixer 3.95.1:

- `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php:48` —
  `static fn (array $t):` → `static fn(array $t):` (no space after `fn`).
- `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php:126+` — inline
  same-line method bodies inside the anonymous tool class need brace expansion per project Pint
  preset (e.g. `public function getRoles(): array { return ['administrator']; }` → multi-line).
- `tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php:211` —
  `sleepMs: static fn (int $ms): null => null,` → `sleepMs: static fn(int $ms): null => null,`.
- `tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php:245+` — same inline-method
  expansion required inside the anonymous `AccountInterface` factory class.

**Acceptance:** `composer cs-check` exits 0 in lane-c HEAD with no `.php-cs-fixer.cache`
stickiness (clear cache, re-run twice — see `feedback_cs_fix_two_passes.md`).

### CR2 — Add HITL **Interactive** behavioural tests (BLOCKER)

`tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php` currently has 4 tests covering only
`None`, `All`, cancellation, and provider-retry exhaustion. The WP-prompt T020 validation
explicitly requires Interactive coverage:

> "Add three contract tests covering None, All, and Interactive (approved + timed-out + denied)
> paths."

Add (at minimum) three new `#[Test]` cases in `ExecutorHitlTest.php`:

- `hitlInteractiveApprovalGrantedResumesWithStoredArgs` — seed `AgentRun.status=Running` then
  flip to `Running` after the executor sets `AwaitingApproval` (simulate the WP-05 approve
  endpoint); assert tool actually runs with the same `$toolArgs`, `approval_required` +
  `approval_granted` audit rows both present, exactly one of each per call_id (per
  data-model.md § Audit invariants).
- `hitlInteractiveTimeoutTerminatesRunWithApprovalTimeout` — set `hitlTimeoutSeconds=1`,
  never flip status; assert run lands `Failed/approval_timeout`, audit ends
  `ApprovalRequired` + `Error` (no spurious `ApprovalGranted`).
- `hitlInteractiveDenialTerminatesRunWithApprovalDenied` — flip status to `Cancelling` while
  the executor polls; assert `Failed/approval_denied` (the existing code path returns
  `HITL_DENIED_INTERACTIVE` and emits `ApprovalDenied`; spec asks this be a distinct user-facing
  audit/terminal pair from cancellation).

These tests will also pin the "no refetch — args come from the outer foreach scope" invariant
that's currently implicit. Reviewer verified this is correct in code (see executor lines 217–280
in `b39f788bd`); the test must lock it in.

### CR3 — Add transcript truncation test (BLOCKER)

WP-prompt T019 validation explicitly requires "Transcript truncation marker appears at the
configured cap." Add a `#[Test]` in `ExecutorHitlTest.php` (or split into a small unit test in
`packages/ai-agent/tests/Unit/`):

- Configure `transcriptMaxBytes` to a tiny value (e.g. 64).
- Drive the executor through enough provider responses to overflow.
- Assert the resulting `AgentRun.transcript` ends with `\n[truncated]` exactly once and that
  subsequent writes are no-ops (call `appendTranscript()` a second time after truncation —
  output unchanged).

### CR4 — Add retry "two failures then success" test (BLOCKER, low effort)

WP-prompt T022 validation explicitly requires "Two failures + one success completes the run."
The current `providerRetryExhaustionTerminatesWithRateLimited` only covers exhaustion. Add a
second retry test that throws `RateLimitException` twice then returns a success
`MessageResponse` — assert: 3 `provider_call` audit rows (2 failure + 1 success), run reaches
`Completed`, and the audit row order is `failure, failure, success`.

### CR5 — Narrow the retry catch (non-blocking refinement)

`packages/ai-agent/src/AgentExecutor.php:608–630` catches `RateLimitException` separately and
then `\Throwable` for everything else, retrying the latter on all values. FR-025 wording:
"Retry on RateLimitException (429) and transport / 5xx errors. Do **not** retry on validation /
4xx errors other than 429."

`AnthropicProvider::httpPost()` currently throws bare `\RuntimeException` for both 4xx (non-429)
and 5xx (lines 207–214, 295–313) — there's no way for the executor to distinguish. Two options:

(a) Introduce `Waaseyaa\AI\Agent\Provider\TransportException` (extends `\RuntimeException`) and
    `ClientErrorException` (extends `\RuntimeException`, for non-429 4xx) in
    `packages/ai-agent/src/Provider/`, have `AnthropicProvider` throw the appropriate one based
    on HTTP code, and narrow the executor's catch to `RateLimitException | TransportException`
    only — letting `ClientErrorException` propagate to the iteration-level handler which marks
    the run `Failed/provider_unavailable` (or a new `provider_client_error`) without retries.

(b) Document this gap as a known follow-up issue and keep the over-retry behaviour with a
    `@todo` comment plus a tracking note in the doctrine spec, accepting the budget burn on
    misclassified 4xx errors as acceptable for v1.

CR5 is **non-blocking** for WP03 — option (b) is acceptable provided a follow-up issue is filed
before mission merge. Mark which option you choose in the cycle-2 commit message.

### CR6 — Add `AgentExecutorHitlContractTest` (non-blocking note)

The WP prompt names `packages/ai-agent/tests/Contract/AgentExecutorHitlContractTest.php` as an
expected artefact. The implementation chose integration tests instead — that is a *better*
choice in this codebase per existing convention (real SQLite, real repositories), and the smoke
`AgentExecutorTest` rationale comment is solid. **Do not** create a redundant contract test;
instead, edit the WP prompt's T020 "Files" list to reflect reality (remove the Contract test
path, keep the integration test path).

This is a one-line spec edit; flag it in the cycle-2 commit message so the orchestrator knows
to update downstream WP prompts that reference Contract test paths.

---

## Unchanged decisions to preserve

The following implementer scope adjustments are **accepted** and must NOT be reverted in cycle 2:

1. **`McpController` kept; production routing rewired through `McpEndpoint`.**
   `McpRouteProvider::registerRoutes()` is the canonical production path and points `/mcp` at
   `McpEndpoint::handle` — verified. The legacy `McpController` is only reachable via the
   older `Foundation\Http\Router\McpRouter` fallback (~10 test files still use it). Deleting it
   in WP03 would cascade into Phase14/Phase15 integration tests outside the WP's scope. The
   chosen rewire path satisfies FR-012 ("McpController SHALL consume the new
   `packages/ai-tools` registry") at the *production-routed* class (now `McpEndpoint`).
   **Follow-up required at mission level (not WP03):** file a tracking issue to either
   (a) port the Phase14/15 tests onto `McpEndpoint` and delete `McpController`, or
   (b) explicitly mark `McpController` as a documented legacy fallback in the doctrine spec.
   Notify the orchestrator so the issue is filed before mission merge.

2. **Broader ai-schema deletions** (`McpToolGenerator`, `TranslationToolGenerator`,
   `McpToolExecutor`, `SchemaRegistry` + tests). External-consumers check is green; PHPStan
   clean; dead-code clean. These were superseded by `AgentTool` / `AttributeToolRegistry` from
   WP01 and have no consumers in the new path. **Accepted.**

3. **Deletion of legacy `packages/ai-agent/src/{ToolRegistry,ToolRegistryInterface}.php`** and
   migration to `Waaseyaa\AI\Tools\ToolRegistryInterface`. Layer 5 → Layer 5 is fine;
   `bin/check-package-layers` green. **Accepted.**

4. **`AiAgentServiceProvider` created alongside WP02's `AiAgentEntityServiceProvider`.**
   No duplicate bindings — verified by diff. **Accepted.**

5. **Pre-existing 8+8 failures in Phase13 / Oidc** — these are not WP03-touched code; the
   stash-test methodology is acceptable. Reviewer skipped the full integration suite run; if
   the orchestrator wants higher confidence, the next reviewer can run
   `composer test --testsuite Unit` on lane-c and on the merge-base and compare. (Not
   required for cycle 2.)

---

## Acceptance criteria for cycle 2

Cycle 2 will be approved when:

- [ ] `composer cs-check` exits 0 (CR1).
- [ ] `ExecutorHitlTest` has at least the three new Interactive cases (CR2) and they pass.
- [ ] Transcript truncation has a regression test (CR3) and it passes.
- [ ] Retry has a "2 fails + 1 success" test (CR4) and it passes.
- [ ] CR5 either implemented (option a) OR a follow-up issue is filed and linked in the cycle-2
      commit message (option b).
- [ ] `composer phpstan`, `bin/check-package-layers`, `bin/check-composer-policy`,
      `bin/check-dead-code`, `bin/check-external-consumers ai-agent-orphans` all still green.
- [ ] No new touches to WP02 territory (`packages/ai-agent/src/{Entity,Repository,Access,Enum}/`,
      `migrations/`, `AiAgentEntityServiceProvider`, `packages/access/src/Capability/`) or to
      WP04+ territory (`packages/ai-agent/src/{Message,Service,Reaper,Broadcast}/`,
      `RunAgentHandler`).
- [ ] CR6: WP prompt T020 Files list edited (or explicitly waived in the cycle-2 commit
      message).

---

## Reviewer signature

Reject reason: hard `cs-check` gate failure + missing required test coverage for
HITL-Interactive, transcript truncation, and retry success-after-failure. All other
acceptance-checklist items pass.

The executor implementation itself is sound and does not need refactoring; cycle 2 is a
tests-plus-style pass, not a re-implementation.
