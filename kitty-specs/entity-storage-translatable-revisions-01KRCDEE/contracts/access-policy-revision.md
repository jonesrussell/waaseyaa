# Contract — Access policy `view_revision` signature

**Status:** Normative for WP05. Resolves spec §9 Q7.
**Refs:** FR-020..FR-024; ADR 016 FR-040 (view_revision fallback); ADR 017 §"Translation operation".

---

## 1. Scope

Defines the access-policy method signature and fallback semantics for the `view_revision` operation on two-axis (revisionable + translatable) entity types, and how `translate` composes with revisions.

## 2. Signature

Existing `AccessPolicyInterface::access()` gains an optional trailing parameter:

```php
namespace Waaseyaa\Access\Policy;

interface AccessPolicyInterface
{
    public function access(
        EntityInterface $entity,
        AccountInterface $account,
        string $operation,
        ?RevisionableEntityInterface $revision = null,   // NEW (FR-020, R-07)
    ): AccessResult;
}
```

### 2.1 `$revision` parameter semantics

| Operation | `$entity` instance | `$revision` argument |
|---|---|---|
| `view` | entity at active langcode | `null` |
| `edit` | entity at active langcode | `null` |
| `delete` | entity at active langcode | `null` |
| `translate` | entity at active langcode | `null` |
| `view_revision` | **translation instance** at active langcode | the historical revision (RevisionableEntityInterface) |

For two-axis types, the runtime passes the **translation instance** as `$entity` (so `$entity->activeLangcode()` discriminates per-language access decisions). For single-axis revisionable-only types, `$entity` is the single language-agnostic entity and `$revision` provides revision metadata.

### 2.2 Backward compatibility

- Existing M-006-era single-axis policies that do NOT take the `$revision` parameter continue to work — PHP's default-parameter handling drops the argument silently.
- Policies that DO take the parameter receive `null` for non-revision operations; `$revision !== null` is the discriminator.

## 3. Fallback rules

### 3.1 `view_revision` falls back to `view` (ADR 016 FR-040)

When a policy class does NOT explicitly declare `view_revision` (i.e., its `access()` doesn't branch on the `'view_revision'` operation string), the runtime SHOULD invoke the same policy with `operation = 'view'` and `$revision = null`.

For two-axis types: `view_revision` on the French translation → fallback to `view` on the French translation (still passing the translation instance as `$entity`).

### 3.2 `translate` falls back to `edit` (ADR 017 §"Translation operation")

When a policy does NOT explicitly declare `translate`, the runtime SHOULD invoke `access()` with `operation = 'edit'`. Same instance / parameter shape.

### 3.3 No new operation

The framework MUST NOT introduce `view_translation_revision` as a separate operation (FR-023). Composition of `view_revision` + langcode introspection inside the policy method is the canonical pattern.

## 4. Worked Minoo example (FR-024, FR-044)

```php
final class TeachingAccessPolicy implements AccessPolicyInterface
{
    public function access(
        EntityInterface $entity,
        AccountInterface $account,
        string $operation,
        ?RevisionableEntityInterface $revision = null,
    ): AccessResult {
        // English (default) revision history: any Coordinator can see.
        if ($operation === 'view_revision' && $entity->activeLangcode() === 'en') {
            return $account->hasRole('coordinator')
                ? AccessResult::allowed()
                : AccessResult::neutral();
        }

        // Anishinaabemowin revision history: Knowledge-Keeper role only.
        if ($operation === 'view_revision' && $entity->activeLangcode() === 'oj') {
            return $account->hasRole('knowledge-keeper')
                ? AccessResult::allowed()
                : AccessResult::forbidden();
        }

        // Translate operation: Knowledge-Keeper or Coordinator.
        if ($operation === 'translate') {
            return ($account->hasRole('coordinator') || $account->hasRole('knowledge-keeper'))
                ? AccessResult::allowed()
                : AccessResult::neutral();
        }

        // Optional: introspect $revision for fine-grained checks.
        if ($operation === 'view_revision' && $revision !== null) {
            // Block viewing revisions older than 90 days for non-curators.
            if ($revision->revisionCreatedAt() < new \DateTimeImmutable('-90 days')) {
                return $account->hasRole('curator')
                    ? AccessResult::allowed()
                    : AccessResult::neutral();
            }
        }

        return AccessResult::neutral();
    }
}
```

## 5. Listing pipeline integration

When the listing pipeline (M-007 `ListingResolver`) applies the row-by-row access filter (per ADR 015 FR-032), it invokes the entity's access policy for each result row:

- For two-axis listings filtered by `Filter::langcode('oj')`, each result entity is loaded with `activeLangcode = 'oj'`. The policy method receives the oj-translation instance.
- The listing pipeline's `view` operation invocation does NOT pass `$revision` (it's `null`). Only explicit `view_revision` invocations (typically from revision-history admin UI) do.

## 6. Test contract (WP05)

`TwoAxisAccessPolicyIntegrationTest` (integration):

1. Coordinator fixture sees English revision history of `teaching` 42: `view_revision` → Allowed for vid in English lineage.
2. Coordinator fixture does NOT see Anishinaabemowin revision history: `view_revision` → Forbidden for vid in oj lineage.
3. Knowledge-Keeper fixture sees BOTH English and Anishinaabemowin revision histories.
4. Policy method receives the translation instance as `$entity`; `$entity->activeLangcode()` returns the correct langcode per call.
5. Policy method receives the historical revision as `$revision`; `$revision->revisionId()` returns the requested vid.
6. Fallback: a policy that does NOT declare `view_revision` is consulted for `view` instead; behavior matches single-axis revisionable-only fallback (ADR 016 FR-040).
7. Fallback: a policy that does NOT declare `translate` is consulted for `edit` instead (ADR 017).

This satisfies FR-044 (the Minoo per-language access policy validation gate).

## 7. Stable surface

`AccessPolicyInterface::access()` signature extension (optional `?RevisionableEntityInterface $revision = null` parameter) lands on charter §5.3 stable-surface map at mission close (WP08). Adding a 4th parameter to a stable interface is permissible per charter §4 because it's a default-valued optional — no breaking change to existing implementers.
