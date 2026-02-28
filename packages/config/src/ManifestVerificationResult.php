<?php

declare(strict_types=1);

namespace Aurora\Config;

/**
 * Result of a config manifest verification.
 */
final readonly class ManifestVerificationResult
{
    public function __construct(
        /** Whether the current config matches the manifest. */
        public bool $isValid,
        /** Config names that have been modified since the manifest was generated. */
        public array $modifiedConfigs = [],
        /** Config names that exist now but were not in the manifest. */
        public array $addedConfigs = [],
        /** Config names that were in the manifest but no longer exist. */
        public array $removedConfigs = [],
        /** Human-readable error message if not valid. */
        public ?string $error = null,
    ) {}
}
