<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Backend\Fixtures;

use Waaseyaa\Entity\ConfigEntityInterface;

/**
 * Minimal fixture implementing {@see ConfigEntityInterface}.
 *
 * Used by `BackendRestrictionEnforcerTest` and
 * `StorageBackendRegistryConfigRestrictionTest` to exercise the
 * config-entity detection branch without depending on real config-entity
 * implementations.
 *
 * @internal
 */
final class FakeConfigEntity implements ConfigEntityInterface
{
    public function status(): bool
    {
        return true;
    }

    public function enable(): static
    {
        return $this;
    }

    public function disable(): static
    {
        return $this;
    }

    /** @return array<string, string[]> */
    public function getDependencies(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toConfig(): array
    {
        return [];
    }

    public function id(): int|string|null
    {
        return 'fake';
    }

    public function uuid(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function getEntityTypeId(): string
    {
        return 'fake_config';
    }

    public function bundle(): string
    {
        return 'fake_config';
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
