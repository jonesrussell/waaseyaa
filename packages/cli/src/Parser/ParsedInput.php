<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Parser;

final readonly class ParsedInput
{
    /**
     * @param array<string, scalar|array|null> $arguments  Parsed positional argument values keyed by name.
     * @param array<string, scalar|array|null> $options    Parsed option values keyed by long name.
     * @param list<string>                     $rawArgv    The original argv tokens (for diagnostics).
     */
    public function __construct(
        public array $arguments,
        public array $options,
        public array $rawArgv,
    ) {}
}
