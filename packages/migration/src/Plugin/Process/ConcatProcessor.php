<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin\Process;

use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;

/**
 * Concatenate multiple source fields and/or literal strings into one value.
 *
 * Each `$parts` entry is either:
 *
 *   - A string beginning with `@` — interpreted as a source-field reference
 *     (e.g. `'@post_slug'` resolves to `$sourceRecord->field('post_slug')`).
 *   - Any other string — treated as a literal segment.
 *
 * Null source-field values become empty strings in the output (never the
 * literal text `"null"`); the `$separator` argument joins the resolved parts.
 *
 * Used for composite slugs, full-name derivations, and other simple string
 * compositions where a single `set()` and a literal map keep the manifest
 * readable.
 *
 * @api
 *
 * @spec FR-010 — framework-reserved process plugin (`concat`)
 */
final readonly class ConcatProcessor implements ProcessPluginInterface
{
    /**
     * @param list<string> $parts Ordered segments — literals or `@field` references.
     * @param string $separator Joiner between resolved segments.
     */
    public function __construct(
        public array $parts,
        public string $separator = '',
    ) {}

    public function id(): string
    {
        return ReservedPluginIds::CONCAT;
    }

    public function stability(): string
    {
        return 'stable';
    }

    public function transform(mixed $value, ProcessContext $context): mixed
    {
        if ($this->parts === []) {
            return '';
        }

        $segments = [];
        foreach ($this->parts as $part) {
            if (str_starts_with($part, '@')) {
                $field = substr($part, 1);
                if ($field === '') {
                    $segments[] = '';
                    continue;
                }

                $resolved = $context->sourceRecord->field($field, null);
                $segments[] = $resolved === null ? '' : (string) $resolved;
                continue;
            }

            $segments[] = $part;
        }

        return implode($this->separator, $segments);
    }
}
