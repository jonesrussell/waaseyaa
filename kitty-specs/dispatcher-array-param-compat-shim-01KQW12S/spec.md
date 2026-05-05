# Specification: Dispatcher Array-Param Compatibility Shim

**Mission**: `dispatcher-array-param-compat-shim-01KQW12S`
**Mission ID**: `01KQW12S3R4TY0563R4ZSQ2QBC`
**Mission Type**: software-dev
**Created**: 2026-05-05
**Target branch**: `main`
**Tracking issue**: [waaseyaa/framework#1390](https://github.com/waaseyaa/framework/issues/1390) (Track 3 — Parity & performance)
**Companion mission (downstream)**: [`post-1390-dispatcher-reconciliation-01KQTTJS`](../post-1390-dispatcher-reconciliation-01KQTTJS/) — gated on this mission landing.

---

## 1. Overview

Alpha.171/172 added a stricter controller-dispatcher invariant in `Waaseyaa\SSR\Http\AppController\AppParameterBindingBuilder` that rejects `array` parameters on controller method signatures unless they carry an explicit `#[MapRoute]` or `#[MapQuery]` attribute. The rejection site:

```
packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php:147-152
  if ($typeName === 'array') {
      throw new InvalidAppControllerBindingException(sprintf(
          'Parameter $%s: array parameters require #[MapRoute] or #[MapQuery].',
          $name,
      ));
  }
```

This is a hard contract break with no compatibility shim or deprecation period. Until alpha.170, the canonical controller signature used by Waaseyaa consumers was:

```php
public function show(array $params, array $query, AccountInterface $account, HttpRequest $request)
```

Bumping any consumer to alpha.171/172 surfaces a runtime 500 on every public route that dispatches such a controller. The blast radius in Minoo alone is **184 affected methods across 37 controller files**; other consumers that adopted the same convention have proportional exposure. Minoo's `upgrade-waaseyaa-alpha-171-01KQTDC2` mission is re-frozen pending this fix.

This mission restores the historical implicit behavior via a **name-keyed compatibility shim** in the dispatcher: an unannotated `array $params` parameter is treated as if it carried `#[MapRoute]`; an unannotated `array $query` parameter is treated as if it carried `#[MapQuery]`. Each implicit binding emits a single structured deprecation log line per registration, identifying the controller, method, parameter name, and the attribute the author should add. Any other unannotated `array` parameter (e.g. `array $headers`, `array $config`) continues to raise `InvalidAppControllerBindingException` so genuinely-broken signatures are not silently accepted.

The shim is **name-keyed**, not type-keyed, because the historical behavior was tied to the conventional parameter names `$params` and `$query`, not to "any array." Treating arbitrary array parameters as route-bound would expand the contract beyond what alpha.170 ever did.

This mission **does not** sweep every alpha.171–172 dispatcher invariant; that wider sweep is filed as a separate follow-up issue per the user's scope choice. This mission only restores the implicit-array signature.

## 2. Goals & Success Criteria

### Goals

1. Restore the alpha.170 implicit-array controller signature so existing consumers can adopt alpha.171/172's other improvements without a 500-on-every-route runtime block.
2. Emit a deprecation signal that lets consumer maintainers inventory their migration debt without grepping source.
3. Preserve the strict invariant for any other unannotated `array` parameter (anything not literally named `$params` or `$query`) so genuine misuse is still caught at registration.
4. Establish a regression / contract test surface that prevents this break from recurring silently.
5. Land the fix in the next alpha and unblock both Minoo's frozen upgrade mission and the `post-1390-dispatcher-reconciliation` mission's WP02–WP04.

### Success Criteria

- **SC-001**: A controller method with the historical implicit signature `public function show(array $params, array $query, AccountInterface $account, HttpRequest $request)` registered against a route with path variables responds with HTTP 200 (or whatever the controller returns) — no 500, no `InvalidAppControllerBindingException`. Verified by an integration test in `packages/ssr/tests/`.
- **SC-002**: The same registration emits exactly one structured deprecation log line via `Waaseyaa\Foundation\Log\LoggerInterface` per request, identifying the controller class, method name, parameter name (`params` or `query`), and the recommended attribute (`#[MapRoute]` or `#[MapQuery]`). Verified by a unit test that injects a recording logger.
- **SC-003**: A controller method using explicit `#[MapRoute] array $params` and `#[MapQuery] array $query` produces zero deprecation log lines and behaves identically to current alpha.172. Verified by a parallel unit test asserting zero recorded log entries.
- **SC-004**: A controller method with `array $headers` (or any other unannotated array name that is not `$params` or `$query`) still raises `InvalidAppControllerBindingException` at registration. Verified by a unit test that asserts the exception is thrown.
- **SC-005**: A controller method with only `array $query` (no `$params`) — or only `array $params` (no `$query`) — gets the shim applied per-parameter. Verified by two unit tests.
- **SC-006**: CHANGELOG `[Unreleased]` carries a release-notes bullet describing the shim, referencing `#1390`, and naming the deprecated shape so it surfaces in `release-cut.yml`'s next promotion.

## 3. User Scenarios

### Scenario A — Stuck consumer upgrades

A Minoo maintainer (or any consumer with the implicit-array signature) bumps `waaseyaa/framework` to the alpha that contains this fix. Their public routes return 200 again. Their logs now contain one deprecation line per affected controller method per request, listing exactly which method needs `#[MapRoute]` / `#[MapQuery]` added. They migrate at their own pace.

### Scenario B — Already-migrated consumer

A consumer whose controllers already use `#[MapRoute]` / `#[MapQuery]` (or whose methods do not take `array $params`/`array $query`) bumps to the new alpha and observes no behavior change and no deprecation log lines. The upgrade is a no-op for them.

### Scenario C — Genuine misuse

A consumer adds a controller method `public function process(array $config, ...)` expecting the framework to inject something. The dispatcher raises `InvalidAppControllerBindingException` at registration with a clear message. The shim does not silently coerce `$config` into a route or query binding.

### Scenario D — Framework maintainer adds a stricter check

A framework maintainer attempts to remove the name-keyed shim or extend the strict invariant to also reject `array $params` / `array $query`. The contract test suite fails with a named regression. The maintainer either revises the change or extends the deprecation policy explicitly.

### Edge cases

- `array $params` declared after typed parameters (e.g. `function show(HttpRequest $request, array $params)`) — shim must apply regardless of position.
- `array $params = []` with a default value — shim must apply; the default is irrelevant once binding takes over.
- `?array $params = null` (nullable) — shim must apply; the dispatcher already treats nulls via existing nullable handling for MapRoute. WP01 verifies this exactly.
- `array $params` declared on a controller method whose route has no path variables — same behavior as `#[MapRoute] array $params` on such a route, whatever that is. WP01 documents.
- `array $query` declared on a controller method whose route accepts no query parameters — should produce an empty array binding, parallel to `#[MapQuery]`.

## 4. Functional Requirements

| ID      | Requirement                                                                                                                                                                                | Status   |
|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|
| FR-001  | `AppParameterBindingBuilder::buildForParameter()` SHALL treat an unannotated `array $params` parameter as if it carried `#[MapRoute]`, returning `AppParameterBindingSpec` with `kind = MapRoute`.    | Required |
| FR-002  | `AppParameterBindingBuilder::buildForParameter()` SHALL treat an unannotated `array $query` parameter as if it carried `#[MapQuery]`, returning `AppParameterBindingSpec` with `kind = MapQuery`.     | Required |
| FR-003  | `AppParameterBindingBuilder::buildForParameter()` SHALL continue to raise `InvalidAppControllerBindingException` for any other unannotated `array` parameter (any name other than literally `params` or `query`). | Required |
| FR-004  | When the shim is invoked (FR-001 or FR-002), the builder SHALL emit exactly one structured deprecation log line per `(class::method, parameter)` triple per request via `Waaseyaa\Foundation\Log\LoggerInterface`. The line MUST include `controller_class`, `method_name`, `parameter_name`, and `recommended_attribute` keys. | Required |
| FR-005  | When the shim is invoked but no `LoggerInterface` is wired into the builder, the deprecation signal MUST NOT crash the request. The shim emission falls through silently (best-effort logging per project convention). | Required |
| FR-006  | The framework SHALL provide unit tests covering: (a) shim applies to `$params`, (b) shim applies to `$query`, (c) explicit attributes still work without deprecation, (d) other array names still throw, (e) per-request dedup works. | Required |
| FR-007  | The framework SHALL provide an integration test in `packages/ssr/tests/` that boots a minimal kernel with a controller using the implicit-array signature and asserts a successful HTTP response. | Required |
| FR-008  | CHANGELOG `[Unreleased]` SHALL carry a release-notes bullet under an appropriate section (likely `Fixed` or `Changed`) referencing `#1390`, naming the implicit-array compatibility behavior, and pointing consumers at `#[MapRoute]` / `#[MapQuery]` for the migration. | Required |
| FR-009  | The deprecation log line format SHALL be documented inline in `AppParameterBindingBuilder` (PHPDoc on the emission method) so consumer tooling can parse it. | Required |
| FR-010  | The dispatcher MUST NOT add per-request work for controllers that do not rely on the shim. The name check (`'params'` / `'query'`) is bounded to the existing array-param branch and does not run on other parameter kinds. | Required |

## 5. Non-Functional Requirements

| ID       | Requirement                                                                                                                                  | Threshold                                                                  | Status   |
|----------|----------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------|----------|
| NFR-001  | The shim MUST NOT degrade request latency for controllers that do not declare `array $params` / `array $query`.                              | Zero added work per request when the array-param branch is not entered.   | Required |
| NFR-002  | The deprecation log signal MUST be deduplicated per request, keyed by `(controller_class::method_name, parameter_name)`.                     | At most one log line per triple per dispatched request.                   | Required |
| NFR-003  | All new tests MUST run within the existing PHPUnit configuration (no new database engines, no external services).                            | Use `DBALDatabase::createSqlite()` or in-memory fixtures.                 | Required |
| NFR-004  | New code MUST satisfy project style: `declare(strict_types=1)`, typed signatures, named constructor parameters where ambiguous, `final` for concrete classes. | PHPStan level 5 passes; PHP-CS-Fixer dry-run clean.                       | Required |
| NFR-005  | The shim MUST keep `AppParameterBindingBuilder` `final` and self-contained. New helper logic lives on the builder or a private class within `Waaseyaa\SSR\Http\AppController`. | No new public surface in `Waaseyaa\SSR\Http\AppController`. | Required |

## 6. Constraints

| ID    | Constraint                                                                                                                                                     |
|-------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| C-001 | This mission MUST NOT modify any consumer outside `waaseyaa/framework` (no Minoo, no other apps).                                                              |
| C-002 | This mission MUST NOT touch `vendor/`.                                                                                                                          |
| C-003 | The shim is **name-keyed** (`$params`, `$query`) only. Generalizing to arbitrary array names is explicitly out of scope; doing so would expand the contract beyond alpha.170. |
| C-004 | This mission MUST NOT include the wider "sweep all alpha.171–172 dispatcher invariants" suggestion from issue #1390. That sweep is filed as a separate issue post-merge. |
| C-005 | Layer discipline: changes MUST stay in the package that owns `AppParameterBindingBuilder` (currently `packages/ssr/`, despite the SSR namespace housing the AppController dispatcher). No upward layer imports introduced. |
| C-006 | All framework changes MUST land on `main` via Spec-Kitty PRs that reference this mission and `#1390` per project workflow rules (`docs/specs/workflow.md`).      |
| C-007 | Composer policy: any manifest changes MUST satisfy `bin/check-composer-policy`.                                                                                 |
| C-008 | The deprecation signal MUST use `Waaseyaa\Foundation\Log\LoggerInterface` (the project's logger contract). `error_log()` / `trigger_error()` are forbidden per project convention. |

## 7. Assumptions

- `AppParameterBindingBuilder` is the single rejection site for array-param signatures. WP01 (or WP-only equivalent) confirms by grep that no other dispatcher path raises a similar exception that would also need the shim.
- The builder is invoked once per controller-method registration per request lifetime (the `build()` call site iterates `$method->getParameters()`). WP-equivalent confirms whether there is a higher-level cache that would let "per request" become "per process"; if so, FR-004 dedup applies at that cache level.
- A `LoggerInterface` is available in the dispatcher's construction context. If not, the builder accepts an optional `?LoggerInterface $logger = null` constructor parameter (consistent with the project pattern of optional logger injection) and degrades to silent emission per FR-005.
- `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery` already exist and are wired through to `AppParameterKind::MapRoute` and `AppParameterKind::MapQuery`. No new attributes are introduced.
- The contract test base class (or fixture pattern) for builder tests already exists in `packages/ssr/tests/`. WP-equivalent confirms or scaffolds.
- No new entity types, schema migrations, or storage changes are required.

## 8. Dependencies & Out-of-Scope

### Hard dependencies

- **None.** This mission can run immediately on `main` (alpha.172) without waiting on any other issue.

### Adjacent / informational

- **framework#1391** — the GitHub tracking issue for the *post-1390 reconciliation* mission. Not this mission's tracker. Will be unblocked by this mission's merge.
- **framework#1388** — companion regression closed in alpha.172. Referenced for CHANGELOG cross-link only.
- **Minoo `upgrade-waaseyaa-alpha-171-01KQTDC2`** — the consumer-side mission that this work unblocks. Out of scope to modify.
- **Wider alpha.171–172 dispatcher invariant sweep** — filed as a separate issue post-merge per user's scope decision (option A). Not this mission.

### Out of scope

- The wider dispatcher invariant sweep (separate issue).
- Generalizing the shim to arbitrary array parameter names.
- Modifying `JsonResponseTrait`, EntityType `_fieldDefinitions`, or ServiceProvider `setKernelServices` — those are separate Minoo migration items.
- New attribute kinds beyond `#[MapRoute]` / `#[MapQuery]`.
- A CLI surface to inventory implicit-array usage. (Such a surface is in the post-1390 mission's optional FR-009; not relevant here.)

## 9. Key Entities (informational)

- **`AppParameterBindingBuilder`** — the dispatcher class that builds `AppParameterBindingSpec` lists from a `\ReflectionMethod`. Lives at `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php`. Final class, namespace `Waaseyaa\SSR\Http\AppController` (note casing: `SSR`, not `Ssr`).
- **`AppParameterBindingSpec`** — immutable binding output. Carries an `index`, a `kind` (enum `AppParameterKind`), and kind-specific fields. The shim returns a spec with `kind = AppParameterKind::MapRoute` or `kind = AppParameterKind::MapQuery`.
- **`AppParameterKind`** — enum naming binding kinds. Includes `MapRoute`, `MapQuery`, `RouteScalar`, `RouteEnum`, `RouteEntity`, `FrameworkService`, `Custom`.
- **`MapRoute` / `MapQuery`** — attribute classes in `Waaseyaa\SSR\Attribute\`. The shim does not use the attribute classes themselves; it short-circuits to the same `AppParameterBindingSpec` shape they produce.
- **Deprecation log line** — structured `LoggerInterface` emission keyed by `(controller_class::method_name, parameter_name)`, deduplicated per request, used by consumer tooling to inventory migration debt. Format documented in PHPDoc on the emission method (per FR-009).

## 10. Risks

| Risk                                                                                                              | Mitigation                                                                                                              |
|-------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| The shim is name-keyed and a consumer has a method like `array $param` (singular) or `array $queryString` — false negative.   | The deprecation log line and CHANGELOG entry name the exact accepted names (`$params`, `$query`) so the consumer sees a clear binding error and can rename or annotate.    |
| Deprecation signal is too noisy and floods consumer logs.                                                          | NFR-002 enforces per-request dedup; FR-004 specifies "exactly one per triple per request"; tests assert.                |
| `LoggerInterface` is not available at builder construction in some dispatch paths.                                | FR-005 makes logger injection optional; emission degrades silently. WP-equivalent verifies all construction call sites. |
| A higher-level cache means "per request" actually means "first request only" — implicit shim usage flies under the radar after warm-up. | WP-equivalent identifies the cache layer and either flushes the dedup memo per request or documents the cache-tier behavior in FR-004's PHPDoc. |
| Layer discipline drift if the fix touches `packages/api/` as well as `packages/ssr/`.                              | C-005 enforces single-package change; `bin/check-package-layers` runs in CI on the merge.                              |
| The strict array-name shim accidentally rejects a legitimate `array $params` defined as a constructor-injected dependency on a controller. | Constructor injection is not in scope for `AppParameterBindingBuilder`; it operates on `\ReflectionMethod::getParameters()` of the action. Action parameters are dispatch-bound by definition. WP-equivalent confirms by reading the call site. |

## 11. Acceptance Gates

- All FRs and NFRs above are addressed (or explicitly deferred with rationale).
- All SCs are demonstrably met by tests or runnable assertions.
- `bin/check-composer-policy`, `bin/check-package-layers`, `composer phpstan`, and the full PHPUnit suite pass on the merged mission branch.
- CHANGELOG `[Unreleased]` carries the bullet (per FR-008) and is ready for `release-cut.yml` to promote at tag time (per `feedback_changelog_release_workflow.md`).
- GitHub issue `#1390` is closed via merge commit / PR link (the issue is the mission tracker per `feedback_pr_traceability_signals.md` — it must be closed manually after merge and the release notes edited).

## 12. Open Questions

None at spec time. WP-equivalent's analysis output may surface clarifications (e.g., whether the builder is wrapped by a per-request or per-process cache), which are recorded in the WP deliverable rather than back-amended into this spec.
