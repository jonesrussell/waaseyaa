---
work_package_id: WP03
title: Test Coverage
dependencies:
- WP02
requirement_refs:
- FR-003
- FR-004
- FR-005
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T010
- T011
- T012
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "89753"
history:
- '2026-05-05: created'
authoritative_surface: packages/ssr/tests/
execution_mode: code_change
mission_id: 01KQTTJS73GVXHFPY5W8E8K3DX
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
owned_files:
- packages/ssr/tests/Contract/**
- packages/ssr/tests/Unit/Http/AppController/**
- packages/ssr/tests/fixtures/**
tags: []
---

# WP03 — Test Coverage

## Objective

Add fixture controllers, unit tests, and the seven contract tests defined in `contracts/dispatcher-deprecation-contract.md` (revised by WP01 in `artifacts/post-1390-dispatcher-contract.md`). Verify both legacy (implicit-array) and modern (attribute-annotated) signatures resolve correctly, the deprecation invariant holds, and dedup works across multiple invocations.

## ⚠️ Hard precondition

framework#1390 must be merged on `main`, AND WP02 must be merged on `main`. The deprecation logger must already be wired before this WP can assert against it. Verify both:

```bash
gh issue view 1390 --repo waaseyaa/framework --json state,closedAt
git log --oneline main | grep -iE 'WP02|deprecation.*plumb' | head -1
```

If either gate fails, **stop**.

## Context

WP02 has wired `LoggerInterface` into the dispatcher binding pipeline. WP03 proves it works.

Read first:

- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md` (canonical contract — schema, dedup, edge cases).
- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/contracts/dispatcher-deprecation-contract.md` (the seven contract tests in §"Test contract").
- `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` (the post-WP02 file you'll be testing).
- `packages/ssr/tests/fixtures/` (existing fixture conventions you'll extend).
- `packages/ssr/tests/Contract/` and `packages/ssr/tests/Unit/Http/AppController/` (existing test patterns).

## Branch Strategy

- Planning/base branch: `main`.
- Final merge target: `main`.
- Lane worktree per `lanes.json`.

## Subtasks

### T010 — Add fixture controllers

**Purpose**: Provide the inputs the contract tests classify and resolve.

**Steps**:

1. Create five small fixture controllers under `packages/ssr/tests/fixtures/AppController/` (mirror existing fixture directory layout if there is one):

   ```php
   namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

   use Symfony\Component\HttpFoundation\Request as HttpRequest;
   use Waaseyaa\Access\AccountInterface;
   use Waaseyaa\SSR\Attribute\MapQuery;
   use Waaseyaa\SSR\Attribute\MapRoute;

   final class LegacyArrayParamsFixture
   {
       public function show(array $params): array { return ['ok' => true, 'received' => $params]; }
   }

   final class LegacyArrayQueryFixture
   {
       public function show(array $query): array { return ['ok' => true, 'q' => $query]; }
   }

   final class AnnotatedFixture
   {
       public function show(#[MapRoute] array $params, #[MapQuery] array $query): array
       {
           return ['ok' => true, 'p' => $params, 'q' => $query];
       }
   }

   final class MixedFixture
   {
       public function show(
           array $params,
           array $query,
           AccountInterface $account,
           HttpRequest $request,
       ): array {
           return ['ok' => true, 'account_id' => $account->id()];
       }
   }

   final class UnboundArrayFixture
   {
       public function show(array $somethingElse): array
       {
           return ['ok' => true, 'received' => $somethingElse];
       }
   }
   ```

2. Confirm namespace matches the existing conventions in `packages/ssr/tests/fixtures/`. If the existing fixtures use a different namespace pattern, follow that pattern instead — do not introduce a new convention.
3. Each fixture is `final`, has `declare(strict_types=1);`, and contains exactly one method.

**Files touched**:

- 5 new files under `packages/ssr/tests/fixtures/AppController/`.

**Validation**:

- `composer phpstan` clean for the fixture directory.
- `bin/check-package-layers` clean (test fixtures may use higher-layer imports — confirm by running).
- A reflection-based load of each fixture class succeeds (verified incidentally by the tests in T011/T012).

### T011 — Add unit tests for the binding builder

**Purpose**: Test the deprecation emission path of `AppParameterBindingBuilder` (or the collaborator WP01 chose) at the unit level — direct invocation, no full request lifecycle.

**Steps**:

1. Open or create `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php`.
2. Use a `RecordingLogger` test double — implement a minimal `LoggerInterface` that captures `(level, message, context)` tuples in an array. Place it under `packages/ssr/tests/Support/RecordingLogger.php` if it doesn't already exist there. *(If the Support directory already has a similar fake, reuse it.)*
3. Add at least these unit tests using `#[Test]` and `#[CoversClass(AppParameterBindingBuilder::class)]`:
   - `testClassifiesAnnotatedParamsWithoutEmittingNotice`
   - `testClassifiesImplicitArrayParamsAsRouteAndEmitsOneNotice`
   - `testClassifiesImplicitArrayQueryAsQueryAndEmitsOneNotice`
   - `testDedupSuppressesRepeatedRegistration`
   - `testNonShimParametersDoNotTouchDedupMap` *(NFR-001 fast-path; if you can't observe this without test doubles for the dedup map, document the reason and skip)*
4. Each test should:
   - Construct the builder with the recording logger.
   - Pass a fixture method via `\ReflectionMethod`.
   - Assert the resulting `AppParameterBindingSpec` has the expected `binding_kind` for each parameter.
   - Assert the recording logger received the expected number of `notice` calls with the expected context schema.
5. Use real `Reflection*` instances, not mocks. PHPUnit `createMock()` cannot mock `final class`, so prefer real fixtures.

**Files touched**:

- `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php`.
- (Possibly) `packages/ssr/tests/Support/RecordingLogger.php`.

**Validation**:

- `./vendor/bin/phpunit packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` passes (no `-v` flag).
- Test names map 1:1 to NFR/FR rows in the spec (`@covers` or docblock annotations are nice-to-have, not required).

### T012 — Add the seven contract tests

**Purpose**: Cover the seven scenarios in `contracts/dispatcher-deprecation-contract.md` §"Test contract" at the dispatcher integration level — full binding-spec construction plus argument resolution.

**Steps**:

1. Create `packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php`.
2. Use `#[CoversNothing]` per the project's contract-test convention (CLAUDE.md: "Contract tests in `packages/*/tests/Contract/` — abstract base classes verify interface compliance, concrete tests per implementation. Use `#[CoversNothing]` for contract tests.").
3. Implement the seven tests defined in the contract:
   1. `testImplicitArrayParamsResolveAndEmitNotice` — `LegacyArrayParamsFixture::show(array $params)`. Exactly one `dispatcher.deprecation` notice fires with `event=implicit_array_shim`, `parameter_name=params`, `recommended_attribute=MapRoute`.
   2. `testImplicitArrayQueryResolvesAndEmitsNotice` — `LegacyArrayQueryFixture::show(array $query)`. Same shape with `recommended_attribute=MapQuery`.
   3. `testAnnotatedAttributesEmitNoNotice` — `AnnotatedFixture::show(#[MapRoute] array $params, #[MapQuery] array $query)`. Zero notices.
   4. `testMixedSignatureResolvesAndEmitsTwoNotices` — `MixedFixture::show(array $params, array $query, AccountInterface $account, HttpRequest $request)`. Exactly two notices (one per implicit-array param). Typed services resolve correctly.
   5. `testQueryOnlyShimWorks` — `LegacyArrayQueryFixture::show(array $query)` (same fixture as #2 but emphasis on the absence of `array $params`). One notice.
   6. `testImplicitArrayUnboundEmitsBoundlessNotice` — `UnboundArrayFixture::show(array $somethingElse)`. One notice with `event=implicit_array_unbound`, `recommended_attribute=''`. Whether the parameter resolves to `[]` or fails is per WP01's final contract decision.
   7. `testDedupHoldsAcrossSecondInvocation` — call the binding pipeline twice for the same fixture method in one test process. Assert exactly one notice total.
4. Use the `RecordingLogger` from T011 (or a separate fixture if shape diverges).
5. Test setup uses real `AccountInterface` / `HttpRequest` instances or null-object fakes; do NOT use `createMock()` against `final` classes (CLAUDE.md gotcha).

**Files touched**:

- `packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php`.

**Validation**:

- `./vendor/bin/phpunit packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php` passes.
- All seven test cases run and exit green.

## Test strategy

The whole WP is the test strategy. Before requesting review:

- `./vendor/bin/phpunit packages/ssr/tests/`
- `./vendor/bin/phpunit` (full suite)
- `composer cs-check`, `composer phpstan`, `bin/check-package-layers`, `bin/check-composer-policy`.

## Definition of Done

- [ ] Five fixture controllers under `packages/ssr/tests/fixtures/AppController/`.
- [ ] Unit tests for the binding builder green.
- [ ] Seven contract tests green.
- [ ] Full PHPUnit suite green (no `-v` flag).
- [ ] All static gates green.
- [ ] No edits outside `owned_files`.
- [ ] `tasks.md` rows T010..T012 marked complete.
- [ ] WP03 PR references mission slug, tracking issue **#1391**, and upstream **#1390** per `docs/specs/workflow.md`.

## Risks

- **Fixture namespace collisions** — `Waaseyaa\SSR\Tests\Fixtures\AppController\*Fixture` may collide with existing fixtures. Grep first.
- **`createMock()` on final classes** — CLAUDE.md gotcha. Use real fixtures and a `RecordingLogger` test double.
- **`-v` flag** — PHPUnit 10.5 rejects it. Don't pass it.
- **The argument resolver requires container infrastructure** — for the contract tests, you may need a minimal kernel boot or a hand-rolled resolver harness. If kernel-boot is required, prefer the existing test pattern in `packages/ssr/tests/Contract/`. If no precedent exists, build a small harness in `packages/ssr/tests/Support/`.
- **Field/parameter naming drift between the fixtures and dispatcher** — keep parameter names exactly `params` / `query` / `somethingElse`; mismatches break the shim-classification logic.

## Reviewer guidance

- Diff scope confined to `packages/ssr/tests/**`.
- Verify each contract test covers exactly one row in `contracts/dispatcher-deprecation-contract.md` §"Test contract".
- Confirm `RecordingLogger` (or the equivalent test double) is reusable for future dispatcher tests.
- Sanity-check that no test passes `-v` to PHPUnit, no test uses `createMock()` against a `final class`, and no test introduces a new `psr/log` dependency.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <your-agent-name> --mission post-1390-dispatcher-reconciliation-01KQTTJS
```

## Activity Log

- 2026-05-05T15:46:46Z – claude:opus-4-7:implementer:implementer – shell_pid=86067 – Started implementation via action command
- 2026-05-05T15:54:12Z – claude:opus-4-7:implementer:implementer – shell_pid=86067 – Ready for review: 5 fixtures under tests/Fixtures/AppController/ (capital-F, matches autoload-dev PSR-4 mapping), RecordingLogger reused from cycle 2, AppParameterBindingBuilder unit tests gain 6 new methods (5 required + 1 unbound coverage), DispatcherDeprecationContractTest implements all 7 cases verbatim from contracts/dispatcher-deprecation-contract.md Test contract section. Validation gates green: ssr Unit 15/15, ssr Contract 7/7 (ssr full 26/26 contract tests in package), composer cs-check / phpstan / check-package-layers / check-composer-policy all pass. Two notes for reviewer: (1) the spec-kitty guard emitted owned_files warnings during commit because the WP03 YAML frontmatter inherited WP02 owned_files (src/Http/AppController/** plus SsrServiceProvider.php) rather than the WP03 spec-body owned files (tests/Contract/, tests/Unit/Http/AppController/, tests/Fixtures/, tests/Support/) — the warnings are metadata drift, not actual scope violation; the WP03 spec body and the implement prompt both authorize the test paths I touched. (2) Pre-existing WP02 reconciliation gap: tests/Integration/AppControllerImplicitArrayDispatchTest still uses the old #1390 message template and old field names (method_name, '#[MapRoute]' with brackets) — outside WP03 owned scope so left untouched; suggest a tiny WP02 follow-up reconciliation patch alongside the existing 3-test reconciliation commit ff430dda2.
- 2026-05-05T15:57:13Z – claude:opus-4-7:implementer:implementer – shell_pid=86067 – Moved to planned
- 2026-05-05T15:57:43Z – claude:opus-4-7:implementer:implementer – shell_pid=88288 – Started implementation via action command
- 2026-05-05T16:01:39Z – claude:opus-4-7:implementer:implementer – shell_pid=88288 – Cycle 2 ready: AppControllerImplicitArrayDispatchTest reconciled with WP01 contract (lane-a worktree commit 2cc659120). Cycle-1 deliverables (5 fixtures, 6 unit tests, 7 contract tests, RecordingLogger reuse) unchanged. All gates green: ssr Integration (1/1), ssr full (244/244), repo phpunit (7220/7222 — only 2 pre-existing tests/Integration/Queue/QueueIntegrationTest failures that pass in isolation and exist on baseline d4cc1726c without my edit; unrelated to dispatcher work), phpstan, cs-check, check-package-layers, check-composer-policy.
- 2026-05-05T16:02:17Z – claude:opus-4-7:reviewer:reviewer – shell_pid=89753 – Started review via action command
