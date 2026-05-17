<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @api
 */
final class ConfigEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $configName,
        private array $data = [],
    ) {}

    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
