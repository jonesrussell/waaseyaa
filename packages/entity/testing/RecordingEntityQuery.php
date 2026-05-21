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
     *
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
     *
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
