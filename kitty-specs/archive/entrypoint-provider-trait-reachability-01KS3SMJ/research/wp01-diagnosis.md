# WP01 Diagnosis: Trait-Member Reachability Failure Mechanism

**Date:** 2026-05-20
**Analyst:** claude:sonnet:implementer
**Closes:** part of #1501

---

## Section 1: Full Entry Inventory

Baseline counts as of this investigation (higher than the 31 originally estimated — trait members were added since the mission was filed):

| Trait | Count | Kind |
|---|---:|---|
| `Waaseyaa\Entity\RevisionableEntityTrait` | 17 | 4 `Property … is never read` + 13 `Unused` method |
| `Waaseyaa\Testing\Traits\InteractsWithApi` | 9 | 1 property + 8 method |
| `Waaseyaa\Testing\Traits\RefreshDatabase` | 5 | 1 property + 4 method |
| **Total** | **31 unique members** | (34+18+10 raw lines includes path repeats) |

### RevisionableEntityTrait members (17)
Properties: `$isCurrentRevision`, `$newRevision`, `$revisionId`, `$revisionMetadata`
Methods: `getRevisionId`, `getRevisionLog`, `isCurrentRevision`, `isDefaultRevision`, `isLatestRevision`, `isNewRevision`, `revisionId`, `revisionMetadata`, `setIsCurrentRevision`, `setNewRevision`, `setRevisionId`, `setRevisionLog`, `setRevisionMetadata`

### InteractsWithApi members (9)
Property: `$requestHeaders`
Methods: `buildRequest`, `delete`, `patch`, `post`, `put`, `withHeader`, `withHeaders`, `withToken`

### RefreshDatabase members (5)
Property: `$databaseTransactionActive`
Methods: `beginDatabaseTransaction`, `migrate`, `rollBackDatabaseTransaction`, `truncateTables`

---

## Section 2: Probe Output

The probe confirmed **zero invocations** of `shouldMarkMethodAsUsed` and `shouldMarkPropertyAsRead` for any of the three traits, across two full runs with cleared cache (`rm -rf tmp/phpstan-dead-code`). Both the method and property probe `error_log` statements were inserted but never fired.

**Conclusion:** shipmonk's `ReflectionBasedMemberUsageProvider.getUsages()` is **never called** for trait files. The provider is not being invoked for these members at all.

---

## Section 3: hasApiPhpDoc Verification

PHP reflection confirms all three traits have correct `@api` and `isTrait`:

```
Waaseyaa\Entity\RevisionableEntityTrait:     isTrait=true  hasApi=true
Waaseyaa\Testing\Traits\InteractsWithApi:    isTrait=true  hasApi=true
Waaseyaa\Testing\Traits\RefreshDatabase:     isTrait=true  hasApi=true
```

`@api` is at:
- `packages/entity/src/RevisionableEntityTrait.php` line 40 (class-level docblock)
- `packages/testing/src/Traits/InteractsWithApi.php` line 14 (inside class docblock, before `trait`)
- `packages/testing/src/Traits/RefreshDatabase.php` line 15 (inside class docblock, before `trait`)

`hasApiPhpDoc()` would return `true` for all three. The docblock check is not the failure.

---

## Section 4: Scanner Coverage for RevisionableEntityTrait

The `loadEntitySupportingTraits` scanner at `tools/phpstan/WaaseyaaEntrypointProvider.php:220–274` globs:
- `packages/*/src/*.php` — top-level src files
- `packages/*/src/Entity/*.php` — Entity subdirectory

Running the scanner logic against the full monorepo found **27 entity classes** (all with `extends EntityBase|ContentEntityBase`). The trait set built from those 27 classes does **NOT** include `Waaseyaa\Entity\RevisionableEntityTrait`.

Root cause for scanner miss: **No entity class in the monorepo currently uses `RevisionableEntityTrait` directly.** A `grep -rl "use RevisionableEntityTrait"` in `packages/` (excluding vendor, tests, trait/interface definitions) returns nothing. The trait is defined but not `use`d by any scanned entity. The only reference is in `packages/ai-agent/vendor/waaseyaa/entity/` (a vendored copy, not scanned). This confirms **Hypothesis (a)** for `RevisionableEntityTrait`.

---

## Section 5: Confirmed Hypothesis

**Hypothesis (d) — Mixed, two distinct root causes:**

### For `RevisionableEntityTrait` — Both (a) and (c)

**(a) Scanner miss:** `loadEntitySupportingTraits` never populates `entitySupportingTraits` with `Waaseyaa\Entity\RevisionableEntityTrait` because no entity class in the monorepo currently `use`s it. The `isEntrypointClass` check at line 112 (`isset($this->entitySupportingTraits[$fqcn])`) therefore fails for this trait.

**(c) InClassNode never fires for traits:** Even if the scanner were fixed, `isEntrypointClass` would still never be called because PHPStan's `NodeScopeResolver` short-circuits trait statements before class processing:

```php
// vendor/phpstan/phpstan/phpstan.phar (NodeScopeResolver.php)
} elseif ($stmt instanceof Node\Stmt\Trait_) {
    return new InternalStatementResult($scope, false, false, [], [], []);  // early return!
} elseif ($stmt instanceof Node\Stmt\ClassLike) {
    // ... new InClassNode(...) only emitted here, never reached for Trait_
```

`Node\Stmt\Trait_` extends `ClassLike` but is matched first. PHPStan returns an empty result for trait AST nodes without ever emitting `InClassNode`. Since `ReflectionBasedMemberUsageProvider.getUsages()` only fires on `InClassNode`, our `shouldMarkMethodAsUsed`/`shouldMarkPropertyAsRead` are never called for trait files.

### For `InteractsWithApi` and `RefreshDatabase` — (c) only

These testing traits have `@api` in their class docblocks. `hasApiPhpDoc()` would return `true`. But `isEntrypointClass` is never called because `InClassNode` is never emitted for their files. Same `NodeScopeResolver` early return.

**The scanner-based path (`entitySupportingTraits`) is irrelevant** for the testing traits — they are not entity-related — the `@api` path is the intended fix. But that path is also unreachable via `InClassNode`.

**Why does shipmonk's own `ApiPhpDocUsageProvider` also fail?** Exact same reason: it too extends `ReflectionBasedMemberUsageProvider` and fires via `InClassNode`. Traits are invisible to the entire `ReflectionBasedMemberUsageProvider` family.

---

## Section 6: Precise Code Lines Requiring Change

In `tools/phpstan/WaaseyaaEntrypointProvider.php`:

| Lines | Description | Change needed |
|---|---|---|
| 220–274 | `loadEntitySupportingTraits` — globs `*/src/*.php` + `*/src/Entity/*.php` | **Optional minor fix:** widen globs to catch future entity classes in subdirectories. Does NOT fix the fundamental problem. |
| 71–81 | `shouldMarkMethodAsUsed` | Cannot be the fix — never called for traits |
| 90–95 | `shouldMarkPropertyAsRead` | Cannot be the fix — never called for traits |
| 97–100 | `shouldMarkPropertyAsWritten` | Cannot be the fix — never called for traits |
| 102–138 | `isEntrypointClass` + `hasApiPhpDoc` | Cannot be the fix — never called for traits |
| **NEW** | Need a new `MemberUsageProvider` that fires on a **non-`InClassNode`** trigger for traits | The fix requires a new provider implementation (see Section 7) |

The fix cannot be in the existing `ReflectionBasedMemberUsageProvider` subclass. It requires implementing `MemberUsageProvider` directly and listening to `Node\Stmt\Trait_` nodes — which PHPStan DOES pass to `ProvidedUsagesCollector.processNode()` (collector gets ALL nodes, `Node::class`), but shipmonk's reflection-based abstraction filters them out by requiring `InClassNode`.

Evidence: `ProvidedUsagesCollector` uses `getNodeType(): string { return Node::class; }` — it receives ALL nodes. The `getUsages` dispatch in `ReflectionBasedMemberUsageProvider` only handles `InClassNode`. A new provider that handles `Node\Stmt\Trait_` directly would work.

---

## Section 7: WP02 Design Instruction

**Add a new method `loadApiTraitMembers(string $projectRoot): array` and wire it through a new `MemberUsageProvider` implementation** (or extend `WaaseyaaEntrypointProvider` with a new `getUsages()` branch). Specifically:

In `WaaseyaaEntrypointProvider`, override `getUsages(Node $node, Scope $scope): array` (implementing `MemberUsageProvider` directly instead of relying solely on `ReflectionBasedMemberUsageProvider`). Add a branch:

```php
if ($node instanceof \PhpParser\Node\Stmt\Trait_) {
    $traitName = $node->namespacedName?->toString();
    if ($traitName !== null && class_exists($traitName)) {
        $reflection = new \ReflectionClass($traitName);
        if (self::hasApiPhpDoc($reflection)) {
            // emit VirtualUsageData for all methods + properties declared on this trait
            return $this->buildTraitMemberUsages($reflection);
        }
    }
}
```

The `buildTraitMemberUsages` method must iterate `$reflection->getMethods()` and `$reflection->getProperties()` (filtering to `getDeclaringClass()->getName() === $traitName`) and call `createMethodUsage`/`createPropertyUsage`. These factory methods are `private` in `ReflectionBasedMemberUsageProvider` — WP02 must either copy them or change visibility to `protected` (shipmonk is a dev-only dep so this is fine).

**Call-site:** Override `getUsages(Node $node, Scope $scope): array` in `WaaseyaaEntrypointProvider` (new method, calling `parent::getUsages()` first then appending trait usages).

**Scanner widening (secondary fix for RevisionableEntityTrait):** Also add `packages/*/src/**/*.php` glob in `loadEntitySupportingTraits` to catch entity classes in subdirectories. This future-proofs the scanner but does not fix the baseline entries since the root cause for all 31+ entries is the `InClassNode` gap.

**Same fix path for entity and testing traits:** Yes. Both failure modes converge on the same fix — emitting virtual usages from `Trait_` nodes. The `entitySupportingTraits` lookup path and the `@api` lookup path both fail identically; the new `Trait_` branch replaces both for trait contexts.

**Signatures:**
```php
// Add to WaaseyaaEntrypointProvider — override parent's getUsages
public function getUsages(Node $node, Scope $scope): array
// New helper
private function buildTraitMemberUsages(\ReflectionClass $traitReflection): array
```

**Call sites that need no change:** `shouldMarkMethodAsUsed`, `shouldMarkPropertyAsRead`, `shouldMarkPropertyAsWritten`, `isEntrypointClass`, `hasApiPhpDoc` — all remain as-is for class/interface handling.

---

## Sanity Check: Additional Findings

1. **Baseline counts are 62 raw lines (34+18+10), not 31.** The mission brief said "31 entries." Each baseline entry appears twice in the neon file (message + path), so 62 lines = 31 unique members. The count is correct.

2. **`ApiPhpDocUsageProvider` (shipmonk built-in, `enabled: true` by default) also fails for the same reason** — it too extends `ReflectionBasedMemberUsageProvider`. Adding `@api` to trait docblocks is not sufficient to fix this; a `Trait_`-node-aware provider is required.

3. **The fix will clear all 31 baseline entries** (17 + 9 + 5 = 31 unique members) once WP02 implements the `Trait_`-node branch with `@api` detection. No additional baseline regeneration for other classes is expected since non-trait classes are covered by the existing `InClassNode` path.

4. **Future entity classes using `RevisionableEntityTrait`** will also be covered by the `Trait_`/`@api` fix, making the scanner-widening a lower-priority secondary concern.
