<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Atomic structural change in a SchemaDiff plan.
 *
 * Implementations are `final readonly class` value objects with no SQL,
 * no DB I/O, and no static state. They produce a canonical-JSON-ready
 * dictionary via {@see toCanonical()} that the {@see CompositeDiff}
 * folds into a stable {@see CompositeDiff::checksum()}.
 *
 * PHP 8.4 has no `sealed` keyword. The closed set is enforced socially
 * (one implementation per {@see OpKind} case) and structurally
 * ({@see CompositeDiff} only consumes the kind + canonical dict, never
 * implementation classes).
 */
interface SchemaDiffOp
{
    public function kind(): OpKind;

    /**
     * Canonical-JSON-ready representation.
     *
     * The returned array is fed to {@see CanonicalJson::encode()}; any
     * keys present here participate in the SHA-256 checksum. Implementations
     * must not include implementation-class names, file paths, or other
     * non-deterministic metadata.
     *
     * @return array<string, mixed>
     */
    public function toCanonical(): array;
}
