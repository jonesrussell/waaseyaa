<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Migration;

/**
 * Validated `migration_id` value object.
 *
 * Format locked by §15 Q1 (ratified 2026-05-02):
 *
 *     {vendor}/{package}:v2:{kebab-slug}
 *
 * Examples:
 *
 *     waaseyaa/groups:v2:add-archived-flag
 *     waaseyaa/entity-storage:v2:rename-bundle-subtable
 *
 * Validation rules (enforced by the constructor):
 *
 * - `{vendor}` and `{package}` are non-empty kebab-case (`[a-z0-9-]+`),
 *   matching Composer package-name conventions.
 * - The literal `:v2:` separator distinguishes v2 migrations from
 *   legacy ledger keys at parse time.
 * - `{kebab-slug}` starts with `[a-z0-9]` and continues with
 *   `[a-z0-9-]*`. Trailing hyphens are tolerated by the regex; callers
 *   that want stricter normalization can layer it on top.
 *
 * **Why a value object.** {@see MigrationInterfaceV2::migrationId()}
 * returns `string` (so it stays compatible with the legacy ledger
 * column), but factories SHOULD construct via this class so format
 * errors surface at authoring time rather than ledger-read time. The
 * legacy `migration` column carries the string verbatim — there is no
 * second identifier column (Q1 ratified Option B-equivalent).
 *
 * @see docs/specs/schema-evolution-v2.md §15 Q1
 */
final readonly class MigrationId
{
    /**
     * Regex for the locked `migration_id` format. The `:v2:` literal is
     * the discriminator; do not loosen it without an ADR.
     */
    public const FORMAT_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+:v2:[a-z0-9][a-z0-9-]*$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::FORMAT_PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid migration_id "%s"; expected format "{vendor}/{package}:v2:{kebab-slug}" (see §15 Q1).',
                $value,
            ));
        }
    }

    /**
     * Constructor alias preferred at call sites that want explicit naming.
     *
     * Equivalent to `new MigrationId($value)`.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the canonical string form for storage in the `migration`
     * ledger column.
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
