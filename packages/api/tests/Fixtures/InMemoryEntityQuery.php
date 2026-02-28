<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Fixtures;

use Aurora\Entity\Storage\EntityQueryInterface;

/**
 * In-memory entity query for testing.
 */
class InMemoryEntityQuery implements EntityQueryInterface
{
    /** @var array<int|string> */
    private array $entityIds;

    private bool $isCount = false;

    /** @var array{field: string, value: mixed, operator: string}[] */
    private array $conditions = [];

    /** @var array{field: string, direction: string}[] */
    private array $sorts = [];

    private ?int $rangeOffset = null;
    private ?int $rangeLimit = null;

    /**
     * @param array<int|string> $entityIds All available entity IDs.
     */
    public function __construct(array $entityIds = [])
    {
        $this->entityIds = $entityIds;
    }

    public function condition(string $field, mixed $value, string $operator = '='): static
    {
        $this->conditions[] = ['field' => $field, 'value' => $value, 'operator' => $operator];
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
        $this->sorts[] = ['field' => $field, 'direction' => $direction];
        return $this;
    }

    public function range(int $offset, int $limit): static
    {
        $this->rangeOffset = $offset;
        $this->rangeLimit = $limit;
        return $this;
    }

    public function count(): static
    {
        $this->isCount = true;
        return $this;
    }

    public function accessCheck(bool $check = true): static
    {
        return $this;
    }

    public function execute(): array
    {
        $ids = $this->entityIds;

        if ($this->rangeOffset !== null && $this->rangeLimit !== null) {
            $ids = array_slice($ids, $this->rangeOffset, $this->rangeLimit);
        }

        if ($this->isCount) {
            return [\count($ids)];
        }

        return $ids;
    }
}
