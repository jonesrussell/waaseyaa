# Research: Access Fail-Closed Completeness (M-B)

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Date**: 2026-05-20
**Scope**: Resolves all NEEDS CLARIFICATION items from `plan.md`.

---

## R-001: Circular dependency — EntityAccessHandler forward-reference (Assumption A2)

### Decision
Use a **two-phase discover protocol** in `AccessPolicyRegistry`. Phase 1 instantiates all policies that do NOT depend on `EntityAccessHandler`. Phase 2 builds the `EntityAccessHandler` from Phase 1 results, then instantiates the remaining policies (those that declared `EntityAccessHandler` as a constructor dependency) using the now-built handler.

This avoids a lazy proxy (which would require a separate proxy class or Symfony's lazy-service mechanism — both Symfony-specific). It also avoids circular-detection complexity.

**Protocol**:
1. First pass: for each policy class, resolve constructor args from `PolicyDependencyResolverInterface`. If an arg type is `EntityAccessHandler` (detected by type name), defer that class to a second-pass list.
2. Build a preliminary `EntityAccessHandler` from first-pass policies.
3. Second pass: resolve deferred classes using the preliminary handler as the `EntityAccessHandler` arg. Policies that *still* fail (e.g. depend on a second unknown service) throw `PolicyInstantiationException`.
4. Build the final `EntityAccessHandler` from all (first-pass + second-pass) policies.

**Rationale**: `ParentDelegatedAccessPolicy` is the only known case that injects `EntityAccessHandler`. The two-pass approach handles it cleanly with no runtime proxy magic. The spec's Assumption ("DI container is available at the point where `discover()` runs") is correct — the container has all services *except* `EntityAccessHandler` itself, which is being assembled.

**Alternatives considered**:
- Lazy proxy (Symfony `LazyGhostTrait`) — imports Symfony types into L0, violates NFR-005.
- Post-construction setter injection — requires `setAccessHandler()` on `ParentDelegatedAccessPolicy`, changes its interface.
- Single-pass with topological sort — over-engineered for a single known case.

---

## R-002: PolicyDependencyResolverInterface signature

### Decision
```php
namespace Waaseyaa\Foundation\Kernel\Bootstrap;

/**
 * Resolves constructor argument values for access policies during kernel boot.
 *
 * Implementations MUST:
 * - Return the resolved service instance for a known abstract.
 * - Throw PolicyInstantiationException (not return null) when resolution fails.
 * - Support scalar parameters with defaults by returning the default (callers
 *   pass the ReflectionParameter to the resolver for default inspection).
 * - Support nullable parameters with defaults (e.g. ?LoggerInterface $logger = null)
 *   by returning null when the service is unbound and the parameter is nullable.
 *
 * @api — M-D adopts this interface for its own container-resolved registry pattern.
 */
interface PolicyDependencyResolverInterface
{
    /**
     * Resolve a single constructor parameter for a policy class.
     *
     * @param class-string $policyClass The policy being instantiated (for error messages).
     * @param \ReflectionParameter $param The constructor parameter to resolve.
     * @return mixed The resolved value (service instance, array, scalar, or null for nullable).
     * @throws PolicyInstantiationException When the parameter cannot be resolved.
     */
    public function resolveParameter(string $policyClass, \ReflectionParameter $param): mixed;
}
```

**Rationale**:
- No Symfony types in signature (NFR-005 satisfied).
- PSR-11-compatible in semantics: throws on unresolvable (like PSR-11 `ContainerInterface::get()`), never returns null for a required binding.
- Passes the full `ReflectionParameter` so the implementation can inspect type, nullability, default values, and parameter name without requiring the caller to pre-process.
- Passes `$policyClass` to enable precise error messages naming the policy and the failing parameter.
- `@api` tag marks it as the M-D reuse point.

**M-D compatibility**: M-D adopts the resolver protocol for its own registry. Because the interface is in L0 (`packages/foundation/`), M-D (also L0 or L1) can depend on it without layer violations.

---

## R-003: bin/check-getquery-bindings scanner approach

### Decision
**Regex-based multi-line scanner** (no external AST dependency).

**Algorithm**:
1. Collect all `.php` files under `packages/*/src/`.
2. For each file, tokenize with PHP's built-in `token_get_all()`.
3. Scan for the pattern: `getQuery()` call followed (within a bounded window of chained calls) by `->execute()` but *without* `->setAccount(` or `->accessCheck(false)` in the same chain.
4. A "chain" is defined as a sequence of `->method(...)` calls on the same expression starting from `getQuery()` and ending at `->execute()`. Calls are bounded by `;` or a line-ending assignment.
5. Output format: `<relative-path>:<line>  <excerpt>` — one line per finding, sorted by path then line.

**Baseline format** (`tools/getquery-bindings-baseline.txt`):
```
# Baseline: getQuery() bindings exempt from CI gate
# Format: <path>:<line>  # <exemption-reason>
# Generated: php bin/check-getquery-bindings --generate-baseline
# To add a new exemption, append a line with the path:line and a mandatory comment.
packages/path/src/PathAliasResolver.php:47  # system-context: no per-user alias access control by design
```

**Why `token_get_all()` not nikic/php-parser**: No new composer dependency needed; the script must be self-contained for CI performance (NFR-001). `token_get_all()` gives token-level precision for identifying method-call chains. The pattern is specific enough that false positives in complex multi-line chains are acceptable (they surface as false positives in the gate, not false negatives — conservative is correct).

**Rationale**: Regex alone would fail on multi-line chains. `token_get_all()` with a sliding window over a chain buffer handles the common case. If a future chain is too complex for the scanner, the script should err on the side of flagging it (false positive), not missing it.

**Alternatives considered**:
- `nikic/php-parser` AST — more accurate but adds a dev dependency and slower startup.
- Simple `grep` — misses multi-line chains and chains broken across assignments.

---

## R-004: RecordingEntityQuery method-chain stub completeness

### Decision
Implement all 7 methods of `EntityQueryInterface`. All chainable methods (`condition`, `exists`, `notExists`, `sort`, `range`, `count`, `accessCheck`, `setAccount`) return `$this`. `execute()` returns `[]` by default (configurable via `withResults(array $ids)` fluent setter). `accessCheck()` records each call to `$this->accessChecks[]`.

```php
/** @var list<bool> */
public array $accessChecks = [];

/** @var list<int|string> */
private array $stubbedResults = [];

public function withResults(array $ids): static { $this->stubbedResults = $ids; return $this; }
public function accessCheck(bool $check = true): static { $this->accessChecks[] = $check; return $this; }
public function execute(): array { return $this->stubbedResults; }
// All other methods: return $this
```

**Rationale**: The four retro regression tests only need to assert that `setAccount()` was called (or `accessCheck(false)`). `RecordingEntityQuery` also records `setAccount()` calls via a `public ?AccountInterface $boundAccount = null` property. This gives tests two surfaces: `$query->boundAccount !== null` and `$query->accessChecks`.

---

## R-005: Integration test phase assignment

### Decision
New integration tests land in **`tests/Integration/Phase24/`**. Phase 23 contains only `MetapackageSmokeTest.php`. Phase 24 is the correct next slot for M-B integration coverage.

**Files**:
- `tests/Integration/Phase24/SemanticSearchAccessTest.php`
- `tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php`
- `tests/Integration/Phase24/GetQueryBindingsGateTest.php`

---

## R-006: AuthController location (Assumption A3)

### Decision
**To be confirmed in WP05** by running:
```bash
grep -rn "findUserByName" packages/ --include="*.php" -l
```
The regression test file path in `plan.md` assumes `packages/user/` — implementer must verify and adjust if needed.
