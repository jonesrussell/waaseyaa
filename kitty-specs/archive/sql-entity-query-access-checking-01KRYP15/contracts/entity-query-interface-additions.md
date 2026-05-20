# Contract: EntityQueryInterface additions

This is an internal PHP interface contract — no OpenAPI / HTTP schema applies to this mission. Documenting the contract shape so all downstream implementers (today: one, `SqlEntityQuery`; tomorrow: possibly NoSQL / graph backends) agree on the semantics.

---

## Current contract (unchanged surface)

`packages/entity/src/Storage/EntityQueryInterface.php`

```php
interface EntityQueryInterface
{
    public function condition(string $field, mixed $value, string $operator = '='): static;
    public function exists(string $field): static;
    public function notExists(string $field): static;
    public function sort(string $field, string $direction = 'ASC'): static;
    public function range(int $offset, int $limit): static;
    public function count(): static;
    public function accessCheck(bool $check = true): static;
    public function execute(): array;
}
```

---

## Mission additions

**One new method** added to the interface:

```php
public function setAccount(?AccountInterface $account): static;
```

- Imports `Waaseyaa\Access\AccountInterface`. Same-layer import (Layer 1 → Layer 1), permitted.
- Nullable parameter: passing `null` clears any previously-bound account.
- Returns `static` for chaining.

---

## Semantics every implementation MUST honour

### `accessCheck(bool $check = true): static`

- When `$check === true` (default state of every new query): the implementation MUST run an entity-level access check against the bound account before returning rows. Forbidden rows are dropped. Allowed and neutral rows are kept.
- When `$check === false`: the implementation MUST skip the access check entirely and return all candidate rows. This is the explicit, audited system-context bypass.
- The check toggle MUST be settable at any point before `execute()`. Last write wins.

### `setAccount(?AccountInterface $account): static`

- The implementation stores the account internally and uses it for subsequent access-check calls inside `execute()`.
- Setting `null` clears the bound account.
- This is a SETTER — implementations MUST NOT require the account to be passed at construction time. Constructor signatures of `EntityQueryInterface` implementations are not part of this contract.

### `execute(): array`

- When `accessCheck(true)` AND no account is bound: implementation MUST throw `MissingQueryAccountException`. It MUST NOT silently bypass.
- When `accessCheck(false)`: implementation skips the check entirely; no exception even if no account is bound.
- Return value is still `array` (typically of entity IDs — the contract does not constrain the element type beyond `array`, but consumers consistently treat the result as IDs).
- Filtered cardinality: when `accessCheck(true)`, the result array length equals the count of access-allowed candidate rows for the current page (after `range()` if set). It is NOT the unfiltered candidate window size.

### `count(): static`

- Marks the query for cardinality return. The current contract returns `static`; the actual count is materialized on `execute()` (or via implementation-specific extension).
- When `accessCheck(true)`: the materialized count reflects post-filter cardinality.
- When `accessCheck(false)`: the materialized count reflects pre-filter cardinality (existing behaviour).

### `range(int $offset, int $limit): static` + filtered pages

- The page cursor advances by the **unfiltered candidate window**. Example: a 25-row page request that returns 18 surviving rows after access-check filtering; the next-page cursor is positioned at `offset + 25` (not `offset + 18`).
- This makes paginated traversal idempotent across the access filter — successive page requests don't re-scan candidates already evaluated.

---

## Error envelope

`Waaseyaa\EntityStorage\Exception\MissingQueryAccountException` (new):

- Extends `\RuntimeException`.
- Class-level `@api` PHPDoc.
- Constructor is non-public; instances created only via the `forQuery(EntityTypeInterface)` named factory. The message names the entity type and points the caller to the two valid resolutions (bind an account, or pass `accessCheck(false)` for a system context).

---

## Backward compatibility

Adding `setAccount(?AccountInterface): static` to `EntityQueryInterface` is **technically a breaking change** for any third-party implementation of the interface. Today there is exactly one implementation (`SqlEntityQuery`). No third-party implementations are known to exist (Composer dependency search would confirm at release time; per spec C-006 the mission's WP-01 ships a quick external-consumer verification grep).

The breakage is justified by the security posture this mission delivers. The change is documented in CHANGELOG `[Unreleased]` § Changed for the v1 release.

---

## Future extensions (out of scope)

- `EntityQueryInterface::executeEntities(): array` — return hydrated entities directly so consumers don't need a second `loadMultiple($ids)` round-trip. Optimisation only; consumers can do the round-trip today.
- `EntityAccessHandler::checkMultiple(EntityInterface[], string, AccountInterface): array<int, AccessResult>` — batch check for hot policies. Only motivated if profiling identifies a policy that hits the DB per row.
- Pre-filter pushdown (`WHERE` clause generation from policy) — v2.x optimisation for hot policies whose logic CAN be expressed as SQL.

These are tracked here for reference; none block v1 mission completion.
