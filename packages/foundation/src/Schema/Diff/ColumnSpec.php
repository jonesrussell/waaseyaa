<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Logical column shape consumed by {@see AddColumn} and {@see AlterColumn}.
 *
 * The semantic shape parallels `SqlSchemaHandler::deriveColumnSpec()` in
 * `waaseyaa/entity-storage` (Layer 1) but is defined here in `foundation`
 * (Layer 0) so the diff layer has no upward dependency. Compilers map
 * `$type` tokens to dialect-specific column types.
 *
 * Recognised `$type` tokens (compiler is the source of truth for the full
 * grammar; these are guaranteed to round-trip):
 *
 * | Token        | Notes                                |
 * |--------------|--------------------------------------|
 * | `varchar`    | Requires `$length`.                  |
 * | `text`       | Length ignored.                      |
 * | `int`        | Length ignored.                      |
 * | `boolean`    | Length ignored.                      |
 * | `float`      | Length ignored.                      |
 *
 * Default-value semantics for v1: `$default === null` means "no default
 * specified". The distinction between "no default" and "DEFAULT NULL" is
 * not material for v1 because both produce the same SQL on every supported
 * dialect. A future revision may introduce an explicit `hasDefault` flag
 * if a use case appears.
 */
final readonly class ColumnSpec
{
    public function __construct(
        public string $type,
        public bool $nullable,
        public mixed $default = null,
        public ?int $length = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCanonical(): array
    {
        return [
            'default' => $this->default,
            'length' => $this->length,
            'nullable' => $this->nullable,
            'type' => $this->type,
        ];
    }
}
