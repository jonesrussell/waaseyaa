# Contract: Dispatcher Deprecation Emission

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Status**: Draft (WP01 finalises). Schema is **stable across the next alpha** once accepted (FR-010).

## Trigger

The framework controller dispatcher emits exactly one deprecation event per controller-method registration when **either** of these holds:

1. The method has an unannotated `array $params` parameter (no `#[MapRoute]`, `#[MapQuery]`, or other binding attribute).
2. The method has an unannotated `array $query` parameter (no binding attribute).

Both can hold simultaneously — that produces *two* events (one per parameter), each with its own deduplication key.

A method with an unannotated `array $X` parameter where `$X` is **not** `params` or `query` (the `implicit_array_unbound` case) also emits an event for visibility, but the dispatcher does **not** auto-bind the parameter. The recommended attribute is empty in that case.

## Channel

- Logger: `Waaseyaa\Foundation\Log\LoggerInterface` (constructor-injected, defaults to `NullLogger`).
- Logical channel name: `dispatcher.deprecation` *(WP01 confirms; final value committed in this contract before WP02 starts)*.
- Log level: `notice`.

## Schema

The deprecation event MUST carry the following structured context fields. The string message is informational; tooling consumes the structured fields.

| Field                   | Type   | Required | Notes                                                                                                  |
|-------------------------|--------|----------|--------------------------------------------------------------------------------------------------------|
| `channel`               | string | yes      | `"dispatcher.deprecation"`                                                                              |
| `event`                 | string | yes      | `"implicit_array_shim"` for `params`/`query` cases; `"implicit_array_unbound"` for the unbound case.    |
| `controller_class`      | string | yes      | FQCN of the controller class.                                                                          |
| `method`                | string | yes      | Method name (without parens).                                                                          |
| `parameter_name`        | string | yes      | Variable name of the parameter (without `$`).                                                          |
| `recommended_attribute` | string | yes      | `"MapRoute"` for `params`, `"MapQuery"` for `query`, `""` (empty) for the `implicit_array_unbound` case. |
| `framework_version`     | string | optional | Best-effort version string from package metadata, useful for consumer log triage.                      |

### String message template

```
Controller {controller_class}::{method} parameter ${parameter_name} relies on the implicit-array shim; add #[{recommended_attribute}] to suppress this notice.
```

For `implicit_array_unbound`:

```
Controller {controller_class}::{method} parameter ${parameter_name} declares array without a binding attribute; the dispatcher cannot auto-resolve it. Add an explicit binding or remove the parameter.
```

## Deduplication invariant

- Dedup key: `(controller_class, method, parameter_name)`.
- Each registration of a controller method emits at most **one** event per dedup key, per process lifetime (NFR-002).
- The dedup state lives on the dispatcher / collaborator instance; it does not need to be persisted across processes.

## Performance invariant (NFR-001)

- For controller methods that contain no `array` parameters (or only annotated ones), the dispatcher MUST NOT add any per-request overhead. The deprecation check fires at registration time (or first invocation), not on every request thereafter.

## Test contract

The contract test in `packages/ssr/tests/Contract/` MUST verify:

1. **`testImplicitArrayParamsResolveAndEmitNotice`** — Fixture controller `LegacyArrayParamsFixture::show(array $params)` resolves successfully through the dispatcher; exactly one event fires with `event=implicit_array_shim`, `parameter_name=params`, `recommended_attribute=MapRoute`.
2. **`testImplicitArrayQueryResolvesAndEmitsNotice`** — Same shape for `array $query` with `recommended_attribute=MapQuery`.
3. **`testAnnotatedAttributesEmitNoNotice`** — Fixture controller `AnnotatedFixture::show(#[MapRoute] array $params, #[MapQuery] array $query)` resolves successfully and emits **zero** events.
4. **`testMixedSignatureResolves`** — Fixture controller `MixedFixture::show(array $params, array $query, AccountInterface $account, HttpRequest $request)` resolves successfully; exactly two events fire (one per implicit-array parameter).
5. **`testQueryOnlyShimWorks`** — `OnlyQueryFixture::show(array $query)` resolves successfully; exactly one event fires (for `query`).
6. **`testImplicitArrayUnboundEmitsBoundlessNotice`** — `UnboundArrayFixture::show(array $somethingElse)` emits one event with `event=implicit_array_unbound` and empty `recommended_attribute`. Whether the dispatcher then errors or no-ops on the parameter is a separate decision left to WP01's contract definition.
7. **`testDedupHoldsAcrossSecondInvocation`** — Invoking the same fixture method twice within one process produces only one event (dedup invariant).

## Open items for WP01

- Final channel name (`dispatcher.deprecation` is the proposed value; confirmed or changed in WP01).
- Whether `framework_version` is included in v1 of the schema or deferred (currently optional).
- Resolution semantics for `implicit_array_unbound`: error vs. silent no-op vs. inject empty array. Current expectation: silent inject of `[]` plus the deprecation notice, but WP01 decides based on the post-#1390 dispatcher source.
