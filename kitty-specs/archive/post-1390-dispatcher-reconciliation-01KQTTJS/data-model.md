# Phase 1 Data Model: Post-#1390 Dispatcher Reconciliation

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`

This mission has no persistent storage. The "data model" is the conceptual entities that participate in the dispatcher contract and its deprecation signal. They are documented here so the contract test suite and the audit artifact can refer to a stable vocabulary.

## Entities

### 1. `ControllerMethodSignature`

Represents a single controller method's parameter signature as observed by the dispatcher at registration time.

| Field           | Type                          | Description                                                                                              |
|-----------------|-------------------------------|----------------------------------------------------------------------------------------------------------|
| `class`         | string                        | Fully-qualified class name of the controller.                                                            |
| `method`        | string                        | Method name (e.g., `show`, `index`).                                                                     |
| `parameters`    | list<`ParameterDescriptor`>   | Ordered list of method parameters.                                                                       |

### 2. `ParameterDescriptor`

A single parameter's binding intent.

| Field                   | Type      | Description                                                                                                                |
|-------------------------|-----------|----------------------------------------------------------------------------------------------------------------------------|
| `name`                  | string    | Parameter variable name (e.g., `params`, `query`, `account`, `request`).                                                   |
| `type`                  | string    | Declared type (`array`, `AccountInterface`, `HttpRequest`, etc.).                                                          |
| `attributes`            | list<string> | Names of attribute classes attached to the parameter (e.g., `MapRoute`, `MapQuery`).                                  |
| `binding_kind`          | enum      | `route_params` \| `query_params` \| `typed_service` \| `implicit_array_shim` \| `implicit_array_unbound`.                  |
| `requires_deprecation`  | bool      | `true` iff `binding_kind == implicit_array_shim`.                                                                          |

`binding_kind` semantics:

- **`route_params`**: parameter has `#[MapRoute]` attribute (or, post-#1390, is an unannotated `array $params` matching the implicit-array shim).
- **`query_params`**: parameter has `#[MapQuery]` attribute (or, post-#1390, is an unannotated `array $query` matching the implicit-array shim).
- **`typed_service`**: parameter is a typed object the resolver injects from the container (e.g., `AccountInterface`, `HttpRequest`).
- **`implicit_array_shim`**: unannotated `array $params` or `array $query` — resolved by the post-#1390 shim with a deprecation signal emitted.
- **`implicit_array_unbound`**: unannotated `array $X` where `$X` is neither `params` nor `query` — *not* shimmed; deprecation signal still emitted to flag the author.

### 3. `DispatcherDeprecationEvent`

A single emission. Schema is stable across the next alpha (FR-010) so consumer tooling can parse it.

| Field                   | Type      | Description                                                                                                          |
|-------------------------|-----------|----------------------------------------------------------------------------------------------------------------------|
| `level`                 | enum      | `notice` (deprecation, not warning).                                                                                 |
| `channel`               | string    | `dispatcher.deprecation` (or final value chosen by WP01).                                                             |
| `controller_class`      | string    | FQCN of the controller.                                                                                              |
| `method`                | string    | Method name.                                                                                                         |
| `parameter_name`        | string    | Variable name of the offending parameter.                                                                             |
| `recommended_attribute` | string    | `MapRoute` or `MapQuery` (or empty for the `implicit_array_unbound` case where no recommendation applies).            |
| `message`               | string    | Human-readable line (e.g., `"Controller App\\Controller\\X::show parameter $params relies on implicit-array shim; add #[MapRoute] to suppress this notice."`). |

Dedup key: `(controller_class, method, parameter_name)`. NFR-002 requires at most one emission per key per process.

### 4. `AttributeEquivalenceRule`

One row per shimmable parameter shape.

| Field                            | Type   | Description                                                |
|----------------------------------|--------|------------------------------------------------------------|
| `unannotated_parameter_name`     | string | The variable name the rule matches (e.g., `params`, `query`). |
| `unannotated_parameter_type`     | string | The declared type that triggers the rule (`array`).        |
| `mapped_attribute`               | string | The attribute class the shim treats it as (`MapRoute`, `MapQuery`). |
| `applies_when`                   | string | Conditions that must hold (e.g., "no other `MapXxx` attribute present"). |

Initial rule set:

| `unannotated_parameter_name` | `unannotated_parameter_type` | `mapped_attribute` | `applies_when`                              |
|------------------------------|------------------------------|--------------------|---------------------------------------------|
| `params`                     | `array`                      | `MapRoute`         | no other binding attribute on the parameter |
| `query`                      | `array`                      | `MapQuery`         | no other binding attribute on the parameter |

WP01 verifies these are the only rules required and that #1390's landed shape matches.

### 5. `ControllerShapeAuditRow`

A single row in WP01's audit artifact (`artifacts/controller-shape-audit.md`).

| Field             | Type                                                            | Description                                                                |
|-------------------|-----------------------------------------------------------------|----------------------------------------------------------------------------|
| `controller_class`| string                                                          | FQCN of a framework-shipped controller.                                    |
| `method`          | string                                                          | Method name.                                                               |
| `category`        | enum: `attribute_annotated` \| `relies_on_shim` \| `no_array_params` | Classification per the rules above.                                  |
| `notes`           | string                                                          | Optional commentary (edge cases, future migration intent).                 |

## State transitions

None. All entities are descriptive (computed at registration or analysis time, not mutated over their lifetime).

## Relationship summary

```
ControllerMethodSignature
  └── ParameterDescriptor (1..n)
        └── triggers DispatcherDeprecationEvent? (0..1, when binding_kind == implicit_array_shim)

AttributeEquivalenceRule (set; consulted by AppParameterBindingBuilder when classifying ParameterDescriptor)

ControllerShapeAuditRow (one per (class, method) pair in the framework's own controllers; produced by WP01)
```
