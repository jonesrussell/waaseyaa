<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Cast;

/**
 * Declarative cast specification for a single field.
 *
 * - String tokens name built-ins (`int`, `array`, `datetime_immutable`, …) or a backed enum class-string.
 * - Array form supports `['type' => 'json']` as an alias for the `array` built-in (JSON in storage).
 */
final readonly class CastDefinition
{
    /**
     * @param string|array<string, mixed> $spec
     */
    public function __construct(public string|array $spec) {}
}
