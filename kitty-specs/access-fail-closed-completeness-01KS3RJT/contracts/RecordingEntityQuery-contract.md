# Contract: RecordingEntityQuery

**FQCN**: `Waaseyaa\Entity\Testing\RecordingEntityQuery`
**Layer**: L1 (`packages/entity/`) — `autoload-dev` only
**File**: `packages/entity/testing/RecordingEntityQuery.php`
**WP**: WP03
**Stability**: Test helper — NOT shipped to consumers

---

## Purpose

A shared test stub implementing `EntityQueryInterface` that records every
`accessCheck()` and `setAccount()` call. Replaces bespoke anonymous stubs
in per-class test files. Used by all four retro regression tests (WP05)
and any future test that needs to assert query access binding.

---

## Full Implementation Contract

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Testing;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

/**
 * Test stub for EntityQueryInterface that records access-binding calls.
 *
 * All chainable methods return $this.
 * execute() returns $stubbedResults (configurable via withResults()).
 *
 * Inspection properties:
 *   $accessChecks  — list<bool>: each accessCheck() call value, in call order.
 *   $boundAccount  — ?AccountInterface: last account passed to setAccount().
 *
 * @api — Public test-helper surface. Safe to depend on from any package's tests.
 */
final class RecordingEntityQuery implements EntityQueryInterface
{
    /**
     * Records each accessCheck() call value in call order.
     * @var list<bool>
     */
    public array $accessChecks = [];

    /**
     * The most recent account bound via setAccount().
     * Null if setAccount() was never called, or if setAccount(null) was called.
     */
    public ?AccountInterface $boundAccount = null;

    /** @var list<int|string> */
    private array $stubbedResults = [];

    /**
     * Configure the stubbed return value of execute().
     * @param list<int|string> $ids
     */
    public function withResults(array $ids): static
    {
        $this->stubbedResults = $ids;
        return $this;
    }

    public function condition(string $field, mixed $value, string $operator = '='): static
    {
        return $this;
    }

    public function exists(string $field): static
    {
        return $this;
    }

    public function notExists(string $field): static
    {
        return $this;
    }

    public function sort(string $field, string $direction = 'ASC'): static
    {
        return $this;
    }

    public function range(int $offset, int $limit): static
    {
        return $this;
    }

    public function count(): static
    {
        return $this;
    }

    public function accessCheck(bool $check = true): static
    {
        $this->accessChecks[] = $check;
        return $this;
    }

    public function setAccount(?AccountInterface $account): static
    {
        $this->boundAccount = $account;
        return $this;
    }

    /**
     * @return array<int|string>
     */
    public function execute(): array
    {
        return $this->stubbedResults;
    }
}
```

---

## composer.json autoload-dev entry

Add to `packages/entity/composer.json` under `autoload-dev.psr-4`:
```json
"Waaseyaa\\Entity\\Testing\\": "testing/"
```

Result (alongside existing entries):
```json
"autoload-dev": {
    "psr-4": {
        "Waaseyaa\\Entity\\PhpStan\\": "testing/PhpStan/",
        "Waaseyaa\\Entity\\Testing\\": "testing/",
        "Waaseyaa\\Entity\\Testing\\Translation\\": "testing/Translation/",
        "Waaseyaa\\Entity\\Tests\\": "tests/"
    }
}
```

**Note**: The existing `Waaseyaa\\Entity\\Testing\\Translation\\` entry is a sub-namespace
of the new `Waaseyaa\\Entity\\Testing\\` root. PSR-4 resolves from the most-specific prefix
first, so `Translation\TranslatableEntityContractTest` continues to resolve to
`testing/Translation/` correctly.

---

## Usage Pattern in Regression Tests

```php
use Waaseyaa\Entity\Testing\RecordingEntityQuery;

// In test:
$query = new RecordingEntityQuery();
// Inject $query wherever the production code calls $storage->getQuery()

// Assert account was bound:
self::assertNotNull($query->boundAccount, 'Expected setAccount() to be called.');

// Assert accessCheck(false) was used (system context):
self::assertContains(false, $query->accessChecks, 'Expected accessCheck(false) for system-context query.');

// Assert double-binding is detectable:
self::assertSame([true, false], $query->accessChecks);
```
