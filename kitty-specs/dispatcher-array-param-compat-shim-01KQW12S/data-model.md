# Data Model: Dispatcher Array-Param Compatibility Shim

**Mission**: `dispatcher-array-param-compat-shim-01KQW12S`
**Phase**: 0 (research)

This mission is a behavior change inside one class. There are no new domain entities, no schema migrations, and no persistent state. The "data model" here is the in-memory shape produced by the dispatcher and the structured log payload it emits.

---

## 1. Existing entities (informational)

| Entity                                       | Where                                                                                                  | Role in this mission                                                                                  |
|----------------------------------------------|--------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------|
| `AppParameterBindingSpec`                    | `packages/ssr/src/Http/AppController/AppParameterBindingSpec.php`                                      | Immutable per-parameter binding output. Returned by `AppParameterBindingBuilder::build()`.           |
| `AppParameterKind` (enum)                    | `packages/ssr/src/Http/AppController/AppParameterKind.php`                                             | Enumerates binding kinds: `FrameworkService`, `RouteEntity`, `RouteScalar`, `RouteEnum`, `MapRoute`, `MapQuery`, `Custom`. |
| `MapRoute` (attribute)                       | `packages/ssr/src/Attribute/MapRoute.php`                                                              | `final readonly class` marker attribute, target `Attribute::TARGET_PARAMETER`. No constructor params. |
| `MapQuery` (attribute)                       | `packages/ssr/src/Attribute/MapQuery.php`                                                              | Same shape as `MapRoute`.                                                                            |
| `AppControllerMethodInvoker`                 | `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`                                   | Owns the static `$specCache` keyed by `class::method\0routeName\0fingerprint`. Sole caller of the builder. |
| `Waaseyaa\Foundation\Log\LoggerInterface`    | `packages/foundation/src/Log/LoggerInterface.php`                                                      | Project logger contract. Used for the deprecation signal.                                            |

## 2. Shim binding shape

When the shim fires, the builder returns the same `AppParameterBindingSpec` shape the explicit-attribute branch already produces:

```
$param->getName() === 'params'  → AppParameterBindingSpec(index, kind = AppParameterKind::MapRoute)
$param->getName() === 'query'   → AppParameterBindingSpec(index, kind = AppParameterKind::MapQuery)
```

No new fields on `AppParameterBindingSpec`. No new enum cases on `AppParameterKind`. The shim is a name-keyed alternative entry into the existing branch logic at `AppParameterBindingBuilder.php:112–126`.

## 3. Deprecation log payload

| Key                       | Type                            | Example                                                  |
|---------------------------|---------------------------------|----------------------------------------------------------|
| `controller_class`        | `string` (FQCN)                 | `App\Controller\StaticPageController`                    |
| `method_name`             | `string`                        | `show`                                                   |
| `parameter_name`          | `'params' \| 'query'`           | `params`                                                 |
| `recommended_attribute`   | `'#[MapRoute]' \| '#[MapQuery]'` | `#[MapRoute]`                                            |

Emitted via `LoggerInterface::notice($message, $context)`:

- **Message** (constant string): `Controller method uses implicit array parameter — add #[MapRoute] or #[MapQuery]`
- **Level**: `notice` (deprecation, not error).
- **Context**: the four keys above.

Format is documented inline in PHPDoc on the private emission method per FR-009. Consumer tooling parses the structured context, not the message string.

## 4. Builder construction shape (changed)

Before:

```php
final class AppParameterBindingBuilder
{
    public function build(...): array { ... }
}
```

After (R-001):

```php
final class AppParameterBindingBuilder
{
    private readonly LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function build(...): array { ... }
}
```

`AppControllerMethodInvoker` constructor mirrors the same optional-logger pattern and passes it into the builder. `SsrPageHandler` threads its existing `$this->logger` into the invoker.

## 5. State / dedup

No new persistent state. Dedup of the deprecation signal is achieved by `AppControllerMethodInvoker::$specCache` (existing static array). The cache key `class::method\0routeName\0fingerprint` ensures `build()` runs at most once per `(controller, method, route)` per request lifetime — and at most once per registration under long-lived workers (R-007).

## 6. Out of model

- No database tables.
- No new HTTP routes.
- No new public API surface beyond the optional ctor param on two classes.
- No GraphQL schema impact.
- No JSON Schema impact.
