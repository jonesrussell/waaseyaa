<?php

declare(strict_types=1);

namespace Waaseyaa\Seo\Tests\Unit;

use Waaseyaa\Entity\Storage\EntityQueryInterface;

/**
 * @internal
 */
final class StubEntityQuery implements EntityQueryInterface
{
    /**
     * @param list<int|string> $ids
     */
    public function __construct(private array $ids) {}

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
        return $this;
    }

    /**
     * @return list<int|string>
     */
    public function execute(): array
    {
        return $this->ids;
    }
}
