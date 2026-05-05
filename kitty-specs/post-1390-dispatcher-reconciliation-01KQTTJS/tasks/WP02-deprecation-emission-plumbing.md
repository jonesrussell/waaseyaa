---
work_package_id: WP02
title: Deprecation Emission Plumbing
dependencies:
- WP01
requirement_refs:
- FR-002
- FR-010
- NFR-001
- NFR-002
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-post-1390-dispatcher-reconciliation-01KQTTJS
base_commit: 454d00f7729665d6b61d7a8e99b3bd5f90e81d9a
created_at: '2026-05-05T14:13:06.233653+00:00'
subtasks:
- T007
- T008
- T009
shell_pid: "79727"
agent: "claude:opus-4-7:implementer:implementer"
history:
- '2026-05-05: created'
authoritative_surface: packages/ssr/src/Http/AppController/
execution_mode: code_change
mission_id: 01KQTTJS73GVXHFPY5W8E8K3DX
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
owned_files:
- packages/ssr/src/Http/AppController/**
- packages/ssr/src/SsrServiceProvider.php
tags: []
---

# WP02 — Deprecation Emission Plumbing

## Objective

Wire `Waaseyaa\Foundation\Log\LoggerInterface` into the dispatcher binding pipeline so each controller-method registration that relies on the implicit-array shim emits exactly one structured deprecation event, deduplicated by `(class::method::parameter)` **per request** (the dedup map lives on the `AppParameterBindingBuilder` instance, which `AppControllerMethodInvoker` instantiates per request via `SsrPageHandler` — see contract §7 for full rationale).

## ⚠️ Hard precondition: framework#1390 must be merged on `main`

Before opening any source file, verify the upstream fix has landed:

```bash
gh issue view 1390 --repo waaseyaa/framework --json state,closedAt
git log --oneline main | grep -iE '(#?1390|implicit.array|dispatcher.*shim)' | head -1
```

If the issue is still `OPEN` or no relevant commit appears in `git log`, **stop**. Do not proceed. Update the WP's history with a "blocked: #1390 not yet merged" note and exit.

## Context

WP01 has produced `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md`. That artifact is the canonical contract for this WP. Read it first; it supersedes any draft contract content elsewhere.

You will work in `packages/ssr/src/Http/AppController/`. The dispatcher's parameter classification logic lives in `AppParameterBindingBuilder.php` (the file that previously held the rejection at line 149). Post-#1390, that rejection is gone, replaced by a shim — but the shim itself does not currently emit a structured deprecation signal. This WP adds it.

## Branch Strategy

- Planning/base branch: `main`.
- Final merge target: `main`.
- This WP runs in its lane worktree per `lanes.json`. Stay inside that worktree.

## Subtasks

### T007 — Inject `LoggerInterface` into the dispatcher binding pipeline

**Purpose**: Make the deprecation logger available where the shim classifies parameters.

**Steps**:

1. Identify the correct collaborator. The default expectation is `AppParameterBindingBuilder`; WP01's contract artifact may have proposed a sibling class (e.g., `DispatcherDeprecationCollector`). Follow the artifact.
2. Add a constructor parameter `?\Waaseyaa\Foundation\Log\LoggerInterface $logger = null`. Default to `\Waaseyaa\Foundation\Log\NullLogger` inside the constructor body if `null` was passed.
   - Pattern (per CLAUDE.md "No psr/log" gotcha):
     ```php
     public function __construct(
         // ... existing parameters ...
         ?LoggerInterface $logger = null,
     ) {
         // ... existing assignments ...
         $this->logger = $logger ?? new NullLogger();
     }
     ```
3. Add `private readonly LoggerInterface $logger;` to the property list. Use `readonly` (PHP 8.4 supports it) and never reassign after construction.
4. Make sure `declare(strict_types=1);` remains at the top of the file.

**Files touched**:

- `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` (or the collaborator chosen by WP01).

**Validation**:

- The class still has all existing tests passing locally before T008 lands: `./vendor/bin/phpunit packages/ssr/tests/Unit/Http/AppController/`.
- `composer phpstan` is clean for the file.

### T008 — Implement deprecation emission with dedup

**Purpose**: Emit exactly one `notice` per `(class::method::parameter)` registration that triggers the implicit-array shim.

**Steps**:

1. Add a `private array $emittedKeys = [];` property to the collaborator chosen in T007.
2. At the point where the shim classifies a parameter as `implicit_array_shim` (per `data-model.md` §2 `binding_kind`), call a new private method `emitDeprecation(string $controllerClass, string $method, string $parameterName, string $recommendedAttribute): void`.
3. `emitDeprecation` body:
   - Build the dedup key: `$key = $controllerClass . '::' . $method . '::' . $parameterName;`.
   - If `isset($this->emittedKeys[$key])`, return immediately.
   - Mark `$this->emittedKeys[$key] = true;`.
   - Call `$this->logger->notice($message, $context);` with the schema in WP01's contract:
     ```php
     $context = [
         'channel' => 'dispatcher.deprecation',  // confirmed in WP01 artifact
         'event' => 'implicit_array_shim',
         'controller_class' => $controllerClass,
         'method' => $method,
         'parameter_name' => $parameterName,
         'recommended_attribute' => $recommendedAttribute,
     ];
     $message = sprintf(
         'Controller %s::%s parameter $%s relies on the implicit-array shim; add #[%s] to suppress this notice.',
         $controllerClass, $method, $parameterName, $recommendedAttribute,
     );
     ```
4. For the `implicit_array_unbound` case (unannotated `array $X` where `$X` is neither `params` nor `query`), emit with `event: 'implicit_array_unbound'` and `recommended_attribute: ''` (empty string). Use the second message template from WP01's contract.
5. NFR-001: Ensure the dedup-cache lookup happens after a fast-path check. If the parameter does not have `binding_kind == implicit_array_shim` or `implicit_array_unbound`, return without touching `$emittedKeys` and without building the message string.

**Files touched**:

- Same file as T007.

**Validation**:

- Hand-trace: a controller method with `array $params` (no attribute) registered twice in one process produces one log call.
- A controller method with `#[MapRoute] array $params` produces zero log calls.
- A method with no `array` parameters produces zero log calls and zero hash-table lookups.
- `composer phpstan` clean.

### T009 — Wire DI through `SsrServiceProvider`

**Purpose**: Production environments must receive a real `LoggerInterface`, not the null fallback. Tests get the null fallback for free via the constructor default.

**Steps**:

1. Open `packages/ssr/src/SsrServiceProvider.php`.
2. Find the existing registration of `AppParameterBindingBuilder` (or the collaborator chosen in T007). It is likely registered via `$this->bind(...)` or instantiated inline inside another factory.
3. Modify the registration so the container resolves a `LoggerInterface` and passes it to the constructor:
   - If the container supports auto-wiring, this may "just work" once the constructor parameter is added. Verify by booting the kernel in a smoke test.
   - If the container does not auto-wire, explicitly resolve via `$this->resolve(\Waaseyaa\Foundation\Log\LoggerInterface::class)` and pass to the factory closure.
4. Do not change the registration shape of any other dispatcher collaborator — keep the diff minimal.
5. Add a brief comment ONLY if the wiring is non-obvious; otherwise no comment per project style.

**Files touched**:

- `packages/ssr/src/SsrServiceProvider.php`.

**Validation**:

- `./vendor/bin/phpunit packages/ssr/tests/Unit/SsrServiceProviderTest.php` passes.
- A scratch boot of the kernel (e.g., `bin/waaseyaa optimize:manifest`) does not regress.
- The deprecation log line actually reaches the configured logger when a fixture controller is registered (verified in WP03).

## Test strategy

This WP focuses on production code; tests land in WP03. However, before requesting review:

- Run `./vendor/bin/phpunit packages/ssr/tests/Unit/` (no `-v`).
- Run `./vendor/bin/phpunit packages/ssr/tests/Contract/` if any contract tests already exist for the dispatcher.
- Run `composer cs-check`, `composer phpstan`, `bin/check-package-layers`.
- All four must exit zero.

## Definition of Done

- [ ] `LoggerInterface` injected into the binding pipeline collaborator chosen by WP01.
- [ ] Deprecation emission implemented with `(class::method::parameter)` dedup.
- [ ] `SsrServiceProvider` wires a real logger in production.
- [ ] Existing PHPUnit suite for `packages/ssr/tests/Unit/` and `Contract/` is green.
- [ ] PHPStan, cs-check, check-package-layers, check-composer-policy all green.
- [ ] No edits outside `owned_files`.
- [ ] All `tasks.md` checkbox rows for T007..T009 marked complete.
- [ ] WP02 PR references mission slug, tracking issue **#1391**, and upstream **#1390** per `docs/specs/workflow.md`.

## Risks

- **The collaborator chosen by WP01 isn't `AppParameterBindingBuilder`** → follow WP01's contract artifact; if ambiguous, stop and ask.
- **Auto-wiring fails in production** → explicit container binding in `SsrServiceProvider`; verify with a kernel boot.
- **Performance regression on cold-path classification** → measure before/after by re-running an existing benchmark fixture if available; otherwise eyeball the hot path to ensure the dedup-map lookup is gated behind the binding-kind check.
- **PHP 8.4 `readonly` constructor property edge cases** → use the constructor body assignment pattern shown in T007.

## Reviewer guidance

- Diff scope is `packages/ssr/src/Http/AppController/**` plus `SsrServiceProvider.php`. Any change outside this scope is a violation of `owned_files`.
- Verify NFR-001: no per-request work for non-shim controllers.
- Verify NFR-002: dedup is **per-request** (one notice per `(class, method, parameter)` triple per request, then re-emitted on the next request that exercises the same triple). See contract §7 for rationale; WP01's cycle-1 B2a fix made this explicit.
- Verify the log schema matches WP01's contract artifact exactly. The schema is **stable across the next alpha** (FR-010); incorrect fields are blocking.

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <your-agent-name> --mission post-1390-dispatcher-reconciliation-01KQTTJS
```

## Activity Log

- 2026-05-05T14:13:07Z – claude:opus-4-7:implementer:implementer – shell_pid=79727 – Assigned agent via action command
