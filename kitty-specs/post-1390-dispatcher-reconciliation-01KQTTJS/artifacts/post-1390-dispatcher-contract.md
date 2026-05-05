# Post-#1390 Dispatcher Contract

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Status**: 🟡 **Draft pending #1390 merge** — written 2026-05-05 against `main` at alpha.172, where #1390 is **OPEN**. Each section flags assumptions that need re-confirmation when the upstream PR lands.
**Authoritative for**: WP02 (deprecation emission plumbing), WP03 (test coverage), WP04 (docs/CHANGELOG).

This artifact supersedes the draft contract at `../contracts/dispatcher-deprecation-contract.md` for any subsequent decision. Where the two diverge, this document wins.

---

## 1. Subsystem location (confirmed)

The dispatcher rejection lives in the SSR package, not in `api`/`routing` as the orchestration table in `CLAUDE.md` might suggest.

| Concern                                  | Location                                                                            |
|------------------------------------------|--------------------------------------------------------------------------------------|
| Parameter classification + rejection     | `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` (line 148–152) |
| Parameter binding spec                   | `packages/ssr/src/Http/AppController/AppParameterBindingSpec.php`                   |
| Parameter kind enum                      | `packages/ssr/src/Http/AppController/AppParameterKind.php`                          |
| Custom resolver hook                     | `packages/ssr/src/Http/AppController/AppControllerArgumentResolver.php`             |
| Method invoker                           | `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`                |
| Invocation context                       | `packages/ssr/src/Http/AppController/AppInvocationContext.php`                      |
| Attribute markers                        | `packages/ssr/src/Attribute/MapRoute.php`, `packages/ssr/src/Attribute/MapQuery.php` (also `FromRoute.php`) |
| Sole call site (host)                    | `packages/ssr/src/SsrPageHandler.php`                                                |

Namespace: `Waaseyaa\SSR\` (uppercase `SSR`, not `Ssr`). Layer: 6 (Interfaces). Logger source: `Waaseyaa\Foundation\Log\LoggerInterface` (Layer 0; downward import is allowed).

**Implication for the audit**: only controllers wired through `SsrPageHandler` are subject to this dispatcher. JSON:API controllers, auth controllers, MCP controllers, and the various router-style classes use independent pipelines and **do not** participate in this contract. (Verified by `rg AppControllerMethodInvoker packages/`, which returns only the SSR package itself.)

## 2. Assumptions awaiting #1390

| # | Assumption                                                                                                                                                        | Confirm-after-merge action                                                                              |
|---|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| A1 | The shim defaults unannotated `array $params` → `#[MapRoute]` semantics and unannotated `array $query` → `#[MapQuery]` semantics, matching the issue body.        | Read the merged PR; if the rule set diverges, rewrite §4 of this document.                              |
| A2 | The shim retains the `assertArrayParameter` invariant for explicit `#[MapRoute]` / `#[MapQuery]` (only `array` parameters can carry these attributes).             | Re-grep the post-merge `AppParameterBindingBuilder.php` for the assertion.                              |
| A3 | The deprecation signal is **not** auto-emitted by #1390 itself; emission is this mission's responsibility (WP02). If #1390 already emits, this mission collapses to docs/tests/CHANGELOG only. | Read the merged PR; if a deprecation signal is already in place, WP02 reduces to wiring tests against it. |
| A4 | `MapRoute` and `MapQuery` remain marker attributes with no constructor parameters.                                                                                  | Read the merged attribute classes; if a `legacy_implicit: bool` flag was added, prefer it over a separate emission path. |
| A5 | The dispatcher's call site continues to be `SsrPageHandler` exclusively.                                                                                            | `rg AppControllerMethodInvoker packages/` after merge; stays at one host file.                          |
| A6 | The current `AppParameterBindingBuilder` remains constructor-less (stateless). WP02's first concrete change is to add a constructor.                                | Verify post-merge.                                                                                      |

When #1390 merges, WP01 may be re-opened with a single subtask "Reconcile A1..A6 against landed PR" if any assumption was invalidated.

## 3. Trigger conditions (post-shim)

The dispatcher emits **exactly one** deprecation event per `(controller_class, method_name, parameter_name)` registration when **any** of the following holds:

1. Method has an unannotated `array $params` parameter (no `#[MapRoute]`, `#[MapQuery]`, or `#[FromRoute]`). Event kind: `implicit_array_shim`. `recommended_attribute = "MapRoute"`.
2. Method has an unannotated `array $query` parameter. Event kind: `implicit_array_shim`. `recommended_attribute = "MapQuery"`.
3. Method has an unannotated `array $X` parameter where `$X` is **neither** `params` nor `query`. Event kind: `implicit_array_unbound`. `recommended_attribute = ""` (empty). The dispatcher injects `[]` for this parameter (chosen here over hard-error to preserve existing semantics; see §6 edge cases).

A method with two unannotated implicit-array params (e.g., `array $params, array $query`) emits **two** events — one per parameter — each with its own dedup key.

A method with `#[MapRoute] array $params` (or `#[MapQuery] array $query`) emits **zero** events. The shim has no work to do; the existing path returns the same `AppParameterBindingSpec` it always has.

A method with no `array` parameters emits **zero** events and **zero** hash-table lookups (NFR-001 fast-path: the dedup map is consulted only when classification produces `implicit_array_shim` or `implicit_array_unbound`).

## 4. Attribute equivalence rules

| `parameter_name` | declared `parameter_type` | mapped attribute  | applies when                                                                              |
|------------------|---------------------------|-------------------|-------------------------------------------------------------------------------------------|
| `params`         | `array`                   | `MapRoute`        | no `#[MapRoute]`, `#[MapQuery]`, or `#[FromRoute]` attribute on the parameter             |
| `query`          | `array`                   | `MapQuery`        | no `#[MapRoute]`, `#[MapQuery]`, or `#[FromRoute]` attribute on the parameter             |
| any other        | `array`                   | (none — `unbound`) | no binding attribute on the parameter; injected as `[]`                                  |

Rules are evaluated in parameter declaration order. The match is on parameter name (case-sensitive) and declared type only.

## 5. Log emission contract (locked)

| Field                   | Type   | Required | Value                                                                                                  |
|-------------------------|--------|----------|--------------------------------------------------------------------------------------------------------|
| `level`                 | string | yes      | `"notice"`                                                                                              |
| `channel`               | string | yes      | `"dispatcher.deprecation"` (locked)                                                                     |
| `event`                 | string | yes      | `"implicit_array_shim"` for `params`/`query`; `"implicit_array_unbound"` otherwise                      |
| `controller_class`      | string | yes      | FQCN of the controller class (e.g., `Waaseyaa\Genealogy\Ssr\GenealogySsrController`)                   |
| `method`                | string | yes      | Method name (no parens)                                                                                 |
| `parameter_name`        | string | yes      | Variable name (no `$`)                                                                                  |
| `recommended_attribute` | string | yes      | `"MapRoute"` for `params`; `"MapQuery"` for `query`; `""` for `implicit_array_unbound`                  |
| `framework_version`     | string | optional | Best-effort version string from package metadata. **Deferred to a follow-up alpha** — not in v1 schema. |

### String message templates

For `implicit_array_shim`:

```
Controller {controller_class}::{method} parameter ${parameter_name} relies on the implicit-array shim; add #[{recommended_attribute}] to suppress this notice.
```

For `implicit_array_unbound`:

```
Controller {controller_class}::{method} parameter ${parameter_name} declares array without a binding attribute; the dispatcher injects []. Add an explicit binding or remove the parameter.
```

### Schema stability (FR-010)

The seven required fields above constitute v1 of the schema. They MUST be stable across the next alpha and any subsequent alpha that does not also bump the major schema version. Optional fields (`framework_version`) may be added later; required fields MUST NOT change without a coordinated breaking-change announcement.

## 6. Edge cases (decided)

| Case                                                                                       | Decision                                                                                       |
|---------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|
| Method with `array $params, array $query, AccountInterface $account, HttpRequest $request` | Two events fire (one per implicit-array param). Typed services resolve unchanged.              |
| Method with only `array $query` (no `array $params`)                                       | One event for `query`. The shim is parameter-local, not paired.                                 |
| Method with `array $somethingElse` (unbound name)                                          | One event with `event: implicit_array_unbound`, `recommended_attribute: ""`. Dispatcher injects `[]`. Hard-error rejected as too disruptive for a deprecation cycle. |
| Method with `array $params` declared **nullable** (`?array $params`)                        | Treated as `array $params` for shim purposes (the `?` is resolved by `primaryNamedType()`'s null-stripping, already in the binding builder). Event fires.        |
| Method with union type `array\|string $params`                                              | The existing `primaryNamedType()` rejects unions with >1 non-null member as `InvalidAppControllerBindingException`. The shim does not engage; behavior unchanged.  |
| Method with `array $params` carrying `#[FromRoute('id')]` only (no `#[MapRoute]`)          | `FromRoute` does not satisfy the shim's "binding attribute present" check — the shim still engages because `FromRoute` is a route-key remapper, not a binding-kind attribute. Event fires; `recommended_attribute: MapRoute`. |
| Method with `#[MapRoute] array $params` AND `#[MapQuery] array $params` on the same param  | Existing behavior (presumably one wins or the second is rejected); shim does not engage; no event. |

## 7. Dedup invariant (NFR-002)

- Dedup key: `sprintf('%s::%s::%s', $controllerClass, $method, $parameterName)`.
- Storage: `private array $emittedKeys = []` on the binding-builder instance.
- Scope: per-process. The cache lives for the lifetime of the binding-builder object. In production this is bounded by the `AppControllerMethodInvoker`'s lifetime, which today is per-request (the invoker is instantiated by `SsrPageHandler`); this is acceptable because each request boots a fresh dedup table and the noise budget per request is bounded by the number of distinct params on the matched route's controller method.

> **Open consideration**: if a future build moves the invoker to a long-lived singleton, the dedup table will accumulate. That is a feature (one notice per registration per process), not a bug — but it should be revisited in a follow-up if memory pressure becomes a concern.

## 8. Performance invariant (NFR-001)

The fast-path check is the binding-kind classification: only when classification produces `implicit_array_shim` or `implicit_array_unbound` does the deprecation collaborator touch the dedup map. For controllers whose every parameter is annotated or non-array, the deprecation path is zero work after the existing classification.

## 9. Wiring guidance for WP02

**Today**:

```php
// packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php:21
private readonly AppParameterBindingBuilder $bindingBuilder = new AppParameterBindingBuilder(),
```

**After WP02**:

`AppParameterBindingBuilder` gains a constructor `__construct(?LoggerInterface $logger = null)` defaulting to `NullLogger`. `AppControllerMethodInvoker` gains a matching `?LoggerInterface $logger = null` constructor parameter, and the inline default becomes `new AppParameterBindingBuilder($this->logger)`. Note: as the builder currently has no constructor, its first edit is to add one.

`SsrPageHandler` (the only caller of the invoker) is the production wiring point — it must pass the framework's real `LoggerInterface` through. If `SsrServiceProvider` already wires `SsrPageHandler` from the container, the change should be invisible there beyond the new constructor argument; otherwise, an explicit container binding is required.

**Test wiring**: the constructor's null-safe default means existing tests do not need to be updated; new tests in WP03 can pass a `RecordingLogger` test double directly.

## 10. Reconciliation against landed #1390

> **Status: not yet merged.** This subsection is a placeholder. When #1390 lands, run:
>
> ```bash
> gh pr view <pr-num> --repo waaseyaa/framework
> rg -n 'array parameters require' packages/ssr/src/  # should now miss
> rg -n 'logger->\|LoggerInterface' packages/ssr/src/Http/AppController/  # check if the PR added emission
> ```
>
> Update this subsection with: PR number, merge commit, what assumptions A1..A6 the PR confirmed, and any deltas to §3..§9.

## 11. Adjacent invariants surfaced

Per spec C-005, anything outside the controller-dispatcher subsystem that WP01 found while doing this analysis is filed as its own GitHub issue, not absorbed into this mission.

**Audit found**: no adjacent invariants in the dispatcher subsystem itself. The Layer 6 SSR package is internally consistent with the rest of the SSR pipeline; the `assertArrayParameter` check, `primaryNamedType` resolver, and entity/scalar/enum branches are coherent.

**Out-of-scope items noted in #1390 but not filed by this mission** (Minoo's existing migration backlog — they are upstream of any framework decision and remain Minoo's problem):

- `JsonResponseTrait` shim
- EntityType `_fieldDefinitions` migration
- `ServiceProvider::setKernelServices` migration
- phpstan baseline drift

These do not require new framework-level deprecation paths; they are documented and patched in Minoo's frozen mission. The CHANGELOG bullet (T015) explicitly references #1390 only — companion items live in their own filings if/when they need framework changes.

**One observation worth flagging for CLAUDE.md (T014 in WP04)**: the orchestration table maps `packages/api/*` and `packages/routing/*` to the `waaseyaa:api-layer` skill, but the *actual* dispatcher implementation lives at `packages/ssr/src/Http/AppController/`. This is documented inside §1 of this contract; T014 in WP04 will optionally clarify it in CLAUDE.md.

## 12. Cross-references

- Mission spec: [`../spec.md`](../spec.md) (FR-001, FR-002, FR-010, NFR-001, NFR-002 anchor here)
- Plan: [`../plan.md`](../plan.md)
- Data model: [`../data-model.md`](../data-model.md)
- Draft contract (superseded for any conflict): [`../contracts/dispatcher-deprecation-contract.md`](../contracts/dispatcher-deprecation-contract.md)
- WP01 prompt: [`../tasks/WP01-analysis-and-artifacts.md`](../tasks/WP01-analysis-and-artifacts.md)
- WP02 prompt: [`../tasks/WP02-deprecation-emission-plumbing.md`](../tasks/WP02-deprecation-emission-plumbing.md)
- WP03 prompt: [`../tasks/WP03-test-coverage.md`](../tasks/WP03-test-coverage.md)
- WP04 prompt: [`../tasks/WP04-docs-changelog-and-gates.md`](../tasks/WP04-docs-changelog-and-gates.md)
- Source: [`framework#1390`](https://github.com/waaseyaa/framework/issues/1390), [`framework#1388`](https://github.com/waaseyaa/framework/issues/1388)

## 13. Open items

None at WP01 close. Assumptions A1..A6 in §2 are the only residual uncertainty; they are explicitly flagged for re-confirmation when #1390 merges, and are not blockers for the mission's overall structure.
