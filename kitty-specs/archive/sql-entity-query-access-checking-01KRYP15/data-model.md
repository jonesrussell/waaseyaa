# Phase 1 Data Model

No new entities, tables, columns, indexes, or migrations.

This mission introduces **runtime state** on an existing class and one
new exception type. Documenting those shapes here for WP-01 / WP-02 to
implement against without re-deriving.

---

## SqlEntityQuery — new internal state

Existing class: `packages/entity-storage/src/SqlEntityQuery.php`.

**New properties** added to the class:

| Property | Type | Default | Notes |
|---|---|---|---|
| `?AccountInterface $account` | nullable | `null` | Bound via `setAccount()`. When `accessCheck=true` and `$account === null`, `execute()` throws `MissingQueryAccountException`. |
| `bool $accessCheckEnabled` | bool | `true` | Replaces the no-op `accessCheck()` flag. Toggled via `accessCheck(bool $check = true)`. |
| `?EntityAccessHandler $accessHandler` | nullable | injected lazily via DI container at first use | Sourced from the kernel container; lazy to avoid changing the constructor signature. |

**New methods** on `SqlEntityQuery`:

| Method | Signature | Notes |
|---|---|---|
| `setAccount(?AccountInterface $account): static` | — | Binds (or unbinds) the account. Chainable. |

**Modified methods:**

| Method | Change |
|---|---|
| `accessCheck(bool $check = true): static` | Now stores `$this->accessCheckEnabled = $check;` instead of no-op. |
| `execute(): array` | After hydrating the candidate page, runs per-row `EntityAccessHandler::check($entity, 'view', $account)`. Drops rows whose result `isForbidden()`. Returns surviving IDs. If `accessCheckEnabled && $account === null`, throws `MissingQueryAccountException`. |
| `count(): static` | Unchanged signature. When `accessCheckEnabled` is true, `count()` triggers the same hydrate+filter pass internally and returns the post-filter cardinality. When `accessCheckEnabled` is false, returns the pre-filter cardinality (existing behaviour). |

**Cursor / pagination behaviour:**

The page cursor advances by the **unfiltered candidate window** so successive page requests don't re-scan candidates already evaluated. Example: a 25-row page request returns 18 surviving rows; the next-page cursor is positioned at `offset + 25` (not `offset + 18`). This matches FR-007.

---

## MissingQueryAccountException — new exception

**Path:** `packages/entity-storage/src/Exception/MissingQueryAccountException.php`

**Shape:**

```
final class MissingQueryAccountException extends \RuntimeException
{
    public static function forQuery(EntityTypeInterface $entityType): self
    {
        return new self(sprintf(
            'Cannot execute SqlEntityQuery for entity type "%s": access checking is enabled but no account is bound. '
            . 'Call setAccount() before execute(), or call accessCheck(false) for system contexts.',
            $entityType->id(),
        ));
    }
}
```

- Class-level `@api` PHPDoc.
- Inherits `\RuntimeException` for compat with existing exception-handling middleware.
- Named factory is the only constructor route — discourages ad-hoc raises with bespoke messages.

---

## EntityAccessHandler consumption

Existing class: `packages/access/src/EntityAccessHandler.php`.

This mission consumes the existing surface:

```
public function check(
    EntityInterface $entity,
    string $operation,
    AccountInterface $account,
    ?array $context = null,
): AccessResult
```

- Operation passed by `SqlEntityQuery::execute()`: always `'view'`.
- `$context` left `null`; not used by this mission.
- Result evaluated via `AccessResult::isForbidden()`. Allowed and neutral both admit the row to the result set (open-by-default at entity level, matches `docs/specs/access-control.md` § "Access result semantics").

**Future optimization (out of scope):** `EntityAccessHandler::checkMultiple(EntityInterface[], string, AccountInterface): array<int, AccessResult>` — keyed by entity index. Decision deferred to a follow-up if profiling identifies a hot policy.

---

## EntityQueryInterface contract addition

Existing interface: `packages/entity/src/Storage/EntityQueryInterface.php`.

**New method** added to the interface:

```
public function setAccount(?AccountInterface $account): static;
```

The interface already defines `accessCheck(bool $check = true): static`. This mission supplies the real implementation in `SqlEntityQuery` and documents the contract (see `contracts/entity-query-interface-additions.md`).

All current implementations of `EntityQueryInterface` (today there is exactly one: `SqlEntityQuery`) MUST be updated.

---

## Configuration

No new config entries.

The existing `config.ai.*` namespace is unaffected. The mission does not introduce a global "entity access enforcement on/off" flag — that would defeat the purpose. The per-query `accessCheck(false)` opt-out remains the only documented bypass.
