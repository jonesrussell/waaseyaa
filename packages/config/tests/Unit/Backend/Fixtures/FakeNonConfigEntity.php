<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Backend\Fixtures;

use Waaseyaa\Entity\EntityInterface;

/**
 * Minimal fixture implementing {@see EntityInterface} but NOT
 * {@see \Waaseyaa\Entity\ConfigEntityInterface}.
 *
 * Used to verify that the backend-restriction enforcer skips content
 * entity types — only the config-entity contract is restricted.
 *
 * @internal
 */
final class FakeNonConfigEntity implements EntityInterface
{
    public function id(): int|string|null
    {
        return 1;
    }

    public function uuid(): string
    {
        return '00000000-0000-0000-0000-000000000001';
    }

    public function label(): string
    {
        return 'Fake Content';
    }

    public function getEntityTypeId(): string
    {
        return 'fake_content';
    }

    public function bundle(): string
    {
        return 'fake_content';
    }

    public function isNew(): bool
    {
        return false;
    }

    public function get(string $name): mixed
    {
        return null;
    }

    public function set(string $name, mixed $value): static
    {
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }

    public function language(): string
    {
        return 'en';
    }
}
