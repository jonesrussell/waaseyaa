<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Cast;

/**
 * Declarative cast specification for a single field.
 *
 * - String tokens name built-ins (`int`, `array`, `datetime_immutable`, …), a backed enum class-string,
 *   or a class-string implementing {@see FromArrayEntityValueInterface} (same JSON storage shape as `array`, #1184).
 * - Array form supports `['type' => 'json']` as an alias for the `array` built-in (JSON in storage).
 * - Value object array form: `['type' => 'value_object', 'class' => SomeVo::class]` when `SomeVo` implements
 *   {@see FromArrayEntityValueInterface}.
 * @api
 */
final readonly class CastDefinition
{
    /**
     * @param string|array<string, mixed> $spec
     */
    public function __construct(public string|array $spec) {}
}
